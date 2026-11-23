<?php

namespace ThemeIsle\WPFullPay\Mailer;

use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Macros\MacroReplacerAddOn;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\TransactionData\TransactionData;

abstract class MailerTask {
    use LoggerAddOn;
    use StaticContextAddOn;

    const TEMPLATE_TYPE_PAYMENT_RECEIPT                     = "PaymentReceipt";
    const TEMPLATE_TYPE_DONATION_RECEIPT                    = "DonationReceipt";
    const TEMPLATE_TYPE_SUBSCRIPTION_RECEIPT                = "SubscriptionReceipt";
    const TEMPLATE_TYPE_SUBSCRIPTION_ENDED                  = "SubscriptionEnded";
    const TEMPLATE_TYPE_CARD_SAVED                          = "CardSaved";
    const TEMPLATE_TYPE_MANAGE_SUBSCRIPTIONS_SECURITY_CODE  = "ManageSubscriptionsSecurityCode";

    protected $template;
    protected $form;
    /**
     * @var TransactionData $transactionData
     */
    protected $transactionData;

    /** @var Options */
    protected $options;
    protected $formName;

    public function __construct( $transactionData, $loggerService, $form = null ) {
        $this->options          = new Options();
        $this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );

        $this->initStaticContext();

        $this->transactionData  = $transactionData;
        $this->form             = $form;
    }

    protected abstract function getSubjectAndMessage();
    protected abstract function getMacroReplacer();

    public final function sendEmail( ) {
        list( $subject, $message ) = $this->getSubjectAndMessage();

        // A macro replacer built on MacroReplacerAddOn.
        $replacer = $this->getMacroReplacer();

        $subjectParams = [
            'template'                  => $this->template,
            'formName'                  => $this->formName,
            'rawPlaceholders'           => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders'     => $replacer->getDecoratedKeyValuePairs(),
        ];
        $subject = $replacer->replaceMacrosWithHtmlEscape(
            do_shortcode( apply_filters( Main::FILTER_NAME_MODIFY_EMAIL_SUBJECT, $subject, $subjectParams ) )
        );

        $messageParams = [
            'template'                  => $this->template,
            'formName'                  => $this->formName,
            'rawPlaceholders'           => $replacer->getRawKeyValuePairs(),
            'decoratedPlaceholders'     => $replacer->getDecoratedKeyValuePairs(),
        ];
        $message = $replacer->replaceMacrosWithHtmlEscape(
            do_shortcode( apply_filters( Main::FILTER_NAME_MODIFY_EMAIL_MESSAGE, $message, $messageParams ) )
        );

        $this->sendEmailViaWordpress( $this->transactionData->getCustomerEmail(), $subject, $message );

    }

    private function sendEmailViaWordpress($email, $subject, $message ) {
        $senderName = html_entity_decode( get_bloginfo( 'name' ) );
        $senderEmail = $this->options->get( Options::OPTION_EMAIL_NOTIFICATION_SENDER_ADDRESS );

        $headers[] = "Content-type: text/html";
        $headers[] = 'From: ' . Mailer::generateSenderStringFromNameAndEmail( $senderName, $senderEmail );

        $bccEmails = json_decode( $this->options->get( Options::OPTION_EMAIL_NOTIFICATION_BCC_ADDRESSES ));
        foreach ( $bccEmails as $bccEmail ) {
            $headers[] = 'Bcc: <' . $bccEmail . '>';
        }

        wp_mail( $email, $subject, $message, apply_filters( 'fullstripe_email_headers_filter', $headers ));
    }

    protected function getEmailReceipts(){
        $emailReceipts = json_decode( $this->options->get( Options::OPTION_EMAIL_TEMPLATES ));

        return $emailReceipts;
    }

    /**
     * Resolves the subject/message preferring the per-form custom template content (when configured)
     * over the supplied global template content.
     *
     * @param string $emailTemplateId
     * @param mixed $globalSubject
     * @param mixed $globalBody
     * @return array{0: string, 1: string} [ $subject, $body ]
     */
    protected function resolveSubjectAndMessage( $emailTemplateId, $globalSubject, $globalBody ) {
        return Mailer::resolveTemplateContent( $this->form, $emailTemplateId, $globalSubject, $globalBody );
    }
}
