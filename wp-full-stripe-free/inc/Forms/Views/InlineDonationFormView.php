<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

class InlineDonationFormView extends DonationFormView {

	use InlineFormView;

	/**
	 * InlineDonationFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->initInlineFields( $this->formHash );

		$this->action->setValue( self::FIELD_ACTION_VALUE_INLINE_DONATION_CHARGE );

	}

	/**
	 * @return array
	 */
	public static function getFields() {
		$inlineFields = self::getInlineFields();
		$inlineFields = array_merge( $inlineFields, self::getInlineSameBillingAndShippingAddressField() );
		$inlineFields = array_merge( $inlineFields, self::getInlineBillingAddressFields() );
		$inlineFields = array_merge( $inlineFields, self::getInlineShippingAddressFields() );

		return array_merge( $inlineFields, parent::getFields() );
	}

	/**
	 * @return array
	 */
	public function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_INLINE_DONATION;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		$inlineFormAttributes = $this->getInlineFormAttributes( $this->form );

		return array_merge( $attributes, $inlineFormAttributes );
	}

}
