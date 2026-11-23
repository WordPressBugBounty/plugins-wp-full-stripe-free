<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Views\CreateFormView;
use ThemeIsle\WPFullPay\Main;

class CreateFormLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
			Main::FORM_TYPE_ADMIN_CREATE_FORM => CreateFormView::getFields()
		]
		);
	}
}
