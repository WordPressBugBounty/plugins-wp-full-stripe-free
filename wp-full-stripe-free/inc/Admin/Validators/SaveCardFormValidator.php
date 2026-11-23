<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

abstract class SaveCardFormValidator extends FormValidator {

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate( $bindingResult, $formModel ) {
        parent::validate( $bindingResult, $formModel );
    }
}
