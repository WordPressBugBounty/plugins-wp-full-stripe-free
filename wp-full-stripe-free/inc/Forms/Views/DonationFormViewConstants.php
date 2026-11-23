<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

interface DonationFormViewConstants {
	const FIELD_CUSTOM_AMOUNT = 'wpfs-custom-amount';
	const FIELD_CUSTOM_AMOUNT_UNIQUE = 'wpfs-custom-amount-unique';
	const FIELD_DONATION_FREQUENCY = 'wpfs-donation-frequency';

	const FIELD_VALUE_CUSTOM_AMOUNT_OTHER = 'other';

	const FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME = "one-time";
	const FIELD_VALUE_DONATION_FREQUENCY_DAILY = "daily";
	const FIELD_VALUE_DONATION_FREQUENCY_WEEKLY = "weekly";
	const FIELD_VALUE_DONATION_FREQUENCY_MONTHLY = "monthly";
	const FIELD_VALUE_DONATION_FREQUENCY_ANNUAL = "annual";

	const FIELD_ACTION_VALUE_INLINE_DONATION_CHARGE = 'wp_full_stripe_inline_donation_charge';
	const FIELD_ACTION_VALUE_POPUP_DONATION_CHARGE = 'wp_full_stripe_popup_donation_charge';
}
