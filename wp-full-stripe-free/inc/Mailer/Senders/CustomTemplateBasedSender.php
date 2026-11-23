<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

trait CustomTemplateBasedSender {
    protected $subject;
    protected $body;

    public function setSubject( $subject ) {
        $this->subject = $subject;
    }

    public function setBody( $body ) {
        $this->body = $body;
    }

    protected function getSubjectAndMessage() {
        return [
            $this->subject,
            $this->body
        ];
    }
}
