<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

trait CheckoutProductImageAddOn {
	protected $checkoutProductImage;

	protected function bindCheckoutProductImageParams( $postData ) {
		$this->checkoutProductImage = $this->getSanitizedArrayParam( $postData, CheckoutProductImageViewConstants::FIELD_FORM_PRODUCT_IMAGE, 0 );
	}

}
