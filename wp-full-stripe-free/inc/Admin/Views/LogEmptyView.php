<?php

namespace ThemeIsle\WPFullPay\Admin\Views;

use ThemeIsle\WPFullPay\Main;

class LogEmptyView extends AdminView implements LogsViewConstants {
    public function __construct() {
        $this->formHash = AdminViewConstants::FORM_HASH_ADMIN_LOG_EMPTY;
        parent::__construct();

        $this->action->setValue( LogsViewConstants::FIELD_ACTION_VALUE_EMPTY_LOGS );
    }

    /**
     * @return array
     */
    public static function getFields() {
        $fields = [
        ];

        return array_merge( $fields, parent::getFields() );
    }

    /**
     * @return array
     */
    protected function getFormAttributes() {
        $attributes = [];

        $attributes[ self::ATTR_ID ]                  = LogsViewConstants::FIELD_ACTION_VALUE_EMPTY_LOGS;
        $attributes[ self::ATTR_DATA_WPFS_FORM_TYPE ] = Main::FORM_TYPE_ADMIN_EMPTY_LOG;
        $attributes                                   = array_merge( $attributes, parent::getFormAttributes() );

        return $attributes;
    }
}
