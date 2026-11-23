<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\CreateFormModel;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\CreateFormViewConstants;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class CreateFormValidator extends Validator {

    /** @var Database */
    private $db = null;

    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );

        $this->db = new Database();
    }

    /**
     * @param BindingResult $bindingResult
     * @param CreateFormModel $formModelObject
     */
    public function validate($bindingResult, $formModelObject) {
        if ($formModelObject instanceof CreateFormModel) {
            $displayNameFieldName  = CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME;
            $displayNameFieldId    = Utils::generateFormElementId( $displayNameFieldName, AdminViewConstants::FORM_HASH_ADMIN_CREATE_FORM);

            if ( empty( $formModelObject->getDisplayName() )) {
                $bindingResult->addFieldError( $displayNameFieldName, $displayNameFieldId,
                    /* translators: Create form validation error when no display name specified */
                    __( "Please enter a display name", 'wp-full-stripe-free' ) );
            } elseif ( ! preg_match('/^[^\s]{1,}.{0,}$/', $formModelObject->getDisplayName() ) ) {
                $bindingResult->addFieldError( $displayNameFieldName, $displayNameFieldId,
                    /* translators: Create form validation error when the display name contains invalid characters */
                    __( 'The display name may contain any characters but it shouldn\'t start with a space.', 'wp-full-stripe-free' ) );
            }

            $nameFieldName  = CreateFormViewConstants::FIELD_FORM_NAME;
            $nameFieldId    = Utils::generateFormElementId( $nameFieldName, AdminViewConstants::FORM_HASH_ADMIN_CREATE_FORM);

            if ( empty( $formModelObject->getName() )) {
                $bindingResult->addFieldError( $nameFieldName, $nameFieldId,
                    /* translators: Create form validation error when no identifier is specified  */
                    __( "Please enter an identifier", 'wp-full-stripe-free' ) );
            } elseif ( ! preg_match('/^[\w\-]+$/', $formModelObject->getName() ) ) {
                $bindingResult->addFieldError( $nameFieldName, $nameFieldId,
                    /* translators: Create form validation error when the identifier contains invalid characters */
                    __( 'The identifier may contain only alphanumeric characters, dashes, and underscores.', 'wp-full-stripe-free' ) );
            } elseif ( $this->isFormNameAlreadyUsed( $formModelObject ) ) {
                $bindingResult->addFieldError( $nameFieldName, $nameFieldId,
                    /* translators: Create form validation error when a form with the specified identifier already exists */
                    __( 'This identifier is already in use, choose a different one.', 'wp-full-stripe-free' ) );
            }

            $formTypes = [
                Main::FORM_TYPE_PAYMENT,
                Main::FORM_TYPE_SUBSCRIPTION,
                Main::FORM_TYPE_DONATION,
                Main::FORM_TYPE_SAVE_CARD ];
            if ( false === array_search( $formModelObject->getType(), $formTypes ) ) {
                $error =
                    /* translators: Create form validation error message when no form type is selected */
                    __( 'Please select a form type', 'wp-full-stripe-free' );

                $bindingResult->addGlobalError( $error );
            }

            $formLayouts = [
                Main::FORM_LAYOUT_INLINE,
                Main::FORM_LAYOUT_CHECKOUT
            ];
            if ( false === array_search( $formModelObject->getLayout(), $formLayouts ) ) {
                $error =
                    /* translators: Create form validation error message when no form layout is selected */
                    __( 'Please select a form layout', 'wp-full-stripe-free' );

                $bindingResult->addGlobalError( $error );
            }
        }
    }

    /**
     * @param CreateFormModel $formModelObject
     *
     * @return boolean
     */
    private function isFormNameAlreadyUsed( $formModelObject ) {
        return count( $this->db->getFormIdsByName( $formModelObject->getName() )) > 0;
    }
}
