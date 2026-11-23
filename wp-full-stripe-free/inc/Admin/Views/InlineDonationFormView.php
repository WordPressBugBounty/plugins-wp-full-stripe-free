<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class InlineDonationFormView extends DonationFormView implements InlineDonationFormViewConstants  {
    use InlineFormView;

    /** @var Control */
    protected $defaultProductName;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_DONATION_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_INLINE_DONATION_FORM );

        $this->initInlineFields( $this->formHash );
        $this->initInlineDonationFields( $this->formHash );
    }

    protected function initInlineDonationFields( $formHash ) {
        $this->defaultProductName = ControlUtils::createControl( $formHash, self::FIELD_FORM_DEFAULT_PRODUCT_NAME, null, null,
            __( 'Donation product name', 'wp-full-stripe-free' ), null );
        $this->defaultProductName->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control'
        ]);
    }

    public static function getFields() {
        $inlineFields = self::getInlineFields();

        $inlineDonationFields = [
            self::FIELD_FORM_DEFAULT_PRODUCT_NAME => ControlUtils::input( self::FIELD_FORM_DEFAULT_PRODUCT_NAME ),
        ];

        return array_merge( $inlineFields,
            parent::getFields(),
            $inlineDonationFields
        );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_INLINE_DONATION_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_INLINE_DONATION_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function defaultProductName(): Control  {
        return $this->defaultProductName;
    }
}
