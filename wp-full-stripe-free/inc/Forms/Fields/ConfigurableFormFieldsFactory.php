<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

use ThemeIsle\WPFullPay\Main;

class ConfigurableFormFieldsFactory {
    public static function createConfiguration( $formType ) {
        switch ( $formType ) {
            case Main::FORM_TYPE_INLINE_SAVE_CARD:
                return new InlineSaveCardConfigurableFormFields();
            case Main::FORM_TYPE_INLINE_PAYMENT:
                return new InlinePaymentConfigurableFormFields();
            case Main::FORM_TYPE_INLINE_SUBSCRIPTION:
                return new InlineSubscriptionConfigurableFormFields();
            case Main::FORM_TYPE_INLINE_DONATION:
                return new InlineDonationConfigurableFormFields();
            case Main::FORM_TYPE_CHECKOUT_SAVE_CARD:
                return new CheckoutSaveCardConfigurableFormFields();
            case Main::FORM_TYPE_CHECKOUT_PAYMENT:
                return new CheckoutPaymentConfigurableFormFields();
            case Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION:
                return new CheckoutSubscriptionConfigurableFormFields();
            case Main::FORM_TYPE_CHECKOUT_DONATION:
                return new CheckoutDonationConfigurableFormFields();
        }
    }
}
