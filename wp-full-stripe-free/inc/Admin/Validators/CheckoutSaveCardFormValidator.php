<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

class CheckoutSaveCardFormValidator extends SaveCardFormValidator {
    use CheckoutFormValidator;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateCheckoutFields( $bindingResult, $formModel );
    }
}
