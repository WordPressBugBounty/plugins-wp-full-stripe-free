<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Main;

class CheckoutSubscriptionFormView extends SubscriptionFormView {

	use CheckoutFormView;

	/**
	 * CheckoutSubscriptionFormView constructor.
	 *
	 * @param $form
	 * @param $stripe
	 */
	public function __construct( $form, $fieldConfiguration, $stripe, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $stripe, $loggerService );

		$this->action->setValue( self::FIELD_ACTION_VALUE_POPUP_SUBSCRIPTION_CHARGE );
	}

	public static function getFields() {
		$fields = [
		];
		$fields = array_merge( $fields, self::getPopupBillingAddressFields() );

		return array_merge( $fields, parent::getFields() );
	}

	/**
	 * @return array
	 */
	public function getFormAttributes() {

		$attributes = [];

		$attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION;
		$attributes[ self::ATTR_DATA_WPFS_SIMPLE_BUTTON_LAYOUT ] = $this->form->simpleButtonLayout;
		$attributes = array_merge( $attributes, parent::getFormAttributes() );

		$popupFormAttributes = $this->getCheckoutFormAttributes( $this->form );

		if ( 1 == $this->form->simpleButtonLayout ) {
			$attributes[ self::ATTR_CLASS ] = self::ATTR_CLASS_VALUE_WPFS_FORM_WPFS_FORM_INLINE;
		}

		return array_merge( $attributes, $popupFormAttributes );
	}

	public function getFirstPlanName() {
		$firstPlanName = null;

		if ( isset( $this->form ) && isset( $this->form->plans ) ) {
			$plans = json_decode( $this->form->plans );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				if ( is_array( $plans ) && count( $plans ) > 0 ) {
					$firstPlanName = $plans[0];
				}
			}
		}

		return $firstPlanName;
	}

}
