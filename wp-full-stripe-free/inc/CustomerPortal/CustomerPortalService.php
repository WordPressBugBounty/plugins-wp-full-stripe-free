<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Context\FindStripeCustomerAddOn;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\StripeUnreachableException;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\ReCaptcha;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Utils;

class CustomerPortalService
{
    use LoggerAddOn;
    use StaticContextAddOn;
    use FindStripeCustomerAddOn;

    const PARAM_CARD_UPDATE_SESSION = 'wpfs-card-update-session';
    const PARAM_CARD_UPDATE_SECURITY_CODE = 'wpfs-security-code';
    const COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID = 'WPFS_CARD_UPDATE_SESSION_ID';
    const TEMPLATE_ENTER_EMAIL_ADDRESS = 'enter-email-address';
    const TEMPLATE_ENTER_SECURITY_CODE = 'enter-security-code';
    const JS_VARIABLE_STRIPE_ACCOUNT_ID = 'stripeAccountId';
    const TEMPLATE_SELECT_ACCOUNT = 'select-account';
    const TEMPLATE_CUSTOMER_PORTAL = 'customer-portal';
    const TEMPLATE_INVALID_SESSION = 'invalid-session';
    const SESSION_STATUS_WAITING_FOR_CONFIRMATION = 'waiting_for_confirmation';
    const SESSION_STATUS_WAITING_FOR_ACCOUNT_SELECTION = 'waiting_for_account_selection';
    const SESSION_STATUS_CONFIRMED = 'confirmed';
    const SESSION_STATUS_INVALIDATED = 'invalidated';
    const SECURITY_CODE_STATUS_PENDING = 'pending';
    const SECURITY_CODE_STATUS_SENT = 'sent';
    const SECURITY_CODE_STATUS_CONSUMED = 'consumed';
    const SECURITY_CODE_REQUEST_LIMIT = 5;
    const SECURITY_CODE_REQUEST_WINDOW = 3600;
    const SECURITY_CODE_CLIENT_REQUEST_LIMIT = 3;
    const SECURITY_CODE_EMAIL_REQUEST_LIMIT = 10;
    const SECURITY_CODE_REQUEST_COUNTER_PREFIX = 'wpfs_cp_limit_';
    const INVOICE_VIEW_TRANSIENT_PREFIX = 'wpfs_cp_invoices_';
    const SECURITY_CODE_INPUT_LIMIT = 5;
    const COOKIE_ACTION_SET = 'set';
    const COOKIE_ACTION_REMOVE = 'remove';
    const CARD_UPDATE_SESSION_VALID_UNTIL_HOURS = 1;
    const URL_RECAPTCHA_API_SITEVERIFY = 'https://www.google.com/recaptcha/api/siteverify';
    const SOURCE_GOOGLE_RECAPTCHA_V2_API_JS = 'https://www.google.com/recaptcha/api.js';
    const ASSET_DIR_CUSTOMER_PORTAL = 'customer-portal';
    const ASSET_ENTER_EMAIL_ADDRESS_PHP = 'wpfs-enter-email-address.php';
    const ASSET_ENTER_SECURITY_CODE_PHP = 'wpfs-enter-security-code.php';
    const ASSET_SELECT_ACCOUNT_PHP = 'wpfs-select-account.php';
    const ASSET_CUSTOMER_PORTAL_PHP = 'wpfs-customer-portal.php';
    const ASSET_INVALID_SESSION_PHP = 'wpfs-invalid-session.php';
    const ASSET_WPFS_CUSTOMER_PORTAL_CSS = 'wpfs-customer-portal.css';
    const ASSET_WPFS_CUSTOMER_PORTAL_JS = 'wpfs-customer-portal.js';
    const CARD_AMERICAN_EXPRESS = 'Amex';
    const CARD_DINERS_CLUB = 'Diners';
    const CARD_DISCOVER = 'Discover';
    const CARD_JCB = 'JCB';
    const CARD_MASTERCARD = 'MasterCard';
    const CARD_UNIONPAY = 'UnionPay';
    const CARD_VISA = 'Visa';
    const CARD_UNKNOWN = 'Unknown';
    const PARAM_WPFS_SUBSCRIPTION_ID = 'wpfs-subscription-id';
    const PARAM_EMAIL_ADDRESS = 'emailAddress';
    const PARAM_GOOGLE_RE_CAPTCHA_RESPONSE = 'googleReCAPTCHAResponse';
    const FULLSTRIPE_SHORTCODE_CUSTOMER_PORTAL = 'fullstripe_customer_portal';
    const FULLSTRIPE_SHORTCODE_MANAGE_SUBSCRIPTIONS = 'fullstripe_manage_subscriptions';

    const JS_VARIABLE_AJAX_URL = 'ajaxUrl';
    const JS_VARIABLE_REST_URL = 'restUrl';
    const JS_VARIABLE_STRIPE_KEY = 'stripeKey';
    const JS_VARIABLE_GOOGLE_RECAPTCHA_SITE_KEY = 'googleReCaptchaSiteKey';

    const SHORTCODE_ATTRIBUTE_NAME_AUTHENTICATION = 'authentication';
    const SHORTCODE_ATTRIBUTE_VALUE_AUTHENTICATION = 'Wordpress';

    const HANDLE_STRIPE_JS_V_3 = 'stripe-js-v3';
    const HANDLE_GOOGLE_RECAPTCHA_V_2 = 'google-recaptcha-v2';
    const HANDLE_MANAGE_SUBSCRIPTIONS_CSS = 'wpfs-manage-subscriptions-css';
    const HANDLE_MANAGE_SUBSCRIPTIONS_JS = 'wpfs-manage-subscriptions-js';

    const WPFS_PLUGIN_SLUG = 'wp-full-stripe';
    const WPFS_REST_API_VERSION = 'v1';
    const WPFS_REST_ROUTE_MANAGE_SUBSCRIPTIONS_SUBSCRIPTION = 'manage-subscriptions/subscription';

    const INVOICE_DISPLAY_HEAD_LIMIT = 5;
    const INVOICE_LIST_PAGE_SIZE = 100;
    const INVOICE_LIST_MAX_INVOICES = 1000;

    const INVOICE_DISPLAY_MODE_FEW = 0;
    const INVOICE_DISPLAY_MODE_HEAD = 1;
    const INVOICE_DISPLAY_MODE_ALL = 2;

    const ATTRIBUTE_NAME_PRODUCT_EXPANDED = 'productExpanded';

    const ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS = 'fullstripe_check_customer_portal_sessions';

    /* @var $db Database */
    protected $db = null;

    /* @var $stripe Stripe */
    protected $stripe = null;

    /* @var $mailer Mailer */
    protected $mailer = null;

    /* @var $mailer Options */
    protected $options = null;

    /**
     * @var ProductLookup|null Products of the portal page being built
     */
    private $productLookup = null;

    /**
     * @var array<int, object>|null Recurring prices of the portal page being built
     */
    private $subscriptionPlans = null;

    public function __construct($loggerService)
    {
        $this->setup($loggerService);
        $this->hooks();
    }

    private function setup($loggerService)
    {
        $this->initLogger($loggerService, LoggerService::MODULE_CUSTOMER_PORTAL);
        $this->options = new Options();

        $this->initStaticContext();

        $this->db = new Database();
        $this->stripe = new Stripe(Stripe::getStripeAuthenticationToken($this->staticContext), $this->loggerService);
        $this->mailer = new Mailer($this->loggerService);

        self::scheduleSessionRemovalTask();
    }

    private function hooks()
    {

        add_shortcode(self::FULLSTRIPE_SHORTCODE_CUSTOMER_PORTAL, [$this, 'renderShortCode']);
        add_shortcode(self::FULLSTRIPE_SHORTCODE_MANAGE_SUBSCRIPTIONS, [$this, 'renderShortCode']);

        add_action(self::ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS, [
            $this,
            'checkSessionsAndCodes'
        ]
        );

        add_action('wp_ajax_wp_full_stripe_create_card_update_session', [
            $this,
            'handleSessionRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_create_card_update_session', [
            $this,
            'handleSessionRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_reset_card_update_session', [
            $this,
            'handleResetSessionRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_reset_card_update_session', [
            $this,
            'handleResetSessionRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_validate_security_code', [
            $this,
            'handleSecurityCodeValidationRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_validate_security_code', [
            $this,
            'handleSecurityCodeValidationRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_select_customer_portal_account', [
            $this,
            'handleSelectAccountRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_select_customer_portal_account', [
            $this,
            'handleSelectAccountRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_show_customer_portal_account_selector', [
            $this,
            'handleShowAccountSelectorRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_show_customer_portal_account_selector', [
            $this,
            'handleShowAccountSelectorRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_update_card', [
            $this,
            'handleCardUpdateRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_update_card', [
            $this,
            'handleCardUpdateRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_cancel_my_subscription', [
            $this,
            'handleSubscriptionCancellationRequest'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_cancel_my_subscription', [
            $this,
            'handleSubscriptionCancellationRequest'
        ]
        );
        add_action('wp_ajax_wp_full_stripe_toggle_invoice_view', [
            $this,
            'toggleInvoiceView'
        ]
        );
        add_action('wp_ajax_nopriv_wp_full_stripe_toggle_invoice_view', [
            $this,
            'toggleInvoiceView'
        ]
        );

        // tnagy register REST API Endpoint for Manage Subscriptions
        add_action('rest_api_init', [$this, 'registerRESTAPIRoutes']);

        // tnagy WPFS-861: prevent caching of pages generated by the shortcode
        add_action('send_headers', [$this, 'addCacheControlHeader'], 10, 1);
    }

    public static function onActivation()
    {
        self::scheduleSessionRemovalTask();
    }

    public static function onDeactivation()
    {
        self::clearSessionRemovalTask();
    }

    protected static function scheduleSessionRemovalTask()
    {
        if (!wp_next_scheduled(self::ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS)) {
            wp_schedule_event(time(), 'hourly', self::ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS);
            Utils::log(__FUNCTION__ . '(): Customer Portal Session Removal Task scheduled.');
            Utils::log(__FUNCTION__ . '(): Executing Customer Portal Session Removal Task...');
            do_action(self::ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS);
        }
    }

    protected static function clearSessionRemovalTask()
    {
        wp_clear_scheduled_hook(self::ACTION_NAME_CHECK_CUSTOMER_PORTAL_SESSIONS);
        Utils::log(__FUNCTION__ . '(): All scheduled Customer Portal Session Removal Task has been removed.');
    }

    /**
     * @param \StripeWPFS\Stripe\Subscription $subscription
     *
     * @return bool
     */
    public static function isDonationPlan($subscription)
    {
        $isDonationPlan = false;

        if (isset($subscription->plan)) {
            $isDonationPlan = strpos($subscription->plan->id, Main::DONATION_PLAN_ID_PREFIX) === 0;
        }
        if (!$isDonationPlan) {
            foreach ($subscription->items->data as $item) {
                if (isset($item->price->lookup_key) && strpos($item->price->lookup_key, Main::DONATION_PLAN_ID_PREFIX) === 0) {
                    $isDonationPlan = true;
                    break;
                }
            }
        }

        return $isDonationPlan;
    }

    public function checkSessionsAndCodes()
    {
        $this->logger->debug(__FUNCTION__, 'CALLED');

        try {

            // tnagy invalidate expired sessions
            $this->db->invalidateExpiredCustomerPortalSessions(self::CARD_UPDATE_SESSION_VALID_UNTIL_HOURS);

            // tnagy invalidate sessions where security code request and security code input limits reached
            $this->db->invalidateCustomerPortalSessionsBySecurityCodeRequestLimit(self::SECURITY_CODE_REQUEST_LIMIT);
            $this->db->invalidateCustomerPortalSessionsBySecurityCodeInputLimit(self::SECURITY_CODE_INPUT_LIMIT);

            $this->db->deleteRequestLimitCountersOutsideWindow(self::SECURITY_CODE_REQUEST_COUNTER_PREFIX, $this->getSecurityCodeRequestWindow());

            // tnagy remove invalidated sessions
            $invalidatedSessionIdObjects = $this->db->findInvalidatedCustomerPortalSessionIds();
            $invalidatedSessionIds = array_map(function ($o) {
                return $o->id;
            }, $invalidatedSessionIdObjects);

            $this->logger->debug(__FUNCTION__, 'Invalidated sessions found=' . sizeof($invalidatedSessionIds));

            if (isset($invalidatedSessionIds) && sizeof($invalidatedSessionIds) > 0) {
                $this->db->deleteSecurityCodesBySessions($invalidatedSessionIds);
                $this->db->deleteInvalidatedCustomerPortalSessions($invalidatedSessionIds);
            }

            $this->logger->debug(__FUNCTION__, 'Invalid sessions removed=' . sizeof($invalidatedSessionIds));

        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, "Error while checking sessions and codes", $ex);
        }

        $this->logger->debug(__FUNCTION__, 'FINISHED');
    }

    private function getSessionByCookie()
    {
        $res = null;

        $sessionHash = $this->findSessionCookieValue();
        if (!is_null($sessionHash)) {
            $res = $this->findCustomerPortalSessionByHash($sessionHash);
        }

        return $res;
    }

    private function validateWordpressSession($session)
    {
        $result = $session;

        if (!is_null($session)) {
            $isWaitingForAccountSelection = $this->isWaitingForAccountSelection($session);
            $isConfirmed = $this->isConfirmed($session);

            if (!$isWaitingForAccountSelection && !$isConfirmed) {
                $result = null;
            }
        }

        return $result;
    }

    private function validateSaaSSession($session)
    {
        $result = $session;

        if (!is_null($session)) {
            $isWaitingForConfirmation = $this->isWaitingForConfirmation($session);
            $isWaitingForAccountSelection = $this->isWaitingForAccountSelection($session);
            $isConfirmed = $this->isConfirmed($session);

            if (!$isWaitingForConfirmation && !$isWaitingForAccountSelection && !$isConfirmed) {
                $result = null;
            }
        }

        return $result;
    }

    /**
     * @param $session
     * @return string|null
     */
    protected function determineCookieAction($session)
    {
        $res = null;

        if ($session === null) {
            $res = self::COOKIE_ACTION_REMOVE;
        }

        return $res;
    }

    /**
     * @param $email
     * @return int
     * @throws \Exception When Stripe can't list the customers, so that no session is confirmed without a pick.
     */
    protected function getNumberOfStripeCustomersByEmail($email): int
    {
        $result = 0;
        $stripeCustomers = $this->stripe->listCustomersByEmail($email);

        foreach ($stripeCustomers as $stripeCustomer) {
            if (isset($stripeCustomer) && (!isset($stripeCustomer->deleted) || !$stripeCustomer->deleted)) {
                $result += 1;
            }
        }

        return $result;
    }

    /**
     * @param $session
     * @return bool
     */
    protected function isAccountSelectionNeeded($session): bool
    {
        return $this->getNumberOfStripeCustomersByEmail($session->email) > 1;
    }

    /**
     * @param $session
     * @return string
     */
    protected function getTemplateToShowBySession($session)
    {
        $result = self::TEMPLATE_ENTER_EMAIL_ADDRESS;

        if (is_null($session)) {
            $result = self::TEMPLATE_ENTER_EMAIL_ADDRESS;
        } elseif ($this->isWaitingForConfirmation($session)) {
            $result = self::TEMPLATE_ENTER_SECURITY_CODE;
        } elseif ($this->isWaitingForAccountSelection($session)) {
            $result = self::TEMPLATE_SELECT_ACCOUNT;
        } elseif ($this->isConfirmed($session)) {
            $result = self::TEMPLATE_CUSTOMER_PORTAL;
        }

        return $result;
    }

    public function renderShortCode($attributes)
    {
		if ( ! Utils::isConnected() ) {
			$content = '';

			if ( current_user_can( 'administrator' ) ) {
				$content = sprintf(
					/* translators: %1$s: opening p tag, %2$s: opening link tag, %3$s: closing link tag, %4$s: closing p tag. */
					__( '%1$sYou are not connected to Stripe. Please connect to Stripe %2$sin the plugin settings%3$s.%4$s', 'wp-full-stripe-free' ),
					'<p>',
					'<a href="' . Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE ) . '">',
					'</a>',
					'</p>'
				);
			}

			return $content;
		}

        try {
            return $this->renderCustomerPortal($attributes);
        } catch (\Exception $ex) {
            // Stripe can stop answering after the session's customer was checked. A failed read confirms no session.
            $this->logger->error(__FUNCTION__, 'Cannot load the customer portal', $ex);

            return $this->getStripeUnreachableMessage();
        }
    }

    /**
     * @param array<string, string>|string $attributes The shortcode's attributes.
     * @return string|null
     * @throws \Exception When Stripe can't be reached after the session's customer was checked.
     */
    private function renderCustomerPortal($attributes)
    {
        $session = null;
        $should_not_redirect = ! ( is_admin() || is_search() || defined('REST_REQUEST') && REST_REQUEST ); // Not redirecting in admin, search pages, or REST requests.
        $useStripePortal = $should_not_redirect && $this->options->get( Options::OPTION_CUSTOMER_PORTAL_USE_STRIPE_CUSTOMER_PORTAL );

        if (self::isWordpressAuthenticationNeeded($attributes)) {
            $content = null;
            $cookieAction = null;

            $session = $this->validateWordpressSession($this->getSessionByCookie());

            if (is_user_logged_in()) {
                $stripeCustomer = null;
                $createSession = false;

                $user = wp_get_current_user();

                if (!is_null($session)) {
                    // A session ends once its customer has another email in Stripe, which may be someone else's now (#791).
                    $sessionCustomer = $user->user_email === $session->email ? $this->checkSessionCustomer($session) : false;
                    if (is_null($sessionCustomer)) {
                        return $this->getStripeUnreachableMessage();
                    }
                    if (false === $sessionCustomer) {
                        $this->invalidateSession($session);

                        $stripeCustomer = $this->findStripeCustomerByEmail($user->user_email);
                        if (!is_null($stripeCustomer)) {
                            $createSession = true;
                        }
                        $session = null;
                    }
                } else {
                    $stripeCustomer = $this->findStripeCustomerByEmail($user->user_email);
                    if (!is_null($stripeCustomer)) {
                        $createSession = true;
                    }
                }

                if ($createSession) {
                    $session = $this->createCardUpdateSessionForWPUser($user, $stripeCustomer);

                    if (!$this->isAccountSelectionNeeded($session)) {
                        $this->confirmSession($session);
                    } else {
                        $this->makeSessionWaitingForAccountSelection($session);
                    }

                    $cookieAction = self::COOKIE_ACTION_SET;
                }

                if (!is_null($session)) {
                    $model = new CustomerPortalModel();
                    $model->setAuthenticationType(CustomerPortalModel::AUTHENTICATION_TYPE_WORDPRESS);
                    $model->setStripeClient($this->stripe);

                    if ($this->isWaitingForAccountSelection($session)) {
                        $this->fetchAccountDataIntoCustomerPortalModel($model, $session->email);

                        $this->enqueueCardUpdateScript($cookieAction, is_null($session) ? null : $session->hash, $model);
                        $content = $this->renderSelectAccountForm($attributes, $model);
                    } elseif ($this->isConfirmed($session)) {
                        if ( $useStripePortal ) {
                            $returnUrl = get_permalink();
                            $portalSession = $this->stripe->createCustomerPortalSession( $session->stripeCustomerId, $returnUrl );

                            if ( isset( $portalSession->url ) && ! empty( $portalSession->url ) ) {
                                $this->invalidateSession($session);
                                wp_redirect( esc_url( $portalSession->url ) );
                                exit;
                            } else {
                                $content = __('Error while creating Customer Portal session', 'wp-full-stripe-free');
                            }
                        } else {
                            $content = $this->renderCustomerAccount($attributes, $model, $session, $cookieAction);
                        }
                    }
                } elseif ($this->hasRecordedStripeCustomerWithAnotherEmail($user->user_email)) {
                    $content = $this->getEmailChangedInStripeMessage();
                } else {
                    $content = __('You haven\'t made any payments yet', 'wp-full-stripe-free');
                }
            } else {
                if (!is_null($session)) {
                    $this->invalidateSession($session);

                    $cookieAction = self::COOKIE_ACTION_REMOVE;
                    $this->enqueueCardUpdateScript($cookieAction, $session->hash, null);
                }

                wp_redirect(wp_login_url(get_permalink()));
            }
        } else {
            $session = $this->validateSaaSSession($this->getSessionByCookie());
            // Every session names a customer, which may have another email in Stripe now.
            if (!is_null($session)) {
                $sessionCustomer = $this->checkSessionCustomer($session);
                if (is_null($sessionCustomer)) {
                    return $this->getStripeUnreachableMessage();
                }
                if (false === $sessionCustomer) {
                    $session = null;
                }
            }
            $cookieAction = $this->determineCookieAction($session);

            $model = new CustomerPortalModel();
            $model->setAuthenticationType(CustomerPortalModel::AUTHENTICATION_TYPE_PLUGIN);
            $model->setStripeClient($this->stripe);

            // Confirmed only once Stripe has answered that the customer checked above is the email's only account, so
            // there is nothing to pick. A read failing after this only loads the account to show, and the session stays
            // confirmed for the next page load.
            if ($this->isWaitingForAccountSelection($session)) {
                if (!$this->isAccountSelectionNeeded($session)) {
                    $this->confirmSession($session);
                }
            }

            $templateToShow = $this->getTemplateToShowBySession($session);

            if (self::TEMPLATE_ENTER_EMAIL_ADDRESS === $templateToShow) {
                $this->enqueueCardUpdateScript($cookieAction, is_null($session) ? null : $session->hash, $model);
                $content = $this->renderEmailForm($attributes);
            } elseif (self::TEMPLATE_ENTER_SECURITY_CODE === $templateToShow) {
                $this->enqueueCardUpdateScript($cookieAction, is_null($session) ? null : $session->hash, $model);
                $content = $this->renderSecurityCodeForm($attributes);
            } elseif (self::TEMPLATE_SELECT_ACCOUNT === $templateToShow) {
                $this->fetchAccountDataIntoCustomerPortalModel($model, $session->email);

                $this->enqueueCardUpdateScript($cookieAction, is_null($session) ? null : $session->hash, $model);
                $content = $this->renderSelectAccountForm($attributes, $model);
            } elseif (self::TEMPLATE_CUSTOMER_PORTAL === $templateToShow) {
                if ( $useStripePortal ) {
                    $returnUrl = get_permalink();
                    $portalSession = $this->stripe->createCustomerPortalSession( $session->stripeCustomerId, $returnUrl );

                    if ( isset( $portalSession->url ) && ! empty( $portalSession->url ) ) {
                        $this->invalidateSession($session);
                        wp_redirect( esc_url( $portalSession->url ) );
                        exit;
                    } else {
                        $content = __('Error while creating Customer Portal session', 'wp-full-stripe-free');
                    }
                } else {
                    $content = $this->renderCustomerAccount($attributes, $model, $session, $cookieAction);
                }
            } else {
                $content = $this->renderInvalidSession($attributes);
            }
        }

        return $content;
    }

    private static function isWordpressAuthenticationNeeded($shortcode_attributes)
    {
        $res = false;

        if (isset($shortcode_attributes) && is_array($shortcode_attributes)) {
            if (
                array_key_exists(self::SHORTCODE_ATTRIBUTE_NAME_AUTHENTICATION, $shortcode_attributes) &&
                $shortcode_attributes[self::SHORTCODE_ATTRIBUTE_NAME_AUTHENTICATION] === self::SHORTCODE_ATTRIBUTE_VALUE_AUTHENTICATION
            ) {
                $res = true;
            }
        }

        return $res;
    }

    /**
     * @return null|string
     */
    private function findSessionCookieValue()
    {
        return isset($_COOKIE[self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID]) ? sanitize_text_field($_COOKIE[self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID]) : null;
    }

    /**
     * @param $customerPortalSessionHash
     *
     * @return array|null|object
     */
    private function findCustomerPortalSessionByHash($customerPortalSessionHash)
    {
        return $this->db->findCustomerPortalSessionByHash($customerPortalSessionHash);
    }

    /**
     * @param $cardUpdateSession
     *
     * @return bool
     */
    private function isConfirmed($cardUpdateSession)
    {
        if (isset($cardUpdateSession) && isset($cardUpdateSession->status)) {
            return self::SESSION_STATUS_CONFIRMED === $cardUpdateSession->status;
        } else {
            return false;
        }
    }

    /**
     * @param $session
     */
    private function invalidateSession($session)
    {
        $session->status = self::SESSION_STATUS_INVALIDATED;

        $this->db->updateCustomerPortalSession($session->id, ['status' => self::SESSION_STATUS_INVALIDATED]);
    }

    /**
     * Whether the session still belongs to its customer: that customer still has the session's email in Stripe. A
     * session whose customer has another email now, which may be someone else's, is ended (#791).
     *
     * @param \stdClass $session
     * @return bool|null Null when Stripe can't be reached, which says nothing about the email, so the session is kept.
     */
    private function checkSessionCustomer($session): ?bool
    {
        try {
            $stripeCustomer = $this->stripe->retrieveCustomer($session->stripeCustomerId);
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Cannot retrieve the customer of the customer portal session', $ex);

            return null;
        }

        if ((!isset($stripeCustomer->deleted) || !$stripeCustomer->deleted) && $this->isStripeCustomerEmail($stripeCustomer, $session->email)) {
            return true;
        }
        $this->invalidateSession($session);

        return false;
    }

    /**
     * Whether the session is confirmed and still belongs to its customer.
     *
     * @param \stdClass $session
     * @throws StripeUnreachableException When Stripe can't tell, which says nothing against the session.
     */
    private function isConfirmedSessionOfItsCustomer($session): bool
    {
        if (!$this->isConfirmed($session)) {
            return false;
        }
        $sessionCustomer = $this->checkSessionCustomer($session);
        if (is_null($sessionCustomer)) {
            throw new StripeUnreachableException($this->getStripeUnreachableMessage());
        }

        return $sessionCustomer;
    }

    /**
     * The session's cards, subscriptions and invoices.
     *
     * @param array<string, string>|string $attributes The shortcode's attributes.
     * @param CustomerPortalModel $model
     * @param \stdClass $session A confirmed session whose customer was checked.
     * @param self::COOKIE_ACTION_*|null $cookieAction
     */
    private function renderCustomerAccount($attributes, $model, $session, $cookieAction): string
    {
        $stripeCustomer = $this->stripe->retrieveCustomerWithParams($session->stripeCustomerId, ['expand' => ['sources']]);
        $this->fetchDataIntoCustomerPortalModel($model, $stripeCustomer);

        $this->enqueueCardUpdateScript($cookieAction, $session->hash, $model);

        return $this->renderCardsAndSubscriptionsTable($attributes, $model);
    }

    /**
     * Shown in place of the portal while Stripe can't be reached to check the session's customer or load its account.
     */
    private function getStripeUnreachableMessage(): string
    {
        return
            /* translators: Customer portal message when Stripe can't be reached to load the account */
            __('Your account can\'t be loaded right now. Please try again in a few minutes.', 'wp-full-stripe-free');
    }

    /**
     * Tells someone whose earlier payments belong to a customer that now has another email in Stripe why the portal
     * doesn't show them, without that email (#791).
     */
    private function getEmailChangedInStripeMessage(): string
    {
        return
            /* translators: Customer portal message when the payments made with the email belong to a billing account that now has another email */
            __('We found earlier payments made with your email address, but the billing account they belong to now uses a different email address. To see it here, change your email address to the one on your billing account, or contact us.', 'wp-full-stripe-free');
    }

    /**
     * @param $wpUser
     * @param $stripeCustomer
     *
     * @return null
     */
    private function createCardUpdateSessionForWPUser($wpUser, $stripeCustomer)
    {
        $liveMode = $this->options->get(Options::OPTION_API_MODE) === Main::STRIPE_API_MODE_LIVE;
        $cardUpdateSession = $this->createSession($wpUser->user_email, $liveMode, $stripeCustomer->id);

        return $cardUpdateSession;
    }

    public function createSession($stripeCustomerEmail, $liveMode, $stripeCustomerId)
    {

        $salt = wp_generate_password(16, false);
        $data = time() . '|' . $stripeCustomerEmail . '|' . $liveMode . '|' . $stripeCustomerId . '|' . $salt;

        $sessionHash = hash('sha256', $data);

        $insertResult = $this->db->insertCustomerPortalSession($stripeCustomerEmail, $liveMode, $stripeCustomerId, $sessionHash);

        if ($insertResult !== -1) {
            return $this->findValidSessionById($insertResult);
        }

        return null;
    }

    private function findValidSessionById($sessionId)
    {
        $sessions = $this->db->findCustomerPortalSessionsById($sessionId);

        $validSession = null;
        if (isset($sessions)) {
            foreach ($sessions as $session) {
                if (is_null($validSession) && !$this->isInvalidatedSession($session)) {
                    $validSession = $session;
                }
            }
        }

        return $validSession;
    }

    /**
     * @param $cardUpdateSession
     *
     * @return bool
     */
    private function isInvalidatedSession($cardUpdateSession)
    {
        if (isset($cardUpdateSession) && isset($cardUpdateSession->status)) {
            return self::SESSION_STATUS_INVALIDATED === $cardUpdateSession->status;
        } else {
            return false;
        }
    }

    /**
     * @param $session
     *
     * @return false|int
     */
    private function confirmSession($session)
    {
        $session->status = self::SESSION_STATUS_CONFIRMED;

        return $this->db->updateCustomerPortalSession($session->id, ['status' => $session->status]);
    }

    /**
     * @param $session
     *
     * @return false|int
     */
    private function makeSessionWaitingForAccountSelection($session)
    {
        $session->status = self::SESSION_STATUS_WAITING_FOR_ACCOUNT_SELECTION;

        return $this->db->updateCustomerPortalSession($session->id, ['status' => $session->status]);
    }

    /**
     * @param string $email
     * @return list<\StripeWPFS\Stripe\Customer|\stdClass>
     * @throws \Exception When Stripe can't list the customers, so that no account is missing from the list.
     */
    private function getActiveStripeCustomersByEmail($email)
    {
        $result = [];

        $stripeCustomers = $this->stripe->listCustomersByEmail($email);
        foreach ($stripeCustomers as $stripeCustomer) {
            if (isset($stripeCustomer) && (!isset($stripeCustomer->deleted) || !$stripeCustomer->deleted)) {
                array_push($result, $stripeCustomer);
            }
        }

        return $result;
    }

    /**
     * @param $subscriptions
     * @return array \StripeWPFS\Stripe\Subscription
     */
    private function filterOutIncompleteSubscriptions($subscriptions)
    {
        $result = [];

        if (isset($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                switch ($subscription->status) {
                    case \StripeWPFS\Stripe\Subscription::STATUS_ACTIVE:
                    case \StripeWPFS\Stripe\Subscription::STATUS_PAST_DUE:
                    case \StripeWPFS\Stripe\Subscription::STATUS_UNPAID:
                    case \StripeWPFS\Stripe\Subscription::STATUS_CANCELED:
                    case \StripeWPFS\Stripe\Subscription::STATUS_TRIALING:
                        array_push($result, $subscription);
                        break;

                    default:
                        //-- Skip other subscription statuses
                        break;
                }
            }
        }

        return $result;
    }

    /**
     * @param CustomerPortalModel $model
     * @param $email
     * @return void
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function fetchAccountDataIntoCustomerPortalModel($model, $email)
    {
        $accounts = [];
        $stripeCustomers = $this->getActiveStripeCustomersByEmail($email);

        /* @var $stripeCustomer \StripeWPFS\Stripe\Customer */
        foreach ($stripeCustomers as $stripeCustomer) {
            $filterParams = [
                'customer' => $stripeCustomer->id,
                'limit' => 100
            ];
            $stripeSubscriptions = $this->filterOutIncompleteSubscriptions($this->stripe->listSubscriptionsWithParams($filterParams));

            $account = new CustomerPortalAccount();
            $account->setStripeCustomerId($stripeCustomer->id);
            $account->setName($stripeCustomer->name);
            $account->setCreatedAt($stripeCustomer->created);
            $account->setCreatedAtLabel(Utils::formatTimestampWithWordpressDateTimeFormat($stripeCustomer->created));
            $account->setNumberOfSubscriptions(count($stripeSubscriptions));

            array_push($accounts, $account);
        }

        $model->setAccountEmail($email);
        $model->setAccounts($accounts);
        $model->setAccountSelectorNeeded(true);
    }

    /**
     * @param CustomerPortalModel $model
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     */
    private function fetchDataIntoCustomerPortalModel($model, $stripeCustomer)
    {
        /**
         * @var null|\StripeWPFS\Stripe\PaymentMethod
         */
        $defaultPaymentMethod = null;
        /**
         * @var null|\StripeWPFS\Stripe\Source
         */
        $defaultSource = null;
        $this->productLookup = new ProductLookup($this->stripe, $this->logger);
        $this->subscriptionPlans = null;

        if (isset($stripeCustomer)) {
            $model->setStripeCustomer($stripeCustomer);

            $paymentMethodParams = [
                'customer' => $stripeCustomer->id,
                'type' => 'card'
            ];
            $paymentMethods = $this->stripe->listPaymentMethodsWithParams($paymentMethodParams);

            if (isset($paymentMethods) && isset($paymentMethods->data)) {
                foreach ($paymentMethods->data as $paymentMethod) {
                    if (is_null($defaultPaymentMethod)) {
                        if ('card' === $paymentMethod->type && $paymentMethod->id == $stripeCustomer->invoice_settings->default_payment_method) {
                            $defaultPaymentMethod = $paymentMethod;
                        }
                    }
                }
            }
            if (isset($stripeCustomer->sources) && isset($stripeCustomer->sources->data)) {
                foreach ($stripeCustomer->sources->data as $source) {
                    if (is_null($defaultSource)) {
                        if ($source->id == $stripeCustomer->default_source) {
                            if ($source->object == 'card') {
                                $defaultSource = $source;
                            } elseif (isset($source->card)) {
                                $defaultSource = $source->card;
                            }
                        }
                    }
                }
            }
        }
        if (isset($defaultPaymentMethod)) {
            $model->setDefaultPaymentMethod($defaultPaymentMethod);
        }
        if (isset($defaultSource)) {
            $model->setDefaultSource($defaultSource);
        }
        $card = $this->getCurrentCard($model);
        $this->updateModelWithCard($model, $card);

        if ($this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS)) {
            $model->setShowSubscriptions(true);
            $model->setSubscriptions($this->prepareSubscriptions($stripeCustomer));
        } else {
            $model->setShowSubscriptions(false);
            $model->setSubscriptions([]);
        }
        if ($this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION)) {
            $this->prepareInvoices($model, $stripeCustomer);
        } else {
            $model->setInvoices([]);
        }

        $stripeCustomers = $this->getActiveStripeCustomersByEmail($stripeCustomer->email);
        $model->setAccountSelectorNeeded(count($stripeCustomers) > 1);

    }

    /**
     * @param CustomerPortalModel $model
     *
     * @return mixed
     */
    private function getCurrentCard($model)
    {
        $card = null;
        if (!is_null($model->getDefaultPaymentMethod())) {
            $card = $model->getDefaultPaymentMethod()->card;
        } elseif (!is_null($model->getDefaultSource())) {
            $card = $model->getDefaultSource();
        }

        return $card;

    }

    /**
     * @param CustomerPortalModel $model
     * @param \StripeWPFS\Stripe\Card|\StripeWPFS\Stripe\Source $card
     */
    private function updateModelWithCard($model, $card)
    {
        if (!is_null($card)) {
            $model->setCardNumber($card->last4);
            if ($this->isAmericanExpress($card)) {
                $model->setCardName(self::CARD_AMERICAN_EXPRESS);
                $model->setCardImageUrl(Assets::images('amex.png'));
            } elseif ($this->isDinersClub($card)) {
                $model->setCardName(self::CARD_DINERS_CLUB);
                $model->setCardImageUrl(Assets::images('diners-club.png'));
            } elseif ($this->isDiscover($card)) {
                $model->setCardName(self::CARD_DISCOVER);
                $model->setCardImageUrl(Assets::images('discover.png'));
            } elseif ($this->isJCB($card)) {
                $model->setCardName(self::CARD_JCB);
                $model->setCardImageUrl(Assets::images('jcb.png'));
            } elseif ($this->isMasterCard($card)) {
                $model->setCardName(self::CARD_MASTERCARD);
                $model->setCardImageUrl(Assets::images('mastercard.png'));
            } elseif ($this->isUnionPay($card)) {
                $model->setCardName(self::CARD_UNIONPAY);
                $model->setCardImageUrl(Assets::images('unionpay.png'));
            } elseif ($this->isVisa($card)) {
                $model->setCardName(self::CARD_VISA);
                $model->setCardImageUrl(Assets::images('visa.png'));
            } elseif ($this->isUnknownCard($card)) {
                $model->setCardName(self::CARD_UNKNOWN);
                $model->setCardImageUrl(Assets::images('generic.png'));
            } else {
                $model->setCardName(self::CARD_UNKNOWN);
                $model->setCardImageUrl(Assets::images('generic.png'));
            }
        }
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isAmericanExpress($card)
    {
        return $this->isBrandOf(self::CARD_AMERICAN_EXPRESS, $card->brand);
    }

    /**
     * Checks if a \StripeWPFS\Stripe\Card's brand is matching a known brand stripped and lowercased.
     *
     * @param $knownBrand
     * @param $currentBrand
     *
     * @return bool
     */
    private function isBrandOf($knownBrand, $currentBrand)
    {
        $strippedKnownBrand = trim(preg_replace('/\s+/', ' ', $knownBrand));
        $strippedCurrentBrand = trim(preg_replace('/\s+/', ' ', $currentBrand));

        return strtolower($strippedKnownBrand) === strtolower($strippedCurrentBrand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isDinersClub($card)
    {
        return $this->isBrandOf(self::CARD_DINERS_CLUB, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isDiscover($card)
    {
        return $this->isBrandOf(self::CARD_DISCOVER, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isJCB($card)
    {
        return $this->isBrandOf(self::CARD_JCB, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isMasterCard($card)
    {
        return $this->isBrandOf(self::CARD_MASTERCARD, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isUnionPay($card)
    {
        return $this->isBrandOf(self::CARD_UNIONPAY, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isVisa($card)
    {
        return $this->isBrandOf(self::CARD_VISA, $card->brand);
    }

    /**
     * @param \StripeWPFS\Stripe\Card $card
     *
     * @return bool
     */
    private function isUnknownCard($card)
    {
        return $this->isBrandOf(self::CARD_UNKNOWN, $card->brand);
    }

    /**
     * @param $subscriptions
     */
    private function prepareSubscriptionsWithProducts($subscriptions)
    {
        $result = [];

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->items->data as $item) {
                $item->price->productExpanded = $this->getProductLookup()->find($item->price->product);
            }

            $result[] = $subscription;
        }

        return $result;
    }

    /**
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     *
     * @return array
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function prepareSubscriptions($stripeCustomer)
    {
        $subscriptions = [];
        if (isset($stripeCustomer)) {
            $subscriptionParams = [
                'customer' => $stripeCustomer->id,
                'limit' => 100,
                'expand' => [
                    'data.items.data.price'
                ]
            ];

            $customerSubscriptions = $this->filterOutIncompleteSubscriptions($this->stripe->listSubscriptionsWithParams($subscriptionParams));

            $subscriptions = $this->prepareSubscriptionsWithProducts($customerSubscriptions);

        }
        return $subscriptions;
    }

    /**
     * @return ProductLookup
     */
    private function getProductLookup()
    {
        if (is_null($this->productLookup)) {
            $this->productLookup = new ProductLookup($this->stripe, $this->logger);
        }

        return $this->productLookup;
    }

    /**
     * The account's recurring prices, listed once per portal page rather than once per subscription.
     *
     * @return array<int, object>
     */
    private function getSubscriptionPlans()
    {
        if (is_null($this->subscriptionPlans)) {
            $this->subscriptionPlans = $this->stripe->getSubscriptionPlans();
        }

        return $this->subscriptionPlans;
    }

    /**
     * Loads the customer's paid invoices into the model, most recently paid first.
     *
     * @param CustomerPortalModel $model
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     *
     * @throws \StripeWPFS\Stripe\Exception\ApiErrorException
     */
    private function prepareInvoices($model, $stripeCustomer): void
    {
        $invoices = [];
        $hasMore = false;
        if (isset($stripeCustomer)) {
            $invoiceParams = [
                'customer' => $stripeCustomer->id,
                'status' => \StripeWPFS\Stripe\Invoice::STATUS_PAID,
                'limit' => self::INVOICE_LIST_PAGE_SIZE
            ];

            // Stripe lists the newest created first, and the latest payment can be on any page.
            do {
                $page = $this->stripe->listInvoicesPageWithParams($invoiceParams);
                $invoices = array_merge($invoices, $page->data);
                $hasMore = !empty($page->has_more) && !empty($page->data);
                if ($hasMore) {
                    $invoiceParams['starting_after'] = end($page->data)->id;
                }
            } while ($hasMore && count($invoices) < self::INVOICE_LIST_MAX_INVOICES);

            if ($hasMore) {
                $this->logger->warning(
                    __FUNCTION__,
                    sprintf(
                        'Customer %s has more than %d paid invoices, the customer portal lists the %d most recent ones.',
                        $stripeCustomer->id,
                        self::INVOICE_LIST_MAX_INVOICES,
                        self::INVOICE_LIST_MAX_INVOICES
                    )
                );
            }
        }

        $model->setInvoices($this->sortInvoicesByPaymentDate($invoices));
        $model->setInvoiceListTruncated($hasMore);
    }

    /**
     * Most recently paid first, so an older invoice paid late tops the list.
     * Invoices paid at the same time keep Stripe's order, newest created first.
     *
     * @param object[] $invoices
     *
     * @return object[]
     */
    private function sortInvoicesByPaymentDate(array $invoices)
    {
        usort($invoices, function ($a, $b) {
            return [ManagedInvoiceEntry::getPaymentTimestamp($b), $b->created, $b->id]
                <=> [ManagedInvoiceEntry::getPaymentTimestamp($a), $a->created, $a->id];
        });

        return $invoices;
    }

    /**
     * @param $subscriptionId
     *
     * @return int
     */
    private function getDonationAmount($subscriptionId)
    {
        $donationAmount = 0;

        $donationRecord = $this->db->getDonationByStripeSubscriptionId($subscriptionId);
        if (!is_null($donationRecord)) {
            $donationAmount = $donationRecord->amount;
        }

        return $donationAmount;
    }

    /**
     * @param $subscriptions array
     * @param $managedSubscriptions array
     */
    private function buildManagedSubscriptionsArray($subscriptions, &$managedSubscriptions)
    {

        foreach ($subscriptions as $subscription) {
            // A Checkout donation prices its lines inline, so only its donation row says it's one.
            $donationAmount = $this->getDonationAmount($subscription->id);

            // A plan with a recovery fee line is listed as its plan; its products were looked up beforehand.
            $managedSubscriptionEntry = new ManagedSubscriptionEntry($subscription, $donationAmount, RecoveryFee::findFeeRecoveryOfSubscription($subscription));

            $availablePlansForSubscription = $managedSubscriptionEntry->canChangePlan() ? $this->findAvailablePlansByForm($managedSubscriptionEntry->getSubscriptionFormName(), $managedSubscriptionEntry->getPlanId()) : [];

            $model = $managedSubscriptionEntry->toModel($this->getPriceAndIntervalLabelForPlans($availablePlansForSubscription, $subscription, $managedSubscriptionEntry->getPlanItem(), $managedSubscriptionEntry->hasRecoveryFee()));
            array_push($managedSubscriptions, $model);
        }
    }

    /**
     * @param \StripeWPFS\Stripe\Invoice $invoice
     * @return bool
     */
    private function shouldDisplayInvoice($invoice)
    {
        return $invoice->total !== 0;
    }

    /**
     * @param $cookieAction
     * @param $cardUpdateSessionHash
     * @param CustomerPortalModel|null $model
     */
    private function enqueueCardUpdateScript($cookieAction, $cardUpdateSessionHash, $model)
    {
        $this->logger->debug(
            __FUNCTION__,
            'enqueueCardUpdateScript() CALLED, cookieAction=' . $cookieAction
            . ', cardUpdateSessionHash=' . $cardUpdateSessionHash
            . ', subscriptions=' . (is_null($model) ? '0' : count($model->getSubscriptions()))
        );

        wp_register_style(Main::HANDLE_STYLE_WPFS_VARIABLES, Assets::css('wpfs-variables.css'), null, Main::VERSION);
        wp_enqueue_style(self::HANDLE_MANAGE_SUBSCRIPTIONS_CSS, Assets::css(self::ASSET_WPFS_CUSTOMER_PORTAL_CSS), [Main::HANDLE_STYLE_WPFS_VARIABLES], Main::VERSION);

        wp_register_script(self::HANDLE_STRIPE_JS_V_3, 'https://js.stripe.com/v3/', ['jquery']);
        wp_register_script(Main::HANDLE_WP_FULL_STRIPE_UTILS_JS, Assets::scripts('wpfs-utils.js'), ['wp-i18n'], Main::VERSION);

        if (ReCaptcha::getSecureCustomerPortal($this->staticContext)) {
            $source = add_query_arg(
                [
                    'render' => 'explicit'
                ],
                Main::SOURCE_GOOGLE_RECAPTCHA_V2_API_JS
            );
            wp_register_script(Main::HANDLE_GOOGLE_RECAPTCHA_V_2, $source, [], Main::VERSION, true /* in footer */);
            $dependencies = [
                'jquery',
                'jquery-ui-core',
                'jquery-ui-spinner',
                'jquery-ui-selectmenu',
                'underscore',
                'backbone',
                'wp-i18n',
                Main::HANDLE_WP_FULL_STRIPE_UTILS_JS,
                self::HANDLE_STRIPE_JS_V_3,
                Main::HANDLE_GOOGLE_RECAPTCHA_V_2
            ];
        } else {
            $dependencies = [
                'jquery',
                'jquery-ui-core',
                'jquery-ui-spinner',
                'jquery-ui-selectmenu',
                'underscore',
                'backbone',
                'wp-i18n',
                Main::HANDLE_WP_FULL_STRIPE_UTILS_JS,
                self::HANDLE_STRIPE_JS_V_3
            ];
        }

        wp_enqueue_script(
            self::HANDLE_MANAGE_SUBSCRIPTIONS_JS,
            Assets::scripts(self::ASSET_WPFS_CUSTOMER_PORTAL_JS),
            $dependencies,
            Main::VERSION
        );
        wp_set_script_translations(self::HANDLE_MANAGE_SUBSCRIPTIONS_JS, 'wp-full-stripe-free');

        $wpfsCustomerPortalSettings = [
            self::JS_VARIABLE_AJAX_URL => admin_url('admin-ajax.php'),
            self::JS_VARIABLE_REST_URL => get_rest_url(null),
            self::JS_VARIABLE_GOOGLE_RECAPTCHA_SITE_KEY => ReCaptcha::getSiteKey($this->staticContext)
        ];

        $apiMode = $this->options->get(Options::OPTION_API_MODE);
        $isTestMode = $apiMode === Main::STRIPE_API_MODE_TEST;
        $isTestPlatform = $this->options->get(Options::OPTION_USE_WP_TEST_PLATFORM);
        $isLivePlatform = $this->options->get(Options::OPTION_USE_WP_LIVE_PLATFORM);

        $wpfsCustomerPortalSettings[self::JS_VARIABLE_STRIPE_ACCOUNT_ID] = $isTestMode
            ? $this->options->get(Options::OPTION_TEST_ACCOUNT_ID)
            : $this->options->get(Options::OPTION_LIVE_ACCOUNT_ID);

        if ($apiMode === Main::STRIPE_API_MODE_TEST && $isTestPlatform) {
            $wpfsCustomerPortalSettings[self::JS_VARIABLE_STRIPE_KEY] = Options::OPTION_TEST_STRIPE_PLATFORM_PK;
        } elseif ($apiMode === Main::STRIPE_API_MODE_LIVE && $isLivePlatform) {
            $wpfsCustomerPortalSettings[self::JS_VARIABLE_STRIPE_KEY] = Options::OPTION_LIVE_STRIPE_PLATFORM_PK;
        } else {
            $wpfsCustomerPortalSettings[self::JS_VARIABLE_STRIPE_KEY] = $isTestMode
                ? $this->options->get(Options::OPTION_API_TEST_PUBLISHABLE_KEY)
                : $this->options->get(Options::OPTION_API_LIVE_PUBLISHABLE_KEY);
        }

        $sessionData = [];
        $wpfsMyAccountOptions = [];

        // Converting the string ('0' or '1') to int, it makes dealing with it in javascript easier
        $wpfsMyAccountOptions['showSubscriptionsToCustomers'] = $this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS) + 0;
        $wpfsMyAccountOptions['letSubscribersCancelSubscriptions'] = $this->options->get(Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS) + 0;
        $wpfsMyAccountOptions['letSubscribersUpdowngradeSubscriptions'] = $this->options->get(Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_UPDOWNGRADE_SUBSCRIPTIONS) + 0;
        $wpfsMyAccountOptions['scrollingPaneIntoView'] = $this->options->get(Options::OPTION_CUSTOMER_PORTAL_SCROLLING_PANE_INTO_VIEW) + 0;
        if ($this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS)) {
            $stripeSubscriptions = [];
            if (!is_null($model)) {
                $this->buildManagedSubscriptionsArray($model->getSubscriptions(), $stripeSubscriptions);
            }
            $sessionData['stripe']['subscriptions'] = $stripeSubscriptions;
            $wpfsMyAccountOptions['showSubscriptionsSection'] = 1;
        } else {

            $wpfsMyAccountOptions['showSubscriptionsSection'] = 0;
        }

        if ($this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION)) {
            $invoices = [];
            if (!is_null($model)) {
                foreach ($model->getInvoices() as $invoice) {
                    if ($this->shouldDisplayInvoice($invoice)) {
                        array_push($invoices, $invoice);
                    }
                }
            }

            if (count($invoices) <= self::INVOICE_DISPLAY_HEAD_LIMIT) {
                $wpfsMyAccountOptions['invoiceDisplayMode'] = self::INVOICE_DISPLAY_MODE_FEW;
            } elseif (!empty($cardUpdateSessionHash) && get_transient($this->getInvoiceViewTransientKey($cardUpdateSessionHash))) {
                $wpfsMyAccountOptions['invoiceDisplayMode'] = self::INVOICE_DISPLAY_MODE_ALL;
            } else {
                $wpfsMyAccountOptions['invoiceDisplayMode'] = self::INVOICE_DISPLAY_MODE_HEAD;
                $invoices = array_slice($invoices, 0, self::INVOICE_DISPLAY_HEAD_LIMIT);
            }

            // Only the invoices shown need their products looked up.
            $stripeInvoices = [];
            foreach ($invoices as $invoice) {
                $managedInvoiceEntry = new ManagedInvoiceEntry($invoice, $this->getProductLookup());
                array_push($stripeInvoices, $managedInvoiceEntry->toModel());
            }

            $sessionData['stripe']['invoices'] = $stripeInvoices;
            $wpfsMyAccountOptions['showInvoicesSection'] = 1;
        } else {
            $wpfsMyAccountOptions['showInvoicesSection'] = 0;
        }

        if (self::COOKIE_ACTION_SET === $cookieAction) {
            $sessionData['action'] = 'setCookie';
            $sessionData['cookieName'] = self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID;
            $sessionData['cookieValidUntilHours'] = self::CARD_UPDATE_SESSION_VALID_UNTIL_HOURS;
            $sessionData['cookiePath'] = COOKIEPATH;
            $sessionData['cookieDomain'] = COOKIE_DOMAIN;
        } elseif (self::COOKIE_ACTION_REMOVE === $cookieAction) {
            $sessionData['cookieName'] = self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID;
            $sessionData['action'] = 'removeCookie';
        }
        if (!is_null($cardUpdateSessionHash)) {
            $sessionData['sessionId'] = $cardUpdateSessionHash;
        }

        $wpfsCustomerPortalSettings['sessionData'] = $sessionData;
        $wpfsCustomerPortalSettings['preferences'] = $wpfsMyAccountOptions;

        wp_localize_script(self::HANDLE_MANAGE_SUBSCRIPTIONS_JS, 'wpfsCustomerPortalSettings', $wpfsCustomerPortalSettings);
    }

    /**
     * @param $attributes
     * @param CustomerPortalModel $model
     *
     * @return string
     */
    public function renderCardsAndSubscriptionsTable($attributes, $model)
    {
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include Assets::templates(self::ASSET_DIR_CUSTOMER_PORTAL . DIRECTORY_SEPARATOR . self::ASSET_CUSTOMER_PORTAL_PHP);
        $content = ob_get_clean();

        return $content;
    }

    /**
     * @param $session
     *
     * @return bool
     */
    private function isWaitingForConfirmation($session)
    {
        if (isset($session) && isset($session->status)) {
            return self::SESSION_STATUS_WAITING_FOR_CONFIRMATION === $session->status;
        } else {
            return false;
        }
    }

    /**
     * @param $session
     *
     * @return bool
     */
    private function isWaitingForAccountSelection($session)
    {
        if (isset($session) && isset($session->status)) {
            return self::SESSION_STATUS_WAITING_FOR_ACCOUNT_SELECTION === $session->status;
        } else {
            return false;
        }
    }

    /**
     * @param $cardUpdateSession
     *
     * @return bool
     */
    private function securityCodeInputExhausted($cardUpdateSession)
    {
        if (isset($cardUpdateSession) && isset($cardUpdateSession->securityCodeInput)) {
            return $cardUpdateSession->securityCodeInput >= self::SECURITY_CODE_INPUT_LIMIT;
        }

        return true;
    }

    /**
     * @param $cardUpdateSession
     */
    private function incrementSecurityCodeInput($cardUpdateSession)
    {
        $this->db->incrementSecurityCodeInput($cardUpdateSession->id);
    }

    /**
     * @param $cardUpdateSession
     * @param $securityCode
     *
     * @return array
     */
    public function validateSecurityCode($cardUpdateSession, $securityCode)
    {
        $valid = false;
        $matchingSecurityCode = null;
        if (isset($cardUpdateSession) && isset($securityCode)) {
            $sanitizedSecurityCode = sanitize_text_field($securityCode);
            $matchingSecurityCode = $this->db->find_security_code_by_session_and_code($cardUpdateSession->id, $sanitizedSecurityCode);
            if (!is_null($matchingSecurityCode) && $matchingSecurityCode->status !== self::SECURITY_CODE_STATUS_CONSUMED) {
                $valid = true;
            }
        }

        if (Utils::isDemoMode()) {
            $valid = true;
        }

        return ['valid' => $valid, 'securityCode' => $matchingSecurityCode];
    }

    /**
     * @param $customerPortalSession
     * @param $matchingSecurityCode
     */
    private function confirmCustomerPortalSessionWithSecurityCode($customerPortalSession, $matchingSecurityCode)
    {
        $this->db->updateCustomerPortalSession($customerPortalSession->id, ['status' => self::SESSION_STATUS_WAITING_FOR_ACCOUNT_SELECTION]);
        $this->db->updateSecurityCode($matchingSecurityCode->id, [
            'consumed' => current_time('mysql'),
            'status' => self::SECURITY_CODE_STATUS_CONSUMED
        ]
        );
    }

    public function renderEmailForm($attributes)
    {

        ob_start();
        /** @noinspection PhpIncludeInspection */
        include Assets::templates(self::ASSET_DIR_CUSTOMER_PORTAL . DIRECTORY_SEPARATOR . self::ASSET_ENTER_EMAIL_ADDRESS_PHP);
        $content = ob_get_clean();

        return $content;

    }

    public function renderSecurityCodeForm($attributes)
    {
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include Assets::templates(self::ASSET_DIR_CUSTOMER_PORTAL . DIRECTORY_SEPARATOR . self::ASSET_ENTER_SECURITY_CODE_PHP);
        $content = ob_get_clean();

        return $content;
    }

    public function renderSelectAccountForm($attributes, $model)
    {
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include Assets::templates(self::ASSET_DIR_CUSTOMER_PORTAL . DIRECTORY_SEPARATOR . self::ASSET_SELECT_ACCOUNT_PHP);
        $content = ob_get_clean();

        return $content;
    }

    public function renderInvalidSession($attributes)
    {
        ob_start();
        /** @noinspection PhpIncludeInspection */
        include Assets::templates(self::ASSET_DIR_CUSTOMER_PORTAL . DIRECTORY_SEPARATOR . self::ASSET_INVALID_SESSION_PHP);
        $content = ob_get_clean();

        return $content;
    }

    public function handleSessionRequest()
    {

        $return = [];

        try {

            $stripeCustomerEmail = isset($_POST[self::PARAM_EMAIL_ADDRESS]) ? sanitize_email($_POST[self::PARAM_EMAIL_ADDRESS]) : null;
            $googleReCAPTCHAResponse = isset($_POST[self::PARAM_GOOGLE_RE_CAPTCHA_RESPONSE]) ? sanitize_text_field($_POST[self::PARAM_GOOGLE_RE_CAPTCHA_RESPONSE]) : null;

            $validRequest = true;
            if (is_null($stripeCustomerEmail) || !filter_var($stripeCustomerEmail, FILTER_VALIDATE_EMAIL)) {
                $return['success'] = false;
                $return['message'] = __('The entered email address is invalid.', 'wp-full-stripe-free');
                $return['fieldError'] = self::PARAM_EMAIL_ADDRESS;
                $validRequest = false;
            }
            $verifyReCAPTCHA = ReCaptcha::getSecureCustomerPortal($this->staticContext);
            if ($verifyReCAPTCHA && $validRequest) {
                if (is_null($googleReCAPTCHAResponse)) {
                    $return['success'] = false;
                    $return['message'] =
                        /* translators: Captcha validation error message displayed when the form is submitted without completing the captcha challenge */
                        __('Please prove that you are not a robot.', 'wp-full-stripe-free');
                    $return['fieldError'] = self::PARAM_GOOGLE_RE_CAPTCHA_RESPONSE;
                    $validRequest = false;
                } else {
                    $googleReCAPTCHVerificationResult = ReCaptcha::verifyReCAPTCHA($this->staticContext, $googleReCAPTCHAResponse);
                    // Utils::log( 'googleReCAPTCHVerificationResult=' . print_r( $googleReCAPTCHVerificationResult, true ) );
                    if ($googleReCAPTCHVerificationResult === false) {
                        $return['success'] = false;
                        $return['message'] =
                            /* translators: Captcha validation error message displayed when the form is submitted without completing the captcha challenge */
                            __('Please prove that you are not a robot.', 'wp-full-stripe-free');
                        $return['fieldError'] = self::PARAM_GOOGLE_RE_CAPTCHA_RESPONSE;
                        $validRequest = false;
                    } elseif (!isset($googleReCAPTCHVerificationResult->success) || $googleReCAPTCHVerificationResult->success === false) {
                        $return['success'] = false;
                        $return['message'] =
                            /* translators: Captcha validation error message displayed when the form is submitted without completing the captcha challenge */
                            __('Please prove that you are not a robot.', 'wp-full-stripe-free');
                        $return['fieldError'] = self::PARAM_GOOGLE_RE_CAPTCHA_RESPONSE;
                        $validRequest = false;
                    }
                }
            }

            if ($validRequest && !$this->claimSecurityCodeRequest($stripeCustomerEmail)) {
                $return['success'] = false;
                $return['message'] = __('Please wait before requesting another security code.', 'wp-full-stripe-free');
                $return['fieldError'] = self::PARAM_EMAIL_ADDRESS;
                $validRequest = false;
            }

            $stripeCustomer = null;
            if ($validRequest) {
                $stripeCustomer = $this->findStripeCustomerByEmail($stripeCustomerEmail);
                if (is_null($stripeCustomer)) {
                    $return['success'] = false;
                    // Anyone can enter any address, so an address whose customer now has another email in Stripe gets this
                    // too: telling it apart would reveal that it paid before and that its billing email changed (#791).
                    $return['message'] = __('The entered email address is invalid.', 'wp-full-stripe-free');
                    $return['fieldError'] = self::PARAM_EMAIL_ADDRESS;
                    $validRequest = false;
                }
            }

            if ($validRequest) {

                $session = $this->findValidSessionByEmailAndCustomer($stripeCustomerEmail, $stripeCustomer->id);

                if (!is_null($session)) {
                    $sessionCookieValue = $this->findSessionCookieValue();
                    if ($session->hash !== $sessionCookieValue) {
                        $session = null;
                    }
                }

                if (is_null($session) || $this->isInvalidatedSession($session)) {
                    $liveMode = $this->options->get(Options::OPTION_API_MODE) === Main::STRIPE_API_MODE_LIVE;
                    $session = $this->createSession($stripeCustomerEmail, $liveMode, $stripeCustomer->id);
                }

                $this->createSessionCookie($session);

                $this->createAndSendSecurityCodeAsEmail($session, $stripeCustomer);

                $return['success'] = true;
            }

        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while validating request/session', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        wp_send_json($return);

    }

    /**
     * Builds the transient key that stores whether a portal session shows all invoices.
     *
     * @param string $cardUpdateSessionHash Customer portal session hash.
     *
     * @return string
     */
    private function getInvoiceViewTransientKey($cardUpdateSessionHash)
    {
        return self::INVOICE_VIEW_TRANSIENT_PREFIX . hash_hmac('sha256', $cardUpdateSessionHash, wp_salt('nonce'));
    }

    /**
     * The current request-limit window, which ends every counter name.
     *
     * @return string
     */
    private function getSecurityCodeRequestWindow()
    {
        return (string) intdiv(time(), self::SECURITY_CODE_REQUEST_WINDOW);
    }

    /**
     * Builds a non-reversible counter name for one request-limit scope in the current window.
     *
     * @param string $scope Limit scope, such as 'client' or 'email'.
     * @param string $value Value the counter belongs to.
     *
     * @return string
     */
    private function getSecurityCodeRequestCounterName($scope, $value)
    {
        return self::SECURITY_CODE_REQUEST_COUNTER_PREFIX
            . hash_hmac('sha256', $scope . '|' . $value, wp_salt('nonce'))
            . '_' . $this->getSecurityCodeRequestWindow();
    }

    /**
     * Claims one login-code request for an email, first from the requester's own limit, then from the email's ceiling.
     *
     * Requests are counted before the customer lookup, so existing and unknown emails share the same limits.
     * The per-client limit keeps one requester from using up the email's ceiling and blocking the customer.
     *
     * @param string $email Customer email address.
     *
     * @return bool Whether the request may send a code.
     */
    private function claimSecurityCodeRequest($email)
    {
        $email = strtolower($email);
        $client = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return $this->db->claimRequestLimitSlot(
            $this->getSecurityCodeRequestCounterName('client', $email . '|' . $client),
            self::SECURITY_CODE_CLIENT_REQUEST_LIMIT
        ) && $this->db->claimRequestLimitSlot(
            $this->getSecurityCodeRequestCounterName('email', $email),
            self::SECURITY_CODE_EMAIL_REQUEST_LIMIT
        );
    }

    private function findValidSessionByEmailAndCustomer($stripeCustomerEmail, $stripeCustomerId)
    {
        $sessions = $this->db->findCustomerPortalSessionsByEmailAndCustomer($stripeCustomerEmail, $stripeCustomerId);

        $validSession = null;
        if (isset($sessions)) {
            foreach ($sessions as $session) {
                if (is_null($validSession) && !$this->isInvalidatedSession($session)) {
                    $validSession = $session;
                }
            }
        }

        return $validSession;
    }

    /**
     * @param $session
     */
    private function createSessionCookie($session)
    {
        if (isset($session)) {
            setcookie(self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID, $session->hash, time() + HOUR_IN_SECONDS, "/", COOKIE_DOMAIN);
        }
    }

    public static function generateSecurityCode()
    {
        return wp_generate_password(8, false);
    }

    private function createAndSendSecurityCodeAsEmail($cardUpdateSession, $stripeCustomer)
    {
        try {
            if (isset($cardUpdateSession) && isset($cardUpdateSession->status)) {
                if (self::SESSION_STATUS_WAITING_FOR_CONFIRMATION === $cardUpdateSession->status) {
                    if (!$this->securityCodeRequestExhausted($cardUpdateSession)) {
                        $securityCode = self::generateSecurityCode();
                        $securityCodeId = $this->db->insert_security_code($cardUpdateSession->id, $securityCode);
                        if ($securityCodeId !== -1) {
                            $this->incrementSecurityCodeRequest($cardUpdateSession);
                            if (!Utils::isDemoMode()) {
                                $transactionData = TransactionDataService::createMyAccountLoginData($stripeCustomer, $cardUpdateSession->hash, $securityCode);
                                $this->mailer->sendMyAccountLoginRequest($transactionData);
                            }
                            $this->db->updateSecurityCode($securityCodeId, [
                                'sent' => current_time('mysql'),
                                'status' => self::SECURITY_CODE_STATUS_SENT
                            ]
                            );
                        }
                    } else {
                        $this->invalidateSession($cardUpdateSession);
                    }
                }
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while creating/sending the security code', $ex);
        }
    }

    /**
     * @param $cardUpdateSession
     *
     * @return bool
     */
    private function securityCodeRequestExhausted($cardUpdateSession)
    {
        if (isset($cardUpdateSession) && isset($cardUpdateSession->securityCodeRequest)) {

            return $cardUpdateSession->securityCodeRequest >= self::SECURITY_CODE_REQUEST_LIMIT;
        }

        return true;
    }

    private function incrementSecurityCodeRequest($cardUpdateSession)
    {
        $this->db->increment_security_code_request($cardUpdateSession->id);
    }

    public function handleResetSessionRequest()
    {
        $return = [];

        try {

            $cardUpdateSessionHash = $this->findSessionCookieValue();
            if (!is_null($cardUpdateSessionHash)) {
                $cardUpdateSession = $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);
                if (!is_null($cardUpdateSession)) {
                    if ($this->isWaitingForAccountSelection($cardUpdateSession) || $this->isConfirmed($cardUpdateSession) || $this->isWaitingForConfirmation($cardUpdateSession)) {
                        $this->invalidateSession($cardUpdateSession);
                    }
                }
            }

            $return['success'] = true;
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while resetting the session', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        header("Content-Type: application/json");
        echo json_encode($return);
        exit;

    }

    public function handleSecurityCodeValidationRequest()
    {
        $return = [];

        try {

            $cardUpdateSessionHash = $this->findSessionCookieValue();
            $securityCode = isset($_POST['securityCode']) ? sanitize_text_field($_POST['securityCode']) : null;
            if (is_null($securityCode) || empty($securityCode)) {
                $return['success'] = false;
                $return['message'] =
                    /* translators: Login form validation error when no security code is entered */
                    __('Enter a security code', 'wp-full-stripe-free');
            } else {
                $success = false;
                if (!is_null($cardUpdateSessionHash)) {
                    $cardUpdateSession = $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);
                    if (!is_null($cardUpdateSession) && $this->isWaitingForConfirmation($cardUpdateSession) && !$this->securityCodeInputExhausted($cardUpdateSession)) {
                        $this->incrementSecurityCodeInput($cardUpdateSession);
                        $validationResult = $this->validateSecurityCode($cardUpdateSession, $securityCode);
                        $valid = $validationResult['valid'];
                        $matchingSecurityCode = $validationResult['securityCode'];
                        if ($valid) {
                            $this->confirmCustomerPortalSessionWithSecurityCode($cardUpdateSession, $matchingSecurityCode);
                            $success = true;
                        } else {
                            $this->deleteCardUpdateSessionCookie();
                        }
                    }
                }

                if ($success) {
                    $return['success'] = true;
                } else {
                    $return['success'] = false;
                    $return['message'] =
                        /* translators: Login form validation error when an invalid security code is entered */
                        __('Enter a valid security code', 'wp-full-stripe-free');
                }
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while validating the security code', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        header("Content-Type: application/json");
        echo json_encode($return);
        exit;
    }

    public function handleSelectAccountRequest()
    {
        $return = [];

        try {
            $stripeCustomerId = isset($_POST['customerId']) ? sanitize_text_field($_POST['customerId']) : null;

            if (empty($stripeCustomerId)) {
                $return['success'] = false;
                $return['message'] =
                    /* translators: Customer portal error when no Stripe customer was selected */
                    __('Select an account', 'wp-full-stripe-free');
            } else {
                $sessionHash = $this->findSessionCookieValue();
                $success = false;

                if (!is_null($sessionHash)) {
                    $session = $this->findCustomerPortalSessionByHash($sessionHash);
                    if (!is_null($session) && $this->isWaitingForAccountSelection($session)) {
                        $stripeCustomers = $this->getActiveStripeCustomersByEmail($session->email);

                        $foundCustomer = false;
                        foreach ($stripeCustomers as $stripeCustomer) {
                            if ($stripeCustomer->id === $stripeCustomerId) {
                                $foundCustomer = true;
                                break;
                            }
                        }

                        if ($foundCustomer) {
                            $session->stripeCustomerId = $stripeCustomerId;
                            $this->db->updateCustomerPortalSession($session->id, ['stripeCustomerId' => $session->stripeCustomerId]);

                            $this->confirmSession($session);

                            $success = true;
                        }
                    }
                }

                if ($success) {
                    $return['success'] = true;
                } else {
                    $return['success'] = false;
                    $return['message'] =
                        /* translators: Login form validation error when an invalid security code is entered */
                        __('Select an account', 'wp-full-stripe-free');
                }
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while selecting account', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        header("Content-Type: application/json");
        echo json_encode($return);
        exit;
    }

    public function handleShowAccountSelectorRequest()
    {
        $return = [];

        try {
            $success = false;

            $sessionHash = $this->findSessionCookieValue();
            if (!is_null($sessionHash)) {
                $session = $this->findCustomerPortalSessionByHash($sessionHash);
                if (!is_null($session) && $this->isConfirmed($session)) {
                    $this->makeSessionWaitingForAccountSelection($session);
                }
            }

            if ($success) {
                $return['success'] = true;
            } else {
                $return['success'] = false;
                $return['message'] =
                    /* translators: Cannot transition the Customer portal to selecting an account because it's in a wrong state  */
                    __('Cannot transition to selecting an account from the current state', 'wp-full-stripe-free');
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while showing accounts', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        header("Content-Type: application/json");
        echo json_encode($return);
        exit;
    }

    private function deleteCardUpdateSessionCookie()
    {
        unset($_COOKIE[self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID]);
        setcookie(self::COOKIE_NAME_WPFS_CARD_UPDATE_SESSION_ID, '', time() - DAY_IN_SECONDS);
    }

    public function handleCardUpdateRequest()
    {
        $return = [];
        try {
            $stripePaymentMethodId = isset($_POST['paymentMethodId']) ? sanitize_text_field($_POST['paymentMethodId']) : null;
            $stripeSetupIntentId = isset($_POST['setupIntentId']) ? sanitize_text_field($_POST['setupIntentId']) : null;

            $this->logger->debug(__FUNCTION__, 'stripePaymentMethodId=' . print_r($stripePaymentMethodId, true));

            $cardUpdateSessionHash = $this->findSessionCookieValue();
            if (!is_null($stripePaymentMethodId) && !is_null($cardUpdateSessionHash)) {
                $cardUpdateSession = $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);
                if (!is_null($cardUpdateSession) && $this->isConfirmedSessionOfItsCustomer($cardUpdateSession)) {
                    $stripeCustomer = $this->stripe->retrieveCustomer($cardUpdateSession->stripeCustomerId);
                    if (isset($stripeCustomer)) {
                        $stripePaymentMethod = $this->stripe->validatePaymentMethodCVCCheck($stripePaymentMethodId);
                        if (isset($stripePaymentMethod)) {
                            $stripeSetupIntent = null;
                            if (is_null($stripeSetupIntentId)) {
                                $stripeSetupIntent = $this->stripe->createSetupIntentWithPaymentMethod($stripePaymentMethod->id, $stripeCustomer->id);
                                $stripeSetupIntentId = $stripeSetupIntent->id;
                                $this->stripe->confirmSetupIntent($stripeSetupIntent);
                            }
                            $stripeSetupIntent = $this->stripe->retrieveSetupIntent($stripeSetupIntentId);
                            if ($stripeSetupIntent && $stripeSetupIntent->status) {
                                $return['setupIntentId'] = $stripeSetupIntent->id;
                                if (
                                    \StripeWPFS\Stripe\SetupIntent::STATUS_REQUIRES_ACTION === $stripeSetupIntent->status
                                    && 'use_stripe_sdk' === $stripeSetupIntent->next_action->type
                                ) {
                                    $this->logger->debug(__FUNCTION__, 'SetupIntent requires action...');

                                    $return['success'] = false;
                                    $return['requiresAction'] = true;
                                    $return['setupIntentClientSecret'] = $stripeSetupIntent->client_secret;
                                    $return['message'] =
                                        /* translators: Banner message of a pending card saving transaction requiring a second factor authentication (SCA/PSD2) */
                                        __('Saving this card requires additional action before completion!', 'wp-full-stripe-free');
                                } elseif (\StripeWPFS\Stripe\SetupIntent::STATUS_SUCCEEDED === $stripeSetupIntent->status) {
                                    $this->logger->debug(__FUNCTION__, 'SetupIntent succeeded.');

                                    $this->stripe->attachPaymentMethodToCustomerIfMissing($stripeCustomer, $stripePaymentMethod, true);
                                    $return['success'] = true;
                                    $return['message'] = __('The default credit card has been updated successfully!', 'wp-full-stripe-free');
                                } else {
                                    $errorMessage = sprintf("Unknown SetupIntent status '%s'!", $stripeSetupIntent->status);
                                    $return['success'] = false;
                                    $return['ex_message'] = sprintf(
                                        /* translators: %s: status of the Stripe SetupIntent */
                                        __("Unknown SetupIntent status '%s'!", 'wp-full-stripe-free'),
                                        $stripeSetupIntent->status
                                    );

                                    $this->logger->error(__FUNCTION__, $errorMessage);
                                }
                            } else {
                                $errorMessage = 'Invalid SetupIntent!';
                                $return['success'] = false;
                                $return['ex_message'] =
                                    /* translators: Error shown when the customer portal can't update the default card */
                                    __('Invalid SetupIntent!', 'wp-full-stripe-free');

                                $this->logger->error(__FUNCTION__, $errorMessage);
                            }
                        } else {
                            $errorMessage = 'Stripe PaymentMethod not found!';
                            $return['success'] = false;
                            $return['ex_message'] =
                                /* translators: Error shown when the customer portal can't update the default card */
                                __('Stripe PaymentMethod not found!', 'wp-full-stripe-free');

                            $this->logger->error(__FUNCTION__, $errorMessage);
                        }
                    } else {
                        $errorMessage = 'Stripe Customer not found!';
                        $return['success'] = false;
                        $return['ex_message'] =
                            /* translators: Error shown when the customer portal can't update the default card */
                            __('Stripe Customer not found!', 'wp-full-stripe-free');

                        $this->logger->error(__FUNCTION__, $errorMessage);
                    }
                } else {
                    $errorMessage = 'Invalid Card Update Session!';
                    $return['success'] = false;
                    $return['ex_message'] =
                        /* translators: Error shown when the customer portal can't update the default card */
                        __('Invalid Card Update Session!', 'wp-full-stripe-free');

                    $this->logger->error(__FUNCTION__, $errorMessage);
                }
            } else {
                $errorMessage = 'Invalid POST parameters!';
                $return['success'] = false;
                $return['ex_message'] =
                    /* translators: Error shown when the customer portal can't update the default card */
                    __('Invalid POST parameters!', 'wp-full-stripe-free');

                $this->logger->error(__FUNCTION__, $errorMessage);
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while updating the default card', $ex);

            $return['success'] = false;
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        wp_send_json($return);

    }

    public function toggleInvoiceView()
    {
        $return = [];
        try {
            $cardUpdateSessionHash = $this->findSessionCookieValue();
            if (!is_null($cardUpdateSessionHash)) {
                $cardUpdateSession = $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);
                if (!$this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION)) {
                    $return['success'] = false;
                    $return['message'] = __('Unauthorized', 'wp-full-stripe-free');
                } elseif (!is_null($cardUpdateSession) && $this->isConfirmedSessionOfItsCustomer($cardUpdateSession)) {
                    // The view belongs to this customer's session, not to the site-wide settings.
                    $invoiceViewKey = $this->getInvoiceViewTransientKey($cardUpdateSessionHash);
                    set_transient(
                        $invoiceViewKey,
                        ((int) get_transient($invoiceViewKey)) ^ 1,
                        self::CARD_UPDATE_SESSION_VALID_UNTIL_HOURS * HOUR_IN_SECONDS
                    );

                    $return['success'] = true;
                } else {
                    $return['success'] = false;
                    $return['message'] =
                        /* translators: Error when the customer portal session hasn't been confirmed with the security code yet */
                        __('Account session is not confirmed.', 'wp-full-stripe-free');
                }
            } else {
                $return['success'] = false;
                $return['message'] =
                    /* translators: Error when the customer portal request has no valid session */
                    __('No valid account session found.', 'wp-full-stripe-free');
            }
        } catch (StripeUnreachableException $ex) {
            $return['success'] = false;
            $return['message'] = $ex->getMessage();
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while toggling invoice view', $ex);

            $return['success'] = false;
            $return['message'] =
                /* translators: Error when switching between recent and all invoices in the customer portal fails */
                __('Invoice view toggle failed.', 'wp-full-stripe-free');
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        wp_send_json($return);
    }

    /**
     * @param object $subscription the Stripe subscription, expanded with its prices
     *
     * @throws \Exception
     */
    private function cancelSubscriptionInDatabase($subscription)
    {
        if ($this->isDonation($subscription)) {
            $this->db->cancelDonationByStripeSubscriptionId($subscription->id);
        } else {
            $this->db->cancelSubscriptionByStripeSubscriptionId($subscription->id);
        }
    }

    /**
     * Retrieves a Stripe subscription only if it belongs to the given customer.
     *
     * @param string $subscriptionId
     * @param string $stripeCustomerId
     *
     * @return object|null the subscription, or null when it does not belong to the customer
     * @throws \Exception
     */
    private function findSubscriptionOfCustomer($subscriptionId, $stripeCustomerId)
    {
        $subscriptionParams = [
            'expand' => [
                'items.data.price'
            ]
        ];
        $subscription = $this->stripe->retrieveSubscriptionWithParams($subscriptionId, $subscriptionParams);
        $subscriptionCustomerId = (is_object($subscription) && isset($subscription->customer)) ? $subscription->customer : null;

        return $subscriptionCustomerId === $stripeCustomerId ? $subscription : null;
    }

    /**
     * @param object $subscription the Stripe subscription
     * @param object|null $resumedSubscription the subscription as Stripe answered the resume with it
     *
     * @throws \Exception
     */
    private function activateSubscriptionInDatabase($subscription, $resumedSubscription)
    {
        if ($this->isDonation($subscription)) {
            // Resuming doesn't make a subscription active by itself, such as one with an unpaid invoice.
            $status = isset($resumedSubscription->status) ? $resumedSubscription->status : $subscription->status;
            $this->db->activateDonationByStripeSubscriptionId($subscription->id, $status);
        } else {
            $this->db->updateSubscriptionToRunning($subscription->id);
        }
    }

    /**
     * A recurring donation, rather than a subscription. A Checkout donation prices its lines inline, so only its
     * donation row says it's one.
     *
     * @param object $subscription the Stripe subscription
     *
     * @return bool
     */
    private function isDonation($subscription)
    {
        return self::isDonationPlan($subscription) || !is_null($this->db->getDonationByStripeSubscriptionId($subscription->id));
    }

    public function handleSubscriptionCancellationRequest()
    {
        $return = [];

        if (
            !$this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS) ||
            !$this->options->get(Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS)
        ) {
            $return['success'] = false;
            $return['message'] = __('Unauthorized', 'wp-full-stripe-free');
            wp_send_json($return);
        }

        $cancelAtPeriodEnd = Utils::getCancelSubscriptionsAtPeriodEnd($this->staticContext);

        try {
            $subscriptionIdsToCancel = isset($_POST[self::PARAM_WPFS_SUBSCRIPTION_ID]) ? $_POST[self::PARAM_WPFS_SUBSCRIPTION_ID] : null;
            if (isset($subscriptionIdsToCancel) && is_array($subscriptionIdsToCancel) && count($subscriptionIdsToCancel) > 0) {
                // stays true unless a confirmed session, its customer and every posted subscription check out
                $unauthorized = true;
                $cardUpdateSessionHash = $this->findSessionCookieValue();
                if (!is_null($cardUpdateSessionHash)) {
                    $cardUpdateSession = $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);
                    if (!is_null($cardUpdateSession) && $this->isConfirmedSessionOfItsCustomer($cardUpdateSession)) {
                        $stripeCustomer = $this->stripe->retrieveCustomer($cardUpdateSession->stripeCustomerId);
                        if (isset($stripeCustomer)) {
                            $unauthorized = false;
                            // resolve every subscription first, so a foreign id cancels nothing at all
                            $subscriptionsToCancel = [];
                            foreach ($subscriptionIdsToCancel as $subscriptionId) {
                                $subscription = $this->findSubscriptionOfCustomer(sanitize_text_field($subscriptionId), $stripeCustomer->id);
                                if (is_null($subscription)) {
                                    $unauthorized = true;
                                    break;
                                }
                                $subscriptionsToCancel[] = $subscription;
                            }

                            if (!$unauthorized) {
                                foreach ($subscriptionsToCancel as $subscription) {
                                    // Saved only once Stripe accepts, or the row says cancelled while Stripe keeps billing.
                                    $this->stripe->cancelSubscriptionUnlessEnded($stripeCustomer->id, $subscription->id, $cancelAtPeriodEnd, $subscription);
                                    $this->cancelSubscriptionInDatabase($subscription);
                                }
                            }
                        }
                    }
                }

                if ($unauthorized) {
                    $return['success'] = false;
                    $return['message'] = __('Unauthorized', 'wp-full-stripe-free');
                } else {
                    $return['success'] = true;
                    $return['message'] = __('The subscriptions have been cancelled', 'wp-full-stripe-free');
                }
            } else {
                $return['success'] = false;
                $return['message'] = __('Select at least one subscription!', 'wp-full-stripe-free');
            }

        } catch (StripeUnreachableException $ex) {
            $return['success'] = false;
            $return['message'] = $ex->getMessage();
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while canceling subscription', $ex);

            $return['success'] = false;
            // The portal shows the message as the error.
            $return['message'] = __('Failed to cancel subscription, try again.', 'wp-full-stripe-free');
            $return['ex_code'] = $ex->getCode();
            $return['ex_message'] = $ex->getMessage();
        }

        wp_send_json($return);
    }

    /**
     * Resolves the customer portal session for the current request, requiring both a valid
     * customer portal cookie and a successfully consumed security code
     * before any protected REST API action may proceed.
     *
     * @return object|\WP_Error
     */
    private function getAuthenticatedCardUpdateSession()
    {
        $cardUpdateSessionHash = $this->findSessionCookieValue();
        $cardUpdateSession = is_null($cardUpdateSessionHash) ? null : $this->findCustomerPortalSessionByHash($cardUpdateSessionHash);

        try {
            $authenticated = !is_null($cardUpdateSession) && $this->isConfirmedSessionOfItsCustomer($cardUpdateSession);
        } catch (StripeUnreachableException $ex) {
            return new \WP_Error('wpfs_customer_portal_stripe_unreachable', $ex->getMessage(), ['status' => 503]);
        }

        if (!$authenticated) {
            return new \WP_Error(
                'wpfs_customer_portal_not_authenticated',
                __('Unauthorized', 'wp-full-stripe-free'),
                ['status' => 401]
            );
        }

        return $cardUpdateSession;
    }

    /**
     * Creates the standard response for a customer portal operation the merchant did not enable.
     *
     * @return \WP_Error
     */
    private function customerPortalOperationForbidden()
    {
        return new \WP_Error(
            'wpfs_customer_portal_forbidden',
            __('Unauthorized', 'wp-full-stripe-free'),
            ['status' => 403]
        );
    }

    /**
     * Returns the current Stripe price id for a subscription.
     *
     * @param object $subscription Stripe subscription.
     *
     * @return string|null
     */
    private function getSubscriptionPlanId($subscription)
    {
        if (
            isset($subscription->items->data[0]->price) &&
            isset($subscription->items->data[0]->price->id)
        ) {
            return (string) $subscription->items->data[0]->price->id;
        }

        if (isset($subscription->plan)) {
            if (is_object($subscription->plan) && isset($subscription->plan->id)) {
                return (string) $subscription->plan->id;
            }

            if (is_scalar($subscription->plan)) {
                return (string) $subscription->plan;
            }
        }

        return null;
    }

    /**
     * Whether the requested price is the current price or belongs to the form's configured catalog.
     *
     * @param object $subscription Stripe subscription.
     * @param string $requestedPlanId Requested Stripe price id.
     *
     * @return bool
     */
    private function isSubscriptionPlanAllowed($subscription, $requestedPlanId)
    {
        if ($requestedPlanId === $this->getSubscriptionPlanId($subscription)) {
            return true;
        }

        if (!$this->options->get(Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_UPDOWNGRADE_SUBSCRIPTIONS)) {
            return false;
        }

        // The same list the portal offers, so every plan the customer can pick is accepted.
        $formName = isset($subscription->metadata->form_name) ? (string) $subscription->metadata->form_name : null;

        return in_array($requestedPlanId, $this->findAvailablePlanIdsByForm($formName, $this->getSubscriptionPlanId($subscription)), true);
    }

    /**
     * Whether the requested quantity is the current one, or a change the subscription's form allows within its limits.
     *
     * @param object $subscription Stripe subscription.
     * @param int    $quantity Requested quantity.
     *
     * @return bool
     */
    private function isSubscriptionQuantityAllowed($subscription, $quantity)
    {
        $currentQuantity = isset($subscription->items->data[0]->quantity) ? (int) $subscription->items->data[0]->quantity : 1;
        if ($quantity === $currentQuantity) {
            return true;
        }

        if (empty($subscription->metadata->allow_multiple_subscriptions)) {
            return false;
        }

        $minimumQuantity = Utils::getMinimumPlanQuantityOfSubscription($subscription);
        $maximumQuantity = Utils::getMaximumPlanQuantityOfSubscription($subscription);

        return ($minimumQuantity <= 0 || $quantity >= $minimumQuantity) &&
            ($maximumQuantity <= 0 || $quantity <= $maximumQuantity);
    }

    /**
     * Fetch Stripe Subscriptions for a given customer to supply data for a Backbone Collection
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_Error|\WP_REST_Response
     */
    public function handleSubscriptionsFetchRequest(\WP_REST_Request $request)
    {
        $data = [];
        try {

            $cardUpdateSession = $this->getAuthenticatedCardUpdateSession();
            if (is_wp_error($cardUpdateSession)) {
                return $cardUpdateSession;
            }

            if (!$this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS)) {
                return $this->customerPortalOperationForbidden();
            }

            $model = new CustomerPortalModel();
            if (is_user_logged_in()) {
                $model->setAuthenticationType(CustomerPortalModel::AUTHENTICATION_TYPE_WORDPRESS);
            } else {
                $model->setAuthenticationType(CustomerPortalModel::AUTHENTICATION_TYPE_PLUGIN);
            }
            $model->setStripeClient($this->stripe);
            $stripeCustomer = $this->stripe->retrieveCustomerWithParams($cardUpdateSession->stripeCustomerId, ['expand' => ['sources']]);
            $this->fetchDataIntoCustomerPortalModel($model, $stripeCustomer);

            $this->buildManagedSubscriptionsArray($model->getSubscriptions(), $data);

        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while fetching subscriptions', $ex);

            return new \WP_Error($ex->getCode(), $ex->getMessage());
        }

        return new \WP_REST_Response($data, 200);

    }

    /**
     * Update subscription
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_Error|\WP_REST_Response
     */
    public function handleSubscriptionUpdateRequest(\WP_REST_Request $request)
    {
        $data = [];

        $cancelAtPeriodEnd = Utils::getCancelSubscriptionsAtPeriodEnd($this->staticContext);

        try {
            $cardUpdateSession = $this->getAuthenticatedCardUpdateSession();
            if (is_wp_error($cardUpdateSession)) {
                return $cardUpdateSession;
            }

            if (!$this->options->get(Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS)) {
                return $this->customerPortalOperationForbidden();
            }

            $updatedSubscription = $request->get_json_params();
            if (is_array($updatedSubscription)) {
                if (array_key_exists('id', $updatedSubscription) && array_key_exists('action', $updatedSubscription)) {
                    $stripeSubscriptionId = sanitize_text_field($updatedSubscription['id']);
                    if ('cancel' === $updatedSubscription['action']) {
                        if (!$this->options->get(Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS)) {
                            return $this->customerPortalOperationForbidden();
                        }

                        if (!is_null($stripeSubscriptionId)) {
                            $subscription = $this->findSubscriptionOfCustomer($stripeSubscriptionId, $cardUpdateSession->stripeCustomerId);
                            if (is_null($subscription)) {
                                return new \WP_Error(
                                    'wpfs_customer_portal_forbidden',
                                    __('Unauthorized', 'wp-full-stripe-free'),
                                    ['status' => 403]
                                );
                            }

                            // Saved only once Stripe accepts, or the row says cancelled while Stripe keeps billing.
                            $this->stripe->cancelSubscriptionUnlessEnded($cardUpdateSession->stripeCustomerId, $stripeSubscriptionId, $cancelAtPeriodEnd, $subscription);
                            $this->cancelSubscriptionInDatabase($subscription);
                        }
                    } elseif ('activate' === $updatedSubscription['action']) {
                        if (!is_null($stripeSubscriptionId)) {
                            $subscription = $this->stripe->retrieveSubscription($stripeSubscriptionId);
                            $subscriptionCustomerId = (is_object($subscription) && isset($subscription->customer)) ? $subscription->customer : null;
                            if ($subscriptionCustomerId !== $cardUpdateSession->stripeCustomerId) {
                                return new \WP_Error(
                                    'wpfs_customer_portal_forbidden',
                                    __('Unauthorized', 'wp-full-stripe-free'),
                                    ['status' => 403]
                                );
                            }

                            // Stripe refuses one that has already ended, which must then stay cancelled here too.
                            $resumedSubscription = $this->stripe->activateCancelledSubscription($stripeSubscriptionId);
                            $this->activateSubscriptionInDatabase($subscription, $resumedSubscription);
                        }
                    }
                } elseif (
                    array_key_exists('id', $updatedSubscription) &&
                    array_key_exists('newPlanId', $updatedSubscription)
                ) {
                    $stripeSubscriptionId = sanitize_text_field($updatedSubscription['id']);
                    $newPlanId = sanitize_text_field($updatedSubscription['newPlanId']);
                    if (array_key_exists('planQuantity', $updatedSubscription)) {
                        $newQuantity = sanitize_text_field($updatedSubscription['planQuantity']);
                    } else {
                        $newQuantity = 1;
                    }
                    if (isset($stripeSubscriptionId) && isset($newPlanId) && is_numeric($newQuantity) && $newQuantity > 0) {
                        $subscription = $this->stripe->retrieveSubscription($stripeSubscriptionId);
                        $subscriptionCustomerId = (is_object($subscription) && isset($subscription->customer)) ? $subscription->customer : null;
                        if ($subscriptionCustomerId !== $cardUpdateSession->stripeCustomerId) {
                            return new \WP_Error(
                                'wpfs_customer_portal_forbidden',
                                __('Unauthorized', 'wp-full-stripe-free'),
                                ['status' => 403]
                            );
                        }

                        if (
                            !$this->isSubscriptionPlanAllowed($subscription, $newPlanId) ||
                            !$this->isSubscriptionQuantityAllowed($subscription, (int) $newQuantity)
                        ) {
                            return $this->customerPortalOperationForbidden();
                        }

                        $success = $this->stripe->updateSubscriptionPlanAndQuantity($cardUpdateSession->stripeCustomerId, $stripeSubscriptionId, $newPlanId, $newQuantity);
                        if ($success) {
                            $this->db->updateSubscriptionPlanAndQuantityByStripeSubscriptionId($stripeSubscriptionId, $newPlanId, $newQuantity);
                        }
                    }
                }
                $data['success'] = true;
                $data['message'] = __('The subscription has been updated successfully', 'wp-full-stripe-free');
            }
        } catch (\Exception $ex) {
            $this->logger->error(__FUNCTION__, 'Error while updating subscription', $ex);

            return new \WP_Error($ex->getCode(), $ex->getMessage());
        }

        return new \WP_REST_Response($data, 200);

    }

    /**
     * Adds Cache-Control HTTP header if a page is displayed with the "Manage Subscriptions" shortcode
     *
     * @param $theWPObject
     */
    public function addCacheControlHeader($theWPObject)
    {
        $started = round(microtime(true) * 1000);
        if (!is_null($theWPObject) && isset($theWPObject->request)) {
            $pageByPath = get_page_by_path($theWPObject->request);
            if (!is_null($pageByPath) && isset($pageByPath->post_content)) {
                if (
                    has_shortcode($pageByPath->post_content, self::FULLSTRIPE_SHORTCODE_CUSTOMER_PORTAL) ||
                    has_shortcode($pageByPath->post_content, self::FULLSTRIPE_SHORTCODE_MANAGE_SUBSCRIPTIONS)
                ) {
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                }
            }
        }
        $finished = round(microtime(true) * 1000) - $started;
    }

    /**
     * Register WPFS Manage Subscriptions REST API routes
     */
    public function registerRESTAPIRoutes()
    {
        register_rest_route($this->getRESTAPINamespace(), $this->getBaseRoute(), [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'handleSubscriptionsFetchRequest'],
                'args' => [],
                'permission_callback' => '__return_true'
            ]
        ]
        );
        register_rest_route($this->getRESTAPINamespace(), $this->getItemRoute(), [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'handleSubscriptionUpdateRequest'],
                'args' => [],
                'permission_callback' => '__return_true'
            ]
        ]
        );
    }

    private function getRESTAPINamespace()
    {
        return self::WPFS_PLUGIN_SLUG . '/' . self::WPFS_REST_API_VERSION;
    }

    private function getBaseRoute()
    {
        return '/' . self::WPFS_REST_ROUTE_MANAGE_SUBSCRIPTIONS_SUBSCRIPTION;
    }

    private function getItemRoute()
    {
        return $this->getBaseRoute() . '/' . '(?P<id>[\w]+)';
    }

    private function getSubscriptionFormPlans($formName)
    {
        $plans = [];
        if (is_null($formName) || empty($formName)) {
            // bail out if form name is not set
            return $plans;
        }

        $form = $this->db->getInlineSubscriptionFormByName($formName);
        if (is_null($form)) {
            $form = $this->db->getCheckoutSubscriptionFormByName($formName);
        }

        if (isset($form)) {
            try {
                $plans = json_decode($form->decoratedPlans);
            } catch (\Exception $ex) {
                $this->logger->error(__FUNCTION__, 'Cannot decode form plans', $ex);
            }
        } else {
            $this->logger->error(__FUNCTION__, "Form not found: '{$formName}'.");
        }

        return $plans;
    }

    /**
     * @param $formPlanIds
     *
     * @return array
     */
    private function getOrderedPlansFromIds($formPlanIds): array
    {
        $orderedPlans = [];

        foreach ($this->getSubscriptionPlans() as $plan) {
            $i = array_search($plan->id, $formPlanIds);
            if ($i !== false) {
                // A copy per subscription: each labels the plan with its own tax.
                $orderedPlans[$i] = clone $plan;
            }
        }
        ksort($orderedPlans);

        return $orderedPlans;
    }

    /**
     * @param $formName
     * @param $currentPlanId
     *
     * @return array
     */
    private function findAvailablePlansByForm($formName, $currentPlanId)
    {
        return $this->getOrderedPlansFromIds($this->findAvailablePlanIdsByForm($formName, $currentPlanId));
    }

    /**
     * Price ids a subscription can switch to: the form's plans after the upgrade/downgrade filter, plus the current plan.
     *
     * @param string|null $formName Subscription form name.
     * @param string|null $currentPlanId Current Stripe price id.
     *
     * @return array<int, mixed>
     */
    private function findAvailablePlanIdsByForm($formName, $currentPlanId)
    {
        $formPlanIds = Pricing::extractPriceIdsFromProductsStatic(
            $this->getSubscriptionFormPlans($formName)
        );

        $formPlanIds = apply_filters(Main::FILTER_NAME_GET_UPGRADE_DOWNGRADE_PLANS, $formPlanIds, $formName);
        if (is_null($formPlanIds) || !is_array($formPlanIds)) {
            $formPlanIds = [];
        }

        if (array_search($currentPlanId, $formPlanIds) === false) {
            array_push($formPlanIds, $currentPlanId);
        }

        return $formPlanIds;
    }

    /**
     * @param $plan
     * @param object $subscription The subscription switching to the plan; its tax applies to the plan too.
     * @param object $planItem The subscription's plan item, whose tax rates the plan would take.
     *
     * @return string
     * @throws \Exception
     */
    protected function getPriceAndIntervalLabelForPlan($plan, $subscription, $planItem): string
    {

        $currency = $plan->currency;
        $interval = $plan->recurring->interval;
        $intervalCount = $plan->recurring->interval_count;
        $amount = $plan->unit_amount;

        $formattedAmount = Currencies::formatAndEscapeByMyAccount(
            $currency,
            $amount,
            true,
            true
        );

        return PriceTaxLabel::apply(
            Localization::getPriceAndIntervalLabel($interval, $intervalCount, $formattedAmount),
            $subscription,
            $planItem,
            $plan
        );
    }

    /**
     * @param $plan
     *
     * @return array
     * @throws \Exception
     */
    protected function getSummaryLabelsForPlan($plan, $priceAndIntervalLabel)
    {
        return [
            sprintf(
                /*
                 * translators:
                 * p1: plan/product name
                 * p2: plan/product formatted price and interval label
                 */
                __(
                    'Your subscription plan will be changed to %1$s. The new subscription fee will be %2$s.',
                    'wp-full-stripe-free'
                ),
                $plan->product->name,
                $priceAndIntervalLabel
            ),
            sprintf(
                /*
                 * translators:
                 * p1: plan/product name
                 * p2: plan/product formatted price and interval label
                 */
                __(
                    'Your subscription plan will be changed to %1$s. The new subscription fee will be @QUANTITY@x %2$s.',
                    'wp-full-stripe-free'
                ),
                $plan->product->name,
                $priceAndIntervalLabel
            )
        ];
    }

    /**
     * @param array $availablePlans
     * @param object $subscription The subscription the plans are offered to.
     * @param object $planItem The subscription's plan item, whose tax rates the plans would take.
     * @param bool $hasRecoveryFee Whether the subscription bills a recovery fee, repriced for the plan chosen.
     *
     * @return array
     */
    private function getPriceAndIntervalLabelForPlans(array $availablePlans, $subscription, $planItem, bool $hasRecoveryFee)
    {
        $plansWithPriceAndIntervalLabels = [];
        foreach ($availablePlans as $plan) {
            $plan->priceAndIntervalLabel = $this->getPriceAndIntervalLabelForPlan($plan, $subscription, $planItem);
            $summaryLabels = $this->getSummaryLabelsForPlan($plan, $plan->priceAndIntervalLabel);
            if ($hasRecoveryFee) {
                $summaryLabels = ManagedSubscriptionEntry::withRecoveryFeeNote($summaryLabels);
            }
            $plan->summaryLabelSingular = $summaryLabels[0];
            $plan->summaryLabelPlural = $summaryLabels[1];
            array_push($plansWithPriceAndIntervalLabels, $plan);
        }

        return $plansWithPriceAndIntervalLabels;
    }

}
