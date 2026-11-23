<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;

class EmailOptionsView extends AdminView {
    /** @var Control */
    protected $fromAddress;
    /** @var Control */
    protected $fromAddressCustom;
    /** @var Control */
    protected $sendCopyToAdmin;
    /** @var Control */
    protected $sendCopyToList;
    /** @var Control */
    protected $sendCopyToListHidden;

    /**
     * @param $name
     * @param $email
     *
     * @return string
     */
    protected function createSiteAdminLabel( $name, $email ) {
        return sprintf(
            /* translators: %s: sender name and email address of the website admin */
            __( 'Website Admin: %s', 'wp-full-stripe-free' ),
            Mailer::generateSenderStringFromNameAndEmail( $name, $email )
        );
    }

    /**
     * EmailOptionsView constructor.
     */
    public function __construct( $siteAdminName, $siteAdminEmail ) {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_EMAIL_OPTIONS;
        parent::__construct();

        $this->action->setValue( EmailOptionsViewConstants::FIELD_ACTION_VALUE_SAVE_EMAIL_OPTIONS );

        $this->fromAddress = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS, null, null,
            /* translators: Form field label for the 'Email From address' option of the form */
            __( "Email 'From' address", 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS, null, null,
            $this->createSiteAdminLabel( $siteAdminName, $siteAdminEmail ), $optionIndex );
        $option->setValue( EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_ADMIN );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input js-email-from-address'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS, null, null,
            /* translators: Option chosen when the administrator enters a custom email 'From' address */
            __( 'Custom email address', 'wp-full-stripe-free' ), $optionIndex );
        $option->setValue( EmailOptionsViewConstants::FIELD_VALUE_FROM_ADDRESS_CUSTOM );
        $optionAttributes = [
            'type'      => 'radio',
            'class'     => 'wpfs-form-check-input js-email-from-address'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->fromAddress->setOptions( $options );

        $this->fromAddressCustom = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS_CUSTOM, __( 'Enter email address', 'wp-full-stripe-free' ), null,
            null, null );
        $this->fromAddressCustom->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-form-control wpfs-from-address-custom-js'
        ]);

        $this->sendCopyToAdmin = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_ADMIN, null, null,
            $this->createSiteAdminLabel( $siteAdminName, $siteAdminEmail ), null );
        $this->sendCopyToAdmin->setValue( $siteAdminEmail );
        $this->sendCopyToAdmin->setAttributes( [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ]);

        $this->sendCopyToList = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST, __( 'Add more email addresses here', 'wp-full-stripe-free' ), null,
            null, null );
        $this->sendCopyToList->setAttributes( [
            'type'      => 'text',
            'class'     => 'wpfs-tags-input'
        ]);

        $this->sendCopyToListHidden = ControlUtils::createControl( $this->formHash, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST_HIDDEN, null, null,
            null, null );
        $this->sendCopyToListHidden->setAttributes( [
            'type'      => 'hidden'
        ]);

    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS          => ControlUtils::input( EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS ),
            EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS_CUSTOM   => ControlUtils::input( EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS_CUSTOM ),
            EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_ADMIN    => ControlUtils::input( EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_ADMIN ),
            EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST     => ControlUtils::inputTags( EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = EmailOptionsViewConstants::FIELD_ACTION_VALUE_SAVE_EMAIL_OPTIONS;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_EMAIL_OPTIONS;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function fromAddress(): Control {
        return $this->fromAddress;
    }

    /**
     * @return Control
     */
    public function fromAddressCustom(): Control {
        return $this->fromAddressCustom;
    }

    /**
     * @return Control
     */
    public function sendCopyToAdmin(): Control {
        return $this->sendCopyToAdmin;
    }

    /**
     * @return Control
     */
    public function sendCopyToList(): Control {
        return $this->sendCopyToList;
    }

    /**
     * @return Control
     */
    public function sendCopyToListHidden(): Control {
        return $this->sendCopyToListHidden;
    }
}
