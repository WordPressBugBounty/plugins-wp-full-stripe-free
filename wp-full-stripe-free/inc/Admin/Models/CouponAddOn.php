<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\CouponViewConstants;

trait CouponAddOn {
	protected $showCouponField;

	protected function bindCouponParameters( $postData ) {
		$this->showCouponField = $this->getSanitizedArrayParam( $postData, CouponViewConstants::FIELD_FORM_SHOW_COUPON, 0 );
	}

	protected function getCouponData() {
		return [
			'showCouponInput' => $this->showCouponField,
		];
	}

	/**
	 * @return mixed
	 */
	public function getShowCouponField() {
		return $this->showCouponField;
	}
}
