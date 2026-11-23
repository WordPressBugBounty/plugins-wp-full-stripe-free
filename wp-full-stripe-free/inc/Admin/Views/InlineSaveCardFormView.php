<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class InlineSaveCardFormView extends SaveCardFormView implements InlineSaveCardFormViewConstants  {
    use InlineFormView;

    /** @var Control */
    protected $stripeLink;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_SAVE_CARD_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_INLINE_SAVE_CARD_FORM );

        $this->initInlineFields( $this->formHash );
        $this->initStripeLink();
    }

    protected function initStripeLink(): void {
        $this->stripeLink = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_STRIPE_LINK, null, null,
            __( 'Stripe Link', 'wp-full-stripe-free' ), null );

        $options = [];
        $choices = [
            '1' => __( 'Enabled', 'wp-full-stripe-free' ),
            '0' => __( 'Disabled', 'wp-full-stripe-free' ),
        ];
        $optionIndex = 0;
        foreach ( $choices as $value => $label ) {
            $option = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_STRIPE_LINK, null, null, $label, $optionIndex );
            $option->setValue( (string) $value );
            $option->setAttributes( [
                'type'  => 'radio',
                'class' => 'wpfs-form-check-input'
            ] );
            $options[] = $option;
            $optionIndex++;
        }

        $this->stripeLink->setOptions( $options );
    }

    public static function getFields() {
        $inlineFields = self::getInlineFields();
        $inlineFields[ self::FIELD_FORM_STRIPE_LINK ] = ControlUtils::input( self::FIELD_FORM_STRIPE_LINK );

        return array_merge( $inlineFields, parent::getFields() );
    }

    /**
     * @return Control
     */
    public function stripeLink(): Control {
        return $this->stripeLink;
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_INLINE_SAVE_CARD_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_INLINE_SAVE_CARD_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }
}
