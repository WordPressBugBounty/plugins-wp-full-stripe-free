<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Utils;

class DonationFormValidator extends FormValidator {

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	protected function validateFields( $bindingResult, $formModelObject ) {
		parent::validateFields( $bindingResult, $formModelObject );
		// Stripe already charged the amount, so the form's current amounts and frequencies don't apply.
		if ( $formModelObject instanceof DonationFormModel && ! $formModelObject->hasChargedAmount() ) {
			if ( $formModelObject->isCustomAmountSelected() ) {
				$fieldName = $formModelObject::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				if ( $this->isCustomAmountEmpty( $formModelObject->getCustomAmountUniqueValue() ) ) {
					$error =
						/* translators: Form field validation error message when custom amount is empty */
						__( 'Please enter an amount', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				} elseif ( is_null( $formModelObject->getAmount() ) || $formModelObject->getAmount() <= 0 ) {
					$error = $this->getInvalidCustomAmountMessage( $formModelObject->getForm() );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				} elseif ( $formModelObject->getAmount() < $formModelObject->getForm()->minimumDonationAmount ) {
					$minimumAmount = Currencies::formatAndEscapeByForm( $formModelObject->getForm(), $formModelObject->getForm()->currency, $formModelObject->getForm()->minimumDonationAmount, false, true );
					$error = sprintf(
						/* translators: Form field validation error message when custom amount is lower than the minimum donation amount */
						__( 'The minimum donation amount is %s.', 'wp-full-stripe-free' ), $minimumAmount );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
			} elseif ( is_null( $formModelObject->getAmount() ) ) {
				// Neither a preset nor "Other" was selected, so there is no amount to charge.
				$fieldName = $formModelObject::PARAM_WPFS_CUSTOM_AMOUNT_UNIQUE;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error =
					/* translators: Form field validation error message when custom amount is empty */
					__( 'Please enter an amount', 'wp-full-stripe-free' );
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			}

			// Validate donation frequency against the frequencies enabled on the form
			$donationFrequencySettings = [
				DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME => 'allowOneTimeDonation',
				DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY    => 'allowDailyRecurring',
				DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY   => 'allowWeeklyRecurring',
				DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY  => 'allowMonthlyRecurring',
				DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL   => 'allowAnnualRecurring',
			];
			$donationFrequency = $formModelObject->getDonationFrequency();
			$form = $formModelObject->getForm();
			if (
				! is_string( $donationFrequency )
				|| ! isset( $donationFrequencySettings[ $donationFrequency ] )
				|| 1 != ( $form->{ $donationFrequencySettings[ $donationFrequency ] } ?? 0 )
			) {
				$fieldName = $formModelObject::PARAM_WPFS_DONATION_FREQUENCY;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error =
					/* translators: Form field validation error message when no donation frequency is selected */
					__( 'Please select a donation frequency', 'wp-full-stripe-free' );

				$bindingResult->addGlobalError( $error );
			}
		}
	}
}
