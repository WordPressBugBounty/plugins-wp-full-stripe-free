<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutProductImageViewConstants;
use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait CheckoutProductImageViewAddOn {
    /** @var Control */
    protected $checkoutProductImage;

    protected function initCheckoutProductImageFields( $formHash ) {
        $this->checkoutProductImage = ControlUtils::createControl( $this->formHash, CheckoutProductImageViewConstants::FIELD_FORM_PRODUCT_IMAGE, null, null,
            __( 'Product image', 'wp-full-stripe-free' ), null );
        $this->checkoutProductImage->setAttributes( [
            'type'      => 'hidden',
        ]);
    }

    /**
     * @return Control
     */
    public function checkoutProductImage(): Control {
        return $this->checkoutProductImage;
    }
}
