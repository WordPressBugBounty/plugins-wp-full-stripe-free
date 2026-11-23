<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\CheckoutPaymentFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormViewConstants;

class CheckoutPaymentFormModel extends PaymentFormModel implements CheckoutForm {
	use CheckoutFormModel;
	use CheckoutPhoneNumberAddOn;

	protected $checkoutProductName;
	protected $checkoutProductDescription;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_PAYMENT_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new CheckoutPaymentFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindCheckoutParams( $postData );
		$this->bindCheckoutPhoneNumberParams( $postData );

		$this->checkoutProductName = $this->getSanitizedArrayParam( $postData, CheckoutPaymentFormViewConstants::FIELD_FORM_CHECKOUT_PRODUCT_NAME );
		$this->checkoutProductDescription = $this->getSanitizedArrayParam( $postData, CheckoutPaymentFormViewConstants::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION );

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'productDesc' => $this->checkoutProductName,
			'companyName' => $this->checkoutProductDescription,
			// Legacy fields
			'showRememberMe' => 0,
			'disableStyling' => 0,
			'useBitcoin' => 0,
			'useAlipay' => 0
		];

		$data = array_merge( $data, $parentData, $this->getCheckoutDataArray(), $this->getCheckoutPhoneNumberData() );

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getCheckoutProductName() {
		return $this->checkoutProductName;
	}

	/**
	 * @return mixed
	 */
	public function getCheckoutProductDescription() {
		return $this->checkoutProductDescription;
	}
}
