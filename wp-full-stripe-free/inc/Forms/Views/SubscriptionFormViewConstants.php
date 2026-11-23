<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

interface SubscriptionFormViewConstants {

	const FIELD_PLAN = 'wpfs-plan';
	const FIELD_PLAN_QUANTITY = 'wpfs-plan-quantity';

	const ATTR_DATA_WPFS_VALUE = 'data-wpfs-value';
	const ATTR_DATA_WPFS_PLAN_AMOUNT = 'data-wpfs-plan-amount';
	const ATTR_DATA_WPFS_PLAN_AMOUNT_IN_SMALLEST_COMMON_CURRENCY = 'data-wpfs-plan-amount-in-smallest-common-currency';
	const ATTR_DATA_WPFS_INTERVAL = 'data-wpfs-interval';
	const ATTR_DATA_WPFS_INTERVAL_COUNT = 'data-wpfs-interval-count';
	const ATTR_DATA_WPFS_CANCELLATION_COUNT = 'data-wpfs-cancellation-count';
	const ATTR_DATA_WPFS_PLAN_SETUP_FEE = 'data-wpfs-plan-setup-fee';
	const ATTR_DATA_WPFS_PLAN_SETUP_FEE_IN_SMALLEST_COMMON_CURRENCY = 'data-wpfs-plan-setup-fee-in-smallest-common-currency';
	const ATTR_DATA_WPFS_SIMPLE_BUTTON_LAYOUT = 'data-wpfs-simple-button-layout';
	const ATTR_DATA_WPFS_TAX_RATE_TYPE = 'data-wpfs-tax-rate-type';
	/** What the plan's first invoice charges at once, one of the FIRST_CHARGE_* values. */
	const ATTR_DATA_WPFS_FIRST_CHARGE = 'data-wpfs-first-charge';

	/** The plan, its setup fee and its recovery fee, with their tax and discount. */
	const FIRST_CHARGE_PLAN = 'plan';
	/** Only the setup fee, with its tax and discount: the plan and its recovery fee are free during a trial. */
	const FIRST_CHARGE_SETUP_FEE = 'setup-fee';
	/** An amount the form can't price, such as the period until a billing anchor day. */
	const FIRST_CHARGE_UNKNOWN = 'unknown';

	const FIELD_ACTION_VALUE_INLINE_SUBSCRIPTION_CHARGE = 'wp_full_stripe_inline_subscription_charge';
	const FIELD_ACTION_VALUE_POPUP_SUBSCRIPTION_CHARGE = 'wp_full_stripe_popup_subscription_charge';

	const FIELD_VALUE_SUBSCRIPTION_FREQUENCY_DAILY = "day";
	const FIELD_VALUE_SUBSCRIPTION_FREQUENCY_WEEKLY = "week";
	const FIELD_VALUE_SUBSCRIPTION_FREQUENCY_MONTHLY = "month";
	const FIELD_VALUE_SUBSCRIPTION_FREQUENCY_ANNUAL = "year";
}
