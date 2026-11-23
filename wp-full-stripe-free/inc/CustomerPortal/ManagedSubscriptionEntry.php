<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;
use ThemeIsle\WPFullPay\Utils;

class ManagedSubscriptionEntry
{

    const PARAM_WPFS_SUBSCRIPTION_ID = 'wpfs-subscription-id[]';

    /**
     * @var \StripeWPFS\Stripe\Subscription
     */
    private $subscription;
    private $donationAmount;

    /**
     * @var array{planItem: object, feeItem: object, feeRecoveryData: array<string, mixed>}|null The plan's and the
     *      fee's item of a subscription created with a fee recovery line, and the fee settings of its form.
     */
    private $feeRecovery;

    /**
     * ManagedSubscriptionEntry constructor.
     *
     * @param \StripeWPFS\Stripe\Subscription $subscription
     * @param int|numeric-string $recurringDonationAmount The donation row's amount, 0 for a subscription that isn't a donation.
     * @param array{planItem: object, feeItem: object, feeRecoveryData: array<string, mixed>}|null $feeRecovery
     *        As RecoveryFee::findFeeRecoveryOfSubscription() finds it.
     */
    public function __construct($subscription, $recurringDonationAmount = 0, $feeRecovery = null)
    {
        $this->subscription = $subscription;
        $this->donationAmount = $recurringDonationAmount;
        $this->feeRecovery = $feeRecovery;
    }

    /**
     * A subscription to a plan with a recovery fee line, rather than a donation.
     *
     * @return bool
     */
    public function hasRecoveryFee()
    {
        return !is_null($this->feeRecovery) && !$this->isDonationEntry();
    }

    /**
     * Whether the customer may be offered another plan or quantity. A subscription with a recovery fee line takes
     * one only while its form still recovers fees, whose settings reprice the fee.
     *
     * @return bool
     */
    public function canChangePlan()
    {
        return !$this->isDonationEntry() && (!$this->hasRecoveryFee() || !empty($this->feeRecovery['feeRecoveryData']));
    }

    /**
     * A recurring donation: one with a row in the donations table, which gave this entry its amount, or one
     * priced with a donation plan.
     *
     * @return bool
     */
    public function isDonationEntry()
    {
        return $this->donationAmount > 0 || CustomerPortalService::isDonationPlan($this->subscription);
    }

    /**
     * Convert this object to a standard class instance for Backbone.js
     *
     * @param array $availablePlans
     *
     * @return \stdClass
     * @throws \Exception
     */
    public function toModel($availablePlans = [])
    {
        $model = new \stdClass();
        $model->id = $this->getValue();
        $model->idAttribute = $this->getId();
        $model->nameAttribute = $this->getName();
        $model->status = $this->getStatus();
        $model->statusClass = $this->getClass();
        $model->created = $this->getCreated();

        // A donation, or a plan, with a recovery fee line is still listed as its donation or plan.
        if (count($this->subscription->items->data) == 1 || $this->isDonationEntry() || $this->hasRecoveryFee()) {
            $model->planId = $this->getPlanId();
            $model->planName = $this->getPlanName();
            $model->planQuantity = $this->getPlanQuantity();
            $model->allowMultipleSubscriptions = $this->isAllowMultipleSubscriptions() && $this->canChangePlan();
            $model->minimumPlanQuantity = $this->getMinimumPlanQuantity();
            $model->maximumPlanQuantity = $this->getMaximumPlanQuantity();
            $model->planLabel = $this->getPlanLabel();
            $model->priceAndIntervalLabel = $this->getPriceAndIntervalLabel();
            $summaryLabels = $this->getSummaryLabels();
            $model->summaryLabelSingular = $summaryLabels[0];
            $model->summaryLabelPlural = $summaryLabels[1];
            $model->newPlanId = '';
            $model->availablePlans = $this->canChangePlan() ? $availablePlans : [];
        } else {
            $model->planId = null;
            $model->planName =
                /* translators: Displayed in the subscription list when a subscription is composed of more than one subscription plan */
                __('Multiple plans', 'wp-full-stripe-free');
            $model->planQuantity = 1;
            $model->allowMultipleSubscriptions = false;
            $model->minimumPlanQuantity = 0;
            $model->maximumPlanQuantity = 0;
            $model->planLabel = $model->planName;
            $model->priceAndIntervalLabel = null;
            $model->summaryLabelSingular = null;
            $model->summaryLabelPlural = null;
            $model->newPlanId = null;
            $model->availablePlans = [];
        }

        if ($this->subscription->cancel_at_period_end === true) {
            $model->cancelAtPeriodEnd = true;
            $periodEnds = array_map(
                fn($item) => $item->current_period_end,
                $this->subscription->items->data
            );

            $currentPeriodEnd = max($periodEnds);
            $model->canceled = Utils::formatTimestampWithWordpressDateFormat($currentPeriodEnd);
        } else {
            $model->cancelAtPeriodEnd = false;
            $model->canceled = null;
        }

        return $model;
    }

    public function getValue()
    {
        return $this->subscription->id;
    }

    public function getId()
    {
        return sprintf('wpfs-subscription--%s', $this->subscription->id);
    }

    public function getName()
    {
        return self::PARAM_WPFS_SUBSCRIPTION_ID;
    }

    public function getStatus()
    {
        $locStatus = '';

        if (\StripeWPFS\Stripe\Subscription::STATUS_TRIALING === $this->subscription->status) {
            /* translators: The 'Trialing' subscription status */
            $locStatus = __('Trialing', 'wp-full-stripe-free');
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_ACTIVE === $this->subscription->status) {
            /* translators: The 'Active' subscription status */
            $locStatus = __('Active', 'wp-full-stripe-free');
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_PAST_DUE === $this->subscription->status) {
            /* translators: The 'Past due' subscription status */
            $locStatus = __('Past due', 'wp-full-stripe-free');
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_CANCELED === $this->subscription->status) {
            /* translators: The 'Canceled' subscription status */
            $locStatus = __('Canceled', 'wp-full-stripe-free');
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_UNPAID === $this->subscription->status) {
            /* translators: The 'Unpaid' subscription status */
            $locStatus = __('Unpaid', 'wp-full-stripe-free');
        }

        return $locStatus;
    }

    public function getClass()
    {
        $clazz = '';
        if (\StripeWPFS\Stripe\Subscription::STATUS_TRIALING === $this->subscription->status) {
            $clazz = 'wpfs-subscription-status--trialing';
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_ACTIVE === $this->subscription->status) {
            $clazz = 'wpfs-subscription-status--active';
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_PAST_DUE === $this->subscription->status) {
            $clazz = 'wpfs-subscription-status--past-due';
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_CANCELED === $this->subscription->status) {
            $clazz = 'wpfs-subscription-status--canceled';
        } elseif (\StripeWPFS\Stripe\Subscription::STATUS_UNPAID === $this->subscription->status) {
            $clazz = 'wpfs-subscription-status--unpaid';
        }

        return $clazz;
    }

    public function getCreated()
    {
        return Utils::formatTimestampWithWordpressDateFormat($this->subscription->created);
    }

    /**
     * @return string|null
     */
    public function getPlanId()
    {
        $planId = null;
        $price = $this->getPlanItem()->price;
        if (isset($price)) {
            $planId = $price->id;
        }

        return $planId;
    }

    /**
     * @return string
     */
    public function getPlanName()
    {
        // This is an unwanted plan name, no need to localize it
        $planName = 'Unknown';
        $product = $this->findProductInSubscription();
        if (isset($product)) {
            $planName = $product->name;
        }

        return $planName;
    }

    /**
     * @return null|\StripeWPFS\Stripe\Product
     */
    private function findProductInSubscription()
    {
        return $this->getPlanItem()->price->productExpanded;
    }

    private function getPlanQuantity()
    {
        return isset($this->getPlanItem()->quantity) ? $this->getPlanItem()->quantity : 1;
    }

    /**
     * The summary of a change shows the plan's price; a recovery fee is repriced for the new plan and quantity.
     *
     * @return array{string, string} For one item, and for several with an @QUANTITY@ placeholder.
     */
    private function getSummaryLabels(): array
    {
        $summaryLabels = [
            sprintf(
                /*
                 * translators:
                 * p1: plan/product formatted price and interval label
                 */
                __(
                    'The new subscription fee will be %1$s.',
                    'wp-full-stripe-free'
                ),
                $this->getPlanPriceAndIntervalLabel()
            ),
            sprintf(
                /*
                 * translators:
                 * p1: plan/product name
                 */
                __(
                    'The new subscription fee will be @QUANTITY@x %1$s.',
                    'wp-full-stripe-free'
                ),
                $this->getPlanPriceAndIntervalLabel()
            )
        ];

        return $this->hasRecoveryFee() ? self::withRecoveryFeeNote($summaryLabels) : $summaryLabels;
    }

    /**
     * Tells the customer that a change adds a recovery fee, which the summary can't price: the page fills in the
     * quantity, and the fee depends on it.
     *
     * @param array{string, string} $summaryLabels
     *
     * @return array{string, string}
     */
    public static function withRecoveryFeeNote(array $summaryLabels): array
    {
        return array_map(
            static function (string $summaryLabel): string {
                return sprintf(
                    /* translators: %s: the summary of a subscription change, e.g. "The new subscription fee will be $20.00 / month." */
                    __('%s A transaction fee is added to cover processing costs.', 'wp-full-stripe-free'),
                    $summaryLabel
                );
            },
            $summaryLabels
        );
    }

    /**
     * @return bool
     */
    public function isAllowMultipleSubscriptions()
    {
        $allowMultipleSubscriptions = false;
        if (isset($this->subscription->metadata) && isset($this->subscription->metadata->allow_multiple_subscriptions)) {
            $allowMultipleSubscriptions = boolval($this->subscription->metadata->allow_multiple_subscriptions);
        }

        return $allowMultipleSubscriptions;
    }

    /**
     * @return bool|int
     */
    public function getMinimumPlanQuantity()
    {
        return Utils::getMinimumPlanQuantityOfSubscription($this->subscription);
    }

    /**
     * @return bool|int
     */
    public function getMaximumPlanQuantity()
    {
        return Utils::getMaximumPlanQuantityOfSubscription($this->subscription);
    }

    public function getPlanLabel()
    {
        $planName = $this->getPlanName();
        $quantity = $this->getPlanQuantity();

        //todo: localize this
        return $quantity == 1 ? $planName : sprintf('%d%s %s', $quantity, 'x', $planName);
    }

    /**
     * The plan's price and billing interval, followed by the recovery fee the subscription is billed with it.
     *
     * @return string
     */
    public function getPriceAndIntervalLabel()
    {
        $label = $this->getPlanPriceAndIntervalLabel();
        if (!$this->hasRecoveryFee()) {
            return $label;
        }

        $feeItem = $this->feeRecovery['feeItem'];

        return sprintf(
            /* translators: %1$s: price and billing interval, e.g. "$20.00 / month", maybe with its tax; %2$s: the transaction fee charged with it every period, e.g. "$0.91" */
            __('%1$s + %2$s fee', 'wp-full-stripe-free'),
            $label,
            Currencies::formatAndEscapeByMyAccount($feeItem->price->currency, RecoveryFee::getAmountOfItem($feeItem), true, true)
        );
    }

    /**
     * @return string
     */
    private function getPlanPriceAndIntervalLabel()
    {
        return $this->getPriceAndIntervalLabelForSubscriptionItem($this->getPlanItem());
    }

    /**
     * The item the subscription is listed by: for a plan with a recovery fee line the plan's, for a donation the one
     * priced with its donation plan, and otherwise the first that isn't a recovery fee, wherever Stripe lists the fee.
     * A Checkout donation prices both inline, so only the fee's product tells them apart.
     *
     * @return \StripeWPFS\Stripe\SubscriptionItem
     */
    public function getPlanItem()
    {
        if ($this->hasRecoveryFee()) {
            return $this->feeRecovery['planItem'];
        }

        foreach ($this->subscription->items->data as $item) {
            if (isset($item->price->lookup_key) && strpos($item->price->lookup_key, Main::DONATION_PLAN_ID_PREFIX) === 0) {
                return $item;
            }
        }
        foreach ($this->subscription->items->data as $item) {
            if (!RecoveryFee::isFeeItem($item)) {
                return $item;
            }
        }

        return $this->subscription->items->data[0];
    }

    public function getSubscriptionFormName()
    {
        if (isset($this->subscription->metadata) && isset($this->subscription->metadata->form_name)) {
            return $this->subscription->metadata->form_name;
        }
        return null;
    }

    /**
     * @param \StripeWPFS\Stripe\SubscriptionItem $subscriptionItem
     *
     * @return string
     * @throws \Exception
     */
    protected function getPriceAndIntervalLabelForSubscriptionItem($subscriptionItem): string
    {
        $currency = $subscriptionItem->price->currency;
        $interval = $subscriptionItem->price->recurring->interval;
        $intervalCount = $subscriptionItem->price->recurring->interval_count;

        $amount = 0;
        if ($this->isDonationEntry()) {
            $amount = $this->donationAmount;
        } else {
            // For graduated and volume prices the amount can be null
            $amount = is_null($subscriptionItem->price->unit_amount) ? 0 : $subscriptionItem->price->unit_amount;
        }

        $formattedAmount = Currencies::formatAndEscapeByMyAccount(
            $currency,
            $amount,
            true,
            true
        );

        return PriceTaxLabel::apply(
            Localization::getPriceAndIntervalLabel($interval, $intervalCount, $formattedAmount),
            $this->subscription,
            $subscriptionItem,
            $subscriptionItem->price
        );
    }

}
