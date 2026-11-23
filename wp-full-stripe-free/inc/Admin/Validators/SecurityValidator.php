<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\SecurityModel;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\SecurityViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Utils;

class SecurityValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param SecurityModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $yesNoValues     = [ '0', '1' ];

        if ( false === array_search( $formModelObject->getSecureInlineForms(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether inline forms are secured with Google reCaptcha */
                __( 'Please select whether inline forms should be secured with Google reCAPTCHA', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getSecureCheckoutForms(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether checkout forms are secured with Google reCaptcha */
                __( 'Please select whether checkout forms should be secured with Google reCAPTCHA', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( false === array_search( $formModelObject->getSecureCustomerPortal(), $yesNoValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether the Customer Portal is secured with Google reCaptcha */
                __( 'Please select whether the Customer Portal should be secured with Google reCAPTCHA', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        $validateRecaptchaKeys = $formModelObject->getSecureInlineForms() == '1' ||
            $formModelObject->getSecureCheckoutForms() == '1' ||
            $formModelObject->getSecureCustomerPortal() == '1';

        if ( $validateRecaptchaKeys ) {
            $reCaptchaSecretKeyFieldName  = SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SECRET_KEY;
            $reCaptchaSecretKeyFieldId    = Utils::generateFormElementId( $reCaptchaSecretKeyFieldName, AdminViewConstants::FORM_HASH_ADMIN_SECURITY );
            if ( empty( $formModelObject->getReCaptchaSecretKey() )) {
                $bindingResult->addFieldError( $reCaptchaSecretKeyFieldName, $reCaptchaSecretKeyFieldId,
                    /* translators: Save security settings validation error when no Google reCaptcha secret key specified */
                    __( 'Please enter a secret key', 'wp-full-stripe-free' ) );
            }

            $reCaptchaSiteKeyFieldName  = SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SITE_KEY;
            $reCaptchaSiteKeyFieldId    = Utils::generateFormElementId( $reCaptchaSiteKeyFieldName, AdminViewConstants::FORM_HASH_ADMIN_SECURITY );
            if ( empty( $formModelObject->getReCaptchaSiteKey() )) {
                $bindingResult->addFieldError( $reCaptchaSiteKeyFieldName, $reCaptchaSiteKeyFieldId,
                    /* translators: Save security settings validation error when no Google reCaptcha site key specified */
                    __( 'Please enter a site key', 'wp-full-stripe-free' ) );
            }
        }
    }
}
