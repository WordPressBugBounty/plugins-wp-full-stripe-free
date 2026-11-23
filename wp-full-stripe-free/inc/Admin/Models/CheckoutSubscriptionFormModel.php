<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\CheckoutSubscriptionFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSubscriptionFormViewConstants;

class CheckoutSubscriptionFormModel extends SubscriptionFormModel implements CheckoutForm {
	use CheckoutFormModel;
	use CheckoutPhoneNumberAddOn;

	protected $simpleButtonLayout;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_SUBSCRIPTION_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new CheckoutSubscriptionFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindCheckoutParams( $postData );
		$this->bindCheckoutPhoneNumberParams( $postData );

		$this->simpleButtonLayout = $this->getSanitizedArrayParam( $postData, CheckoutSubscriptionFormViewConstants::FIELD_FORM_SIMPLE_BUTTON_LAYOUT, 0 );

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'simpleButtonLayout' => $this->simpleButtonLayout,
			// Legacy fields
			'showRememberMe' => 0,
			'disableStyling' => 0,
		];

		$data = array_merge( $parentData, $data, $this->getCheckoutDataArray(), $this->getCheckoutPhoneNumberData() );

		return $data;
	}
}
