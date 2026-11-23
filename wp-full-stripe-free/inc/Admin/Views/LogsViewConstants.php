<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

interface LogsViewConstants {
    const FIELD_FORM_LOG_LEVEL    = 'wpfs-logs-log-level';
    const FIELD_FORM_LOG_CHANNEL    = 'wpfs-logs-log-channel';
    const FIELD_FORM_BEHAVIOR = 'wpfs-logs-behavior';

    const FIELD_FORM_LOG_TO_WORDPRESS_DATABASE    = 'wpfs-log-to-wordpress-database';
    const FIELD_FORM_LOG_TO_WEB_SERVER    = 'wpfs-log-to-web-server';
    const FIELD_FORM_CATCH_UNCAUGHT_ERRORS    = 'wpfs-catch-uncaught-errors';

    const FIELD_ACTION_VALUE_SAVE_LOGS = 'wpfs-save-logs';
    const FIELD_ACTION_VALUE_EMPTY_LOGS = 'wpfs-empty-logs';
}
