<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class CreateFormView extends AdminView {
    /** @var Control */
    protected $displayName;
    /** @var Control */
    protected $name;
    /** @var Control */
    protected $type;
    /** @var Control */
    protected $layout;

    /**
     * CreateFormView constructor.
     * @param $params array
     */
    public function __construct( $params ) {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CREATE_FORM;

        parent::__construct();

        $formType = $this->getFormTypeFromParams( $params );

        $this->action->setValue( CreateFormViewConstants::FIELD_ACTION_VALUE_CREATE_FORM );

        $this->displayName = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME, null, null,
            /* translators: Form field label for the display name of the form */
            __( 'Display name', 'wp-full-stripe-free' ), null );
        $this->name = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_NAME, null, null,
            /* translators: Form field label for the identifier of the form */
            __( 'Identifier', 'wp-full-stripe-free' ), null );

        $this->type = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_TYPE, null, null,
            /* translators: Form field label for the type of the form */
            __( 'Form type', 'wp-full-stripe-free' ), null );

        $typeOptionIndex = 0;
        $typeOptions = [];

        $paymentTypeOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_TYPE, null, null,
            /* translators: One-time payment form type */
            __( 'One-time payment', 'wp-full-stripe-free' ), $typeOptionIndex );
        $paymentTypeAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_TYPE_PAYMENT,
        ];
        if ( $formType === Main::FORM_TYPE_PAYMENT ) {
            $paymentTypeAttributes[ 'checked' ] = 'checked';
        }
        $paymentTypeOption->setAttributes( $paymentTypeAttributes );
        $paymentTypeOption->setMetadata( [
            'description'   =>
            /* translators: Description of the one-time payment form type */
                __( 'Software, ticket, physical goods, and settle invoices', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-book-close-bookmark'
        ]);
        array_push( $typeOptions, $paymentTypeOption );
        $typeOptionIndex++;

        $subscriptionTypeOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_TYPE, null, null,
            /* translators: Subscription form type */
            __( 'Subscription', 'wp-full-stripe-free' ), $typeOptionIndex );
        $paymentTypeAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_TYPE_SUBSCRIPTION,
        ];
        if ( $formType === Main::FORM_TYPE_SUBSCRIPTION ) {
            $paymentTypeAttributes[ 'checked' ] = 'checked';
        }
        $subscriptionTypeOption->setAttributes( $paymentTypeAttributes );
        $subscriptionTypeOption->setMetadata( [
            'description'   =>
            /* translators: Description of the subscription form type */
                __( 'Online services, consulting, and professional services', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-subscription'
        ]);
        array_push( $typeOptions, $subscriptionTypeOption );
        $typeOptionIndex++;

        $donationTypeOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_TYPE, null, null,
            /* translators: Donation form type */
            __( 'Donation', 'wp-full-stripe-free' ), $typeOptionIndex );
        $paymentTypeAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_TYPE_DONATION,
        ];
        if ( $formType === Main::FORM_TYPE_DONATION ) {
            $paymentTypeAttributes[ 'checked' ] = 'checked';
        }
        $donationTypeOption->setAttributes( $paymentTypeAttributes );
        $donationTypeOption->setMetadata( [
            'description'   =>
            /* translators: Description of the donation form type */
                __( 'Donor platform for your fundraising efforts', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-donation'
        ]);
        array_push( $typeOptions, $donationTypeOption );
        $typeOptionIndex++;

        $saveCardTypeOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_TYPE, null, null,
            /* translators: Save card form type */
            __( 'Save card', 'wp-full-stripe-free' ), $typeOptionIndex );
        $paymentTypeAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_TYPE_SAVE_CARD,
        ];
        if ( $formType === Main::FORM_TYPE_SAVE_CARD ) {
            $paymentTypeAttributes[ 'checked' ] = 'checked';
        }
        $saveCardTypeOption->setAttributes( $paymentTypeAttributes );
        $saveCardTypeOption->setMetadata( [
            'description'   =>
            /* translators: Description of the save card form type */
                __( 'Customer\'s payment information', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-credit-card'
        ]);
        array_push( $typeOptions, $saveCardTypeOption );

        $this->type->setOptions( $typeOptions );

        $this->layout = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_LAYOUT, null, null,
            /* translators: Form field label for the layout of the form */
            __( 'Form layout', 'wp-full-stripe-free' ), null );

        $layoutOptionIndex = 0;
        $layoutOptions = [];

        $inlineLayoutOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_LAYOUT, null, null,
            /* translators: Inline layout type on the Create form page  */
            __( 'Inline', 'wp-full-stripe-free' ), $layoutOptionIndex );
        $inlineLayoutOption->setAttributes( [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_LAYOUT_INLINE,
            'checked'   => 'checked'
        ]);
        $inlineLayoutOption->setMetadata( [
            'description'   =>
            /* translators: Description of the inline form layout */
                __( 'Enough screen estate to display the entire form', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-layout-inline'
        ]);
        array_push( $layoutOptions, $inlineLayoutOption );
        $layoutOptionIndex++;

        $checkoutLayoutOption = ControlUtils::createControl( $this->formHash, CreateFormViewConstants::FIELD_FORM_LAYOUT, null, null,
            /* translators: Checkout layout type on the Create form page */
            __( 'Checkout', 'wp-full-stripe-free' ), $layoutOptionIndex );
        $checkoutLayoutOption->setAttributes( [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input',
            'value'     => Main::FORM_LAYOUT_CHECKOUT,
        ]);
        $checkoutLayoutOption->setMetadata( [
            'description'   =>
            /* translators: Description of the checkout form layout */
                __( 'When screen estate is constrained, or using pricing tables', 'wp-full-stripe-free' ),
            'iconClass'     => 'wpfs-illu-layout-checkout'
        ]);
        array_push( $layoutOptions, $checkoutLayoutOption );

        $this->layout->setOptions( $layoutOptions );
    }

    /**
     * @param $params
     *
     * @return string
     */
    private function getFormTypeFromParams( $params ) : string {
        $types = [
            Main::FORM_TYPE_PAYMENT,
            Main::FORM_TYPE_SUBSCRIPTION,
            Main::FORM_TYPE_DONATION,
            Main::FORM_TYPE_SAVE_CARD
        ];

        $type = array_key_exists( Menu::PARAM_NAME_TYPE, $params ) ? $params[ Menu::PARAM_NAME_TYPE ] : null;
        $type = array_search( $type, $types ) !== false ? $type : Main::FORM_TYPE_PAYMENT;

        return $type;
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME => ControlUtils::input( CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME ),
            CreateFormViewConstants::FIELD_FORM_NAME         => ControlUtils::input( CreateFormViewConstants::FIELD_FORM_NAME ),
            CreateFormViewConstants::FIELD_FORM_TYPE         => ControlUtils::input( CreateFormViewConstants::FIELD_FORM_TYPE ),
            CreateFormViewConstants::FIELD_FORM_LAYOUT       => ControlUtils::input( CreateFormViewConstants::FIELD_FORM_LAYOUT )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = CreateFormViewConstants::FIELD_ACTION_VALUE_CREATE_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CREATE_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function displayName() {
        return $this->displayName;
    }

    /**
     * @return Control
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return Control
     */
    public function type() {
        return $this->type;
    }

    /**
     * @return Control
     */
    public function layout() {
        return $this->layout;
    }
}
