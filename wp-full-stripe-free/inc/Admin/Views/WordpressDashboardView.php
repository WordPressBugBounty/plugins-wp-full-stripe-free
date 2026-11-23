<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class WordpressDashboardView extends AdminView {
    /** @var Control */
    protected $decimalSeparator;
    /** @var Control */
    protected $useSymbolNotCode;
    /** @var Control */
    protected $currencySymbolAtFirstPosition;
    /** @var Control */
    protected $putSpaceBetweenSymbolAndAmount;

    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_WP_DASHBOARD;
        parent::__construct();

        $this->action->setValue( WordpressDashboardViewConstants::FIELD_ACTION_VALUE_SAVE_WP_DASHBOARD );

        $this->decimalSeparator = ControlUtils::createControl( $this->formHash, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_DECIMAL_SEPARATOR, null, null,
            /* translators: Form field label for the 'Format decimals with' option of the form */
            __( 'Format decimals with', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->decimalSeparator, [
            [ Main::DECIMAL_SEPARATOR_SYMBOL_DOT,
                /* translators: It's a example showing that the decimal separator is a dot */
                __( '$10.99 (dot)', 'wp-full-stripe-free' ) ],
            [ Main::DECIMAL_SEPARATOR_SYMBOL_COMMA,
                /* translators: It's a example showing that the decimal separator is a comma */
                __( '$10,99 (comma)', 'wp-full-stripe-free' ) ],
        ] );

        $this->useSymbolNotCode = ControlUtils::createControl( $this->formHash, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_NOT_CODE, null, null,
            /* translators: Form field label for the 'Use currency symbol or code?' option of the form */
            __( 'Use currency symbol or code?', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->useSymbolNotCode, [
            [ '1',
                /* translators: It's a example showing that the currency symbol ($) is used */
                __( '$10.99 (symbol)', 'wp-full-stripe-free' ) ],
            [ '0',
                /* translators: It's a example showing that the currency code (USD) is used */
                __( 'USD 10.99 (code)', 'wp-full-stripe-free' ) ],
        ] );

        $this->currencySymbolAtFirstPosition = ControlUtils::createControl( $this->formHash, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_AT_FIRST_POSITION, null, null,
            /* translators: Form field label for the 'Put currency identifier on' option of the form */
            __( 'Put currency identifier on', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->currencySymbolAtFirstPosition, [
            [ '1',
                /* translators: It's a example showing that the currency symbol is on the left */
                __( '€10.99 (left)', 'wp-full-stripe-free' ) ],
            [ '0',
                /* translators: It's a example showing that the currency symbol is on the right */
                __( '10.99€ (right)', 'wp-full-stripe-free' ) ],
        ] );

        $this->putSpaceBetweenSymbolAndAmount = ControlUtils::createControl( $this->formHash, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SPACE_BETWEEN_SYMBOL_AND_AMOUNT, null, null,
            /* translators: Form field label for the 'Insert space between amount and currency?' option of the form */
            __( 'Insert space between amount and currency?', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->putSpaceBetweenSymbolAndAmount, [
            [ '0',
                /* translators: It's a example showing that there is no space between the number and the currency code */
                __( '10.99EUR (no)', 'wp-full-stripe-free' ) ],
            [ '1',
                /* translators: It's a example showing that there is space between the number and the currency code */
                __( '10.99 EUR (yes)', 'wp-full-stripe-free' ) ],
        ] );
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_DECIMAL_SEPARATOR                 => ControlUtils::input( WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_DECIMAL_SEPARATOR ),
            WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_NOT_CODE                   => ControlUtils::input( WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_NOT_CODE ),
            WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_AT_FIRST_POSITION          => ControlUtils::input( WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_AT_FIRST_POSITION ),
            WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SPACE_BETWEEN_SYMBOL_AND_AMOUNT   => ControlUtils::input( WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SPACE_BETWEEN_SYMBOL_AND_AMOUNT ),
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = WordpressDashboardViewConstants::FIELD_ACTION_VALUE_SAVE_WP_DASHBOARD;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_WP_DASHBOARD;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function decimalSeparator(): Control {
        return $this->decimalSeparator;
    }

    /**
     * @return Control
     */
    public function useSymbolNotCode(): Control {
        return $this->useSymbolNotCode;
    }

    /**
     * @return Control
     */
    public function currencySymbolAtFirstPosition(): Control {
        return $this->currencySymbolAtFirstPosition;
    }

    /**
     * @return Control
     */
    public function putSpaceBetweenSymbolAndAmount(): Control {
        return $this->putSpaceBetweenSymbolAndAmount;
    }
}
