<?php

namespace ThemeIsle\WPFullPay\Mailer;

use ThemeIsle\WPFullPay\Context\StaticContext;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Senders\DonationReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\MyAccountLoginNotificationSender;
use ThemeIsle\WPFullPay\Mailer\Senders\OneTimePaymentReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\SaveCardNotificationSender;
use ThemeIsle\WPFullPay\Mailer\Senders\SubscriptionEmailReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\SubscriptionEndedNotificationSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestDonationReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestMyAccountLoginNotificationSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestOneTimePaymentReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestSaveCardNotificationSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestSubscriptionEmailReceiptSender;
use ThemeIsle\WPFullPay\Mailer\Senders\TestSubscriptionEndedNotificationSender;
use ThemeIsle\WPFullPay\Mailer\TestData\TestTransactionDataCreatorFactory;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\FormTransactionData;
use ThemeIsle\WPFullPay\TransactionData\MyAccountLoginTransactionData;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\Utils;

class Mailer {
    use LoggerAddOn;

    public function __construct( $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
    }

    public static function generateSenderStringFromNameAndEmail( $name, $email ) {
        return "{$name} <{$email}>";
    }

    /**
     * @param $form
     * @param $templateType string
     */
    private static function isEmailTemplateEnabled( $form, $templateType ) {
        $isEnabled = false;
        $templates = json_decode( $form->emailTemplates );
        if ( !is_null( $templates )) {
            $isEnabled = $templates->{$templateType}->enabled;
        }

        return $isEnabled;
    }

    /**
     * @param $form
     */
    public static function canSendSaveCardPluginReceipt($form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_CARD_SAVED );
    }

    /**
     * @param $form
     */
    public static function canSendDonationPluginReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT );
    }

    /**
     * @param $form
     */
    public static function canSendDonationStripeReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT_STRIPE );
    }

    /**
     * @param $form
     */
    public static function canSendSubscriptionPluginReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT );
    }

    /**
     * @param $form
     */
    public static function canSendSubscriptionStripeReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT_STRIPE );
    }

    /**
     * @param $form
     */
    public static function canSendSubscriptionEndedPluginNotification( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED );
    }

    /**
     * @param $form
     */
    public static function canSendPaymentPluginReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT );
    }

    /**
     * @param $form
     */
    public static function canSendPaymentStripeReceipt( $form ) {
        return self::isEmailTemplateEnabled( $form, Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT_STRIPE );
    }

    /**
     * @param $formType
     *
     * @return array
     */
    public static function getEmailTemplateDescriptors($formType ) {
        $res = [];

        switch ( $formType ) {
            case Main::FORM_TYPE_INLINE_SAVE_CARD:
            case Main::FORM_TYPE_CHECKOUT_SAVE_CARD:
                $saveCardReceipt = new \StdClass();
                $saveCardReceipt->type              = Main::EMAIL_TEMPLATE_ID_CARD_SAVED;
                $saveCardReceipt->typeLabel         =
                    /* translators: Name of the email template that is used to send an email when a card is saved */
                    __('Card saved', 'wp-full-stripe-free');
                $saveCardReceipt->typeDescription   =
                    /* translators: Description of the 'Card saved' email template */
                    __('The plugin sends this email when a customer submits a save card form.', 'wp-full-stripe-free');
                array_push( $res, $saveCardReceipt );

                break;

            case Main::FORM_TYPE_INLINE_DONATION:
            case Main::FORM_TYPE_CHECKOUT_DONATION:
                $donationReceipt = new \StdClass();
                $donationReceipt->type              = Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT;
                $donationReceipt->typeLabel         =
                    /* translators: Name of the email template that is used to send an email when a donation is made */
                    __('Donation receipt', 'wp-full-stripe-free');
                $donationReceipt->typeDescription   =
                    /* translators: Description of the 'Donation receipt' email template */
                    __('The plugin sends this email when a donor makes a donation.', 'wp-full-stripe-free');
                array_push( $res, $donationReceipt );

                $donationReceiptStripe = new \StdClass();
                $donationReceiptStripe->type              = Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT_STRIPE;
                $donationReceiptStripe->typeLabel         =
                    /* translators: Name of the email template that Stripe sends when a donation is made */
                    __('Donation receipt (Stripe)', 'wp-full-stripe-free');
                $donationReceiptStripe->typeDescription   =
                    /* translators: Description of the 'Donation receipt (Stripe)' email template */
                    __('Stripe sends a payment receipt when a donor makes a donation.', 'wp-full-stripe-free');
                array_push( $res, $donationReceiptStripe );

                break;

            case Main::FORM_TYPE_INLINE_PAYMENT:
            case Main::FORM_TYPE_CHECKOUT_PAYMENT:
                $paymentReceipt = new \StdClass();
                $paymentReceipt->type              = Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT;
                $paymentReceipt->typeLabel         =
                    /* translators: Name of the email template that is used to send an email when a payment is made */
                    __('Payment receipt', 'wp-full-stripe-free');
                $paymentReceipt->typeDescription   =
                    /* translators: Description of the 'Payment receipt' email template */
                    __('The plugin sends this email when a customer makes a one-time payment.', 'wp-full-stripe-free');
                array_push( $res, $paymentReceipt );

                $paymentReceiptStripe = new \StdClass();
                $paymentReceiptStripe->type              = Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT_STRIPE;
                $paymentReceiptStripe->typeLabel         =
                    /* translators: Name of the email template that Stripe sends when a payment is made */
                    __('Payment receipt (Stripe)', 'wp-full-stripe-free');
                $paymentReceiptStripe->typeDescription   =
                    /* translators: Description of the 'Payment receipt (Stripe)' email template */
                    __('Stripe sends this payment receipt when a customer makes a payment.', 'wp-full-stripe-free');
                array_push( $res, $paymentReceiptStripe );

                break;

            case Main::FORM_TYPE_INLINE_SUBSCRIPTION:
            case Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION:
                $subscriptionReceipt = new \StdClass();
                $subscriptionReceipt->type              = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT;
                $subscriptionReceipt->typeLabel         =
                    /* translators: Name of the email template that is used to send an email when a subscription is started */
                    __('Subscription receipt', 'wp-full-stripe-free');
                $subscriptionReceipt->typeDescription   =
                    /* translators: Description of the 'Subscription receipt' email template */
                    __('The plugin sends this email when a customer subscribes to a plan.', 'wp-full-stripe-free');
                array_push( $res, $subscriptionReceipt );

                $subscriptionEndedReceipt = new \StdClass();
                $subscriptionEndedReceipt->type              = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED;
                $subscriptionEndedReceipt->typeLabel         =
                    /* translators: Name of the email template that is used to send an email when the plugin ends a subscription automatically */
                    __('Subscription ended', 'wp-full-stripe-free');
                $subscriptionEndedReceipt->typeDescription   =
                    /* translators: Description of the 'Subscription ended' email template */
                    __('The plugin sends this email when payment-in-installments plan is cancelled automatically.', 'wp-full-stripe-free');
                array_push( $res, $subscriptionEndedReceipt );

                $subscriptionReceiptStripe = new \StdClass();
                $subscriptionReceiptStripe->type              = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT_STRIPE;
                $subscriptionReceiptStripe->typeLabel         =
                    /* translators: Name of the email template that Stripe sends when a subscription is started */
                    __('Subscription receipt (Stripe)', 'wp-full-stripe-free');
                $subscriptionReceiptStripe->typeDescription   =
                    /* translators: Description of the 'Subscription receipt (Stripe)' email template */
                    __('Stripe sends a payment receipt when a customer subscribes to a plan.', 'wp-full-stripe-free');
                array_push( $res, $subscriptionReceiptStripe );

            break;

        }

        return $res;
    }

    /**
     * @param StaticContext $context
     * @param $templateType
     * @return bool
     */
    public static function isTemplateEnabled( $context, $templateType ) {
        // todo: This option is not used anymore, remove all traces of it
        $sendPluginEmail = $context->getOptions()->get( Options::OPTION_RECEIPT_EMAIL_TYPE ) === Options::OPTION_VALUE_RECEIPT_EMAIL_PLUGIN;

        switch ( $templateType ) {
            case Main::EMAIL_TEMPLATE_ID_CARD_SAVED:
            case Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED:
                $templateEnabled = $sendPluginEmail;
                break;

            case Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT_STRIPE:
            case Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT_STRIPE:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT_STRIPE:
                $templateEnabled = ! $sendPluginEmail;
                break;
        }

        return $templateEnabled;
    }

    /**
     * Tells whether the given email template is generated by the plugin (as opposed to Stripe).
     * Only plugin-generated templates support per-form subject/body customization.
     *
     * @param string $templateType
     * @return bool
     */
    public static function isPluginEmailTemplate( $templateType ) {
        switch ( $templateType ) {
            case Main::EMAIL_TEMPLATE_ID_CARD_SAVED:
            case Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED:
                return true;

            default:
                return false;
        }
    }

    /**
     * Returns the per-form custom content object ({subject, body}) for the given template id,
     * or null when the form has no custom content stored for it.
     *
     * @param mixed $form
     * @param string $emailTemplateId
     * @return object|null
     */
    public static function getFormTemplateContent( $form, $emailTemplateId ) {
        if ( ! is_object( $form ) || ! isset( $form->emailTemplates ) || empty( $form->emailTemplates ) ) {
            return null;
        }

        $templates = json_decode( $form->emailTemplates );
        if ( ! is_object( $templates ) || ! isset( $templates->{$emailTemplateId} ) ) {
            return null;
        }

        $template = $templates->{$emailTemplateId};
        if ( ! isset( $template->content ) || ! isset( $template->content->default ) ) {
            return null;
        }

        $default = $template->content->default;

        return is_object( $default ) ? $default : null;
    }

    /**
     * Resolves the subject and body for a notification. Per-form custom content (when configured)
     * takes precedence over the global template content; an empty per-form field falls back to the
     * global value, keeping existing forms fully backward compatible.
     *
     * @param mixed $form
     * @param string $emailTemplateId
     * @param mixed $globalSubject
     * @param mixed $globalBody
     * @return array{0: string, 1: string} [ $subject, $body ]
     */
    public static function resolveTemplateContent( $form, $emailTemplateId, $globalSubject, $globalBody ) {
        $subject = is_scalar( $globalSubject ) ? (string) $globalSubject : '';
        $body    = is_scalar( $globalBody ) ? (string) $globalBody : '';

        $content = self::getFormTemplateContent( $form, $emailTemplateId );
        if ( $content !== null ) {
            if ( isset( $content->subject ) && is_scalar( $content->subject ) && trim( (string) $content->subject ) !== '' ) {
                $subject = (string) $content->subject;
            }
            if ( isset( $content->body ) && is_scalar( $content->body ) && trim( (string) $content->body ) !== '' ) {
                $body = (string) $content->body;
            }
        }

        return [ $subject, $body ];
    }

    /**
     * @param StaticContext $context
     * @param $formType
     * @param $sendEmail
     * @param bool $returnObject
     *
     * @return array|false|string|\stdClass
     */
    public static function createDefaultEmailTemplates( $context, $formType, $sendEmail, $returnObject = false ) {
        $templateDescriptors = Mailer::getEmailTemplateDescriptors( $formType );
        $emailTemplates      = new \StdClass;

        foreach( $templateDescriptors as $currentDescriptor ) {
            $isTemplateEnabled = $sendEmail === true ? self::isTemplateEnabled( $context, $currentDescriptor->type ) : false;

            $defaultLanguage = new \StdClass;
            $defaultLanguage->subject = '';
            $defaultLanguage->body = '';

            $content = new \StdClass;
            $content->default = $defaultLanguage;

            $emailTemplate = new \StdClass;
            $emailTemplate->enabled = $isTemplateEnabled;
            $emailTemplate->senderName = '';
            $emailTemplate->senderAddress = '';
            $emailTemplate->receiverAddresses = [];
            $emailTemplate->content = $content;

            $emailTemplates->{$currentDescriptor->type} = $emailTemplate;
        }

        if ( $returnObject ) {
            return $emailTemplates;
        } else {
            return json_encode( $emailTemplates );
        }
    }

    /**
     * @param StaticContext $context
     * @param $formType
     * @param $emailTemplatesJson
     * @return array|false|mixed|\stdClass|string
     */
    public static function extractEmailTemplates( $context, $formType, $emailTemplatesJson ) {
        $emailTemplates = null;
        if ( $emailTemplatesJson !== null ) {
            $emailTemplates = json_decode( $emailTemplatesJson );
        }

        if ( json_last_error() !== JSON_ERROR_NONE || $emailTemplates === null ) {
            $emailTemplates = Mailer::createDefaultEmailTemplates( $context, $formType, false, true );
        }

        return $emailTemplates;
    }

    public static function updateMissingEmailTemplatesWithDefaults( &$emailTemplates ) {
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT)) {
            $emailTemplates->paymentMade = Mailer::createDefaultPaymentReceiptTemplate();
        }
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT)) {
            $emailTemplates->subscriptionStarted = Mailer::createDefaultSubscriptionReceiptTemplate();
        }
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED)) {
            $emailTemplates->subscriptionFinished = Mailer::createDefaultSubscriptionEndedTemplate();
        }
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT)) {
            $emailTemplates->donationMade = Mailer::createDefaultDonationReceiptTemplate();
        }
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_CARD_SAVED)) {
            $emailTemplates->cardCaptured = Mailer::createDefaultCardSavedTemplate();
        }
        if (!property_exists($emailTemplates, Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE)) {
            $emailTemplates->cardUpdateConfirmationRequest = Mailer::createDefaultCustomerPortalSecurityCodeTemplate();
        }

        $emailTemplates = apply_filters('fullstripe_email_template_defaults', $emailTemplates );
    }

    /**
     * Constructs an \StdClass with the default email receipt templates.
     *
     * @return \StdClass
     */
    public static function getDefaultEmailTemplates() {
        $emailTemplates = new \StdClass;
        self::updateMissingEmailTemplatesWithDefaults( $emailTemplates );

        return $emailTemplates;
    }

    /**
     * @param Options $options
     * @return void
     */
    public static function updateDefaultEmailTemplatesInOptions( $options ) {
        $emailReceipts = json_decode( $options->get( Options::OPTION_EMAIL_TEMPLATES ));
        Mailer::updateMissingEmailTemplatesWithDefaults($emailReceipts);
        $options->set( Options::OPTION_EMAIL_TEMPLATES, json_encode( $emailReceipts ));
    }

    /**
     * @return \stdClass
     */
    public static function createDefaultSubscriptionEndedTemplate() {
        $subscriptionFinished = new \stdClass();
        $subscriptionFinished->subject = 'Subscription Ended';
        $subscriptionFinished->html = '<html><body><p>Hi,</p><p>Your subscription has ended.</p><p>Thanks</p><br/>%NAME%</body></html>';

        return $subscriptionFinished;
    }

    public static function createDefaultCustomerPortalSecurityCodeTemplate() {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $homeUrl = home_url();
        $cardUpdateConfirmationRequest = new \stdClass();
        $cardUpdateConfirmationRequest->subject = 'Login code for managing your account';
        $cardUpdateConfirmationRequest->html = '<html>
<body>
<p>Dear %CUSTOMER_EMAIL%,</p>

<p>You are receiving this email because you requested access to the page where you can manage your subscription(s).</p>

<br/>
<table>
    <tr>
        <td><b>Subscription management page:</b></td>
        <td><a href="https://www.example.com/manage-subscription">https://www.example.com/manage-subscription</a></td>
    </tr>
    <tr>
        <td><b>Your security code:</b></td>
        <td>%CARD_UPDATE_SECURITY_CODE%</td>
    </tr>
</table>

<br/>
<p>
    Thanks,<br/>
    %NAME%
</p>
</body>
</html>';

        return $cardUpdateConfirmationRequest;
    }

    /**
	 * @return \stdClass
	 */
    public static function createDefaultPaymentReceiptTemplate() {
        $paymentMade = new \stdClass();
        $paymentMade->subject = 'Payment Receipt';
        $paymentMade->html = "<html><body><p>Hi,</p><p>Here's your receipt for your payment of %CHARGED_TOTAL%</p><p>Thanks</p><br/>%NAME%</body></html>";

        return $paymentMade;
    }

    /**
     * @return \stdClass
     */
    public static function createDefaultDonationReceiptTemplate() {
        $paymentMade = new \stdClass();
        $paymentMade->subject = 'Donation Receipt';
        $paymentMade->html = "<html><body><p>Hi,</p><p>Here's your receipt for your donation of %CHARGED_TOTAL%</p><p>Thanks</p><br/>%NAME%</body></html>";

        return $paymentMade;
    }

    /**
     * @return \stdClass
     */
    public static function createDefaultSubscriptionReceiptTemplate() {
        $subscriptionStarted = new \stdClass();
        $subscriptionStarted->subject = 'Subscription Receipt';
        $subscriptionStarted->html = "<html><body><p>Hi,</p><p>Here's your receipt for your subscription of %CHARGED_TOTAL%</p><p>Thanks</p><br/>%NAME%</body></html>";

        return $subscriptionStarted;
    }

    /**
	 * @return \stdClass
	 */
    public static function createDefaultCardSavedTemplate() {
        $cardCaptured = new \stdClass();
        $cardCaptured->subject = 'Card Information Saved';
        $cardCaptured->html = '<html><body><p>Hi,</p><p>Your payment information has been saved.</p><p>Thanks</p><br/>%NAME%</body></html>';

        return $cardCaptured;
    }

    /**
     * @param $form
     * @param DonationTransactionData $transactionData
     */
    public function sendDonationEmailReceipt( $form, $transactionData ) {
        $this->sendUnlessDemo( DonationReceiptSender::class, $transactionData, $form );
    }

    /**
     * @param $form
     * @param DonationTransactionData $transactionData
     * @param $subject
     * @param $body
     */
    public function sendTestDonationEmailReceipt( $form, $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestDonationReceiptSender::class, $transactionData, $form, $subject, $body );
    }

    /**
     * @param $form
     * @param $transactionData OneTimePaymentTransactionData
     */
    public function sendOneTimePaymentReceipt($form, $transactionData ) {
        $this->sendUnlessDemo( OneTimePaymentReceiptSender::class, $transactionData, $form );
    }

    /**
     * Tell the site admin about a payment recorded at the amount its draft approved and Stripe charged, which the
     * form now prices otherwise, so its tax and discount can be checked.
     *
     * @param object $form Payment form record from the database.
     * @param string $paymentIntentId
     * @param int $chargedAmount In the smallest currency unit.
     * @param int|null $expectedAmount What the form prices the payment at now, in the smallest currency unit, or null
     *                             when it can't price it.
     * @param bool $isRecoveryFeeUnknown Whether the charge's recovery fee could not be told apart from its amount.
     * @return bool False when wp_mail() could not send the notice.
     */
    public function sendRepricedChargeAdminNotice( $form, string $paymentIntentId, int $chargedAmount, ?int $expectedAmount, bool $isRecoveryFeeUnknown = false ): bool {
        if ( Utils::isDemoMode() ) {
            return true;
        }

        if ( is_null( $expectedAmount ) ) {
            $message = sprintf(
                /* translators: 1: Stripe PaymentIntent id, 2: form name, 3: amount Stripe charged */
                __( 'Stripe charged %3$s for PaymentIntent %1$s on form %2$s, the amount approved when the customer paid, but the form could not price the payment again. The payment was recorded at the charged amount. Check its tax and discount.', 'wp-full-stripe-free' ),
                $paymentIntentId,
                $form->name,
                Currencies::formatByForm( $form, $form->currency, $chargedAmount )
            );
        } else {
            $message = sprintf(
                /* translators: 1: Stripe PaymentIntent id, 2: form name, 3: amount Stripe charged, 4: amount the form prices the payment at now */
                __( 'Stripe charged %3$s for PaymentIntent %1$s on form %2$s, the amount approved when the customer paid, but the form now prices the payment at %4$s. The payment was recorded at the charged amount. Check its tax and discount.', 'wp-full-stripe-free' ),
                $paymentIntentId,
                $form->name,
                Currencies::formatByForm( $form, $form->currency, $chargedAmount ),
                Currencies::formatByForm( $form, $form->currency, $expectedAmount )
            );
        }
        if ( $isRecoveryFeeUnknown ) {
            $message .= ' ' . __( 'Its recovery fee could not be worked out, so it was recorded as part of the payment amount.', 'wp-full-stripe-free' );
        }
        // An invoice of what the form prices now would not add up to the charge, so the payment gets none.
        if ( isset( $form->generateInvoice ) && 1 === (int) $form->generateInvoice ) {
            $message .= ' ' . __( 'No invoice was created for it.', 'wp-full-stripe-free' );
        }

        return $this->sendChargeAdminNotice( $paymentIntentId, $message );
    }

    /**
     * Tell the site admin about a payment that was refunded, or an authorization that was released, because its
     * product was removed from the form while the customer was paying.
     *
     * @param object $form Payment form record from the database.
     * @param string $paymentIntentId
     * @param int $chargedAmount In the smallest currency unit.
     * @param bool $isAuthorization The payment was only authorized, for capture later.
     * @return bool False when wp_mail() could not send the notice.
     */
    public function sendRemovedProductChargeAdminNotice( $form, string $paymentIntentId, int $chargedAmount, bool $isAuthorization = false ): bool {
        if ( Utils::isDemoMode() ) {
            return true;
        }

        if ( $isAuthorization ) {
            /* translators: 1: Stripe PaymentIntent id, 2: form name, 3: amount Stripe authorized */
            $template = __( 'Stripe authorized %3$s for PaymentIntent %1$s on form %2$s, but the product it was authorized for is no longer on the form, so the authorization was released.', 'wp-full-stripe-free' );
        } else {
            /* translators: 1: Stripe PaymentIntent id, 2: form name, 3: amount Stripe charged */
            $template = __( 'Stripe charged %3$s for PaymentIntent %1$s on form %2$s, but the product it was charged for is no longer on the form, so the payment was refunded.', 'wp-full-stripe-free' );
        }

        return $this->sendChargeAdminNotice(
            $paymentIntentId,
            sprintf( $template, $paymentIntentId, $form->name, Currencies::formatByForm( $form, $form->currency, $chargedAmount ) )
        );
    }

    /**
     * @param string $message Translated message.
     * @return bool False when wp_mail() could not send the notice.
     */
    private function sendChargeAdminNotice( string $paymentIntentId, string $message ): bool {
        // A pluggable wp_mail() or a pre_wp_mail filter may return a non-boolean.
        return true === wp_mail(
            get_bloginfo( 'admin_email' ),
            /* translators: %s: Stripe PaymentIntent id */
            sprintf( __( 'Payment %s needs review', 'wp-full-stripe-free' ), $paymentIntentId ),
            $message
        );
    }

    /**
     * @param $form
     * @param $transactionData OneTimePaymentTransactionData
     * @param $subject
     * @param $body
     */
    public function sendTestOneTimePaymentReceipt( $form, $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestOneTimePaymentReceiptSender::class, $transactionData, $form, $subject, $body );
    }

    /**
     * @param $form
     * @param $transactionData SaveCardTransactionData
     */
	public function sendSaveCardNotification($form, $transactionData ) {
        $this->sendUnlessDemo( SaveCardNotificationSender::class, $transactionData, $form );
    }

    /**
     * @param $form
     * @param $transactionData SaveCardTransactionData
     * @param $subject
     * @param $body
     */
    public function sendTestSaveCardNotification( $form, $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestSaveCardNotificationSender::class, $transactionData, $form, $subject, $body );
    }

    /**
	 * @param $form
	 * @param SubscriptionTransactionData $transactionData
	 */
	public function sendSubscriptionStartedEmailReceipt( $form, $transactionData ) {
        $this->sendUnlessDemo( SubscriptionEmailReceiptSender::class, $transactionData, $form );
	}

    /**
     * @param $form
     * @param SubscriptionTransactionData $transactionData
     * @param $subject
     * @param $body
     */
    public function sendTestSubscriptionStartedEmailReceipt( $form, $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestSubscriptionEmailReceiptSender::class, $transactionData, $form, $subject, $body );
    }

    /**
	 * @param $form
	 * @param SubscriptionTransactionData $transactionData
	 */
	public function sendSubscriptionFinishedEmailReceipt( $form, $transactionData ) {
        $this->sendUnlessDemo( SubscriptionEndedNotificationSender::class, $transactionData, $form );
	}

    /**
     * @param $form
     * @param SubscriptionTransactionData $transactionData
     * @param $subject
     * @param $body
     */
    public function sendTestSubscriptionFinishedEmailReceipt( $form, $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestSubscriptionEndedNotificationSender::class, $transactionData, $form, $subject, $body );
    }

    /**
     * @param MyAccountLoginTransactionData $transactionData
     */
	public function sendMyAccountLoginRequest( $transactionData ) {
        $this->sendUnlessDemo( MyAccountLoginNotificationSender::class, $transactionData );
    }

    /**
     * @param MyAccountLoginTransactionData $transactionData
     * @param $subject
     * @param $body
     */
    public function sendTestMyAccountLoginRequest( $transactionData, $subject, $body ) {
        $this->sendTestUnlessDemo( TestMyAccountLoginNotificationSender::class, $transactionData, null, $subject, $body );
    }

    /**
     * @param class-string<MailerTask> $senderClass
     * @param FormTransactionData|MyAccountLoginTransactionData $transactionData
     * @param object|null $form The form's database row.
     */
    private function sendUnlessDemo( string $senderClass, $transactionData, $form = null ): void {
        if ( Utils::isDemoMode() ) {
            return;
        }

        $sender = new $senderClass( $transactionData, $this->loggerService, $form );
        $sender->sendEmail();
    }

    /**
     * Sends the subject and body being edited on the Email templates page.
     *
     * @param class-string<TestDonationReceiptSender|TestMyAccountLoginNotificationSender|TestOneTimePaymentReceiptSender|TestSaveCardNotificationSender|TestSubscriptionEmailReceiptSender|TestSubscriptionEndedNotificationSender> $senderClass
     * @param FormTransactionData|MyAccountLoginTransactionData $transactionData
     * @param object|null $form The form's database row, or the stand-in createPhonyForm() builds.
     * @param string $subject
     * @param string $body
     */
    private function sendTestUnlessDemo( string $senderClass, $transactionData, $form, $subject, $body ): void {
        if ( Utils::isDemoMode() ) {
            return;
        }

        $sender = new $senderClass( $transactionData, $this->loggerService, $form );
        $sender->setSubject( $subject );
        $sender->setBody( $body );
        $sender->sendEmail();
    }

    private function createPhonyForm() {
        $form = new \StdClass;
        $form->decimalSeparator = Main::DECIMAL_SEPARATOR_SYMBOL_DOT;
        $form->showCurrencySymbolInsteadOfCode = 1;
        $form->showCurrencySignAtFirstPosition = 1;
        $form->putWhitespaceBetweenCurrencyAndAmount = 0;
        $form->currency = 'USD';

        return $form;
    }

    private function sendTestEmailByTemplateType( $emailTemplateType, $form, $transactionData, $subject, $body ) {
        switch( $emailTemplateType ) {
            case Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT:
                $this->sendTestOneTimePaymentReceipt( $form, $transactionData, $subject, $body );
                break;

            case Main::EMAIL_TEMPLATE_ID_CARD_SAVED:
                $this->sendTestSaveCardNotification( $form, $transactionData, $subject, $body );
                break;

            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT:
                $this->sendTestSubscriptionStartedEmailReceipt( $form, $transactionData, $subject, $body );
                break;

            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED:
                $this->sendTestSubscriptionFinishedEmailReceipt( $form, $transactionData, $subject, $body );
                break;

            case Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT:
                $this->sendTestDonationEmailReceipt( $form, $transactionData, $subject, $body );
                break;

            case Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE:
                $this->sendTestMyAccountLoginRequest( $transactionData, $subject, $body );
                break;

            default:
                throw new \Exception( __CLASS__ . '::' . __FUNCTION__ . "() - invalid email template type: " . $emailTemplateType );
        }
    }

    public function sendTestEmail( $recipients, $subject, $body, $emailTemplateType ) {
        $form = $this->createPhonyForm();

        foreach ( $recipients as $recipient ) {
            $transactionData = TestTransactionDataCreatorFactory::createByEmailTemplateType( $recipient, $emailTemplateType );

            $this->sendTestEmailByTemplateType( $emailTemplateType, $form, $transactionData, $subject, $body );
        }
    }
}
