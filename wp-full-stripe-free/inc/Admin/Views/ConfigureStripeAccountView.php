<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Forms\Views\Control;
use ThemeIsle\WPFullPay\Forms\Views\ControlUtils;
use ThemeIsle\WPFullPay\Main;

class ConfigureStripeAccountView extends AdminView {
    /** @var Control */
    protected $testSecretKey;
    /** @var Control */
    protected $testPublishableKey;
    /** @var Control */
    protected $liveSecretKey;
    /** @var Control */
    protected $livePublishableKey;
    /** @var Control */
    protected $apiMode;
    /** @var Control */
    protected $connectAccountOnboarding;
    /** @var Control */
    protected $testAccountId;
    /** @var Control */
    protected $liveAccountId;

    /**
     * ConfigureStripeAccountView constructor.
     */
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_SAVE_STRIPE_ACCOUNT;

        parent::__construct();

        $this->action->setValue( ConfigureStripeAccountViewConstants::FIELD_ACTION_VALUE_SAVE_STRIPE_ACCOUNT );

        $this->testSecretKey = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_TEST_SECRET_KEY, null, null,
            /* translators: Form field label for API test secret key   */
            __( 'Secret key', 'wp-full-stripe-free' ), null );
        $this->testPublishableKey = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_TEST_PUBLISHABLE_KEY, null, null,
            /* translators: Form field label for API test publishable key   */
            __( 'Publishable key', 'wp-full-stripe-free' ), null );

        $this->liveSecretKey = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_LIVE_SECRET_KEY, null, null,
            /* translators: Form field label for API live secret key   */
            __( 'Secret key', 'wp-full-stripe-free' ), null );
        $this->livePublishableKey = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_LIVE_PUBLISHABLE_KEY, null, null,
            /* translators: Form field label for API live publishable key   */
            __( 'Publishable key', 'wp-full-stripe-free' ), null );

        $this->apiMode = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_MODE, null, null,
            /* translators: Label for a toggle which controls the Stripe API mode (test or live)  */
            __( 'Stripe API mode', 'wp-full-stripe-free' ), null );
        $this->testAccountId = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_TEST_ACCOUNT_ID, null, null,
            /* translators: Form field label for API test account ID   */
            __( 'Account ID', 'wp-full-stripe-free' ), null );
        $this->liveAccountId = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::FIELD_API_LIVE_ACCOUNT_ID, null, null,
            /* translators: Form field label for API live account ID   */
            __( 'Account ID', 'wp-full-stripe-free' ), null );

        $this->connectAccountOnboarding = ControlUtils::createControl( $this->formHash, ConfigureStripeAccountViewConstants::CONNECT_ACCOUNT_ONBOARDING, null, null,
            __( 'Connect Account Onboarding', 'wp-full-stripe-free'), null );
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
            ConfigureStripeAccountViewConstants::FIELD_API_TEST_SECRET_KEY        => ControlUtils::input( ConfigureStripeAccountViewConstants::FIELD_API_TEST_SECRET_KEY ),
            ConfigureStripeAccountViewConstants::FIELD_API_TEST_PUBLISHABLE_KEY   => ControlUtils::input( ConfigureStripeAccountViewConstants::FIELD_API_TEST_PUBLISHABLE_KEY ),
            ConfigureStripeAccountViewConstants::FIELD_API_LIVE_SECRET_KEY        => ControlUtils::input( ConfigureStripeAccountViewConstants::FIELD_API_LIVE_SECRET_KEY ),
            ConfigureStripeAccountViewConstants::FIELD_API_LIVE_PUBLISHABLE_KEY   => ControlUtils::input( ConfigureStripeAccountViewConstants::FIELD_API_LIVE_PUBLISHABLE_KEY ),
            ConfigureStripeAccountViewConstants::FIELD_API_MODE                   => ControlUtils::checkbox( ConfigureStripeAccountViewConstants::FIELD_API_MODE ),
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = ConfigureStripeAccountViewConstants::FIELD_ACTION_VALUE_SAVE_STRIPE_ACCOUNT;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_CONFIGURE_STRIPE_ACCOUNT;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }

    /**
     * @return Control
     */
    public function testSecretKey(): Control {
        return $this->testSecretKey;
    }

    /**
     * @return Control
     */
    public function testPublishableKey(): Control {
        return $this->testPublishableKey;
    }

    /**
     * @return Control
     */
    public function liveSecretKey(): Control {
        return $this->liveSecretKey;
    }

    /**
     * @return Control
     */
    public function livePublishableKey(): Control {
        return $this->livePublishableKey;
    }

    /**
     * @return Control
     */
    public function apiMode(): Control {
        return $this->apiMode;
    }

    /**
     * @return Control
     */
    public function connectAccountOnboarding(): Control {
        return $this->connectAccountOnboarding;
    }

    /**
     * @return Control
     */
    public function testAccountId(): Control {
        return $this->testAccountId;
    }

    /**
     * @return Control
     */
    public function liveAccountId(): Control {
        return $this->liveAccountId;
    }
}
