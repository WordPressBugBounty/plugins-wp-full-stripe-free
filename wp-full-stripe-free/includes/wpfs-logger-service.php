<?php

/**
 * Created by PhpStorm.
 * User: tnagy
 * Date: 2019.11.27.
 * Time: 14:42
 */
class MM_WPFS_LoggerService {

	const LEVEL_DEBUG = 'DEBUG';
	const LEVEL_INFO = 'INFO';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_ERROR = 'ERROR';

	const LEVEL_PRIORITY_DEBUG = 10;
	const LEVEL_PRIORITY_INFO = 20;
	const LEVEL_PRIORITY_WARNING = 30;
	const LEVEL_PRIORITY_ERROR = 40;

	const MODULE_PATCHER = 'Patcher';
	const MODULE_DATABASE = 'Database';
	const MODULE_ADMIN = 'Admin';
	const MODULE_RUNTIME = 'Runtime';
	const MODULE_CUSTOMER_PORTAL = 'Customer portal';
	const MODULE_WEBHOOK_EVENT_HANDLER = 'Webhook event handler';
	const MODULE_CHECKOUT_SUBMISSION = 'Checkout submission';
	const MODULE_STRIPE = 'Stripe';
	const MODULE_API = 'API';

	const LOG_DATABASE_PAGE_SIZE = 500;

	/**
	 * @var MM_WPFS_Database
	 */
	private $db;

	/**
	 * @var bool
	 */
	private $isPhpLoggingEnabled = true;

	/**
	 * @var string[]
	 */
	private $levels;
	/**
	 * @var string[]
	 */
	private $modules;
	/**
	 * @var array
	 */
	private $moduleLevels;

	/**
	 * @var array
	 */
	private $levelPriorities;

	/**
	 * MM_WPFS_LoggerService constructor.
	 */
	public function __construct( $logLevel, $isPhpLoggingEnabled ) {
		$this->db = new MM_WPFS_Database();
		$this->isPhpLoggingEnabled = $isPhpLoggingEnabled;

		$this->levels = $this->buildLevels();
		$this->modules = $this->buildModules();
		$this->levelPriorities = $this->buildLevelPriorities();
		$this->moduleLevels = $this->buildModuleLevels( $logLevel );
	}

	protected function buildLevels() {
		return [
			self::LEVEL_DEBUG,
			self::LEVEL_INFO,
			self::LEVEL_WARNING,
			self::LEVEL_ERROR
		];
	}

	protected function buildModules() {
		return [
			self::MODULE_PATCHER,
			self::MODULE_DATABASE,
			self::MODULE_ADMIN,
			self::MODULE_RUNTIME,
			self::MODULE_CUSTOMER_PORTAL,
			self::MODULE_WEBHOOK_EVENT_HANDLER,
			self::MODULE_CHECKOUT_SUBMISSION,
			self::MODULE_STRIPE,
			self::MODULE_API,
		];
	}

	/**
	 * @return array
	 */
	protected function buildModuleLevels( $logLevel ) {
		$result = [];

		foreach ( $this->modules as $module ) {
			$result[ $module ] = $logLevel;
		}

		return $result;
	}

	protected function buildLevelPriorities() {
		return [
			self::LEVEL_DEBUG => self::LEVEL_PRIORITY_DEBUG,
			self::LEVEL_INFO => self::LEVEL_PRIORITY_INFO,
			self::LEVEL_WARNING => self::LEVEL_PRIORITY_WARNING,
			self::LEVEL_ERROR => self::LEVEL_PRIORITY_ERROR
		];
	}

	protected function getModuleLevel( $module ) {
		$result = self::LEVEL_ERROR;

		if ( array_key_exists( $module, $this->moduleLevels ) ) {
			$result = $this->moduleLevels[ $module ];
		}

		return $result;
	}

	public function getLevels() {
		return $this->levels;
	}

	public function getLevelPriority( $level ) {
		$result = false;

		$levelPriorities = $this->levelPriorities;
		if ( array_key_exists( $level, $levelPriorities ) ) {
			$result = $levelPriorities[ $level ];
		}

		return $result;
	}

	/**
	 * @param string $module
	 * @param string $class always use __CLASS__ when possible
	 *
	 * @return MM_WPFS_Logger
	 */
	public function createLogger( $module, $class ) {
		return new MM_WPFS_Logger( $this, $module, $class, $this->getModuleLevel( $module ) );
	}

	public function createApiLogger( $class ) {
		return $this->createLogger( self::MODULE_API, $class );
	}

	public function createPatcherLogger( $class ) {
		return $this->createLogger( self::MODULE_PATCHER, $class );
	}
	public function createAdminLogger( $class ) {
		return $this->createLogger( self::MODULE_ADMIN, $class );
	}
	public function createStripeLogger( $class ) {
		return $this->createLogger( self::MODULE_STRIPE, $class );
	}

	public function createRuntimeLogger( $class ) {
		return $this->createLogger( self::MODULE_RUNTIME, $class );
	}

	public function createCheckoutSubmissionLogger( $class ) {
		return $this->createLogger( self::MODULE_CHECKOUT_SUBMISSION, $class );
	}

	/**
	 * @param string $class always use __CLASS__ when possible
	 *
	 * @return MM_WPFS_Logger
	 */
	public function createWebHookEventHandlerLogger( $class ) {
		return $this->createLogger( self::MODULE_WEBHOOK_EVENT_HANDLER, $class );
	}

	/**
	 * @param $class
	 *
	 * @return MM_WPFS_Logger
	 */
	public function createCustomerPortalLogger( $class ) {
		return $this->createLogger( self::MODULE_CUSTOMER_PORTAL, $class );
	}

	/**
	 * @param string $module one of the MODULE constant
	 * @param string $class always use __CLASS__ when possible
	 * @param string $function always use __FUNCTION__ when possible
	 * @param string $level
	 * @param string $message
	 * @param string $function
	 * @param null|Throwable $throwable
	 */
	public function log( $module, $class, $function, $level, $message, $throwable = null ) {
		$loggedMessage = $message . ( is_null( $throwable ) ? '' : ' - ' . $throwable->getMessage() );
		$loggedStackTrace = is_null( $throwable ) ? '' : $throwable->getTraceAsString();

		$this->db->insertLog( $module, $class, $function, $level, $loggedMessage, $loggedStackTrace );

		if ( $this->isPhpLoggingEnabled ) {
			error_log( $this->formatLogEntryAsParams( '', $level, $module, $class, $function, $loggedMessage, $loggedStackTrace ) );
		}
	}

	public static function localizeLogLevel( $level ) {
		$result = $level;

		switch ( $level ) {
			case self::LEVEL_ERROR:
				$result = __( 'Error', 'wp-full-stripe-free' );
				break;

			case self::LEVEL_WARNING:
				$result = __( 'Warning', 'wp-full-stripe-free' );
				break;

			case self::LEVEL_INFO:
				$result = __( 'Info', 'wp-full-stripe-free' );
				break;

			case self::LEVEL_DEBUG:
				$result = __( 'Debug', 'wp-full-stripe-free' );
				break;
		}

		return $result;
	}


	protected function sendLogFileHeader() {
		$fileName = 'wp-full-pay_logs_' . date( "Y-m-d_H-i-s" ) . '.txt';

		header( "Expires: 0" );
		header( "Cache-Control: no-cache, no-store, must-revalidate" );
		header( 'Cache-Control: pre-check=0, post-check=0, max-age=0', false );
		header( "Pragma: no-cache" );
		header( "Content-type: text/plain" );
		header( "Content-Disposition:attachment; filename={$fileName}" );
		header( "Content-Type: application/force-download" );
	}

	protected function determineLogPageCount( $logCount ): int {
		$result = intval( $logCount / self::LOG_DATABASE_PAGE_SIZE );
		$result += ( $logCount % self::LOG_DATABASE_PAGE_SIZE ) > 0 ? 1 : 0;

		return $result;
	}

	protected function formatLogEntryAsParams( $createdAt, $level, $module, $class, $function, $message, $exception ) {
		$result = "{$createdAt} {$level} {$module} / {$class}::{$function}(): {$message}\n";
		$result .= $exception ? ( $exception . "\n" ) : '';

		return $result;
	}
	protected function formatLogEntry( $entry ): string {
		return $this->formatLogEntryAsParams( $entry->created, $entry->level, $entry->module, $entry->class, $entry->function, $entry->message, $entry->exception );

	}

	public function downloadLog() {
		if ( ! isset( $_GET["wpfp-download-log"] ) ) {
			return;
		}

		// check for nonce and return 400 error if not valid
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'wp-full-stripe-admin-nonce' ) ) {
			http_response_code( 400 );
			echo json_encode( [ 'success' => false, 'msg' => 'Invalid nonce ' . __FUNCTION__ ] );
			exit;
		}

		$this->sendLogFileHeader();

		try {
			$logCount = ( $this->db->getNumberOfLogEntries() )->logCount;
			$pageCount = $this->determineLogPageCount( $logCount );

			for ( $pageIndex = 0; $pageIndex < $pageCount; $pageIndex++ ) {
				$outBuffer = '';

				$logEntries = $this->db->getLogEntries( $pageIndex * self::LOG_DATABASE_PAGE_SIZE, self::LOG_DATABASE_PAGE_SIZE );
				foreach ( $logEntries as $logEntry ) {
					$outBuffer .= $this->formatLogEntry( $logEntry );
				}

				echo $outBuffer;
			}
		} catch (Exception $ex) {
			MM_WPFS_Utils::log( __CLASS__ . '::' . __FUNCTION__ . '(): Cannot generate log file: ' . $ex );
		}

		exit();
	}
}

class MM_WPFS_Logger {

	/**
	 * @var MM_WPFS_LoggerService
	 */
	private $loggerService;
	private $module;
	private $class;
	private $level;

	/**
	 * MM_WPFS_Logger constructor.
	 *
	 * @param $loggerService
	 * @param $module
	 * @param $class
	 * @param null $level
	 */
	public function __construct( $loggerService, $module, $class, $level = null ) {
		$this->loggerService = $loggerService;
		$this->module = $module;
		$this->class = $class;
		$this->setLevel( $level );
	}

	public function setLevel( $level ) {
		if ( false !== array_search( $level, $this->loggerService->getLevels() ) ) {
			$this->level = $level;
		}
	}

	/**
	 * @return MM_WPFS_LoggerService
	 */
	public function getLoggerService() {
		return $this->loggerService;
	}

	/**
	 * @return mixed
	 */
	public function getModule() {
		return $this->module;
	}

	/**
	 * @return mixed
	 */
	public function getClass() {
		return $this->class;
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 */
	public function info( $function, $message ) {
		if ( $this->isInfoEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, MM_WPFS_LoggerService::LEVEL_INFO, $message, null );
		}
	}

	public function isInfoEnabled() {
		return $this->isLevelEnabled( MM_WPFS_LoggerService::LEVEL_INFO );
	}

	protected function isLevelEnabled( $level ) {
		if ( $this->loggerService->getLevelPriority( $level ) >= $this->loggerService->getLevelPriority( $this->level ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 */
	public function debug( $function, $message ) {
		if ( $this->isDebugEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, MM_WPFS_LoggerService::LEVEL_DEBUG, $message, null );
		}
	}

	public function isDebugEnabled() {
		return $this->isLevelEnabled( MM_WPFS_LoggerService::LEVEL_DEBUG );
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 */
	public function warning( $function, $message ) {
		if ( $this->isWarnEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, MM_WPFS_LoggerService::LEVEL_WARNING, $message, null );
		}
	}

	public function isWarnEnabled() {
		return $this->isLevelEnabled( MM_WPFS_LoggerService::LEVEL_WARNING );
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 * @param null|Throwable $exception
	 */
	public function error( $function, $message, Throwable $exception = null ) {
		if ( $this->isErrorEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, MM_WPFS_LoggerService::LEVEL_ERROR, $message, $exception );
		}
	}

	public function isErrorEnabled() {
		return $this->isLevelEnabled( MM_WPFS_LoggerService::LEVEL_ERROR );
	}

}


trait MM_WPFS_Logger_AddOn {

	/**
	 * @var MM_WPFS_LoggerService
	 */
	protected $loggerService;
	/**
	 * @var MM_WPFS_Logger
	 */
	protected $logger;

	protected function initLogger( $loggerService, $module ) {
		$this->loggerService = $loggerService;
		$this->logger = $this->loggerService->createLogger( $module, get_class( $this ) );
	}
}

/**
 * Tracking/Logger Activation REST API Endpoint Handler
 */
class MM_WPFS_TrackingActivationEndpoint {

	const ROUTE_NAMESPACE = 'wp-full-stripe/v1';
	const ROUTE_PATH = '/tracking-activation';
	const NONCE_ACTION = 'wp_rest';

	/**
	 * Register the REST API endpoint.
	 * 
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Get the URL for the tracking activation endpoint.
	 * @return string
	 */
	public static function get_url() {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}

	/**
	 * Register REST routes for tracking activation.
	 * 
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'handle_tracking_activation' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'enabled' => [
						'type'        => 'boolean',
						'description' => 'Enable or disable tracking',
						'required'    => true,
					],
					'nonce'   => [
						'type'        => 'string',
						'description' => 'Security nonce',
						'required'    => true,
					],
				],
			]
		);
	}

	/**
	 * Check if user has permission to access this endpoint.
	 *
	 * @param WP_REST_Request<array{enabled: bool, nonce: string}> $request The request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_permission( $request ) {
		$nonce = $request->get_param( 'nonce' );
		
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error(
				'rest_nonce_invalid',
				'Invalid security nonce.',
				[ 'status' => 403 ]
			);
		}
	   
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				'You do not have permission to manage tracking settings.', // NOTE: This is used internally only.
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle the tracking activation request.
	 *
	 * @param WP_REST_Request<array{enabled: bool, nonce: string}> $request The request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_tracking_activation( $request ) {
		try {
			$enabled = $request->get_param( 'enabled' );
			
			$flag_value = $enabled ? 'yes' : 'no';
			$option_key = WPFS_License::get_namespace() . '_logger_flag';
			update_option( $option_key, $flag_value );
			
			return new WP_REST_Response(
				[
					'success'      => true,
					'message'      => 'Tracking settings updated successfully.', // NOTE: This is used internally only.
					'enabled'      => $enabled,
					'option_key'   => $option_key,
					'option_value' => $flag_value,
				],
				200
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'tracking_activation_error',
				sprintf( 'Failed to update tracking settings: %s', $e->getMessage() ),
				[ 'status' => 500 ]
			);
		}
	}
}