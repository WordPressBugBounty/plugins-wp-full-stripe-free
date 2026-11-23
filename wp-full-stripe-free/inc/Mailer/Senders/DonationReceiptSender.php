<?php

namespace ThemeIsle\WPFullPay\Mailer\Senders;

use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\MailerTask;
use ThemeIsle\WPFullPay\Main;

class DonationReceiptSender extends ReceiptSender {
    const TEMPLATE_TYPE = MailerTask::TEMPLATE_TYPE_DONATION_RECEIPT;
    const TEMPLATE_ID = Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT;
    const MACRO_REPLACER = DonationMacroReplacer::class;
}
