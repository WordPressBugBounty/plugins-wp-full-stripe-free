<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

class InlineSubscriptionFormView extends SubscriptionFormView implements FormViewInlineTaxAddOnConstants {
	use InlineFormView;
	use FormViewInlineTaxAddOn;

	/**
	 * InlineSubscriptionFormView constructor.
	 *
	 * @param $form
	 * @param $stripe
	 */
	public function __construct( $form, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $stripe, $loggerService );
		$this->initInlineFields( $this->formHash );
		$this->initInlineTaxFields();

		$this->action->setValue( self::FIELD_ACTION_VALUE_INLINE_SUBSCRIPTION_CHARGE );

	}

	/**
	 * @return array
	 */
	public static function getFields() {
		$inlineFields = self::getInlineFields();
		$inlineFields = array_merge( $inlineFields, self::getInlineSameBillingAndShippingAddressField() );
		$inlineFields = array_merge( $inlineFields, self::getInlineBillingAddressFields() );
		$inlineFields = array_merge( $inlineFields, self::getInlineShippingAddressFields() );

		return array_merge( $inlineFields, parent::getFields(), self::getInlineTaxFields() );
	}

	/**
	 * @return array
	 */
	public function getFormAttributes() {

		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_INLINE_SUBSCRIPTION;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		$inlineFormAttributes = $this->getInlineFormAttributes( $this->form );

		return array_merge( $attributes, $inlineFormAttributes );
	}

}
