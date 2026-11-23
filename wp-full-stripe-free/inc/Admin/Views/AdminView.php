<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;

abstract class AdminView implements AdminViewConstants, \ThemeIsle\WPFullPay\Forms\Views\FormViewConstants {

    /** @var Control */
    protected $action;
    /** @var Control */
    protected $formGetParameters;
    /** @var Control */
    protected $submitButton;

    protected $formHash;
    protected $attributes = [];

    /**
     * \ThemeIsle\WPFullPay\Forms\Views\FormView constructor.
     *
     */
    public function __construct() {
        $this->attributes        = $this->getFormAttributes();
        $this->action            = ControlUtils::createControl( $this->formHash, self::FIELD_ACTION, null, null, null, null );
        $this->formGetParameters = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_GET_PARAMETERS, null, null, null, null );
        $this->submitButton      = ControlUtils::createControl( $this->formHash, \ThemeIsle\WPFullPay\Forms\Views\FormViewConstants::BUTTON_SUBMIT, null, __( 'Create and Edit form', 'wp-full-stripe-free' ), null, null );

        $this->action->setAttributes(
            [
                'type' => 'hidden'
            ]
        );
        $this->formGetParameters->setAttributes(
            [
                'type' => 'hidden'
            ]
        );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ACTION ]              = '';
        $attributes[ self::ATTR_METHOD ]              = self::ATTR_METHOD_VALUE_POST;
        $attributes[ self::ATTR_CLASS ]               = self::ATTR_CLASS_VALUE_WPFS_FORM;

        return $attributes;
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            self::FIELD_ACTION                    => ControlUtils::inputHidden( self::FIELD_ACTION ),
        ];

        return $fields;
    }

    /**
     * @return Control
     */
    public function submitButton() {
        return $this->submitButton;
    }

    public function formAttributes() {
        $attributesAsString = Control::attributesAsString( $this->attributes );

        echo( $attributesAsString );
    }

    /**
     * @return Control
     */
    public function action() {
        return $this->action;
    }

    /**
     * @return Control
     */
    public function formGetParameters() {
        return $this->formGetParameters;
    }

    /**
     * Gives a radio group its radios, in order. They share the group's name, and their index
     * makes each id unique.
     *
     * @param list<array{0: string|int, 1: string, 2?: array<string, string>}> $options Each radio's value, label and, optionally, metadata.
     */
    protected function setRadioOptions( Control $group, array $options ): void {
        $radios = [];
        foreach ( $options as $index => $option ) {
            $radio = ControlUtils::createControl( $this->formHash, $group->name( false ), null, null, $option[1], $index );
            $radio->setValue( $option[0] );
            $radio->setAttributes( [
                'type'      => 'radio',
                'class'     => 'wpfs-form-check-input'
            ] );
            if ( isset( $option[2] ) ) {
                $radio->setMetadata( $option[2] );
            }
            $radios[] = $radio;
        }

        $group->setOptions( $radios );
    }

}
