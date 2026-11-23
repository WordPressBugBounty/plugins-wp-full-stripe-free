<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

class CheckoutDonationFormView extends DonationFormView {

	use CheckoutFormView;

	/**
	 * CheckoutDonationFormView constructor.
	 *
	 * @param $form
	 */
	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->action->setValue( self::FIELD_ACTION_VALUE_POPUP_DONATION_CHARGE );
	}

	public static function getFields() {
		$fields = self::getPopupBillingAddressFields();

		return array_merge( $fields, parent::getFields() );
	}

	protected function getFormAttributes() {
		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_CHECKOUT_DONATION;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		$popupFormAttributes = $this->getCheckoutFormAttributes( $this->form );

		return array_merge( $attributes, $popupFormAttributes );
	}

}
