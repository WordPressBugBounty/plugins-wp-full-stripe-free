<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Admin\Views\SubscriptionFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

abstract class SubscriptionFormValidator extends FormValidator {
    use TaxRateValidatorTools;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate( $bindingResult, $formModel ) {
        parent::validate( $bindingResult, $formModel );

        $this->validateSubscriptionFields( $bindingResult, $formModel );
    }

    protected function validateSubscriptionFields( $bindingResult, $formModel ) {
        $this->validatePlans( $bindingResult, $formModel );
        $this->validateTaxRates( $bindingResult, $formModel );
        $this->validateMinMaxSubscriptionQuantity( $bindingResult, $formModel );
        $this->validatePlanSelectorStyle( $bindingResult, $formModel );
        $this->validateShowCouponField( $bindingResult, $formModel );
        $this->validateShowPaymentDetail( $bindingResult, $formModel );
        $this->validateFeeRecovery( $bindingResult, $formModel );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|SubscriptionFormModel
     */
    protected function validatePlans( $bindingResult, $formModel ) {
        if ( count( $formModel->getRecurringProducts() ) === 0 ) {
            $fieldName = SubscriptionFormViewConstants::FIELD_FORM_RECURRING_PRODUCTS_ERROR;
            $fieldId   = SubscriptionFormViewConstants::FIELD_FORM_RECURRING_PRODUCTS_ERROR;
            /* translators: Validation error message when no subscription plan is selected on a subscription form */
            $error     = __( 'Please select at least one plan', 'wp-full-stripe-free' );

            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|SubscriptionFormModel
     */
    protected function validateMinMaxSubscriptionQuantity($bindingResult, $formModel ) {
        if ( $formModel->getAllowSubscriptionQuantity() == 1 ) {
            $filterOptions = [
                'options' => [
                    'min_range' => 0
                ]
            ];
            if ( filter_var( $formModel->getSubscriptionMinimumQuantity(), FILTER_VALIDATE_INT, $filterOptions ) === false ) {
                $fieldName = SubscriptionFormViewConstants::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY;
                $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
                /* translators: Validation error message when subscriptions can be purchased in bulk but no minimum quantity is entered */
                $error     = __( 'Please enter the minimum subscription quantity or zero', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
            $filterOptions = [
                'options' => [
                    'min_range' => 0
                ]
            ];
            if ( filter_var( $formModel->getSubscriptionMaximumQuantity(), FILTER_VALIDATE_INT, $filterOptions ) === false ) {
                $fieldName = SubscriptionFormViewConstants::FIELD_FORM_SUBSCRIPTION_MAXIMUM_QUANTITY;
                $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
                /* translators: Validation error message when subscriptions can be purchased in bulk but no maximum quantity is entered */
                $error     = __( 'Please enter the maximum subscription quantity or zero', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
            if ( ! $bindingResult->hasErrors() ) {
                if ( $formModel->getSubscriptionMinimumQuantity() > 0 && $formModel->getSubscriptionMaximumQuantity() > 0 ) {
                    if ($formModel->getSubscriptionMinimumQuantity() > $formModel->getSubscriptionMaximumQuantity()) {
                        $fieldName = SubscriptionFormViewConstants::FIELD_FORM_SUBSCRIPTION_MINIMUM_QUANTITY;
                        $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );
                        /* translators: Validation error message when subscriptions can be purchased in bulk but maximum quantity is less than maximum quantity */
                        $error     = __( 'Please enter a lower value for minimum subscription quantity', 'wp-full-stripe-free' );
                        $bindingResult->addFieldError( $fieldName, $fieldId, $error );
                    }
                }
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|SubscriptionFormModel
     */
    protected function validatePlanSelectorStyle( $bindingResult, $formModel ) {
        $productSelectorStyles = [
            Main::SELECTOR_STYLE_RADIO_BUTTONS,
            Main::SELECTOR_STYLE_DROPDOWN,
        ];

        if ( false === array_search( $formModel->getProductSelectorStyle(), $productSelectorStyles )) {
            $error =
                /* translators: Validation error message when no plan selector style selected, such as radio button list, dropdown, etc */
                __( 'Please select a plan selector style (Appearance tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|SubscriptionFormModel
     */
    protected function validateShowCouponField( $bindingResult, $formModel ) {
        $yesNoValues = [ '0', '1' ];

        if ( false === array_search( $formModel->getShowCouponField(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether the coupon field should be displayed (Layout tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * Validate whether it's selected if the payment details should be displayed or not.
     *
     * @param BindingResult $bindingResult
     * @param SubscriptionFormModel $formModel
     * @return void
     */
    protected function validateShowPaymentDetail( $bindingResult, $formModel ) {
        $paymentDetailValues = [ '0', '1', '2' ];

        if ( false === array_search( $formModel->showPaymentDetail(), $paymentDetailValues ) ) {
            $error =
                __( 'Please select how the payment details should be displayed.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }
}
