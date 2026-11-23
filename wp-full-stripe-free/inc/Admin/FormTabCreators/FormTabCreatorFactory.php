<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

use ThemeIsle\WPFullPay\Main;

class FormTabCreatorFactory {
	const TAB_CREATORS = [
		Main::FORM_TYPE_INLINE_SAVE_CARD => InlineSaveCardFormTabCreator::class,
		Main::FORM_TYPE_CHECKOUT_SAVE_CARD => CheckoutSaveCardFormTabCreator::class,
		Main::FORM_TYPE_INLINE_DONATION => InlineDonationFormTabCreator::class,
		Main::FORM_TYPE_CHECKOUT_DONATION => CheckoutDonationFormTabCreator::class,
		Main::FORM_TYPE_INLINE_PAYMENT => InlinePaymentFormTabCreator::class,
		Main::FORM_TYPE_CHECKOUT_PAYMENT => CheckoutPaymentFormTabCreator::class,
		Main::FORM_TYPE_INLINE_SUBSCRIPTION => InlineSubscriptionFormTabCreator::class,
		Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION => CheckoutSubscriptionFormTabCreator::class,
	];

	/**
	 * @param string $formType
	 *
	 * @return FormTabCreator
	 * @throws \Exception
	 */
	public static function createFormTabCreator( $formType ) {
		if ( ! isset( self::TAB_CREATORS[ $formType ] ) ) {
			throw new \Exception( __CLASS__ . '.' . __FUNCTION__ . "(): unsupported form type: " . $formType );
		}

		$tabCreator = self::TAB_CREATORS[ $formType ];

		return new $tabCreator();
	}
}
