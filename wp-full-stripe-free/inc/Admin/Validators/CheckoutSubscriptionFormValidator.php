<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

class CheckoutSubscriptionFormValidator extends SubscriptionFormValidator {
    use CheckoutFormValidator;
    use CheckoutPhoneNumberValidatorAddOn;


    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    public function validate($bindingResult, $formModel) {
        parent::validate( $bindingResult, $formModel );
        $this->validateCheckoutFields( $bindingResult, $formModel );

        $this->validateCheckoutCollectPhoneNumber( $bindingResult, $formModel );
    }
}
