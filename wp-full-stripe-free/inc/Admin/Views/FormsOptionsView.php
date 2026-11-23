<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class FormsOptionsView extends AdminView {
    /** @var Control */
    protected $defaultBillingCountry;
    /** @var Control */
    protected $fillInEmailForLoggedInUsers;
    /** @var Control */
    protected $setFormFieldsViaUrlParameters;
    /** @var Control */
    protected $showPaymentDetail;

    /**
     * FormsOptionsView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_FORMS_OPTIONS;
        parent::__construct();

        $this->action->setValue( FormsOptionsViewConstants::FIELD_ACTION_VALUE_SAVE_FORMS_OPTIONS );

        $defaultBillingCountryLabel = __( "Seller's Seat Country", 'wp-full-stripe-free' );
        $this->defaultBillingCountry = ControlUtils::createControl( $this->formHash, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_DEFAULT_BILLING_COUNTRY, null, null,
            $defaultBillingCountryLabel, null );
        $this->defaultBillingCountry->setAttributes( [
            'class'     => 'js-combobox'
        ]);

        $this->fillInEmailForLoggedInUsers = ControlUtils::createControl( $this->formHash, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_FILL_IN_EMAIL, null, null,
            /* translators: Form field label for the 'Fill in email for logged in users?' option of the form */
            __( 'Fill in email field for logged in users', 'wp-full-stripe-free' ), null );
        $this->fillInEmailForLoggedInUsers->setValue('1' );
        $this->fillInEmailForLoggedInUsers->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->setFormFieldsViaUrlParameters = ControlUtils::createControl( $this->formHash, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SET_FIELDS_VIA_URL_PARAMETERS, null, null,
            /* translators: Form field label for the 'Enable setting form fields via URL parameters' option of the form */
            __( 'Allow setting form fields via URL parameters', 'wp-full-stripe-free' ), null );
        $this->setFormFieldsViaUrlParameters->setValue('1' );
        $this->setFormFieldsViaUrlParameters->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->showPaymentDetail = ControlUtils::createControl( $this->formHash, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SHOW_PAYMENT_DETAIL, null, null,
            /* translators: Form field label for the global default of how the payment details summary is displayed on new forms */
            __( 'Default payment details display', 'wp-full-stripe-free' ), null );
        $this->showPaymentDetail->setAttributes( [
            'class'     => 'js-selectmenu'
        ]);
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_DEFAULT_BILLING_COUNTRY => ControlUtils::input( FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_DEFAULT_BILLING_COUNTRY ),
            FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_FILL_IN_EMAIL           => ControlUtils::input( FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_FILL_IN_EMAIL ),
            FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SHOW_PAYMENT_DETAIL     => ControlUtils::input( FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SHOW_PAYMENT_DETAIL )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = FormsOptionsViewConstants::FIELD_ACTION_VALUE_SAVE_FORMS_OPTIONS;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_FORMS_OPTIONS;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function defaultBillingCountry(): Control {
        return $this->defaultBillingCountry;
    }

    /**
     * @return Control
     */
    public function fillInEmailForLoggedInUsers(): Control {
        return $this->fillInEmailForLoggedInUsers;
    }

    /**
     * @return Control
     */
    public function setFormFieldsViaUrlParameters(): Control {
        return $this->setFormFieldsViaUrlParameters;
    }

    /**
     * @return Control
     */
    public function showPaymentDetail(): Control {
        return $this->showPaymentDetail;
    }
}
