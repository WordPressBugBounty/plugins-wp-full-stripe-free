<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Utils;

class SubscriptionFormValidator extends FormValidator {

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		if ( $formModelObject instanceof SubscriptionFormModel ) {

			if ( is_null( $formModelObject->getStripePlan() ) || ! $formModelObject->isStripePlanOnForm() ) {
				$fieldName = $formModelObject::PARAM_WPFS_STRIPE_PLAN;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error = __( 'Invalid plan selected, check your plans and Stripe API mode.', 'wp-full-stripe-free' );
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			} elseif (
				empty( $formModelObject->getStripePaymentIntentId() )
				&& empty( $formModelObject->getStripeSetupIntentId() )
				&& Utils::isArchivedPrice( $formModelObject->getStripePlan() )
			) {
				// Stripe refuses to subscribe anyone to a plan the site owner archived, so say why before asking it.
				// A request finishing a subscription Stripe already created goes on.
				$fieldName = $formModelObject::PARAM_WPFS_STRIPE_PLAN;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error = __( 'This plan is no longer available. Please choose another plan.', 'wp-full-stripe-free' );
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			}

			$quantityError = self::getQuantityError( $formModelObject->getForm(), $formModelObject->getStripePlanQuantity() );
			if ( ! is_null( $quantityError ) ) {
				$fieldName = $formModelObject::PARAM_WPFS_STRIPE_PLAN_QUANTITY;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$bindingResult->addFieldError( $fieldName, $fieldId, $quantityError );
			}
		}
	}

	/**
	 * Why a subscription quantity may not be bought on the form, or null when it may.
	 *
	 * @param object $form
	 * @param string|int $quantityInput The quantity as posted.
	 * @return string|null
	 */
	public static function getQuantityError( $form, $quantityInput ) {
		// Digits only, so "02" typed in the quantity box is 2 while "1.5" and "-2" are refused.
		$quantityInput = trim( (string) $quantityInput );
		$quantity = ctype_digit( $quantityInput ) ? (int) $quantityInput : 0;
		if ( $quantity < 1 || ( $form->allowMultipleSubscriptions != 1 && 1 !== $quantity ) ) {
			// The form only offers a quantity input when multiple subscriptions are allowed.
			return
				/* translators: Form field validation error message when the subscription quantity is not allowed */
				__( 'Please enter a valid subscription quantity.', 'wp-full-stripe-free' );
		}

		if ( $form->allowMultipleSubscriptions == 1 ) {
			if ( isset( $form->minimumQuantityOfSubscriptions )
				&& $form->minimumQuantityOfSubscriptions > 0
				&& $quantity < $form->minimumQuantityOfSubscriptions
			) {
				/* translators: %d: minimum subscription quantity */
				return sprintf( __( 'Subscription quantity is less than the minimum quantity of %d.', 'wp-full-stripe-free' ), $form->minimumQuantityOfSubscriptions );
			}
			if ( isset( $form->maximumQuantityOfSubscriptions )
				&& $form->maximumQuantityOfSubscriptions > 0
				&& $quantity > $form->maximumQuantityOfSubscriptions
			) {
				/* translators: %d: maximum subscription quantity */
				return sprintf( __( 'Subscription quantity is greater than the maximum quantity of %d.', 'wp-full-stripe-free' ), $form->maximumQuantityOfSubscriptions );
			}
		}

		return null;
	}

}
