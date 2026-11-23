<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;

class LogLevelView extends AdminView implements LogsViewConstants {
    /** @var Control */
    protected $logLevel;
    /** @var Control */
    protected $logChannel;
    /** @var Control */
    protected $logBehavior;

    /**
     * SecurityView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_LOG_LEVELS;
        parent::__construct();

        $this->action->setValue( LogsViewConstants::FIELD_ACTION_VALUE_SAVE_LOGS );

        $this->initLogLevel();
        $this->initLogChannels();
        $this->initLogBehavior();
    }

    protected function initLogBehavior() {
        $this->logBehavior = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_BEHAVIOR, null, null,
            __( 'Behavior', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_CATCH_UNCAUGHT_ERRORS, null, null,
            __('Catch all errors not caught by plugins', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->logBehavior->setOptions( $options );
    }

    protected function initLogChannels() {
        $this->logChannel = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOG_CHANNEL, null, null,
            __( 'Logging channels', 'wp-full-stripe-free' ), null );

        $optionIndex = 0;
        $options = [];

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOG_TO_WORDPRESS_DATABASE, null, null,
            __('WordPress database', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input',
            'disabled'  => 'disabled'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );
        $optionIndex++;

        $option = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOG_TO_WEB_SERVER, null, null,
            __('Webserver error log', 'wp-full-stripe-free'), $optionIndex );
        $option->setValue( 1 );
        $optionAttributes = [
            'type'      => 'checkbox',
            'class'     => 'wpfs-form-check-input'
        ];
        $option->setAttributes( $optionAttributes );
        array_push( $options, $option );

        $this->logChannel->setOptions( $options );
    }

    protected function initLogLevel() {
        $this->logLevel = ControlUtils::createControl( $this->formHash, self::FIELD_FORM_LOG_LEVEL, null, null,
            __( 'Logging level', 'wp-full-stripe-free' ), null );

        $this->setRadioOptions( $this->logLevel, [
            [ LoggerService::LEVEL_ERROR,
                LoggerService::localizeLogLevel( LoggerService::LEVEL_ERROR ) ],
            [ LoggerService::LEVEL_WARNING,
                LoggerService::localizeLogLevel( LoggerService::LEVEL_WARNING ) ],
            [ LoggerService::LEVEL_INFO,
                LoggerService::localizeLogLevel( LoggerService::LEVEL_INFO ) ],
            [ LoggerService::LEVEL_DEBUG,
                LoggerService::localizeLogLevel( LoggerService::LEVEL_DEBUG ) ],
        ] );
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            self::FIELD_FORM_LOG_LEVEL => ControlUtils::input( self::FIELD_FORM_LOG_LEVEL ),
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = LogsViewConstants::FIELD_ACTION_VALUE_SAVE_LOGS;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_LOGGING;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function logLevel(): Control {
        return $this->logLevel;
    }

    /**
     * @return Control
     */
    public function logChannel(): Control {
        return $this->logChannel;
    }

    /**
     * @return Control
     */
    public function behavior(): Control {
        return $this->logBehavior;
    }
}
