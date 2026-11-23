<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

interface PaymentFormViewConstants {

	const FIELD_CUSTOM_AMOUNT = 'wpfs-custom-amount';
	const FIELD_CUSTOM_AMOUNT_UNIQUE = 'wpfs-custom-amount-unique';

	const FIELD_ACTION_VALUE_INLINE_PAYMENT_CHARGE = 'wp_full_stripe_inline_payment_charge';
	const FIELD_ACTION_VALUE_POPUP_PAYMENT_CHARGE = 'wp_full_stripe_popup_payment_charge';

	const ATTR_DATA_WPFS_TAX_RATE_TYPE = 'data-wpfs-tax-rate-type';
}
