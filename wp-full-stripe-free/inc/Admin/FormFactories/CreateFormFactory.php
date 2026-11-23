<?php

namespace ThemeIsle\WPFullPay\Admin\FormFactories;

use ThemeIsle\WPFullPay\Admin\Models\CreateFormModel;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

/**
 * @phpstan-import-type FormTypeKey from FormTypes
 */
class CreateFormFactory {

	const CHARGE_TYPE_IMMEDIATE = 'immediate';
	const AMOUNT_10_USD = 1000;
	const CURRENCY_USD = 'usd';
	const CUSTOM_AMOUNT_LIST_OF_AMOUNTS = 'list_of_amounts';
	const CUSTOM_AMOUNT_SPECIFIED_AMOUNT = 'specified_amount';
	const CUSTOM_AMOUNT_SAVE_CARD = 'card_capture';
	const AMOUNT_SELECTOR_STYLE_DROPDOWN = 'dropdown';
	const PREFERRED_LANGUAGE_AUTO = 'auto';
	const DECIMAL_SEPARATOR_DOT = 'dot';
	const VAT_RATE_TYPE_NO_VAT = 'no_vat';
	const PLAN_SELECTOR_STYLE_DROPDOWN = 'dropdown';

	/** @var Database */
	private $db = null;

	/** @var Options */
	private $options = null;

	public function __construct() {
		$this->db = new Database();
		$this->options = new Options();
	}

	/**
	 * Global default for how the payment details summary is displayed on newly created forms.
	 * Falls back to "on hover" (1) when the option has not been set yet.
	 *
	 * @return string
	 */
	private function getDefaultShowPaymentDetail() {
		$mode = $this->options->get( Options::OPTION_DEFAULT_SHOW_PAYMENT_DETAIL );

		return ( is_null( $mode ) || '' === $mode ) ? '1' : $mode;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileInlinePaymentFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['formTitle'] = '';
		$form['chargeType'] = self::CHARGE_TYPE_IMMEDIATE;
		$form['amount'] = self::AMOUNT_10_USD;
		$form['currency'] = self::CURRENCY_USD;
		$form['customAmount'] = self::CUSTOM_AMOUNT_LIST_OF_AMOUNTS;
		$form['amountSelectorStyle'] = self::AMOUNT_SELECTOR_STYLE_DROPDOWN;
		$form['vatRateType'] = Main::FIELD_VALUE_TAX_RATE_NO_TAX;
		$form['vatRates'] = json_encode( [] );
		$form['buttonTitle'] = Utils::getDefaultPaymentButtonTitle();
		$form['customInputTitle'] = '';
		$form['stripeDescription'] = Utils::getDefaultPaymentStripeDescription();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['paymentmethods'] = '["card","link"]';
		$form['showPaymentDetail'] = $this->getDefaultShowPaymentDetail();

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileCheckoutPaymentFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['companyName'] = '';
		$form['productDesc'] = Utils::getDefaultProductDescription();
		$form['chargeType'] = self::CHARGE_TYPE_IMMEDIATE;
		$form['amount'] = self::AMOUNT_10_USD;
		$form['currency'] = self::CURRENCY_USD;
		$form['customAmount'] = self::CUSTOM_AMOUNT_LIST_OF_AMOUNTS;
		$form['amountSelectorStyle'] = self::AMOUNT_SELECTOR_STYLE_DROPDOWN;
		$form['vatRateType'] = Main::FIELD_VALUE_TAX_RATE_NO_TAX;
		$form['vatRates'] = json_encode( [] );
		$form['openButtonTitle'] = Utils::getDefaultPaymentOpenButtonTitle();
		$form['buttonTitle'] = Utils::getDefaultPaymentButtonTitle();
		$form['customInputTitle'] = '';
		$form['stripeDescription'] = Utils::getDefaultPaymentStripeDescription();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['showPaymentDetail'] = $this->getDefaultShowPaymentDetail();

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileInlineSubscriptionFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['formTitle'] = '';
		$form['customInputTitle'] = '';
		$form['buttonTitle'] = Utils::getDefaultSubscriptionButtonTitle();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['planSelectorStyle'] = self::PLAN_SELECTOR_STYLE_DROPDOWN;
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['decoratedPlans'] = json_encode( [] );
		$form['vatRateType'] = Main::FIELD_VALUE_TAX_RATE_NO_TAX;
		$form['vatRates'] = json_encode( [] );
		$form['showPaymentDetail'] = $this->getDefaultShowPaymentDetail();

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileCheckoutSubscriptionFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['companyName'] = '';
		$form['productDesc'] = Utils::getDefaultProductDescription();
		$form['customInputTitle'] = '';
		$form['buttonTitle'] = Utils::getDefaultSubscriptionButtonTitle();
		$form['openButtonTitle'] = Utils::getDefaultSubscriptionOpenButtonTitle();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['planSelectorStyle'] = self::PLAN_SELECTOR_STYLE_DROPDOWN;
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['decoratedPlans'] = json_encode( [] );
		$form['vatRateType'] = Main::FIELD_VALUE_TAX_RATE_NO_TAX;
		$form['vatRates'] = json_encode( [] );
		$form['showPaymentDetail'] = $this->getDefaultShowPaymentDetail();

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileInlineDonationFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['currency'] = self::CURRENCY_USD;
		$form['donationAmounts'] = json_encode( [ '100', '200', '500', '1000', '2000', '5000', '10000' ] );
		$form['allowCustomDonationAmount'] = 1;
		$form['allowMonthlyRecurring'] = 1;
		$form['allowAnnualRecurring'] = 1;
		$form['stripeDescription'] = Utils::getDefaultDonationDescription();
		$form['productDesc'] = Utils::getDefaultDonationProductDescription();
		$form['buttonTitle'] = Utils::getDefaultDonationButtonTitle();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['customInputTitle'] = '';

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileCheckoutDonationFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['currency'] = self::CURRENCY_USD;
		$form['donationAmounts'] = json_encode( [ '100', '200', '500', '1000', '2000', '5000', '10000' ] );
		$form['allowCustomDonationAmount'] = 1;
		$form['allowMonthlyRecurring'] = 1;
		$form['allowAnnualRecurring'] = 1;
		$form['stripeDescription'] = Utils::getDefaultDonationDescription();
		$form['companyName'] = '';
		$form['productDesc'] = Utils::getDefaultDonationProductDescription();
		$form['openButtonTitle'] = Utils::getDefaultDonationOpenButtonTitle();
		$form['buttonTitle'] = Utils::getDefaultDonationButtonTitle();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['customInputTitle'] = '';

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileInlineSaveCardFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['formTitle'] = '';
		$form['chargeType'] = self::CHARGE_TYPE_IMMEDIATE;
		$form['amount'] = 0;
		$form['currency'] = self::CURRENCY_USD;
		$form['customAmount'] = self::CUSTOM_AMOUNT_SAVE_CARD;
		$form['amountSelectorStyle'] = self::AMOUNT_SELECTOR_STYLE_DROPDOWN;
		$form['buttonTitle'] = Utils::getDefaultSaveCardButtonTitle();
		$form['customInputTitle'] = '';
		$form['stripeDescription'] = Utils::getDefaultSaveCardDescription();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;

		return $form;
	}

	/**
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array
	 */
	private function compileCheckoutSaveCardFormData( $displayName, $name ) {
		$form = [];

		$form['name'] = $name;
		$form['displayName'] = $displayName;
		$form['companyName'] = '';
		$form['productDesc'] = Utils::getDefaultProductDescription();
		$form['chargeType'] = self::CHARGE_TYPE_IMMEDIATE;
		$form['amount'] = self::AMOUNT_10_USD;
		$form['currency'] = self::CURRENCY_USD;
		$form['customAmount'] = self::CUSTOM_AMOUNT_SAVE_CARD;
		$form['amountSelectorStyle'] = self::AMOUNT_SELECTOR_STYLE_DROPDOWN;
		$form['openButtonTitle'] = Utils::getDefaultSaveCardButtonTitle();
		$form['buttonTitle'] = Utils::getDefaultSaveCardButtonTitle();
		$form['customInputTitle'] = '';
		$form['stripeDescription'] = Utils::getDefaultSaveCardDescription();
		$form['termsOfUseLabel'] = Utils::getDefaultTermsOfUseLabel();
		$form['termsOfUseNotCheckedErrorMessage'] = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
		$form['preferredLanguage'] = self::PREFERRED_LANGUAGE_AUTO;
		$form['decimalSeparator'] = self::DECIMAL_SEPARATOR_DOT;

		return $form;
	}

	/**
	 * @param CreateFormModel $createFormModel
	 * @return numeric-string The new form's id.
	 *
	 * @throws \Exception
	 */
	public function createForm( $createFormModel ) {
		$formType = FormTypes::find( $createFormModel->getType(), $createFormModel->getLayout() );
		if ( null === $formType ) {
			throw new \Exception( "Unsupported form type" );
		}

		$name = $createFormModel->getName();
		$this->db->insertForm( $formType, $this->compileFormData( $formType, $createFormModel->getDisplayName(), $name ) );

		return $this->db->getFormByName( $formType, $name )->{FormTypes::get( $formType )->idColumn};
	}

	/**
	 * The default settings of a new form of the given type.
	 *
	 * @param FormTypeKey $formType
	 * @param string $displayName
	 * @param string $name
	 *
	 * @return array<string, mixed>
	 */
	private function compileFormData( string $formType, $displayName, $name ): array {
		switch ( $formType ) {
			case Main::FORM_TYPE_INLINE_PAYMENT:
				return $this->compileInlinePaymentFormData( $displayName, $name );
			case Main::FORM_TYPE_CHECKOUT_PAYMENT:
				return $this->compileCheckoutPaymentFormData( $displayName, $name );
			case Main::FORM_TYPE_INLINE_SUBSCRIPTION:
				return $this->compileInlineSubscriptionFormData( $displayName, $name );
			case Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION:
				return $this->compileCheckoutSubscriptionFormData( $displayName, $name );
			case Main::FORM_TYPE_INLINE_DONATION:
				return $this->compileInlineDonationFormData( $displayName, $name );
			case Main::FORM_TYPE_CHECKOUT_DONATION:
				return $this->compileCheckoutDonationFormData( $displayName, $name );
			case Main::FORM_TYPE_INLINE_SAVE_CARD:
				return $this->compileInlineSaveCardFormData( $displayName, $name );
			default: // Main::FORM_TYPE_CHECKOUT_SAVE_CARD
				return $this->compileCheckoutSaveCardFormData( $displayName, $name );
		}
	}
}
