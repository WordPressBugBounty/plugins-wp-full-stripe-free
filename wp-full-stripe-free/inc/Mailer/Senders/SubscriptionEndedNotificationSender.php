<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\Main;

class SubscriptionEndedNotificationSender extends ReceiptSender {
    const TEMPLATE_TYPE = MailerTask::TEMPLATE_TYPE_SUBSCRIPTION_ENDED;
    const TEMPLATE_ID = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED;
    const MACRO_REPLACER = SubscriptionMacroReplacer::class;
}
