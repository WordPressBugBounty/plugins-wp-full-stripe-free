<?php

namespace ThemeIsle\WPFullPay\Api;

use ThemeIsle\WPFullPay\Logging\Logger;

class PrivateApiV2 extends PrivateApiV1 {
    protected function __construct () {
        parent::__construct();
    }

    /**
     * @return PrivateApiV2
     */
    protected static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new PrivateApiV2();
        }

        return self::$instance;
    }

    /**
     * @param $module
     * @param $class
     * @return Logger
     */
    protected static function createLogger( $module, $class ) : Logger {
        return self::getInstance()->loggerService->createLogger( $module, $class );
    }

    public static function logError( $module, $class, $function, $message, $exception = null ) {
        self::createLogger( $module, $class )->error( $function, $message, $exception );
    }

    public static function logWarning( $module, $class, $function, $message ) {
        self::createLogger( $module, $class )->warning( $function, $message );
    }

    public static function logInfo( $module, $class, $function, $message ) {
        self::createLogger( $module, $class )->info( $function, $message );
    }

    public static function logDebug( $module, $class, $function, $message ) {
        self::createLogger( $module, $class )->debug( $function, $message );
    }
}
