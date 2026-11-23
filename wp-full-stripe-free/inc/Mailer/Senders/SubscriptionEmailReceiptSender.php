<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\Main;

class SubscriptionEmailReceiptSender extends ReceiptSender {
    const TEMPLATE_TYPE = MailerTask::TEMPLATE_TYPE_SUBSCRIPTION_RECEIPT;
    const TEMPLATE_ID = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT;
    const MACRO_REPLACER = SubscriptionMacroReplacer::class;
}
