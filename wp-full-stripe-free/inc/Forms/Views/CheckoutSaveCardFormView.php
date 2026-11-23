<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

class CheckoutSaveCardFormView extends SaveCardFormView {

	use CheckoutFormView;

	/**
	 * CheckoutSaveCardFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->action->setValue( self::FIELD_ACTION_VALUE_POPUP_PAYMENT_CHARGE );
	}

	public static function getFields() {
		$fields = self::getPopupBillingAddressFields();

		return array_merge( $fields, parent::getFields() );
	}

	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_CHECKOUT_SAVE_CARD;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		$popupFormAttributes = $this->getCheckoutFormAttributes( $this->form );

		return array_merge( $attributes, $popupFormAttributes );
	}
}
