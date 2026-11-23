<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\CheckoutSaveCardFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;

class CheckoutSaveCardFormModel extends SaveCardFormModel implements CheckoutForm {
	use CheckoutFormModel;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_SAVE_CARD_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new CheckoutSaveCardFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindCheckoutParams( $postData );

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$inlineData = $this->getCheckoutDataArray();

		$data = [
			// Legacy fields
			'showRememberMe' => 0,
			'disableStyling' => 0,
		];

		$data = array_merge( $data, $inlineData, $parentData );

		return $data;
	}
}
