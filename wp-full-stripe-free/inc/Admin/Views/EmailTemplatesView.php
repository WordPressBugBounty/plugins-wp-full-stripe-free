<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class EmailTemplatesView extends AdminView {
    /** @var Control */
    protected $emailTemplatesHidden;

    /**
     * EmailTemplatesView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_EMAIL_TEMPLATES;
        parent::__construct();

        $this->action->setValue( EmailTemplatesViewConstants::FIELD_ACTION_VALUE_SAVE_EMAIL_TEMPLATES );

        $this->emailTemplatesHidden = ControlUtils::createControl( $this->formHash, EmailTemplatesViewConstants::FIELD_EMAIL_TEMPLATES_HIDDEN, null, null,
            null, null );
        $this->emailTemplatesHidden->setAttributes( [
            'type'      => 'hidden',
            'class'     => 'wpfs-email-templates-hidden'
        ]);
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = EmailTemplatesViewConstants::FIELD_ACTION_VALUE_SAVE_EMAIL_TEMPLATES;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_EMAIL_TEMPLATES;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function emailTemplatesHidden(): Control {
        return $this->emailTemplatesHidden;
    }
}
