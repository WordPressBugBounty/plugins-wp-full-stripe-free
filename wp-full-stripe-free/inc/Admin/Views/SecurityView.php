<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class SecurityView extends AdminView {
    /** @var Control */
    protected $secureInlineForms;
    /** @var Control */
    protected $secureCheckoutForms;
    /** @var Control */
    protected $secureCustomerPortal;
    /** @var Control */
    protected $reCaptchaSecretKey;
    /** @var Control */
    protected $reCaptchaSiteKey;
    /** @var Control */
    protected $fillInEmailForLoggedInUsers;

    /**
     * SecurityView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_SECURITY;
        parent::__construct();

        $this->action->setValue( SecurityViewConstants::FIELD_ACTION_VALUE_SAVE_SECURITY );

        $this->secureInlineForms = ControlUtils::createControl( $this->formHash, SecurityViewConstants::FIELD_SECURITY_SECURE_INLINE_FORMS, null, null,
            /* translators: Form field label for the 'Secure inline forms' checkbox of the form */
            __( 'Inline forms', 'wp-full-stripe-free' ), null );
        $this->secureInlineForms->setValue('1' );
        $this->secureInlineForms->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input js-google-recaptcha-toggle'
        ]);

        $this->secureCheckoutForms = ControlUtils::createControl( $this->formHash, SecurityViewConstants::FIELD_SECURITY_SECURE_CHECKOUT_FORMS, null, null,
            /* translators: Form field label for the 'Secure checkout forms' checkbox of the form */
            __( 'Checkout forms', 'wp-full-stripe-free' ), null );
        $this->secureCheckoutForms->setValue('1' );
        $this->secureCheckoutForms->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input js-google-recaptcha-toggle'
        ]);

        $this->secureCustomerPortal = ControlUtils::createControl( $this->formHash, SecurityViewConstants::FIELD_SECURITY_SECURE_CUSTOMER_PORTAL, null, null,
            /* translators: Form field label for the 'Secure Customer portal' checkbox of the form */
            __( 'Customer portal', 'wp-full-stripe-free' ), null );
        $this->secureCustomerPortal->setValue('1' );
        $this->secureCustomerPortal->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input js-google-recaptcha-toggle'
        ]);

        $this->reCaptchaSecretKey = ControlUtils::createControl( $this->formHash, SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SECRET_KEY, null, null,
            /* translators: Form field label for the 'Google reCaptcha secret key' field of the form */
            __( 'Google reCAPTCHA secret key', 'wp-full-stripe-free' ), null );
        $this->reCaptchaSecretKey->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);

        $this->reCaptchaSiteKey = ControlUtils::createControl( $this->formHash, SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SITE_KEY, null, null,
            /* translators: Form field label for the 'Google reCaptcha site key' field of the form */
            __( 'Google reCAPTCHA site key', 'wp-full-stripe-free' ), null );
        $this->reCaptchaSiteKey->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            SecurityViewConstants::FIELD_SECURITY_SECURE_INLINE_FORMS    => ControlUtils::input( SecurityViewConstants::FIELD_SECURITY_SECURE_INLINE_FORMS ),
            SecurityViewConstants::FIELD_SECURITY_SECURE_CHECKOUT_FORMS  => ControlUtils::input( SecurityViewConstants::FIELD_SECURITY_SECURE_CHECKOUT_FORMS ),
            SecurityViewConstants::FIELD_SECURITY_SECURE_CUSTOMER_PORTAL      => ControlUtils::input( SecurityViewConstants::FIELD_SECURITY_SECURE_CUSTOMER_PORTAL ),
            SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SECRET_KEY   => ControlUtils::input( SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SECRET_KEY ),
            SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SITE_KEY     => ControlUtils::input( SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SITE_KEY ),
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = SecurityViewConstants::FIELD_ACTION_VALUE_SAVE_SECURITY;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_SECURITY;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function secureInlineForms(): Control {
        return $this->secureInlineForms;
    }

    /**
     * @return Control
     */
    public function secureCheckoutForms(): Control {
        return $this->secureCheckoutForms;
    }

    /**
     * @return Control
     */
    public function secureCustomerPortal(): Control {
        return $this->secureCustomerPortal;
    }

    /**
     * @return Control
     */
    public function reCaptchaSecretKey(): Control {
        return $this->reCaptchaSecretKey;
    }

    /**
     * @return Control
     */
    public function reCaptchaSiteKey(): Control {
        return $this->reCaptchaSiteKey;
    }
}
