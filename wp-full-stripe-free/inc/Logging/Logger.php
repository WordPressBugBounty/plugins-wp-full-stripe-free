<?php

namespace ThemeIsle\WPFullPay\Logging;

class Logger {

	/**
	 * @var LoggerService
	 */
	private $loggerService;
	private $module;
	private $class;
	private $level;

	/**
	 * Logger constructor.
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
	 * @return LoggerService
	 */
	public function getLoggerService() {
		return $this->loggerService;
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
			$this->loggerService->log( $this->module, $this->class, $function, LoggerService::LEVEL_INFO, $message, null );
		}
	}

	public function isInfoEnabled() {
		return $this->isLevelEnabled( LoggerService::LEVEL_INFO );
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
			$this->loggerService->log( $this->module, $this->class, $function, LoggerService::LEVEL_DEBUG, $message, null );
		}
	}

	public function isDebugEnabled() {
		return $this->isLevelEnabled( LoggerService::LEVEL_DEBUG );
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 */
	public function warning( $function, $message ) {
		if ( $this->isWarnEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, LoggerService::LEVEL_WARNING, $message, null );
		}
	}

	public function isWarnEnabled() {
		return $this->isLevelEnabled( LoggerService::LEVEL_WARNING );
	}

	/**
	 * @param string $function always use __FUNCTION__ when possible
	 * @param $message
	 * @param null|\Throwable $exception
	 */
	public function error( $function, $message, ?\Throwable $exception = null ) {
		if ( $this->isErrorEnabled() ) {
			$this->loggerService->log( $this->module, $this->class, $function, LoggerService::LEVEL_ERROR, $message, $exception );
		}
	}

	public function isErrorEnabled() {
		return $this->isLevelEnabled( LoggerService::LEVEL_ERROR );
	}

}
