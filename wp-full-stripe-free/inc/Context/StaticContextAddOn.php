<?php

namespace ThemeIsle\WPFullPay\Context;

trait StaticContextAddOn {
	/** @var StaticContext */
	protected $staticContext = null;

	/** @noinspection PhpUndefinedFieldInspection */
	protected function initStaticContext() {
		$this->staticContext = new StaticContext( $this->loggerService, $this->options );
	}
}
