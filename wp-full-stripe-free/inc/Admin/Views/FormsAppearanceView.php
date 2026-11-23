<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class FormsAppearanceView extends AdminView {
    /** @var Control */
    protected $customCss;
    /** @var Control */
    protected $customCssHidden;

    /**
     * EmailTemplatesView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_FORMS_APPEARANCE;
        parent::__construct();

        $this->action->setValue( FormsAppearanceViewConstants::FIELD_ACTION_VALUE_SAVE_FORMS_APPEARANCE );

        $this->customCss = ControlUtils::createControl( $this->formHash, FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS, null, null,
            null, null );
        $this->customCss->setAttributes( [
            'class' => 'wpfs-code-editor js-code-editor',
        ]);

        $this->customCssHidden = ControlUtils::createControl( $this->formHash, FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS_HIDDEN, null, null,
            null, null );
        $this->customCssHidden->setAttributes( [
            'type'      => 'hidden',
            'class'     => 'wpfs-custom-css-hidden'
        ]);
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS        => ControlUtils::input( FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS ),
            FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS_HIDDEN => ControlUtils::input( FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS_HIDDEN )
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = FormsAppearanceViewConstants::FIELD_ACTION_VALUE_SAVE_FORMS_APPEARANCE;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_FORMS_APPEARANCE;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function customCss(): Control {
        return $this->customCss;
    }

    /**
     * @return Control
     */
    public function customCssHidden(): Control {
        return $this->customCssHidden;
    }
}
