<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

class InlineSubscriptionFormValidator extends SubscriptionFormValidator {
    use InlineFormValidator;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateInlineFields( $bindingResult, $formModel );
    }
}
