<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\StripeAccountValidator;
use ThemeIsle\WPFullPay\Admin\Views\ConfigureStripeAccountViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

class StripeAccountModel implements Binder {
	use Model;
	use LoggerAddOn;
	private $options = null;

	protected $testSecretKey;
	protected $testPublishableKey;
	protected $liveSecretKey;
	protected $livePublishableKey;
	protected $apiMode;
	protected $testAccountId;
	protected $liveAccountId;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new StripeAccountValidator( $this->loggerService );
		$this->options = new Options();
	}

	/**
	 * @return string
	 */
	public function getTestSecretKey() {
		return $this->testSecretKey;
	}

	/**
	 * @return string
	 */
	public function getTestPublishableKey() {
		return $this->testPublishableKey;
	}

	/**
	 * @return string
	 */
	public function getLiveSecretKey() {
		return $this->liveSecretKey;
	}

	/**
	 * @return string
	 */
	public function getLivePublishableKey() {
		return $this->livePublishableKey;
	}

	/**
	 * @return string
	 */
	public function getApiMode() {
		return $this->apiMode;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		// Each settings tab posts only some of these fields; the rest keep their stored values.
		$this->testSecretKey = $this->getSanitizedArrayParam( $postData, ConfigureStripeAccountViewConstants::FIELD_API_TEST_SECRET_KEY, $this->options->get( Options::OPTION_API_TEST_SECRET_KEY ) );
		$this->testPublishableKey = $this->getSanitizedArrayParam( $postData, ConfigureStripeAccountViewConstants::FIELD_API_TEST_PUBLISHABLE_KEY, $this->options->get( Options::OPTION_API_TEST_PUBLISHABLE_KEY ) );
		$this->liveSecretKey = $this->getSanitizedArrayParam( $postData, ConfigureStripeAccountViewConstants::FIELD_API_LIVE_SECRET_KEY, $this->options->get( Options::OPTION_API_LIVE_SECRET_KEY ) );
		$this->livePublishableKey = $this->getSanitizedArrayParam( $postData, ConfigureStripeAccountViewConstants::FIELD_API_LIVE_PUBLISHABLE_KEY, $this->options->get( Options::OPTION_API_LIVE_PUBLISHABLE_KEY ) );
		$this->apiMode = $this->getSanitizedArrayParam( $postData, ConfigureStripeAccountViewConstants::FIELD_API_MODE, $this->options->get( Options::OPTION_API_MODE ) ?? Main::STRIPE_API_MODE_TEST );
		$this->testAccountId = $this->options->get( Options::OPTION_TEST_ACCOUNT_ID );
		$this->liveAccountId = $this->options->get( Options::OPTION_LIVE_ACCOUNT_ID );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'testSecretKey' => $this->testSecretKey,
			'testPublishableKey' => $this->testPublishableKey,
			'liveSecretKey' => $this->liveSecretKey,
			'livePublishableKey' => $this->livePublishableKey,
			'apiMode' => $this->apiMode
		];

		return $data;
	}

	public function getPostData() {
		return [];
	}

	public function afterBind() {
		// noop
	}
}
