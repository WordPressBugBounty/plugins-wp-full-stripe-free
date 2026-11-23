<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Views\PaymentFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

abstract class PaymentFormValidator extends FormValidator {
    use TaxRateValidatorTools;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
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
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validatePaymentType( $bindingResult, $formModel ) {
        $paymentTypes = [
            Main::PAYMENT_TYPE_LIST_OF_AMOUNTS,
            Main::PAYMENT_TYPE_CUSTOM_AMOUNT
        ];

        if ( false === array_search( $formModel->getPaymentType(), $paymentTypes )) {
            $error =
                __( 'Please select a payment type (Payment tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param $formModel PaymentFormModel
     * @return int
     */
    protected function getMinimumProductAmount( $formModel ) {
        $result = PHP_INT_MAX;

        foreach( $formModel->getOnetimeProducts() as $product ) {
            if ( $product['price'] < $result ) {
                $result = $product['price'];
            }
        }

        return $result;
    }

    /**
     * @param $formModel PaymentFormModel
     * @return bool
     */
    protected function isMinimumAmountValidationRequired( $formModel ) {
        return   $formModel->getMinimumPaymentAmount() != 0 ||
            $formModel->getPaymentType() === Main::PAYMENT_TYPE_CUSTOM_AMOUNT ||
            ( $formModel->getPaymentType() === Main::PAYMENT_TYPE_LIST_OF_AMOUNTS && $formModel->getAllowCustomPaymentAmount() == 1 );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateMinimumPaymentAmount( $bindingResult, $formModel ) {
        if ( ! $this->isMinimumAmountValidationRequired( $formModel  )) {
            return;
        }

        if ( $formModel->getPaymentType() === Main::PAYMENT_TYPE_LIST_OF_AMOUNTS && $formModel->getAllowCustomPaymentAmount() == 1 ) {
            $minimumProductAmount = $this->getMinimumProductAmount( $formModel );

            if ( count( $formModel->getOnetimeProducts() ) > 0 && $minimumProductAmount < $formModel->getMinimumPaymentAmount() ) {
                $fieldName = PaymentFormViewConstants::FIELD_FORM_MINIMUM_PAYMENT_AMOUNT;
                $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );

                $productAmount = Currencies::formatAndEscapeByAdmin( $this->staticContext, $formModel->getCurrency(), $minimumProductAmount, false, true );
                $minimumAmount = Currencies::formatAndEscapeByAdmin( $this->staticContext, $formModel->getCurrency(), $formModel->getMinimumPaymentAmount(), false, true );
                $error = sprintf(
                /* translators: This error message is displayed when the payment form's minimum payment amount is larger than the lowest product price */
                    __( 'The product price of %1$s is lower than the minimum payment amount of %2$s.', 'wp-full-stripe-free' ), $productAmount, $minimumAmount  );
                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateChargeType( $bindingResult, $formModel ) {
        $chargeTypes = [
            Main::CHARGE_TYPE_IMMEDIATE,
            Main::CHARGE_TYPE_AUTHORIZE_AND_CAPTURE
        ];

        if ( false === array_search( $formModel->getChargeType(), $chargeTypes )) {
            $error =
                __( 'Please select a payment type (Payment tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateProducts( $bindingResult, $formModel ) {
        if ( $formModel->getPaymentType() === Main::PAYMENT_TYPE_LIST_OF_AMOUNTS ) {
            if ( count( $formModel->getOnetimeProducts() ) === 0 ) {
                $fieldName = PaymentFormViewConstants::FIELD_FORM_ONETIME_PRODUCTS_ERROR;
                $fieldId   = PaymentFormViewConstants::FIELD_FORM_ONETIME_PRODUCTS_ERROR;
                $error     = __( 'Please select at least one product', 'wp-full-stripe-free' );

                $bindingResult->addFieldError( $fieldName, $fieldId, $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateProductSelectorStyle( $bindingResult, $formModel ) {
        $productSelectorStyles = [
            Main::SELECTOR_STYLE_RADIO_BUTTONS,
            Main::SELECTOR_STYLE_DROPDOWN,
            Main::SELECTOR_STYLE_BUTTON_GROUP,
        ];

        if ( false === array_search( $formModel->getProductSelectorStyle(), $productSelectorStyles )) {
            $error =
                /* translators: Validation error message when no product selector style selected, such as radio button list, or dropdown */
                __( 'Please select a product selector style (Appearance tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
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
     * @param BindingResult $bindingResult
     * @param $formModel Binder|PaymentFormModel
     */
    protected function validateGenerateInvoice( $bindingResult, $formModel ) {
        $yesNoValues = [ '0', '1' ];

        if ( false === array_search( $formModel->getGenerateInvoice(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether invoice should be generated (Payment tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * Validate whether it's selected if the payment details should be displayed or not.
     *
     * @param BindingResult $bindingResult
     * @param PaymentFormModel $formModel
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

    public function validate( $bindingResult, $formModel ) {
        parent::validate( $bindingResult, $formModel );

        $this->validateCurrency( $bindingResult, $formModel );
        $this->validatePaymentType( $bindingResult, $formModel );
        $this->validateChargeType( $bindingResult, $formModel );
        $this->validateMinimumPaymentAmount( $bindingResult, $formModel );
        $this->validateGenerateInvoice( $bindingResult, $formModel );
        $this->validateProducts( $bindingResult, $formModel );
        $this->validateTaxRates( $bindingResult, $formModel );
        $this->validateProductSelectorStyle( $bindingResult, $formModel );
        $this->validateShowCouponField( $bindingResult, $formModel );
        $this->validateShowPaymentDetail( $bindingResult, $formModel );
        $this->validateFeeRecovery( $bindingResult, $formModel );
    }
}
