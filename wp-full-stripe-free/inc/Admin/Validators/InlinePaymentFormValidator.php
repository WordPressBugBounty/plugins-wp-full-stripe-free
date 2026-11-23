<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

class InlinePaymentFormValidator extends PaymentFormValidator {
    use InlineFormValidator;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateInlineFields( $bindingResult, $formModel );
    }
}
