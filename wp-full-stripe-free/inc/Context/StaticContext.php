<?php

namespace ThemeIsle\WPFullPay\Context;

use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;

class StaticContext {
	/** @var LoggerService */
	protected $loggerService;
	/** @var Options */
	protected $options;

	public function __construct( $loggerService, $options ) {
		$this->loggerService = $loggerService;
		$this->options = $options;
	}

	/**
	 * @return mixed
	 */
	public function getLoggerService() {
		return $this->loggerService;
	}

	/**
	 * @return mixed
	 */
	public function getOptions() {
		return $this->options;
	}
}
