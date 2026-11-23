<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\PaymentMethods;

class InlineSaveCardFormView extends SaveCardFormView {

	use InlineFormView;

	/**
	 * InlineSaveCardFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->initInlineFields( $this->formHash );

		$this->action->setValue( self::FIELD_ACTION_VALUE_INLINE_PAYMENT_CHARGE );
	}

	public static function getFields() {
		$inlineFields = self::getInlineFields();
		$inlineFields = array_merge( $inlineFields, self::getInlineSameBillingAndShippingAddressField() );
		$inlineFields = array_merge( $inlineFields, self::getInlineBillingAddressFields() );
		$inlineFields = array_merge( $inlineFields, self::getInlineShippingAddressFields() );

		return array_merge( $inlineFields, parent::getFields() );
	}

	public function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_INLINE_SAVE_CARD;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		if ( PaymentMethods::is_link_disabled( $this->form->paymentMethods ?? null ) ) {
			$attributes[ self::ATTR_DATA_WPFS_STRIPE_LINK ] = 'never';
		}

		$inlineFormAttributes = $this->getInlineFormAttributes( $this->form );

		return array_merge( $attributes, $inlineFormAttributes );
	}

}
