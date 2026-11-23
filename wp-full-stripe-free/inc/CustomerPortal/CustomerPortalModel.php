<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Stripe;

class CustomerPortalModel
{

    const AUTHENTICATION_TYPE_PLUGIN = 'Plugin';
    const AUTHENTICATION_TYPE_WORDPRESS = 'Wordpress';

    /**
     * @var \StripeWPFS\Stripe\Customer
     */
    private $stripeCustomer;
    /**
     * @var \StripeWPFS\Stripe\Card
     */
    private $defaultSource;
    /**
     * @var \StripeWPFS\Stripe\PaymentMethod
     */
    private $defaultPaymentMethod;
    /**
     * @var string
     */
    private $cardImageUrl;
    /**
     * @var string
     */
    private $cardName;
    /**
     * @var string
     */
    private $cardNumber;
    /**
     * @var bool
     */
    private $showSubscriptions;
    /**
     * @var array
     */
    private $subscriptions = [];
    /**
     * @var string
     */
    private $authenticationType;
    /**
     * @var array
     */
    private $invoices = [];
    /**
     * @var bool Whether the customer has more paid invoices than the portal lists.
     */
    private $invoiceListTruncated = false;
    /**
     * @var array
     */
    private $accounts = [];
    /**
     * @var bool
     */
    private $accountSelectorNeeded;
    /**
     * @var string
     */
    private $accountEmail;

    /**
     * @var Stripe
     */
    private $stripeClient;

    /**
     * @return bool
     */
    public function isAccountSelectorNeeded()
    {
        return $this->accountSelectorNeeded;
    }

    /**
     * @param bool $accountSelectorNeeded
     */
    public function setAccountSelectorNeeded($accountSelectorNeeded)
    {
        $this->accountSelectorNeeded = $accountSelectorNeeded;
    }
    /**
     * @return array
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }
    /**
     * @param array $accounts
     */
    public function setAccounts(array $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @return array
     */
    public function getInvoices()
    {
        return $this->invoices;
    }

    /**
     * @param array $invoices
     */
    public function setInvoices($invoices)
    {
        $this->invoices = $invoices;
    }

    /**
     * @return bool
     */
    public function isInvoiceListTruncated()
    {
        return $this->invoiceListTruncated;
    }

    /**
     * @param bool $invoiceListTruncated
     */
    public function setInvoiceListTruncated($invoiceListTruncated): void
    {
        $this->invoiceListTruncated = $invoiceListTruncated;
    }

    /**
     * @return string
     */
    public function getAuthenticationType()
    {
        return $this->authenticationType;
    }

    /**
     * @param string $authenticationType
     */
    public function setAuthenticationType($authenticationType)
    {
        $this->authenticationType = $authenticationType;
    }

    /**
     * @return \StripeWPFS\Stripe\Customer
     */
    public function getStripeCustomer()
    {
        return $this->stripeCustomer;
    }

    /**
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     */
    public function setStripeCustomer($stripeCustomer)
    {
        $this->stripeCustomer = $stripeCustomer;
    }

    /**
     * @return \StripeWPFS\Stripe\Card
     */
    public function getDefaultSource()
    {
        return $this->defaultSource;
    }

    /**
     * @param \StripeWPFS\Stripe\Card $defaultSource
     */
    public function setDefaultSource($defaultSource)
    {
        $this->defaultSource = $defaultSource;
    }

    /**
     * @return \StripeWPFS\Stripe\PaymentMethod
     */
    public function getDefaultPaymentMethod()
    {
        return $this->defaultPaymentMethod;
    }

    /**
     * @param \StripeWPFS\Stripe\PaymentMethod $defaultPaymentMethod
     */
    public function setDefaultPaymentMethod($defaultPaymentMethod)
    {
        $this->defaultPaymentMethod = $defaultPaymentMethod;
    }

    /**
     * @return string
     */
    public function getCardImageUrl()
    {
        return $this->cardImageUrl;
    }

    /**
     * @param string $cardImageUrl
     */
    public function setCardImageUrl($cardImageUrl)
    {
        $this->cardImageUrl = $cardImageUrl;
    }

    /**
     * @return string
     */
    public function getCardName()
    {
        return $this->cardName;
    }

    /**
     * @param string $cardName
     */
    public function setCardName($cardName)
    {
        $this->cardName = $cardName;
    }

    /**
     * @return string
     */
    public function getCardNumber()
    {
        return $this->cardNumber;
    }

    /**
     * @param string $cardNumber
     */
    public function setCardNumber($cardNumber)
    {
        $this->cardNumber = $cardNumber;
    }

    /**
     * @return string
     */
    public function getFormattedCardNumber()
    {
        return sprintf('x-%s', $this->cardNumber);
    }

    public function getExpiration()
    {
        if (isset($this->defaultPaymentMethod)) {
            return sprintf('%02d / %d', $this->defaultPaymentMethod->card->exp_month, $this->defaultPaymentMethod->card->exp_year);
        } elseif (isset($this->defaultSource)) {
            return sprintf('%02d / %d', $this->defaultSource->exp_month, $this->defaultSource->exp_year);
        }

        return '';
    }

    /**
     * @return boolean
     */
    public function showSubscriptions()
    {
        return $this->showSubscriptions;
    }

    /**
     * @param boolean $showSubscriptions
     */
    public function setShowSubscriptions($showSubscriptions)
    {
        $this->showSubscriptions = $showSubscriptions;
    }

    /**
     * @return array
     */
    public function getSubscriptions()
    {
        return $this->subscriptions;
    }

    /**
     * @param array $subscriptions
     */
    public function setSubscriptions($subscriptions)
    {
        $this->subscriptions = $subscriptions;
    }

    /**
     * @return null|string
     */
    public function getCustomerEmail()
    {
        if (isset($this->stripeCustomer)) {
            return $this->stripeCustomer->email;
        }

        return null;
    }

    /**
     * @return string
     */
    public function getAccountEmail(): string
    {
        return $this->accountEmail;
    }

    /**
     * @param string $accountEmail
     */
    public function setAccountEmail(string $accountEmail)
    {
        $this->accountEmail = $accountEmail;
    }

    /**
     * @return Stripe
     */
    public function getStripeClient()
    {
        return $this->stripeClient;
    }

    /**
     * @param Stripe $stripeClient
     */
    public function setStripeClient($stripeClient)
    {
        $this->stripeClient = $stripeClient;
    }

}
