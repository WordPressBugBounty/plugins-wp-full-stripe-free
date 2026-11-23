<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Main;

class InlinePaymentFormView extends PaymentFormView implements InlinePaymentFormViewConstants  {
    use InlineFormView;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_PAYMENT_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_INLINE_PAYMENT_FORM );

        $this->initInlineFields( $this->formHash );
    }

    public static function getFields() {
        $inlineFields = self::getInlineFields();

        return array_merge( $inlineFields, parent::getFields() );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_INLINE_PAYMENT_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_INLINE_PAYMENT_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }
}
