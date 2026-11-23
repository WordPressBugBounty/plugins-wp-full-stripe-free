<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\FormsAppearanceModel;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;

class FormsAppearanceValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param FormsAppearanceModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
    }
}
