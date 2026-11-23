<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait FormViewCouponAddOn {
    /** @var Control */
    protected $showCouponField;

    /**
     * @return Control
     */
    public function showCouponField(): Control {
        return $this->showCouponField;
    }

    protected function initCoupon() {
        $this->showCouponField = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SHOW_COUPON_FIELD, null, null,
            __( 'Coupon', 'wp-full-stripe-free' ), null );
        $this->showCouponField->setValue('1' );
        $this->showCouponField->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);
    }

    public static function getCouponFields() {
        return [
            CouponViewConstants::FIELD_FORM_SHOW_COUPON => ControlUtils::input( CouponViewConstants::FIELD_FORM_SHOW_COUPON ),
        ];
    }
}
