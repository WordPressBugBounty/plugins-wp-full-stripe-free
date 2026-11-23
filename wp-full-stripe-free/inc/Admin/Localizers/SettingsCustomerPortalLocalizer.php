<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Views\CustomerPortalView;
use ThemeIsle\WPFullPay\Main;

class SettingsCustomerPortalLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
			Main::FORM_TYPE_ADMIN_CONFIGURE_CUSTOMER_PORTAL => CustomerPortalView::getFields()
		]
		);
	}
}
