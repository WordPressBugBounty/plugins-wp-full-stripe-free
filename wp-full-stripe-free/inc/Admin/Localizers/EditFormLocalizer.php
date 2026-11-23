<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSaveCardFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSubscriptionFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlinePaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineSaveCardFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineSubscriptionFormView;
use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Macros\MacroHelperTools;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Main;

class EditFormLocalizer extends AdminScriptLocalizer {
	protected $formType;

	public function __construct( $options, $tab = null, $getParameters = null ) {
		parent::__construct( $options, $tab, $getParameters );

		$this->getFormType( $getParameters );
	}

	protected function getFormType( $getParameters ) {
		if ( array_key_exists( Menu::PARAM_NAME_TYPE, $getParameters ) ) {
			$this->formType = $getParameters[ Menu::PARAM_NAME_TYPE ];
		} else {
			throw new \Exception( __CLASS__ . '.' . __FUNCTION__ . "(): URL parameter 'type' not found." );
		}
	}

	public function localizeScripts() {
		if ( $this->formType === Main::FORM_TYPE_INLINE_SAVE_CARD ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_INLINE_SAVE_CARD_FORM => InlineSaveCardFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_CHECKOUT_SAVE_CARD ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CHECKOUT_SAVE_CARD_FORM => CheckoutSaveCardFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_INLINE_DONATION ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_INLINE_DONATION_FORM => InlineDonationFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_CHECKOUT_DONATION ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CHECKOUT_DONATION_FORM => CheckoutDonationFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_INLINE_PAYMENT ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_INLINE_PAYMENT_FORM => InlinePaymentFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_CHECKOUT_PAYMENT ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CHECKOUT_PAYMENT_FORM => CheckoutPaymentFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_INLINE_SUBSCRIPTION ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_INLINE_SUBSCRIPTION_FORM => InlineSubscriptionFormView::getFields()
			]
			);
		} elseif ( $this->formType === Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION ) {
			wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsFormFields', [
				Main::FORM_TYPE_ADMIN_CHECKOUT_SUBSCRIPTION_FORM => CheckoutSubscriptionFormView::getFields()
			]
			);
		}

	}

	public function getSettingsOptions() {
		$options = [];

		$options['macroDescriptions'] = MacroHelperTools::getMacroDescriptions();
		if ( $this->codeEditorSettings !== null ) {
			$options['codeEditorSettings'] = $this->codeEditorSettings;
		}

		if (
			$this->formType === Main::FORM_TYPE_INLINE_SAVE_CARD ||
			$this->formType === Main::FORM_TYPE_CHECKOUT_SAVE_CARD
		) {
			$options['macroKeys'] = SaveCardMacroReplacer::getMacroKeys();
		} elseif (
			$this->formType === Main::FORM_TYPE_INLINE_DONATION ||
			$this->formType === Main::FORM_TYPE_CHECKOUT_DONATION
		) {
			$options['macroKeys'] = DonationMacroReplacer::getMacroKeys();
		} elseif (
			$this->formType === Main::FORM_TYPE_INLINE_PAYMENT ||
			$this->formType === Main::FORM_TYPE_CHECKOUT_PAYMENT
		) {
			$options['macroKeys'] = OneTimePaymentMacroReplacer::getMacroKeys();
		} elseif (
			$this->formType === Main::FORM_TYPE_INLINE_SUBSCRIPTION ||
			$this->formType === Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION
		) {
			$options['macroKeys'] = SubscriptionMacroReplacer::getMacroKeys();
		}

		return $options;
	}

	/**
	 * Enqueue the core CSS editor for inline forms.
	 *
	 * @return void
	 */
	public function enqueueScripts() {
		if (
			$this->formType === Main::FORM_TYPE_INLINE_SAVE_CARD ||
			$this->formType === Main::FORM_TYPE_INLINE_DONATION ||
			$this->formType === Main::FORM_TYPE_INLINE_PAYMENT ||
			$this->formType === Main::FORM_TYPE_INLINE_SUBSCRIPTION
		) {
			$this->enqueueCssEditor();
		}
	}
}
