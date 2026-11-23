<?php

namespace ThemeIsle\WPFullPay\Logging;

trait LoggerAddOn {

	/**
	 * @var LoggerService
	 */
	protected $loggerService;
	/**
	 * @var Logger
	 */
	protected $logger;

	protected function initLogger( $loggerService, $module ) {
		$this->loggerService = $loggerService;
		$this->logger = $this->loggerService->createLogger( $module, get_class( $this ) );
	}
}
