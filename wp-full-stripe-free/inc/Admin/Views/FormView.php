<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class FormView extends AdminView implements FormViewConstants {
    /** @var Control */
    protected $name;
    /** @var Control */
    protected $displayName;
    /** @var Control */
    protected $redirectType;
    /** @var Control */
    protected $redirectPagePostId;
    /** @var Control */
    protected $redirectUrl;
    /** @var Control */
    protected $inheritLocale;
    /** @var Control */
    protected $localeDecimalSeparator;
    /** @var Control */
    protected $localeUseSymbolNotCode;
    /** @var Control */
    protected $localeCurrencySymbolAtFirstPosition;
    /** @var Control */
    protected $localePutSpaceBetweenSymbolAndAmount;
    /** @var Control */
    protected $buttonLabel;
    /** @var Control */
    protected $collectBillingAddress;
    /** @var Control */
    protected $defaultBillingCountry;
    /** @var Control */
    protected $collectShippingAddress;
    /** @var Control */
    protected $showTermsOfService;
    /** @var Control */
    protected $termsOfServiceLabel;
    /** @var Control */
    protected $termsOfServiceErrorMessage;
    /** @var Control */
    protected $customFields;
    /** @var Control */
    protected $makeCustomFieldsRequired;
    /** @var Control */
    protected $emailTemplates;
    /** @var Control */
    protected $webhook;
    /** @var Control */
    protected $stripeElementsThemeSelector;
    /** @var Control */
    protected $stripeElementsFontSelector;
    /** @var Control */
    protected $feeRecovery;
    /** @var Control */
    protected $feeRecoveryOptIn;
    /** @var Control */
    protected $feeRecoveryOptInMessage;
    /** @var Control */
    protected $feeRecoveryFeePercentage;
    /** @var Control */
    protected $feeRecoveryFeeAdditionalAmount;
    /** @var Control */
    protected $displayPaymentDetail;
    /** @var Control */
    protected $stripeElementsCustomCss;
    /** @var Control */
    protected $stripeElementsCustomCssHidden;

    public function __construct() {
        parent::__construct();

        $this->createNameFields();
        $this->createCurrencyFields();
        $this->createRedirectFields();
        $this->createAppearanceFields();
        $this->createOptionalFormFields();
        $this->createCustomFields();
        $this->createEmailTemplates();
        $this->createWebhook();
        $this->initStripeElementsThemeSelector();
        $this->initStripeElementsFontSelector();
        $this->createFeeRecoveryFields();
        $this->initStripeElementsCustomCss();
    }

    protected function initStripeElementsThemeSelector() {

        $this->stripeElementsThemeSelector = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_STRIPE_ELEMENTS_THEME_SELECTOR, null, null,
            __( 'Stripe Elements Appearance Theme', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->stripeElementsThemeSelector, [
            [ Main::ELEMENTS_THEME_STRIPE,
                /* translators: This selector style is a list of radio buttons  */
                __( 'Stripe', 'wp-full-stripe-free' ),
                [
                    'iconClass'     => 'wpfs-illu-element-style-stripe'
                ] ],
            [ Main::ELEMENTS_THEME_NIGHT,
                /* translators: This selector style is a list of radio buttons  */
                __( 'Night', 'wp-full-stripe-free' ),
                [
                    'iconClass'     => 'wpfs-illu-element-style-night'
                ] ],
            [ Main::ELEMENTS_THEME_FLAT,
                /* translators: This selector style is a list of radio buttons  */
                __( 'Flat', 'wp-full-stripe-free' ),
                [
                    'iconClass'     => 'wpfs-illu-element-style-flat'
                ] ],
        ] );
    }

    /**
     * Initialize the custom CSS field for stripe elements.
     *
     * @return void
     */
    protected function initStripeElementsCustomCss() {
        $this->stripeElementsCustomCss = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CUSTOM_CSS, null, null,
            __( 'Custom CSS for This Form', 'wp-full-stripe-free' ), null );
        $this->stripeElementsCustomCss->setAttributes( [
            'class' => 'wpfs-code-editor js-code-editor',
        ]);

        $this->stripeElementsCustomCssHidden = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CUSTOM_CSS_HIDDEN, null, null,
            null, null );
        $this->stripeElementsCustomCssHidden->setAttributes( [
            'type'      => 'hidden',
            'class'     => 'wpfs-custom-css-hidden'
        ]);
    }

    protected function initStripeElementsFontSelector() {
        $this->stripeElementsFontSelector = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_STRIPE_ELEMENTS_FONT_SELECTOR, null, null,
            /* translators: Form field label for the display name of the form */
            __( 'Stripe Elements Appearance Font', 'wp-full-stripe-free' ), null );
    }

    protected function createEmailTemplates() {
        $this->emailTemplates = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_EMAIL_TEMPLATES, null, null,
            __( 'Email templates', 'wp-full-stripe-free' ), null );
        $this->emailTemplates->setAttributes( [
            'type' => 'hidden'
        ]);
    }

    protected function createCustomFields() {
        $this->customFields = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CUSTOM_FIELDS, null, null,
            __( 'Custom fields', 'wp-full-stripe-free' ), null );
        $this->customFields->setAttributes( [
            'type' => 'hidden'
        ]);

        $this->makeCustomFieldsRequired = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_MAKE_CUSTOM_FIELDS_REQUIRED, null, null,
            __( 'Make custom fields required?', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->makeCustomFieldsRequired, [
            [ '0',
                __( 'No', 'wp-full-stripe-free' ) ],
            [ '1',
                __( 'Yes', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function createWebhook() {
        $this->webhook = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_WEBHOOK, null, null,
            __( 'Webhook', 'wp-full-stripe-free' ), null );
        $this->webhook->setAttributes( [
            'type' => 'hidden'
        ]);
    }

    protected function createOptionalFormFields() {
        $this->collectBillingAddress = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_COLLECT_BILLING_ADDRESS, null, null,
            __( 'Billing address', 'wp-full-stripe-free' ), null );
        $this->collectBillingAddress->setValue('1' );
        $this->collectBillingAddress->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $defaultBillingCountryLabel = $this instanceof DonationFormView ?
            __( "Nonprofit's seat country", 'wp-full-stripe-free' ) :
            __( "Seller's seat country", 'wp-full-stripe-free' );
        $this->defaultBillingCountry = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DEFAULT_BILLING_COUNTRY, null, null,
            $defaultBillingCountryLabel, null );
        $this->defaultBillingCountry->setAttributes( [
            'class'     => 'js-combobox'
        ]);

        $this->collectShippingAddress = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_COLLECT_SHIPPING_ADDRESS, null, null,
            __( 'Shipping address', 'wp-full-stripe-free' ), null );
        $this->collectShippingAddress->setValue('1' );
        $this->collectShippingAddress->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->showTermsOfService = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_SHOW_TERMS_OF_SERVICE, null, null,
            __( 'Terms of Service checkbox', 'wp-full-stripe-free' ), null );
        $this->showTermsOfService->setValue('1' );
        $this->showTermsOfService->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->termsOfServiceLabel = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_TERMS_OF_SERVICE_LABEL, null, null,
            /* translators: Label of the Terms of Service checkbox displayed to the customer */
            __( 'Terms of service label', 'wp-full-stripe-free' ), null );
        $this->termsOfServiceErrorMessage = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_TERMS_OF_SERVICE_ERROR, null, null,
            /* translators: Validation error message displayed when the custom doesn't accept the Terms of Service */
            __( 'Terms of service error message', 'wp-full-stripe-free' ), null );
    }

    protected function createAppearanceFields() {
        $this->buttonLabel = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_BUTTON_LABEL, null, null,
            __( 'Form submit button label', 'wp-full-stripe-free' ), null );
        $this->buttonLabel->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);
    }

    protected function createRedirectFields() {
        $this->redirectType = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_REDIRECT_TYPE, null, null,
            /* translators: Title of the section where the administrator can decide what should happen after payment, eg. display success banner, redirect to Thank you page, etc */
            __( 'After successful payment', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->redirectType, [
            [ Main::REDIRECT_TYPE_SHOW_CONFIRMATION_MESSAGE,
                __( 'Show confirmation message', 'wp-full-stripe-free' ) ],
            [ Main::REDIRECT_TYPE_TO_PAGE_OR_POST,
                __( 'Redirect to a Thank you page', 'wp-full-stripe-free' ) ],
            [ Main::REDIRECT_TYPE_TO_CUSTOM_URL,
                __( 'Redirect to a custom URL', 'wp-full-stripe-free' ) ],
        ] );

        $this->redirectPagePostId = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_REDIRECT_PAGE_POST_ID, null, null,
            __( 'Redirect to a Thank you page', 'wp-full-stripe-free' ), null );
        $this->redirectPagePostId->setAttributes( [
            'class'     => 'js-combobox'
        ]);

        $this->redirectUrl = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_REDIRECT_CUSTOM_URL, __( 'Enter an URL starting with http:// or https://', 'wp-full-stripe-free' ), null,
            __( 'Redirect to custom URL', 'wp-full-stripe-free' ), null );
        $this->redirectUrl->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);

    }

    protected function createNameFields() {
        $this->displayName = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_DISPLAY_NAME, null, null,
            /* translators: Form field label for the display name of the form */
            __( 'Display name', 'wp-full-stripe-free' ), null );
        $this->name = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_NAME, null, null,
            /* translators: Form field label for the identifier of the form */
            __( 'Identifier', 'wp-full-stripe-free' ), null );
    }

    protected function createCurrencyFields() {
        $this->inheritLocale = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_INHERIT_LOCALE, null, null,
            /* translators: Form field label for the 'Inherit global locale settings' option of the form */
            __( 'Use Global Locale Settings', 'wp-full-stripe-free' ), null );
        $this->inheritLocale->setValue( '1' );
        $this->inheritLocale->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->localeDecimalSeparator = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOCALE_DECIMAL_SEPARATOR, null, null,
            /* translators: Form field label for the 'Format decimals with' option of the form */
            __( 'Format decimals with', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->localeDecimalSeparator, [
            [ Main::DECIMAL_SEPARATOR_SYMBOL_DOT,
                /* translators: Example of using dot for the decimal point */
                __( '$10.99 (dot)', 'wp-full-stripe-free' ) ],
            [ Main::DECIMAL_SEPARATOR_SYMBOL_COMMA,
                /* translators: Example of using comma for the decimal point */
                __( '$10,99 (comma)', 'wp-full-stripe-free' ) ],
        ] );

        $this->localeUseSymbolNotCode = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOCALE_SYMBOL_NOT_CODE, null, null,
            /* translators: Form field label for the 'Use currency symbol or code?' option of the form */
            __( 'Use currency symbol or code?', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->localeUseSymbolNotCode, [
            [ '1',
                /* translators: Example of displaying the currency symbol ($)  */
                __( '$10.99 (symbol)', 'wp-full-stripe-free' ) ],
            [ '0',
                /* translators: Example of displaying the currency code (USD)  */
                __( 'USD 10.99 (code)', 'wp-full-stripe-free' ) ],
        ] );

        $this->localeCurrencySymbolAtFirstPosition = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOCALE_SYMBOL_AT_FIRST_POSITION, null, null,
            /* translators: Form field label for the 'Put currency identifier on' option of the form */
            __( 'Put currency identifier on', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->localeCurrencySymbolAtFirstPosition, [
            [ '1',
                /* translators: Example of displaying the currency symbol on the left  */
                __( '€10.99 (left)', 'wp-full-stripe-free' ) ],
            [ '0',
                /* translators: Example of displaying the currency symbol on the right  */
                __( '10.99€ (right)', 'wp-full-stripe-free' ) ],
        ] );

        $this->localePutSpaceBetweenSymbolAndAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOCALE_SPACE_BETWEEN_SYMBOL_AND_AMOUNT, null, null,
            /* translators: Form field label for the 'Insert space between amount and currency?' option of the form */
            __( 'Insert space between amount and currency?', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->localePutSpaceBetweenSymbolAndAmount, [
            [ '0',
                /* translators: Example of not having a space between the amount and the currency symbol */
                __( '10.99EUR (no)', 'wp-full-stripe-free' ) ],
            [ '1',
                /* translators: Example of inserting a space between the amount and the currency symbol */
                __( '10.99 EUR (yes)', 'wp-full-stripe-free' ) ],
        ] );
    }

    protected function createFeeRecoveryFields() {
        $this->feeRecovery = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_FEE_RECOVERY, null, null,
            /* translators: Form field label for the 'Fee recovery' option of the form */
            __( 'Fee Recovery', 'wp-full-stripe-free' ), null );

        // It will have three options, Inherit from Global Settings, Customize and Disable
        $this->setRadioOptions( $this->feeRecovery, [
            [ Main::FEE_RECOVERY_ENABLE,
                /* translators: Form field label for the 'Customize' option of the form */
                __( 'Enable', 'wp-full-stripe-free' ) ],
            [ Main::FEE_RECOVERY_DISABLE,
                /* translators: Form field label for the 'Disable' option of the form */
                __( 'Disable', 'wp-full-stripe-free' ) ],
        ] );

        $this->feeRecoveryOptIn = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_FEE_RECOVERY_OPT_IN, null, null,
            /* translators: Form field label for the 'Opt-in' option of the form */
            __( 'Opt-in', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->feeRecoveryOptIn, [
            [ 1,
                /* translators: Form field label for the 'No' option of the form */
                __( 'Optional', 'wp-full-stripe-free' ) ],
            [ 0,
                /* translators: Form field label for the 'Yes' option of the form */
                __( 'Required', 'wp-full-stripe-free' ) ],
        ] );

        $this->feeRecoveryOptInMessage = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_FEE_RECOVERY_OPT_IN_MESSAGE, null, null,
            __( "Opt-in Message", 'wp-full-stripe-free' ), null );

        $this->feeRecoveryOptInMessage->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control',
            'placeholder' => Utils::getFeeRecoveryMessagePlaceholder()
        ]);

        $this->feeRecoveryFeePercentage = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_FEE_RECOVERY_FEE_PERCENTAGE, null, null,
            __( "Fee Percentage", 'wp-full-stripe-free' ), null );

        $this->feeRecoveryFeePercentage->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-input-group-form-control'
        ]);

        $this->feeRecoveryFeeAdditionalAmount = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT, null, null,
            __( "Additional Fee Amount", 'wp-full-stripe-free' ), null );

        $this->feeRecoveryFeeAdditionalAmount->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-input-group-form-control'
        ]);
    }

    /**
     * Initialize whether to display the payment detail field.
     *
     * @return void
     */
    protected function initPaymentDetailField() {
        $this->displayPaymentDetail = ControlUtils::createControl(
            $this->formHash,
            self::FIELD_FORM_SHOW_PAYMENT_DETAIL,
            null,
            null,
            __( 'Display the payment details link', 'wp-full-stripe-free' ),
            null
        );
        $this->displayPaymentDetail->setAttributes( [
            'class'     => 'js-combobox'
        ]);
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            self::FIELD_FORM_NAME                                     => ControlUtils::input( self::FIELD_FORM_NAME ),
            self::FIELD_FORM_DISPLAY_NAME                             => ControlUtils::input( self::FIELD_FORM_DISPLAY_NAME ),
            self::FIELD_FORM_REDIRECT_CUSTOM_URL                      => ControlUtils::input( self::FIELD_FORM_REDIRECT_CUSTOM_URL ),
            self::FIELD_FORM_BUTTON_LABEL                             => ControlUtils::input( self::FIELD_FORM_BUTTON_LABEL ),
            self::FIELD_FORM_INHERIT_LOCALE                           => ControlUtils::input( self::FIELD_FORM_INHERIT_LOCALE ),
            self::FIELD_FORM_LOCALE_DECIMAL_SEPARATOR                 => ControlUtils::input( self::FIELD_FORM_LOCALE_DECIMAL_SEPARATOR ),
            self::FIELD_FORM_LOCALE_SYMBOL_NOT_CODE                   => ControlUtils::input( self::FIELD_FORM_LOCALE_SYMBOL_NOT_CODE ),
            self::FIELD_FORM_LOCALE_SYMBOL_AT_FIRST_POSITION          => ControlUtils::input( self::FIELD_FORM_LOCALE_SYMBOL_AT_FIRST_POSITION ),
            self::FIELD_FORM_LOCALE_SPACE_BETWEEN_SYMBOL_AND_AMOUNT   => ControlUtils::input( self::FIELD_FORM_LOCALE_SPACE_BETWEEN_SYMBOL_AND_AMOUNT ),
            self::FIELD_FORM_TERMS_OF_SERVICE_LABEL                   => ControlUtils::input( self::FIELD_FORM_TERMS_OF_SERVICE_LABEL ),
            self::FIELD_FORM_TERMS_OF_SERVICE_ERROR                   => ControlUtils::input( self::FIELD_FORM_TERMS_OF_SERVICE_ERROR ),
            self::FIELD_FORM_WEBHOOK                                  => ControlUtils::input( self::FIELD_FORM_WEBHOOK ),
            self::FIELD_FORM_FEE_RECOVERY                             => ControlUtils::input( self::FIELD_FORM_FEE_RECOVERY ),
            self::FIELD_FORM_FEE_RECOVERY_OPT_IN                      => ControlUtils::input( self::FIELD_FORM_FEE_RECOVERY_OPT_IN ),
            self::FIELD_FORM_FEE_RECOVERY_OPT_IN_MESSAGE              => ControlUtils::input( self::FIELD_FORM_FEE_RECOVERY_OPT_IN_MESSAGE ),
            self::FIELD_FORM_FEE_RECOVERY_FEE_PERCENTAGE              => ControlUtils::input( self::FIELD_FORM_FEE_RECOVERY_FEE_PERCENTAGE ),
            self::FIELD_FORM_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT       => ControlUtils::input( self::FIELD_FORM_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ),
            self::FIELD_FORM_CUSTOM_CSS                               => ControlUtils::input( self::FIELD_FORM_CUSTOM_CSS ),
            self::FIELD_FORM_CUSTOM_CSS_HIDDEN                        => ControlUtils::input( self::FIELD_FORM_CUSTOM_CSS_HIDDEN )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];
        $attributes[self::ATTR_DATA_WPFS_ELEMENTS_THEME] = $this->stripeElementsThemeSelector;
        $attributes[self::ATTR_DATA_WPFS_ELEMENTS_FONT] = $this->stripeElementsFontSelector;

        $attributes = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function name(): Control {
        return $this->name;
    }

    /**
     * @return Control
     */
    public function displayName(): Control {
        return $this->displayName;
    }

    /**
     * @return Control
     */
    public function redirectType(): Control {
        return $this->redirectType;
    }

    /**
     * @return Control
     */
    public function redirectPagePostId(): Control {
        return $this->redirectPagePostId;
    }

    /**
     * @return Control
     */
    public function redirectUrl(): Control {
        return $this->redirectUrl;
    }

    /**
     * @return Control
     */
    public function inheritLocale(): Control {
        return $this->inheritLocale;
    }

    /**
     * @return Control
     */
    public function localeDecimalSeparator(): Control {
        return $this->localeDecimalSeparator;
    }

    /**
     * @return Control
     */
    public function localeUseSymbolNotCode(): Control {
        return $this->localeUseSymbolNotCode;
    }

    /**
     * @return Control
     */
    public function localeCurrencySymbolAtFirstPosition(): Control {
        return $this->localeCurrencySymbolAtFirstPosition;
    }

    /**
     * @return Control
     */
    public function localePutSpaceBetweenSymbolAndAmount(): Control {
        return $this->localePutSpaceBetweenSymbolAndAmount;
    }

    /**
     * @return Control
     */
    public function buttonLabel(): Control {
        return $this->buttonLabel;
    }

    /**
     * @return Control
     */
    public function collectBillingAddress(): Control {
        return $this->collectBillingAddress;
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
    public function collectShippingAddress(): Control {
        return $this->collectShippingAddress;
    }

    /**
     * @return Control
     */
    public function showTermsOfService(): Control {
        return $this->showTermsOfService;
    }

    /**
     * @return Control
     */
    public function termsOfServiceLabel(): Control {
        return $this->termsOfServiceLabel;
    }

    /**
     * @return Control
     */
    public function termsOfServiceErrorMessage(): Control {
        return $this->termsOfServiceErrorMessage;
    }

    /**
     * @return Control
     */
    public function customFields(): Control {
        return $this->customFields;
    }

    /**
     * @return Control
     */
    public function makeCustomFieldsRequired(): Control {
        return $this->makeCustomFieldsRequired;
    }

    /**
     * @return Control
     */
    public function emailTemplates(): Control {
        return $this->emailTemplates;
    }

    /**
     * @return Control
     */
    public function webhook(): Control {
        return $this->webhook;
    }

    /**
     * @return Control
     */
    public function stripeElementsThemeSelector(): Control {
        return $this->stripeElementsThemeSelector;
    }

    /**
     * @return Control
     */
    public function stripeElementsFont(): Control {
        return $this->stripeElementsFontSelector;
    }

    /**
     * @return Control
     */
    public function feeRecovery(): Control {
        return $this->feeRecovery;
    }

    /**
     * @return Control
     */
    public function feeRecoveryOptIn(): Control {
        return $this->feeRecoveryOptIn;
    }

    /**
     * @return Control
     */
    public function feeRecoveryOptInMessage(): Control {
        return $this->feeRecoveryOptInMessage;
    }

    /**
     * @return Control
     */
    public function feeRecoveryFeePercentage(): Control {
        return $this->feeRecoveryFeePercentage;
    }

    /**
     * @return Control
     */
    public function feeRecoveryFeeAdditionalAmount(): Control {
        return $this->feeRecoveryFeeAdditionalAmount;
    }

    /**
     * @return Control
     */
    public function paymentDetail(): Control {
        return $this->displayPaymentDetail;
    }

    /**
     * @return Control
     */
    public function stripeElementsCustomCss(): Control {
        return $this->stripeElementsCustomCss;
    }

    /**
     * @return Control
     */
    public function stripeElementsCustomCssHidden(): Control {
        return $this->stripeElementsCustomCssHidden;
    }
}
