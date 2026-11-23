<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Admin\Models\InlineDonationFormModel;
use ThemeIsle\WPFullPay\Admin\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Utils;

abstract class DonationFormValidator extends FormValidator {

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|DonationFormModel
     */
    protected function validateDonationAmounts( $bindingResult, $formModel ) {
        if ( count( $formModel->getDonationAmounts() ) === 0 &&
            $formModel->getAllowCustomDonationAmount() == 0 ) {
            $fieldName = DonationFormViewConstants::FIELD_FORM_ALLOW_CUSTOM_DONATION_AMOUNT;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
            /* translators: Donations forms can have a custom amount field and/or suggested amount buttons. Having none of them is an issue */
            $error     = __( 'Please add suggested donation amounts or allow entering a custom donation amount', 'wp-full-stripe-free' );

            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    /**
     * @param $formModel DonationFormModel
     * @return bool
     */
    protected function isMinimumAmountValidationRequired( $formModel ) {
        return $formModel->getMinimumDonationAmount() != 0 &&
            $formModel->getAllowCustomDonationAmount() == 1;
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|DonationFormModel
     */
    protected function validateMinimumDonationAmount( $bindingResult, $formModel ) {
        if ( ! $this->isMinimumAmountValidationRequired( $formModel )) {
            return;
        }

        if ( count( $formModel->getDonationAmounts() ) > 0 &&
            min( $formModel->getDonationAmounts() ) < $formModel->getMinimumDonationAmount() ) {
            $fieldName = DonationFormViewConstants::FIELD_FORM_MINIMUM_DONATION_AMOUNT;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );

            $suggestedAmount = Currencies::formatAndEscapeByAdmin( $this->staticContext, $formModel->getCurrency(), min( $formModel->getDonationAmounts() ), false, true );
            $minimumAmount = Currencies::formatAndEscapeByAdmin( $this->staticContext, $formModel->getCurrency(), $formModel->getMinimumDonationAmount(), false, true );
            $error = sprintf(
            /* translators: This error message is displayed when the donation form's minimum donation amount is larger than the lowest suggested donation amount */
                __( 'The suggested donation amount of %1$s is lower than the minimum donation amount of %2$s.', 'wp-full-stripe-free' ), $suggestedAmount, $minimumAmount  );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    /**
     * @param $formModel DonationFormModel
     *
     * @returns boolean
     */
    protected function isDonationFrequencySelected( $formModel ) {
        return  1 == $formModel->getDonationFrequencyOnetime() ||
            1 == $formModel->getDonationFrequencyDaily() ||
            1 == $formModel->getDonationFrequencyWeekly() ||
            1 == $formModel->getDonationFrequencyMonthly() ||
            1 == $formModel->getDonationFrequencyAnnual();
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|DonationFormModel
     */
    protected function validateDonationFrequencies( $bindingResult, $formModel ) {
        if ( ! $this->isDonationFrequencySelected( $formModel ) ) {
            $fieldName = DonationFormViewConstants::FIELD_FORM_DONATION_FREQUENCIES;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
            /* translators: Validation error message when no donation frequency selected such as one-time, daily, weekly, etc */
            $error     = __( 'Please select at least one donation frequency', 'wp-full-stripe-free' );

            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateCurrency( $bindingResult, $formModel ) {
        $currencies = Currencies::getAvailableCurrencies();

        if ( false === array_search( $formModel->getCurrency(), array_keys( $currencies ) )) {
            $error =
                __( 'Please select the payment currency (Payment tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel InlineDonationFormModel
     */
    protected function validateShowDonationGoal( $bindingResult, $formModel ) {
        $yesNoValues = [ 0, 1 ];

        if ( false === array_search( $formModel->getShowDonationGoal(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether the donation goal should be displayed */
                __( 'Please select if the donation goal should be displayed', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel InlineDonationFormModel
     */
    protected function validateDonationGoal( $bindingResult, $formModel ) {
        if ( $formModel->getShowDonationGoal() === 1 ) {
            $fieldName = InlineDonationFormViewConstants::FIELD_FORM_DONATION_GOAL;
            $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );

            if ( empty( $formModel->getDonationGoal() )) {
                $error     = __( 'Please enter a donation goal', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            } elseif ( !is_numeric( $formModel->getDonationGoal() ) ) {
                $error     = __( 'Please enter a valid donation goal', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
        }
    }

    public function validate( $bindingResult, $formModel ) {
        parent::validate( $bindingResult, $formModel );

        $this->validateCurrency( $bindingResult, $formModel );
        $this->validateDonationAmounts( $bindingResult, $formModel );
        $this->validateMinimumDonationAmount( $bindingResult, $formModel );
        $this->validateDonationFrequencies( $bindingResult, $formModel );
        $this->validateShowDonationGoal( $bindingResult, $formModel );
        $this->validateDonationGoal( $bindingResult, $formModel );
        $this->validateFeeRecovery( $bindingResult, $formModel );
    }
}
