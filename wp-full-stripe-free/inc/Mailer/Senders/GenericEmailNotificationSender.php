<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\GenericMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;

class GenericEmailNotificationSender extends MailerTask {

    protected $decoratedKeyValuePairs;

    public function setTemplateType( $templateType ) {
        $this->template = $templateType;
    }

    public function setDecoratedKeyValuePairs( $keyValuePairs ) {
        $this->decoratedKeyValuePairs = $keyValuePairs;
    }

    protected function getSubjectAndMessage() {
        $emailReceipts = $this->getEmailReceipts();

        return [
            $emailReceipts->{$this->template}->subject,
            $emailReceipts->{$this->template}->html
        ];
    }

    protected function getMacroReplacer() {
        return new GenericMacroReplacer( $this->decoratedKeyValuePairs );
    }
}
