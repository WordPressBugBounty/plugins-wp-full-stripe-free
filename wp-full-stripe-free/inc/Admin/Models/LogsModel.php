<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\LogsValidator;
use ThemeIsle\WPFullPay\Admin\Views\LogsViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class LogsModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $logLevel;
	protected $logToWebServer;
	protected $catchUncaughtErrors;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new LogsValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getLogLevel() {
		return $this->logLevel;
	}

	public function getLogToWebServer() {
		return $this->logToWebServer;
	}

	/**
	 * @return mixed
	 */
	public function getCatchUncaughtErrors() {
		return $this->catchUncaughtErrors;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->logLevel = $this->getSanitizedArrayParam( $postData, LogsViewConstants::FIELD_FORM_LOG_LEVEL, LoggerService::LEVEL_ERROR );
		$this->logToWebServer = $this->getSanitizedArrayParam( $postData, LogsViewConstants::FIELD_FORM_LOG_TO_WEB_SERVER, 0 );
		$this->catchUncaughtErrors = $this->getSanitizedArrayParam( $postData, LogsViewConstants::FIELD_FORM_CATCH_UNCAUGHT_ERRORS, 0 );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'logLevel' => $this->logLevel,
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
