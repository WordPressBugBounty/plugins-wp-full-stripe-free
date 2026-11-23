<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\FormModel;
use ThemeIsle\WPFullPay\Admin\Models\MyAccountModel;
use ThemeIsle\WPFullPay\Admin\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Admin\Views\FormView;
use ThemeIsle\WPFullPay\Admin\Views\FormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

abstract class FormValidator extends Validator {

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateTermsOfUse($bindingResult, $formModel ) {
        if ( $formModel->getShowTermsOfUse() == 1 ) {
            $labelFieldName = FormView::FIELD_FORM_TERMS_OF_SERVICE_LABEL;
            $labelFieldId   = Utils::generateFormElementId( $labelFieldName, $formModel->getFormHash() );
            if ( empty( $formModel->getTermsOfUseLabel() )) {
                $error     = __( 'Please enter a label for the terms of service input field', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $labelFieldName, $labelFieldId, $error );
            }

            $errorFieldName = FormView::FIELD_FORM_TERMS_OF_SERVICE_ERROR;
            $errorFieldId   = Utils::generateFormElementId( $errorFieldName, $formModel->getFormHash() );
            if ( empty( $formModel->getTermsOfUseErrorMessage() )) {
                $error     = __( "Please enter an error message that's displayed when the terms of service field is empty", 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $errorFieldName, $errorFieldId, $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateWebhook( $bindingResult, $formModel ) {
        $webhook_json = $formModel->getWebhook();

        if ( !empty( $webhook_json ) ) {
            $labelFieldName = FormViewConstants::FIELD_FORM_WEBHOOK;
            $labelFieldId   = Utils::generateFormElementId( $labelFieldName, $formModel->getFormHash() );

            $webhook = json_decode( $webhook_json, true );
            if ( $webhook === null ) {
                $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                    /* translators: Validation error message when the webhook is not a valid JSON string */
                    __( 'The webhook is not a valid JSON string', 'wp-full-stripe-free' ) );
            } else {
                if ( array_key_exists( 'url', $webhook ) && !empty( $webhook['url'] ) ) {
                    if ( filter_var( $webhook['url'], FILTER_VALIDATE_URL) === false) {
                        $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                            /* translators: Validation error message when the webhook URL is not a valid URL */
                            __( 'The webhook URL is not a valid URL', 'wp-full-stripe-free' ) );
                    }
                }

                if ( !array_key_exists( 'headers', $webhook ) ) {
                    $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                        /* translators: Validation error message when the webhook headers are missing */
                        __( 'The webhook headers are missing', 'wp-full-stripe-free' ) );
                } elseif ( !is_array( $webhook['headers'] ) ) {
                    $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                        /* translators: Validation error message when the webhook headers are not an array */
                        __( 'The webhook headers should be an array', 'wp-full-stripe-free' ) );
                } else {
                    foreach ( $webhook['headers'] as $key => $value ) {
                        if ( !is_string( $key ) ) {
                            $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                                /* translators: Validation error message when the webhook header key is not a string */
                                __( 'The webhook header key should be a string', 'wp-full-stripe-free' ) );
                            break;
                        }
                        if ( !is_string( $value ) ) {
                            $bindingResult->addFieldError( $labelFieldName, $labelFieldId,
                                /* translators: Validation error message when the webhook header value is not a string */
                                __( 'The webhook header value should be a string', 'wp-full-stripe-free' ) );
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateLocaleSettings($bindingResult, $formModel ) {
        $yesNoValues            = [ '0', '1' ];
        $decimalSeparatorValues = [
            Main::DECIMAL_SEPARATOR_SYMBOL_DOT,
            Main::DECIMAL_SEPARATOR_SYMBOL_COMMA
        ];

        if ( false === array_search( $formModel->getInheritLocale(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether the form should inherit global locale settings (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModel->getLocaleDecimalSeparator(), $decimalSeparatorValues ) ) {
            $error =
                __( 'Please select the decimal separator (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModel->getLocaleUseSymbolNotCode(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether currency symbols or codes should be displayed (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModel->getLocaleCurrencySymbolAtFirstPosition(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether the currency symbol should be displayed on the left or right (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModel->getLocalePutSpaceBetweenSymbolAndAmount(), $yesNoValues ) ) {
            $error =
                __( 'Please select whether space should be inserted between the currency symbol and amount (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateButtonLabel( $bindingResult, $formModel ) {
        $fieldName = FormView::FIELD_FORM_BUTTON_LABEL;
        $fieldId   = Utils::generateFormElementId( $fieldName, $formModel->getFormHash() );

        if ( empty( $formModel->getButtonLabel() )) {
            /* translators: Validation error message when no form submit button label is entered */
            $error     = __( 'Please enter a button label', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $fieldName, $fieldId, $error );
        } elseif ( empty( preg_replace('/\s+/', '', $formModel->getButtonLabel())) ) {
            $bindingResult->addFieldError( $fieldName, $fieldId,
                /* translators: Validation error message when the submit button label doesn't contain at least one non-whitespace character */
                __( 'The button label should contain at least one character other than whitespace', 'wp-full-stripe-free' ));
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateNameAndDisplayName( $bindingResult, $formModel ) {
        $displayNameFieldName = FormView::FIELD_FORM_DISPLAY_NAME;
        $displayNameFieldId   = Utils::generateFormElementId( $displayNameFieldName, $formModel->getFormHash() );
        if ( empty( $formModel->getDisplayName() )) {
            /* translators: Validation error message when the display name of the form is empty */
            $error     = __( 'Please enter a display name', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $displayNameFieldName, $displayNameFieldId, $error );
        } elseif ( ! preg_match('/^[^\s]{1,}.{0,}$/', $formModel->getDisplayName() ) ) {
            $bindingResult->addFieldError( $displayNameFieldName, $displayNameFieldId,
                /* translators: Validation error message when the display name starts with a space */
                __( 'The display name may contain any characters but it shouldn\'t start with a space.', 'wp-full-stripe-free' ) );
        }

        $nameFieldName = FormView::FIELD_FORM_NAME;
        $nameFieldId   = Utils::generateFormElementId( $nameFieldName, $formModel->getFormHash() );
        if ( empty( $formModel->getName() )) {
            /* translators: Validation error message when the identifier of the form is empty */
            $error     = __( 'Please enter the form identifier', 'wp-full-stripe-free' );
            $bindingResult->addFieldError( $nameFieldName, $nameFieldId, $error );
        } elseif ( ! preg_match('/^[\w\-]+$/', $formModel->getName() ) ) {
            $bindingResult->addFieldError( $nameFieldName, $nameFieldId,
                /* translators: Validation error message when the identifier of the form contains invalid characters */
                __( 'The identifier may contain only alphanumeric characters, dashes, and underscores.', 'wp-full-stripe-free' ) );
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param $formModel Binder|FormModel
     */
    protected function validateRedirect($bindingResult, $formModel ) {
        $redirectValues = [
            Main::REDIRECT_TYPE_SHOW_CONFIRMATION_MESSAGE,
            Main::REDIRECT_TYPE_TO_PAGE_OR_POST,
            Main::REDIRECT_TYPE_TO_CUSTOM_URL,
        ];
        if ( false === array_search( $formModel->getRedirectType(), $redirectValues ) ) {
            $error =
                __( 'Please decide what should happen after a successful payment (General tab)', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( $formModel->getRedirectType() === Main::REDIRECT_TYPE_TO_CUSTOM_URL ) {
            $redirectFieldName = FormView::FIELD_FORM_REDIRECT_CUSTOM_URL;
            $redirectFieldId   = Utils::generateFormElementId( $redirectFieldName, $formModel->getFormHash() );

            if ( empty( $formModel->getRedirectURl() )) {
                $error     = __( 'Please enter an URL', 'wp-full-stripe-free' );
                $bindingResult->addFieldError( $redirectFieldName, $redirectFieldId, $error );
            } elseif ( filter_var( $formModel->getRedirectURl(), FILTER_VALIDATE_URL) === false) {
                $bindingResult->addFieldError( $redirectFieldName, $redirectFieldId,
                    __( 'Please enter a valid URL', 'wp-full-stripe-free' ));
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param MyAccountModel|FormModel $formModelObject
     */
    public function validateFeeRecovery( $bindingResult, $formModelObject ) {
        $yesNoValues = [ '0', '1' ];
        $feeRecoveryValues = [
            Main::FEE_RECOVERY_ENABLE,
            Main::FEE_RECOVERY_DISABLE
        ];

        if ( false === array_search( $formModelObject->getFeeRecovery(), $feeRecoveryValues ) ) {
            $error =
                __( 'Please select if the Fee Recovery should be enabled.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getFeeRecoveryOptIn(), $yesNoValues ) ) {
            $error =
                __( 'Please select the Fee Opt-in method.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( $formModelObject->getFeeRecovery() === Main::FEE_RECOVERY_ENABLE && empty( $formModelObject->getFeeRecoveryOptInMessage() )) {
            $error =
            __( 'Please enter the Fee Opt-in message.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( $formModelObject->getFeeRecovery() === Main::FEE_RECOVERY_ENABLE ) {
            if ( empty( $formModelObject->getFeeRecoveryFeePercentage() )) {
            $error =
                __( 'Please enter the Fee Recovery percentage.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
            } elseif ( !is_numeric( $formModelObject->getFeeRecoveryFeePercentage() )) {
            $error =
                __( 'Please enter a numeric value for the Fee Recovery percentage.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
            }
        }

        if ( $formModelObject->getFeeRecovery() === Main::FEE_RECOVERY_ENABLE ) {
            if ( empty( $formModelObject->getFeeRecoveryFeeAdditionalAmount() )) {
            $error =
                __( 'Please enter a valid additional fee amount for the Fee Recovery.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
            } elseif ( !is_numeric( $formModelObject->getFeeRecoveryFeeAdditionalAmount() ) ) {
            $error =
                __( 'Please enter a valid number for the additional fee used in Fee Recovery.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
            }
        }
    }

    /**
     * @param BindingResult $bindingResult
     * @param PaymentFormModel $formModel
     */
    public function validate( $bindingResult, $formModel ) {
        $this->validateNameAndDisplayName( $bindingResult, $formModel );
        $this->validateRedirect( $bindingResult, $formModel );
        $this->validateButtonLabel( $bindingResult, $formModel );
        $this->validateLocaleSettings( $bindingResult, $formModel );
        $this->validateTermsOfUse( $bindingResult, $formModel );
        $this->validateWebhook( $bindingResult, $formModel );
    }
}
