<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

class SaveCardFormView extends FormView implements SaveCardFormViewConstants {
    /** @var Control */
    protected $transactionDescription;

    public function __construct() {
        parent::__construct();
        $this->initFields();
    }

    protected function initFields() {
        $this->transactionDescription = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_TRANSACTION_DESCRIPTION, null, null,
            __( 'Transaction description', 'wp-full-stripe-free' ), null );
        $this->transactionDescription->setAttributes( [
            'class'     => 'wpfs-form-control js-token-target-transaction-description js-position-tracking-transaction-description',
            'rows'      => '3'
        ]);
    }

    public static function getFields() {
        $fields = [
            self::FIELD_FORM_TRANSACTION_DESCRIPTION => ControlUtils::input( self::FIELD_FORM_TRANSACTION_DESCRIPTION )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return Control
     */
    public function transactionDescription(): Control {
        return $this->transactionDescription;
    }
}
