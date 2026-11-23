<?php

namespace ThemeIsle\WPFullPay\Forms;

use ThemeIsle\WPFullPay\Admin\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Models\CheckoutSaveCardFormModel;
use ThemeIsle\WPFullPay\Admin\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Admin\Models\InlineDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Models\InlinePaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Models\InlineSaveCardFormModel;
use ThemeIsle\WPFullPay\Admin\Models\InlineSubscriptionFormModel;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutPaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSaveCardFormView;
use ThemeIsle\WPFullPay\Admin\Views\CheckoutSubscriptionFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlinePaymentFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineSaveCardFormView;
use ThemeIsle\WPFullPay\Admin\Views\InlineSubscriptionFormView;
use ThemeIsle\WPFullPay\Main;

/**
 * The eight form types (inline or Checkout payment, subscription, donation and save card),
 * where each one is stored and which admin model and view edit it.
 *
 * Save-card forms share the payment tables; their customAmount is 'card_capture'.
 *
 * @phpstan-type FormTypeKey key-of<self::DEFINITIONS>
 */
class FormTypes {

	/** Checkout tables have no Stripe Elements columns. */
	const CHECKOUT_IGNORED_ON_UPDATE = [ 'stripeElementsTheme', 'stripeElementsFont' ];
	/** The Checkout payment table also has no paymentMethods column. */
	const CHECKOUT_PAYMENT_IGNORED_ON_UPDATE = [ 'stripeElementsTheme', 'stripeElementsFont', 'paymentMethods' ];

	const DEFINITIONS = [
		Main::FORM_TYPE_INLINE_PAYMENT => [
			'type' => Main::FORM_TYPE_PAYMENT,
			'layout' => Main::FORM_LAYOUT_INLINE,
			'table' => 'fullstripe_payment_forms',
			'idColumn' => 'paymentFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => [],
			'cardCapture' => false,
			'transactionTable' => 'fullstripe_payments',
			'modelClass' => InlinePaymentFormModel::class,
			'viewClass' => InlinePaymentFormView::class,
		],
		Main::FORM_TYPE_CHECKOUT_PAYMENT => [
			'type' => Main::FORM_TYPE_PAYMENT,
			'layout' => Main::FORM_LAYOUT_CHECKOUT,
			'table' => 'fullstripe_checkout_forms',
			'idColumn' => 'checkoutFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => self::CHECKOUT_PAYMENT_IGNORED_ON_UPDATE,
			'cardCapture' => false,
			'transactionTable' => 'fullstripe_payments',
			'modelClass' => CheckoutPaymentFormModel::class,
			'viewClass' => CheckoutPaymentFormView::class,
		],
		Main::FORM_TYPE_INLINE_SUBSCRIPTION => [
			'type' => Main::FORM_TYPE_SUBSCRIPTION,
			'layout' => Main::FORM_LAYOUT_INLINE,
			'table' => 'fullstripe_subscription_forms',
			'idColumn' => 'subscriptionFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => [],
			'cardCapture' => null,
			'transactionTable' => 'fullstripe_subscribers',
			'modelClass' => InlineSubscriptionFormModel::class,
			'viewClass' => InlineSubscriptionFormView::class,
		],
		Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION => [
			'type' => Main::FORM_TYPE_SUBSCRIPTION,
			'layout' => Main::FORM_LAYOUT_CHECKOUT,
			'table' => 'fullstripe_checkout_subscription_forms',
			'idColumn' => 'checkoutSubscriptionFormID',
			'idPlaceholder' => '%d',
			'ignoredOnUpdate' => self::CHECKOUT_IGNORED_ON_UPDATE,
			'cardCapture' => null,
			'transactionTable' => 'fullstripe_subscribers',
			'modelClass' => CheckoutSubscriptionFormModel::class,
			'viewClass' => CheckoutSubscriptionFormView::class,
		],
		Main::FORM_TYPE_INLINE_DONATION => [
			'type' => Main::FORM_TYPE_DONATION,
			'layout' => Main::FORM_LAYOUT_INLINE,
			'table' => 'fullstripe_donation_forms',
			'idColumn' => 'donationFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => [],
			'cardCapture' => null,
			'transactionTable' => 'fullstripe_donations',
			'modelClass' => InlineDonationFormModel::class,
			'viewClass' => InlineDonationFormView::class,
		],
		Main::FORM_TYPE_CHECKOUT_DONATION => [
			'type' => Main::FORM_TYPE_DONATION,
			'layout' => Main::FORM_LAYOUT_CHECKOUT,
			'table' => 'fullstripe_checkout_donation_forms',
			'idColumn' => 'checkoutDonationFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => self::CHECKOUT_IGNORED_ON_UPDATE,
			'cardCapture' => null,
			'transactionTable' => 'fullstripe_donations',
			'modelClass' => CheckoutDonationFormModel::class,
			'viewClass' => CheckoutDonationFormView::class,
		],
		Main::FORM_TYPE_INLINE_SAVE_CARD => [
			'type' => Main::FORM_TYPE_SAVE_CARD,
			'layout' => Main::FORM_LAYOUT_INLINE,
			'table' => 'fullstripe_payment_forms',
			'idColumn' => 'paymentFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => [],
			'cardCapture' => true,
			'transactionTable' => 'fullstripe_card_captures',
			'modelClass' => InlineSaveCardFormModel::class,
			'viewClass' => InlineSaveCardFormView::class,
		],
		Main::FORM_TYPE_CHECKOUT_SAVE_CARD => [
			'type' => Main::FORM_TYPE_SAVE_CARD,
			'layout' => Main::FORM_LAYOUT_CHECKOUT,
			'table' => 'fullstripe_checkout_forms',
			'idColumn' => 'checkoutFormID',
			'idPlaceholder' => '%s',
			'ignoredOnUpdate' => self::CHECKOUT_PAYMENT_IGNORED_ON_UPDATE,
			'cardCapture' => true,
			'transactionTable' => 'fullstripe_card_captures',
			'modelClass' => CheckoutSaveCardFormModel::class,
			'viewClass' => CheckoutSaveCardFormView::class,
		],
	];

	/** @var array<FormTypeKey,FormType> */
	private static $formTypes = [];

	/**
	 * @param string $formType
	 * @phpstan-assert FormTypeKey $formType
	 *
	 * @throws \InvalidArgumentException When the form type is not one of the eight.
	 */
	public static function get( string $formType ): FormType {
		if ( ! isset( self::DEFINITIONS[ $formType ] ) ) {
			throw new \InvalidArgumentException( 'Unsupported form type: ' . $formType );
		}

		if ( ! isset( self::$formTypes[ $formType ] ) ) {
			self::$formTypes[ $formType ] = new FormType( $formType, self::DEFINITIONS[ $formType ] );
		}

		return self::$formTypes[ $formType ];
	}

	/**
	 * Whether a value is one of the eight form types; popup types and request input that is not a string are not.
	 *
	 * @param mixed $formType
	 * @phpstan-assert-if-true FormTypeKey $formType
	 */
	public static function exists( $formType ): bool {
		return is_string( $formType ) && isset( self::DEFINITIONS[ $formType ] );
	}

	/**
	 * The form type with the given kind and layout, as the admin posts them.
	 *
	 * @param mixed $type   Main::FORM_TYPE_PAYMENT, _SUBSCRIPTION, _DONATION or _SAVE_CARD.
	 * @param mixed $layout Main::FORM_LAYOUT_INLINE or Main::FORM_LAYOUT_CHECKOUT.
	 *
	 * @return FormTypeKey|null Null when they name none of the eight.
	 */
	public static function find( $type, $layout ): ?string {
		foreach ( self::DEFINITIONS as $formType => $definition ) {
			if ( $definition['type'] === $type && $definition['layout'] === $layout ) {
				return $formType;
			}
		}

		return null;
	}

	/**
	 * Every form type, inline before Checkout and payment, subscription, donation, then save card.
	 *
	 * @return list<FormType>
	 */
	public static function all(): array {
		return array_map( [ self::class, 'get' ], array_keys( self::DEFINITIONS ) );
	}

	/**
	 * The inline and the Checkout form type of one kind of form.
	 *
	 * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $type
	 *
	 * @return list<FormType>
	 */
	public static function ofType( string $type ): array {
		return array_values( array_filter(
			self::all(),
			function ( FormType $formType ) use ( $type ) {
				return $formType->type === $type;
			}
		) );
	}
}
