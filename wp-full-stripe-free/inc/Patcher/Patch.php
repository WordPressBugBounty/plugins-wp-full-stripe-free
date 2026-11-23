<?php

namespace ThemeIsle\WPFullPay\Patcher;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;

abstract class Patch {
    use LoggerAddOn;
    use StaticContextAddOn;

    /* @var $id string */
    protected $id;
    /* @var $plugin_version string */
    protected $plugin_version;
    /* @var $description string */
    protected $description;
    /* @var $repeatable boolean */
    protected $repeatable = false;

    /** @var Options */
    protected $options;

    public function __construct( $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_PATCHER);
        $this->options = new Options();

        $this->initStaticContext();
    }

    /**
     * @return boolean
     */
    public abstract function apply();

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPluginVersion() {
        return $this->plugin_version;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * @return boolean
     */
    public function isRepeatable() {
        return $this->repeatable;
    }
}
