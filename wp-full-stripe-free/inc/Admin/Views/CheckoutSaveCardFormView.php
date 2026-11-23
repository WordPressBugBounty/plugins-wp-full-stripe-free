<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Main;

class CheckoutSaveCardFormView extends SaveCardFormView implements CheckoutSaveCardFormViewConstants  {
    use CheckoutFormView;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_CHECKOUT_SAVE_CARD_FORM;
        parent::__construct();
        $this->action->setValue( self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_SAVE_CARD_FORM );

        $this->initCheckoutFields( $this->formHash );
    }

    public static function getFields() {
        $checkoutFields = self::getCheckoutFields();

        return array_merge( $checkoutFields, parent::getFields() );
    }

    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = self::FIELD_ACTION_VALUE_SAVE_CHECKOUT_SAVE_CARD_FORM;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CHECKOUT_SAVE_CARD_FORM;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }
}
