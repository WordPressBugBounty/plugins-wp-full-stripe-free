<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\Main;

class OneTimePaymentReceiptSender extends ReceiptSender {
    const TEMPLATE_TYPE = MailerTask::TEMPLATE_TYPE_PAYMENT_RECEIPT;
    const TEMPLATE_ID = Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT;
    const MACRO_REPLACER = OneTimePaymentMacroReplacer::class;
}
