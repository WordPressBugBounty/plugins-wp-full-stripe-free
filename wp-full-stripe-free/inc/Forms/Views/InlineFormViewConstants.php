<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

interface InlineFormViewConstants {

	const FIELD_CARD_HOLDER_NAME = 'wpfs-card-holder-name';
	const FIELD_CARD_HOLDER_EMAIL = 'wpfs-card-holder-email';
	/**
	 * This is the default field name that Stripe Elements uses for Cards. It should be changed only in correlation with
	 * the Stripe Elements configuration.
	 */
	const FIELD_CARD_NUMBER = 'cardnumber';

	const ATTR_DATA_WPFS_PREFERRED_LANGUAGE = 'data-wpfs-preferred-language';

}
