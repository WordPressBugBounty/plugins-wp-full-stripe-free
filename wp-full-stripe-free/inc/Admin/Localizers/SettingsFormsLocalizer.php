<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\FormsAppearanceView;
use ThemeIsle\WPFullPay\Admin\Views\FormsOptionsView;
use ThemeIsle\WPFullPay\Main;

class SettingsFormsLocalizer extends AdminScriptLocalizer {
	public function localizeScripts() {
		if (
			$this->tab === Menu::PARAM_VALUE_TAB_OPTIONS ||
			$this->tab === null
		) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CONFIGURE_FORMS_OPTIONS => FormsOptionsView::getFields()
			]
			);
		} elseif ( $this->tab === Menu::PARAM_VALUE_TAB_APPEARANCE ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CONFIGURE_FORMS_APPEARANCE => FormsAppearanceView::getFields()
			]
			);
		}
	}

	public function getSettingsOptions() {
		$options = [];

		if ( $this->tab === Menu::PARAM_VALUE_TAB_APPEARANCE ) {
			$options['codeEditorSettings'] = $this->codeEditorSettings;
		}

		return $options;
	}

	public function enqueueScripts() {
		if ( $this->tab === Menu::PARAM_VALUE_TAB_APPEARANCE ) {
			$this->enqueueCssEditor();
		}
	}
}
