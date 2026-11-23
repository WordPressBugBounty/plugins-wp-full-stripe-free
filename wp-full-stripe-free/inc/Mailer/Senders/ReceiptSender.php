<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Macros\FormMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\TransactionData\FormTransactionData;

/**
 * A receipt sent with the global template TEMPLATE_ID, or with the form's own content for it.
 */
abstract class ReceiptSender extends MailerTask {
    /** A MailerTask::TEMPLATE_TYPE_* value, passed to the email filters as 'template'. */
    const TEMPLATE_TYPE = '';
    /** A Main::EMAIL_TEMPLATE_ID_* value, which is also the template's key in the global templates. */
    const TEMPLATE_ID = '';
    /** @var class-string<FormMacroReplacer> The macro replacer for the receipt's transaction data. */
    const MACRO_REPLACER = FormMacroReplacer::class;

    /**
     * @param FormTransactionData $transactionData
     * @param LoggerService       $loggerService
     * @param object|null         $form The form's database row.
     */
    public function __construct( $transactionData, $loggerService, $form = null ) {
        parent::__construct( $transactionData, $loggerService, $form );

        $this->template = static::TEMPLATE_TYPE;
        $this->formName = $transactionData->getFormName();
    }

    /**
     * @return FormMacroReplacer
     */
    protected function getMacroReplacer() {
        $replacer = static::MACRO_REPLACER;

        return new $replacer( $this->form, $this->transactionData, $this->loggerService );
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function getSubjectAndMessage() {
        $template = $this->getEmailReceipts()->{static::TEMPLATE_ID};

        return $this->resolveSubjectAndMessage( static::TEMPLATE_ID, $template->subject, $template->html );
    }
}
