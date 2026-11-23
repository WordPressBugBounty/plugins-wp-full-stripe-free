<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Views\ConfigureStripeAccountView;
use ThemeIsle\WPFullPay\Main;

class SettingsStripeLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
			Main::FORM_TYPE_ADMIN_CONFIGURE_STRIPE_ACCOUNT => ConfigureStripeAccountView::getFields()
		]
		);
	}
}
