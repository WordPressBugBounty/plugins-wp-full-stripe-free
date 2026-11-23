<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\Main;

class SaveCardNotificationSender extends ReceiptSender {
    const TEMPLATE_TYPE = MailerTask::TEMPLATE_TYPE_CARD_SAVED;
    const TEMPLATE_ID = Main::EMAIL_TEMPLATE_ID_CARD_SAVED;
    const MACRO_REPLACER = SaveCardMacroReplacer::class;
}
