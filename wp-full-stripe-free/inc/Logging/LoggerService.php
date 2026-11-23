<?php

namespace ThemeIsle\WPFullPay\Logging;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Utils;

class LoggerService {

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
	 * @var Database
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
	 * LoggerService constructor.
	 */
	public function __construct( $logLevel, $isPhpLoggingEnabled ) {
		$this->db = new Database();
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
	 * @return Logger
	 */
	public function createLogger( $module, $class ) {
		return new Logger( $this, $module, $class, $this->getModuleLevel( $module ) );
	}

	public function createPatcherLogger( $class ) {
		return $this->createLogger( self::MODULE_PATCHER, $class );
	}
	public function createAdminLogger( $class ) {
		return $this->createLogger( self::MODULE_ADMIN, $class );
	}

	/**
	 * @param string $module one of the MODULE constant
	 * @param string $class always use __CLASS__ when possible
	 * @param string $function always use __FUNCTION__ when possible
	 * @param string $level
	 * @param string $message
	 * @param string $function
	 * @param null|\Throwable $throwable
	 */
	public function log( $module, $class, $function, $level, $message, $throwable = null ) {
		$loggedMessage = $message;
		// Wrapped exceptions keep the technical detail, e.g. database errors shown to users in a generic form.
		for ( $current = $throwable; null !== $current; $current = $current->getPrevious() ) {
			$loggedMessage .= ' - ' . $current->getMessage();
		}
		$loggedStackTrace = is_null( $throwable ) ? '' : self::traceWithoutArguments( $throwable );

		try {
			$this->db->insertLog( $module, $class, $function, $level, $loggedMessage, $loggedStackTrace );
		} catch ( \Throwable $loggingError ) {
			$loggedStackTrace .= ( empty( $loggedStackTrace ) ? '' : "\n" )
				. 'Database logging failed: ' . $loggingError->getMessage()
				. "\n" . self::traceWithoutArguments( $loggingError );
		}

		if ( $this->isPhpLoggingEnabled ) {
			$this->writePhpLog( $this->formatLogEntryAsParams( '', $level, $module, $class, $function, $loggedMessage, $loggedStackTrace ) );
		}
	}

	/**
	 * The stack trace without argument values. PHP can include them in traces, and arguments carry secrets
	 * such as the site's platform credential, which must never reach a log.
	 *
	 * @param \Throwable $throwable
	 * @return string The trace in the format of Throwable::getTraceAsString(), with every argument list empty.
	 */
	public static function traceWithoutArguments( \Throwable $throwable ): string {
		$lines = [];
		foreach ( $throwable->getTrace() as $index => $frame ) {
			$location = isset( $frame['file'] ) ? $frame['file'] . '(' . ( $frame['line'] ?? 0 ) . ')' : '[internal function]';
			$lines[] = '#' . $index . ' ' . $location . ': ' . ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . $frame['function'] . '()';
		}
		$lines[] = '#' . count( $lines ) . ' {main}';

		return implode( "\n", $lines );
	}

	/**
	 * Write an entry to the configured PHP error log.
	 *
	 * @param string $message Log entry.
	 */
	protected function writePhpLog( $message ): void {
		error_log( $message );
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
		} catch (\Exception $ex) {
			Utils::log( __CLASS__ . '::' . __FUNCTION__ . '(): Cannot generate log file: ' . $ex );
		}

		exit();
	}
}
