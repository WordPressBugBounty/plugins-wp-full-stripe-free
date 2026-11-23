<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Views\LogEmptyView;
use ThemeIsle\WPFullPay\Admin\Views\LogLevelView;
use ThemeIsle\WPFullPay\Main;

class SettingsLogsLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
			Main::FORM_TYPE_ADMIN_CONFIGURE_LOGGING => LogLevelView::getFields(),
			Main::FORM_TYPE_ADMIN_EMPTY_LOG => LogEmptyView::getFields()
		]
		);
	}
}
