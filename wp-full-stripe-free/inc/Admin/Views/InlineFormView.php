<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

trait InlineFormView {
    /** @var Control */
    protected $cardFieldLanguage;

    protected function initInlineFields( $formHash ) {
        $this->cardFieldLanguage = ControlUtils::createControl( $formHash, InlineFormViewConstants::FIELD_FORM_CARD_FIELD_LANGUAGE, null, null,
            __( 'Card input field language', 'wp-full-stripe-free' ), null );
        $this->cardFieldLanguage->setAttributes( [
            'class'     => 'js-combobox'
        ]);
    }

    public static function getInlineFields() {
        $fields = [
            InlineFormViewConstants::FIELD_FORM_CARD_FIELD_LANGUAGE => ControlUtils::input( InlineFormViewConstants::FIELD_FORM_CARD_FIELD_LANGUAGE )
        ];

        return $fields;
    }

    /**
     * @return Control
     */
    public function cardFieldLanguage(): Control {
        return $this->cardFieldLanguage;
    }
}
