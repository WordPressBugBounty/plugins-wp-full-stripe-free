<?php

namespace ThemeIsle\WPFullPay\Webhooks;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Webhooks\Events\ChargeRefunded;
use ThemeIsle\WPFullPay\Webhooks\Events\ChargeStatusEvent;
use ThemeIsle\WPFullPay\Webhooks\Events\ChargeSucceeded;
use ThemeIsle\WPFullPay\Webhooks\Events\CustomerSubscriptionDeleted;
use ThemeIsle\WPFullPay\Webhooks\Events\CustomerSubscriptionUpdated;
use ThemeIsle\WPFullPay\Webhooks\Events\InvoiceCreated;
use ThemeIsle\WPFullPay\Webhooks\Events\InvoicePaymentSucceeded;

class EventHandler
{
	use LoggerAddOn;
	use StaticContextAddOn;

	const WPFS_PLUGIN_SLUG = 'wp-full-stripe';
	const WPFS_REST_API_VERSION = 'v1';
	const WPFS_STRIPE_HOOKS_ENDPOINT = 'stripe-hooks';

	/** @var Database $db */
	protected $db = null;

	/** @var Mailer $mailer */
	protected $mailer = null;

	/** @var Options $options */
	protected $options = null;

	/** @var array */
	protected $eventProcessors = [];

	/**
	 * EventHandler constructor.
	 *
	 * @param Database $db
	 * @param Mailer $mailer
	 * @param LoggerService $loggerService
	 */
	public function __construct(Database $db, Mailer $mailer, LoggerService $loggerService)
	{
		$this->initLogger($loggerService, LoggerService::MODULE_WEBHOOK_EVENT_HANDLER);
		$this->options = new Options();

		$this->initStaticContext();

		$this->db = $db;
		$this->mailer = $mailer;

		$this->initProcessors();
		$this->hooks();
	}

	protected function hooks()
	{
		// tnagy WPFS-554: register REST API Endpoint for Stripe Webhooks
		add_action('rest_api_init', [$this, 'registerRESTAPIRoutes']);
	}

	/**
	 * @return string
	 */
	public static function getRESTNamespace(): string
	{
		return self::WPFS_PLUGIN_SLUG . '/' . self::WPFS_REST_API_VERSION;
	}

	/**
	 * Registers a REST endpoint for Stripe Webhooks handling
	 */
	public function registerRESTAPIRoutes()
	{
		register_rest_route(self::getRESTNamespace(), $this->getRESTRoute(), [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [$this, 'handleRESTRequest'],
			'args' => [],
			'permission_callback' => '__return_true'
		]);
	}

	/**
	 * @return string the REST API endpoint path for Stripe Webhooks
	 */
	public static function getWebhookPath()
	{
		return self::getRESTNamespace() . self::getRESTRoute();
	}

	/**
	 * @return string
	 */
	public static function getWebhookEndpointURL($context)
	{
		return add_query_arg(
			[
				'auth_token' => EventHandler::getWebhookToken($context)
			],
			get_rest_url(null, EventHandler::getWebhookPath())
		);
	}

	/**
	 * @return string
	 */
	public static function getDemoWebhookURL()
	{
		$webhookUrl = 'https://demo.example.com/wp-admin/admin-post.php?action=handle_wpfs_event&auth_token=mfdg78er7rnvc74tnv7werndsfjkfds';

		return $webhookUrl;
	}

	/**
	 * @return string
	 */
	public static function getLegacyWebhookEndpointURL($context)
	{
		return add_query_arg(
			[
				'action' => 'handle_wpfs_event',
				'auth_token' => EventHandler::getWebhookToken($context)
			],
			admin_url('admin-post.php')
		);
	}

	protected function initProcessors()
	{
		$processors = [
			new CustomerSubscriptionDeleted($this->db, $this->mailer, $this->loggerService),
			new InvoiceCreated($this->db, $this->mailer, $this->loggerService),
			new InvoicePaymentSucceeded($this->db, $this->mailer, $this->loggerService),
			new ChargeStatusEvent(\StripeWPFS\Stripe\Event::CHARGE_CAPTURED, $this->db, $this->mailer, $this->loggerService),
			new ChargeStatusEvent(\StripeWPFS\Stripe\Event::CHARGE_EXPIRED, $this->db, $this->mailer, $this->loggerService),
			new ChargeStatusEvent(\StripeWPFS\Stripe\Event::CHARGE_FAILED, $this->db, $this->mailer, $this->loggerService),
			new ChargeStatusEvent(\StripeWPFS\Stripe\Event::CHARGE_PENDING, $this->db, $this->mailer, $this->loggerService),
			new ChargeRefunded($this->db, $this->mailer, $this->loggerService),
			new ChargeSucceeded($this->db, $this->mailer, $this->loggerService),
			new ChargeStatusEvent(\StripeWPFS\Stripe\Event::CHARGE_UPDATED, $this->db, $this->mailer, $this->loggerService),
			new CustomerSubscriptionUpdated($this->db, $this->mailer, $this->loggerService)
		];
		foreach ($processors as $processor) {
			$this->eventProcessors[$processor->getType()] = $processor;
		}
	}

	/**
	 * @param $event
	 */
	protected function logEventTimeStamp($event)
	{
		if (isset($event) && property_exists($event, 'livemode')) {
			$this->updateLastEventTimestamp($event->livemode, time());
		}
	}

	/**
	 * @param bool $isLiveMode
	 * @param int $time
	 */
	protected function updateLastEventTimestamp(bool $isLiveMode, int $time)
	{
		if ($isLiveMode) {
			$this->options->set(Options::OPTION_LAST_WEBHOOK_EVENT_LIVE, $time);
		} else {
			$this->options->set(Options::OPTION_LAST_WEBHOOK_EVENT_TEST, $time);
		}
	}

	public function handle($event)
	{
		try {

			$this->logEventTimeStamp($event);

			$eventProcessed = false;
			if (isset($event) && isset($event->type)) {
				$eventProcessor = null;
				if (array_key_exists($event->type, $this->eventProcessors)) {
					$eventProcessor = $this->eventProcessors[$event->type];
				}
				if ($eventProcessor instanceof EventProcessor) {
					$eventProcessor->process($event, $this->createContextForEvent($event));
					$eventProcessed = true;
				} else {
					$this->logger->debug(__FUNCTION__, 'Event processor not found');
				}
			} else {
				$this->logger->debug(__FUNCTION__, 'Event not recognized or event has no type property');
			}

			return $eventProcessed;
		} catch (\Exception $ex) {
			$this->logger->error(__FUNCTION__, 'Error while handling webhook event', $ex);
			throw $ex;
		}
	}

	/**
	 * @param StaticContext $context
	 * @return mixed
	 */
	public static function getWebhookToken($context)
	{
		return $context->getOptions()->get(Options::OPTION_WEBHOOK_TOKEN);
	}

	public function handleRESTRequest()
	{
		$this->logger->debug(__FUNCTION__, 'CALLED');

		$auth_token = empty($_REQUEST['auth_token']) ? '' : $_REQUEST['auth_token'];
		$web_hook_token = self::getWebhookToken($this->staticContext);

		if ($web_hook_token !== $auth_token) {
			$this->logger->debug(__FUNCTION__, 'Authentication failed, abort.');

			// return HTTP Unauthorized
			status_header(401);
			header('Content-Type: application/json');
			exit;
		}

		try {

			// tnagy retrieve the request's body and parse it as JSON
			$input = @file_get_contents("php://input");

			$event = json_decode($input);
			$eventProcessed = $this->handle($event);

			$this->logger->debug(__FUNCTION__, 'Event processed? ' . ($eventProcessed === true ? 'true' : 'false'));

			// return HTTP OK
			status_header(200);
		} catch (\Exception $ex) {
			$this->logger->error(__FUNCTION__, 'Error while handling REST request', $ex);

			// return HTTP Internal Server Error
			status_header(500);
		}

		header("Content-Type: application/json");
		exit;
	}

	/**
	 * @return string
	 */
	public static function getRESTRoute()
	{
		return '/' . self::WPFS_STRIPE_HOOKS_ENDPOINT;
	}

	/**
	 * @param \StripeWPFS\Stripe\Event $event
	 *
	 * @return LiveModeAwareEventProcessorContext
	 * @throws \Exception
	 */
	private function createContextForEvent($event)
	{
		$context = new LiveModeAwareEventProcessorContext($this->loggerService, $this->db, $this->mailer);
		// tnagy update context's liveMode property and Stripe instance to Live/Test mode depending on event's livemode attribute
		if (property_exists($event, 'livemode')) {
			if (true === $event->livemode) {
				$context->setLiveMode(true);
				$context->setStripe(new Stripe(Stripe::getStripeLiveAuthenticationToken($this->staticContext), $this->loggerService));
			} else {
				$context->setLiveMode(false);
				$context->setStripe(new Stripe(Stripe::getStripeTestAuthenticationToken($this->staticContext), $this->loggerService));
			}
		} else {
			// tnagy use default settings
			$context->setLiveMode(Stripe::isStripeApiInLiveMode($this->staticContext));
			$context->setStripe(new Stripe(Stripe::getStripeAuthenticationToken($this->staticContext), $this->loggerService));
		}

		return $context;
	}

}
