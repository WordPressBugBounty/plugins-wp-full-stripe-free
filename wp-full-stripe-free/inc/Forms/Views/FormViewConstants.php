<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
interface FormViewConstants {

	const ATTR_ACTION = 'action';
	const ATTR_ID = 'id';
	const ATTR_CLASS = 'class';
	const ATTR_METHOD = 'method';
	const ATTR_VALUE = 'value';
	const ATTR_DATA_WPFS_FORM_ID = 'data-wpfs-form-id';
	const ATTR_DATA_WPFS_FORM_TYPE = 'data-wpfs-form-type';
	const ATTR_DATA_WPFS_FORM_HASH = 'data-wpfs-form-hash';
	const ATTR_DATA_WPFS_SHOW_ADDRESS = 'data-wpfs-show-address';
	const ATTR_DATA_WPFS_SHOW_BILLING_ADDRESS = 'data-wpfs-show-billing-address';
	const ATTR_DATA_WPFS_SHOW_SHIPPING_ADDRESS = 'data-wpfs-show-shipping-address';
	const ATTR_DATA_WPFS_CUSTOM_INPUT_TITLE = 'data-wpfs-custom-input-title';
	const ATTR_DATA_WPFS_CUSTOM_INPUTS = 'data-wpfs-custom-inputs';
	const ATTR_DATA_WPFS_CUSTOM_INPUT_REQUIRED = 'data-wpfs-custom-input-required';
	const ATTR_DATA_WPFS_SHOW_TERMS_OF_USE = 'data-wpfs-show-terms-of-use';
	const ATTR_DATA_WPFS_TERMS_OF_USE_NOT_CHECKED_ERROR_MESSAGE = 'data-wpfs-terms-of-use-not-checked-error-message';
	const ATTR_DATA_WPFS_CUSTOM_INPUT_FIELD = 'data-wpfs-custom-input-field';
	const ATTR_DATA_WPFS_CUSTOM_INPUT_LABEL = 'data-wpfs-custom-input-label';

	// Metadata keys used to carry typed custom field information to the form template.
	const CUSTOM_FIELD_META_TYPE        = 'type';
	const CUSTOM_FIELD_META_REQUIRED    = 'required';
	const CUSTOM_FIELD_META_DESCRIPTION = 'description';
	const CUSTOM_FIELD_META_OPTIONS     = 'options';
	const CUSTOM_FIELD_META_SETTINGS    = 'settings';
	const CUSTOM_FIELD_META_FIELD_ID    = 'fieldId';
	const CUSTOM_FIELD_META_CONTENT     = 'content';
	const ATTR_DATA_WPFS_VAT_RATE_TYPE = 'data-wpfs-vat-rate-type';
	const ATTR_DATA_WPFS_CURRENCY = 'data-wpfs-currency';
	const ATTR_DATA_WPFS_ZERO_DECIMAL_SUPPORT = 'data-wpfs-zero-decimal-support';
	const ATTR_DATA_WPFS_CURRENCY_SYMBOL = 'data-wpfs-currency-symbol';
	const ATTR_DATA_WPFS_AMOUNT = 'data-wpfs-amount';
	const ATTR_DATA_WPFS_AMOUNT_IN_SMALLEST_COMMON_CURRENCY = 'data-wpfs-amount-in-smallest-common-currency';
	const ATTR_DATA_WPFS_AMOUNT_INDEX = 'data-wpfs-amount-index';
	const ATTR_DATA_WPFS_AMOUNT_DESCRIPTION = 'data-wpfs-amount-description';
	const ATTR_DATA_WPFS_PRODUCT_NAME = 'data-wpfs-product-name';
	const ATTR_DATA_WPFS_AMOUNT_TYPE = 'data-wpfs-amount-type';
	const ATTR_DATA_WPFS_AMOUNT_PRICE_ID = 'data-wpfs-amount-price-id';
	const ATTR_DATA_WPFS_ALLOW_LIST_OF_AMOUNTS_CUSTOM = 'data-wpfs-allow-list-of-amounts-custom';
	const ATTR_DATA_WPFS_SELECTOR_STYLE = 'data-wpfs-selector-style';
	const ATTR_DATA_WPFS_BUTTON_TITLE = 'data-wpfs-button-title';
	const ATTR_DATA_WPFS_SHOW_AMOUNT = 'data-wpfs-show-amount';
	const ATTR_DATA_WPFS_STEPPER = 'data-wpfs-stepper';
	const ATTR_DATA_WPFS_DECIMAL_SEPARATOR = 'data-wpfs-decimal-separator';
	const ATTR_DATA_WPFS_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE = 'data-wpfs-show-currency-symbol-instead-of-code';
	const ATTR_DATA_WPFS_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION = 'data-wpfs-show-currency-sign-at-first-position';
	const ATTR_DATA_WPFS_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT = 'data-wpfs-put-whitespace-between-currency-and-amount';
	const ATTR_DATA_WPFS_SHOW_COUPON_FIELD = 'data-wpfs-show-coupon-field';
	const ATTR_DATA_WPFS_ELEMENTS_THEME = 'data-wpfs-elements-theme';
	const ATTR_DATA_WPFS_ELEMENTS_FONT = 'data-wpfs-elements-font';

	const ATTR_DATA_WPFS_FEE_RECOVERY_ENABLED = 'data-wpfs-fee-recovery-enabled';
	const ATTR_DATA_WPFS_FEE_AMOUNT     = 'data-wpfs-fee-amount';
	const ATTR_DATA_WPFS_FEE_PERCENTAGE = 'data-wpfs-fee-percentage';
	const ATTR_DATA_WPFS_FEE_RECOVERY_LABEL = 'data-wpfs-fee-recovery-label';
	const ATTR_DATA_WPFS_PAYMENT_METHOD_TYPES = 'data-wpfs-payment-method-types';
	const ATTR_DATA_WPFS_STRIPE_LINK = 'data-wpfs-stripe-link';
	const ATTR_DATA_WPFS_KEEP_DISABLED = 'data-wpfs-keep-disabled';

	const ATTR_DATA_DEFAULT_VALUE = 'data-default-value';
	const ATTR_DATA_MINIMUM_VALUE = 'data-min';
	const ATTR_DATA_MAXIMUM_VALUE = 'data-max';

	const ATTR_ID_VALUE_PREFIX = 'wpfs-form--';
	const ATTR_ID_VALUE_WPFS_CREATE_FORM = 'wpfs-create-form';
	const ATTR_CLASS_VALUE_WPFS_FORM = 'wpfs-form';
	const ATTR_CLASS_VALUE_WPFS_FORM_WPFS_W_60 = 'wpfs-form wpfs-w-60';
	const ATTR_CLASS_VALUE_WPFS_FORM_WPFS_FORM_INLINE = 'wpfs-form wpfs-form--inline';
	const ATTR_METHOD_VALUE_POST = 'post';

	const FIELD_ACTION = 'action';
	const FIELD_FORM_NAME = 'wpfs-form-name';
	const FIELD_FORM_GET_PARAMETERS = 'wpfs-form-get-parameters';
	const FIELD_SAME_BILLING_AND_SHIPPING_ADDRESS = 'wpfs-same-billing-and-shipping-address';
	const FIELD_ADDRESS_SWITCHER = 'wpfs-address-switcher';
	const FIELD_BILLING_ADDRESS_PANEL = 'wpfs-billing-address-panel';
	const FIELD_BILLING_NAME = 'wpfs-billing-name';
	const FIELD_BILLING_ADDRESS_LINE1 = 'wpfs-billing-address-line-1';
	const FIELD_BILLING_ADDRESS_LINE2 = 'wpfs-billing-address-line-2';
	const FIELD_BILLING_ADDRESS_ZIP = 'wpfs-billing-address-zip';
	const FIELD_BILLING_ADDRESS_STATE = 'wpfs-billing-address-state';
	const FIELD_BILLING_ADDRESS_STATE_SELECT = 'wpfs-billing-address-state-select';
	const FIELD_BILLING_ADDRESS_CITY = 'wpfs-billing-address-city';
	const FIELD_BILLING_ADDRESS_COUNTRY = 'wpfs-billing-address-country';
	const FIELD_SHIPPING_ADDRESS_PANEL = 'wpfs-shipping-address-panel';
	const FIELD_SHIPPING_NAME = 'wpfs-shipping-name';
	const FIELD_SHIPPING_ADDRESS_LINE1 = 'wpfs-shipping-address-line-1';
	const FIELD_SHIPPING_ADDRESS_LINE2 = 'wpfs-shipping-address-line-2';
	const FIELD_SHIPPING_ADDRESS_ZIP = 'wpfs-shipping-address-zip';
	const FIELD_SHIPPING_ADDRESS_STATE = 'wpfs-shipping-address-state';
	const FIELD_SHIPPING_ADDRESS_STATE_SELECT = 'wpfs-shipping-address-state-select';
	const FIELD_SHIPPING_ADDRESS_CITY = 'wpfs-shipping-address-city';
	const FIELD_SHIPPING_ADDRESS_COUNTRY = 'wpfs-shipping-address-country';
	const FIELD_CUSTOM_INPUT = 'wpfs-custom-input';
	const FIELD_COUPON = 'wpfs-coupon';
	const FIELD_TERMS_OF_USE_ACCEPTED = 'wpfs-terms-of-use-accepted';
	const FIELD_FEE_RECOVERY_ACCEPTED = 'wpfs-fee-recovery-accepted';
	const FIELD_GOOGLE_RECAPTCHA_RESPONSE = 'g-recaptcha-response';
	const FIELD_NONCE = 'wpfs-nonce';

	const MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT = '{{amount}}';

	const BUTTON_SUBMIT = 'submit';
}
