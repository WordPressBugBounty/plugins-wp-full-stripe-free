<?php

namespace ThemeIsle\WPFullPay\ThankYou;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

class ThankYouPostProcessor {
    use LoggerAddOn;
    use StaticContextAddOn;

    /* @var $database Database */
    protected $database;
    /* @var $form array */
    protected $form;
    /* @var $transactionData FormTransactionData */
    protected $transactionData;
    /* @var $replacer FormMacroReplacer */
    protected $replacer;

    protected $options;

    /** @var Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD */
    protected $formType;

    /**
     * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $formType The kind of form the transaction came from.
     * @param class-string $replacerClass The macro replacer for the transaction data.
     */
    public function __construct( $database, $transactionData, $loggerService, string $formType, string $replacerClass ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
        $this->options = new Options();

        $this->initStaticContext();

        $this->database         = $database;
        $this->transactionData = $transactionData;
        $this->formType        = $formType;

        $this->form = $this->getFormByName( $this->transactionData->getFormName() );
        $this->replacer = new $replacerClass( $this->form, $transactionData, $this->loggerService );
    }

    /**
     * Returns the form of the transaction's kind with the given name.
     *
     * @param $formName
     *
     * @return mixed|null
     */
    function getFormByName( $formName ) {
        foreach ( FormTypes::ofType( $this->formType ) as $formType ) {
            $form = $this->database->getFormByName( $formType->formType, $formName );
            if ( ! is_null( $form ) ) {
                return $form;
            }
        }

        return null;
    }

    public function process( $content) {
        $params = [
            'formType'                => $this->getFormType(),
            'rawPlaceholders'         => $this->replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders'   => $this->replacer->getDecoratedKeyValuePairs()
        ];

        $content = apply_filters( 'fullstripe_thank_you_output', $content, $params );
        $result = $this->replacer->replaceMacrosWithHtmlEscape( $content );

        return $result;
    }

    protected function getFormType() {
        return $this->formType;
    }
}
