<?php

namespace ThemeIsle\WPFullPay\Webhooks;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Stripe;

class LiveModeAwareEventProcessorContext
{

	/**
	 * @var bool
	 */
	protected $liveMode;
	/**
	 * @var LoggerService
	 */
	protected $loggerService;
	/**
	 * @var Database
	 */
	protected $db = null;
	/**
	 * @var Mailer
	 */
	protected $mailer = null;
	/**
	 * This property is instantiated per request due to Stripe API mode to use the correct API key
	 * @var Stripe
	 */
	protected $stripe = null;

	/**
	 * LiveModeAwareEventProcessorContext constructor.
	 *
	 * @param LoggerService $loggerService
	 * @param Database $db
	 * @param Mailer $mailer
	 */
	public function __construct(LoggerService $loggerService, Database $db, Mailer $mailer)
	{
		$this->loggerService = $loggerService;
		$this->db = $db;
		$this->mailer = $mailer;
	}

	/**
	 * @return bool
	 */
	public function isLiveMode(): bool
	{
		return $this->liveMode;
	}

	/**
	 * @param bool $liveMode
	 */
	public function setLiveMode(bool $liveMode)
	{
		$this->liveMode = $liveMode;
	}

	/**
	 * @return LoggerService
	 */
	public function getLoggerService()
	{
		return $this->loggerService;
	}

	/**
	 * @return Database
	 */
	public function getDb()
	{
		return $this->db;
	}

	/**
	 * @return Stripe
	 */
	public function getStripe()
	{
		return $this->stripe;
	}

	/**
	 * @param Stripe $stripe
	 */
	public function setStripe(Stripe $stripe)
	{
		$this->stripe = $stripe;
	}

}
