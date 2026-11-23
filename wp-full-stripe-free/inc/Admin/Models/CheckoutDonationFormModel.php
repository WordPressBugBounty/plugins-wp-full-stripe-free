<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\CheckoutDonationFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormViewConstants;

class CheckoutDonationFormModel extends DonationFormModel implements CheckoutForm {
	use CheckoutFormModel;
	use CheckoutPhoneNumberAddOn;
	use CheckoutProductImageAddOn;

	protected $checkoutProductName;
	protected $checkoutProductDescription;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_DONATION_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new CheckoutDonationFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindCheckoutParams( $postData );
		$this->bindCheckoutPhoneNumberParams( $postData );
		$this->bindCheckoutProductImageParams( $postData );

		$this->checkoutProductName = $this->getSanitizedArrayParam( $postData, CheckoutDonationFormViewConstants::FIELD_FORM_CHECKOUT_PRODUCT_NAME );
		$this->checkoutProductDescription = $this->getSanitizedArrayParam( $postData, CheckoutDonationFormViewConstants::FIELD_FORM_CHECKOUT_PRODUCT_DESCRIPTION );

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'productDesc' => $this->checkoutProductName,
			'companyName' => $this->checkoutProductDescription,
		];
		$data = array_merge( $parentData, $data, $this->getCheckoutDataArray(), $this->getCheckoutPhoneNumberData() );
		$data['image'] = $this->checkoutProductImage;

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
