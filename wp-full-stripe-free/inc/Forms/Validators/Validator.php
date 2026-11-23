<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
abstract class Validator {
	use LoggerAddOn;
	use StaticContextAddOn;

	protected $options;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
		$this->options = new Options();

		$this->initStaticContext();
	}

	/**
	 * @param BindingResult $bindingResult
	 * @param Binder $formModelObject
	 */
	public abstract function validate( $bindingResult, $formModelObject );

}
