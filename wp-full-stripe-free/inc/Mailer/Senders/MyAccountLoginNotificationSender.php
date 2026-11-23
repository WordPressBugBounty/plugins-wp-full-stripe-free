<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\MyAccountLoginMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\TransactionData\MyAccountLoginTransactionData;

class MyAccountLoginNotificationSender extends MailerTask {
    // $transactionData holds a MyAccountLoginTransactionData in this sender.

    public function __construct( $transactionData, $loggerService, $form = null ) {
        parent::__construct( $transactionData, $loggerService, $form );

        $this->template = MailerTask::TEMPLATE_TYPE_MANAGE_SUBSCRIPTIONS_SECURITY_CODE;
        $this->formName = null;
    }

    protected function getMacroReplacer() {
        return new MyAccountLoginMacroReplacer( $this->transactionData );
    }

    protected function getSubjectAndMessage() {
        $emailReceipts = $this->getEmailReceipts();

        return [
            $emailReceipts->cardUpdateConfirmationRequest->subject,
            $emailReceipts->cardUpdateConfirmationRequest->html
        ];
    }
}
