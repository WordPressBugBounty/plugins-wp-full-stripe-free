<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;

class AdminScriptLocalizerFactory {

	/**
	 * @param $options
	 * @param $page
	 * @param $tab
	 * @return AdminScriptLocalizer
	 * @throws UserFriendlyException
	 */
	public static function createLocalizer( $options, $page, $tab ): AdminScriptLocalizer {
		switch ( $page ) {
			case Menu::SLUG_SETTINGS_STRIPE:
				return new SettingsStripeLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_FORMS:
				return new SettingsFormsLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_CUSTOMER_PORTAL:
				return new SettingsCustomerPortalLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_SECURITY:
				return new SettingsSecurityLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS:
				return new SettingsEmailNotificationsLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_LOGS:
				return new SettingsLogsLocalizer( $options, $tab );

			case Menu::SLUG_SETTINGS_LICENSE:
				return new SettingsLicenseLocalizer( $options, $tab );

			case Menu::SLUG_CREATE_FORM:
				return new CreateFormLocalizer( $options, $tab );

			case Menu::SLUG_EDIT_FORM:
				return new EditFormLocalizer( $options, $tab, $_GET );

			case Menu::SLUG_FORMS:
				return new FormsLocalizer( $options, $tab );

			case Menu::SLUG_TRANSACTIONS:
				return new TransactionsLocalizer( $options, $tab );

			case Menu::SLUG_REPORTS:
				return new ReportsLocalizer( $options, $tab );

			default:
				return new DefaultLocalizer( $options, $tab );
		}
	}
}
