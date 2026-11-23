<?php

namespace ThemeIsle\WPFullPay\Admin\Validators;

use ThemeIsle\WPFullPay\Admin\Models\EmailOptionsModel;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\EmailOptionsViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Validators\Validator;
use ThemeIsle\WPFullPay\Utils;

class EmailOptionsValidator extends Validator {
    public function __construct( $loggerService ) {
        parent::__construct( $loggerService );
    }

    /**
     * @param BindingResult $bindingResult
     * @param EmailOptionsModel $formModelObject
     */
    public function validate( $bindingResult, $formModelObject ) {
        $fromAddressValues = [
            EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_ADMIN,
            EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_CUSTOM
        ];

        if ( false === array_search( $formModelObject->getFromAddress(), $fromAddressValues ) ) {
            $error =
                /* translators: Validation error message displayed if it's not selected whether the "From" address of emails sent by the plugin should be the site admin or somebody else */
                __( 'Please select whether the From address should be the site admin or somebody else.', 'wp-full-stripe-free' );
            $bindingResult->addGlobalError( $error );
        }

        if ( $formModelObject->getFromAddress() === EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_CUSTOM ) {
            $fromAddressCustomFieldName  = EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS_CUSTOM;
            $fromAddressCustomFieldId    = Utils::generateFormElementId( $fromAddressCustomFieldName, AdminViewConstants::FORM_HASH_ADMIN_EMAIL_OPTIONS );

            if ( empty( $formModelObject->getFromAddressCustom() )) {
                $bindingResult->addFieldError( $fromAddressCustomFieldName, $fromAddressCustomFieldId,
                    /* translators: Email options error when no custom email sender address is provided */
                    __( 'Please enter a sender address', 'wp-full-stripe-free' ) );
            } elseif ( !filter_var( $formModelObject->getFromAddressCustom(), FILTER_VALIDATE_EMAIL) ) {
                $bindingResult->addFieldError( $fromAddressCustomFieldName, $fromAddressCustomFieldId,
                    /* translators: Email options error when no custom email sender address is provided */
                    __( 'Please enter a valid sender address', 'wp-full-stripe-free' ) );
            }
        }

        if ( !empty( $formModelObject->getSendCopyToAdmin() ) &&
            !filter_var( $formModelObject->getSendCopyToAdmin(), FILTER_VALIDATE_EMAIL ) ) {
            $copyToAdminFieldName  = EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_ADMIN;
            $copyToAdminFieldId    = Utils::generateFormElementId( $copyToAdminFieldName, AdminViewConstants::FORM_HASH_ADMIN_EMAIL_OPTIONS );

            $bindingResult->addFieldError( $copyToAdminFieldName, $copyToAdminFieldId,
                /* translators: Email options error when no custom email sender address is provided
                 * p1: The invalid email address
                 */
                sprintf( __( "The email address '%s' is invalid", 'wp-full-stripe-free' ), $formModelObject->getSendCopyToAdmin() ) );
        }

        foreach( $formModelObject->getSendCopyToList() as $email ) {
            if ( !filter_var( $email, FILTER_VALIDATE_EMAIL) ) {
                $copyToListFieldName  = EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST;
                $copyToListFieldId    = Utils::generateFormElementId( $copyToListFieldName, AdminViewConstants::FORM_HASH_ADMIN_EMAIL_OPTIONS );

                $bindingResult->addFieldError( $copyToListFieldName, $copyToListFieldId,
                    sprintf(
                    /* translators: Email options error when an invalid email sender address is provided */
                        __( "The email address '%s' is invalid", 'wp-full-stripe-free' ), $email ) );

                break;
            }
        }
    }
}
