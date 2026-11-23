<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

interface ConfigureStripeAccountViewConstants {

    const FIELD_API_TEST_SECRET_KEY = 'wpfs-api-test-secret-key';
    const FIELD_API_TEST_PUBLISHABLE_KEY = 'wpfs-api-test-publishable-key';
    const FIELD_API_LIVE_SECRET_KEY = 'wpfs-api-live-secret-key';
    const FIELD_API_LIVE_PUBLISHABLE_KEY = 'wpfs-api-live-publishable-key';
    const FIELD_API_MODE = 'wpfs-api-mode';
    const FIELD_API_TEST_ACCOUNT_ID = 'wpfs-api-test-account-id';
    const FIELD_API_LIVE_ACCOUNT_ID = 'wpfs-api-live-account-id';
    const CONNECT_ACCOUNT_ONBOARDING = 'wpfs-connect-account-onboarding';
    const FIELD_USE_WP_TEST_PLATFORM = 'wpfs-use-wp-test-platform';
    const FIELD_USE_WP_LIVE_PLATFORM = 'wpfs-use-wp-live-platform';

    const FIELD_ACTION_VALUE_SAVE_STRIPE_ACCOUNT = 'wpfs-save-stripe-account';
}
