<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\EmailOptionsView;
use ThemeIsle\WPFullPay\Admin\Views\EmailTemplatesView;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

class SettingsEmailNotificationsLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		if (
			$this->tab === Menu::PARAM_VALUE_TAB_OPTIONS ||
			$this->tab === null
		) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CONFIGURE_EMAIL_OPTIONS => EmailOptionsView::getFields()
			]
			);
		} elseif ( $this->tab === Menu::PARAM_VALUE_TAB_TEMPLATES ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CONFIGURE_EMAIL_TEMPLATES => EmailTemplatesView::getFields()
			]
			);
		}
	}

	public function getSettingsOptions() {
		$result = [];

		if ( $this->tab === Menu::PARAM_VALUE_TAB_TEMPLATES ) {
			$options = $this->options->getSeveral( [ 
				Options::OPTION_EMAIL_TEMPLATES
			] );

			// todo: refactor magic strings
			$result['emailTemplates'] = json_decode( $options[ Options::OPTION_EMAIL_TEMPLATES ] );
			$result['defaultEmailTemplates'] = Mailer::getDefaultEmailTemplates();
			$result['macros'] = Menu::createMacroData();
		}

		return $result;
	}
}
