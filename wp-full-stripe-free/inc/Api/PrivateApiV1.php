<?php

namespace ThemeIsle\WPFullPay\Api;

use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Utils;

class PrivateApiV1 {
    use LoggerAddOn;
    use StaticContextAddOn;

    protected static $instance;

    /** @var Options */
    protected $options;

    protected function __construct() {
        $this->options          = new Options();
        $this->loggerService    = new LoggerService( $this->options->get( Options::OPTION_LOG_LEVEL ), $this->options->get( Options::OPTION_LOG_TO_WEB_SERVER ) == 1 );
        $this->initLogger( $this->loggerService, LoggerService::MODULE_API );

        $this->initStaticContext();
    }

    /**
     * @return PrivateApiV1
     */
    protected static function getInstance() {
        if ( self::$instance === null ) {
            self::$instance = new PrivateApiV1();
        }

        return self::$instance;
    }

    /**
     * @param $message
     * @return void
     */
    static public function log( $message ) {
        Utils::log( $message );
    }

    /**
     * @param $exception
     * @return void
     */
    static public function logException($exception, $object = null ) {
        Utils::logException( $exception, $object );
    }

    /**
     * @param $slug
     *
     * @return string
     */
    static public function getAdminUrlBySlug( $slug ) : string {
        return Menu::getAdminUrlBySlug( $slug );
    }

    /**
     * @param $slug
     * @param $params
     *
     * @return string
     */
    static public function getAdminUrlBySlugAndParams( $slug, $params = [] ) : string {
        return Menu::getAdminUrlBySlugAndParams( $slug, $params );
    }

    /**
     * @param $currency
     * @param $amount
     * @param $interval
     * @param $intervalCount
     *
     * @return string
     * @throws \Exception
     */
    static public function formatPriceWithAdminSettings( $currency, $amount, $interval, $intervalCount ) {
        $formattedAmount = Currencies::formatAndEscapeByAdmin(
            self::getInstance()->staticContext,
            $currency,
            $amount,
            true,
            true );

        return Localization::getPriceAndIntervalLabel( $interval, $intervalCount, $formattedAmount );
    }

    /**
     * @return string
     */
    public static function getWordpressTimezone() {
        return wp_timezone_string();
    }

    /**
     * @param $timestamp
     *
     * @return string
     */
    public static function formatTimestampWithWordpressDateFormat( $timestamp ) {
        return Utils::formatTimestampWithWordpressDateFormat( $timestamp );
    }

    /**
     * @param $timestamp
     *
     * @return string
     */
    public static function formatTimestampWithWordpressDateTimeFormat( $timestamp ) {
        return Utils::formatTimestampWithWordpressDateTimeFormat( $timestamp );
    }

    /**
     * @param int $liveMode
     *
     * @return string
     */
    public static function getApiModeLabelFromInteger( $liveMode ) : string {
        return Admin::getApiModeLabelFromInteger( $liveMode );
    }

    /**
     * @param $assetName
     *
     * @return string
     */
    public static function getScriptAssetUrl( $assetName ) {
        return Assets::scripts( $assetName );
    }

    /**
     * @param $bindingResult
     * @return array
     */
    public static function generateReturnValueFromBindings( $bindingResult ) {
        return Utils::generateReturnValueFromBindings( $bindingResult );
    }

    /**
     * @param $elementId
     * @param $formHash
     * @param $index
     * @return string|void|null
     */
    public static function generateFormElementId( $elementId, $formHash, $index = null ) {
        return Utils::generateFormElementId( $elementId, $formHash, $index );
    }
}
