<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Main;

/**
 * Tells how tax applies to a subscription price in the customer portal: "(incl. 10% tax)" or "+ 12% tax".
 * The rates come from Stripe; no tax is computed here, so the label can't disagree with what Stripe charges.
 *
 * @phpstan-type TaxRateData object{percentage: float|int, inclusive: bool, display_name?: string|null}
 * @phpstan-type LabelRate array{percentage: float, displayName: string}
 */
class PriceTaxLabel
{
    /**
     * @param string $priceLabel The formatted price and billing interval, e.g. "$20.00 / month".
     * @param object{default_tax_rates?: array<int, TaxRateData|string>|null, automatic_tax?: object{enabled: bool}|null} $subscription The Stripe subscription.
     * @param object{tax_rates?: array<int, TaxRateData|string>|null} $subscriptionItem The subscription item whose tax rates apply.
     * @param object{tax_behavior?: 'inclusive'|'exclusive'|'unspecified'|null} $price The price the label shows; its tax behavior applies under Stripe Tax.
     *
     * @return string
     */
    public static function apply($priceLabel, $subscription, $subscriptionItem, $price)
    {
        $inclusiveRates = [];
        $exclusiveRates = [];
        foreach (self::getTaxRates($subscription, $subscriptionItem) as $taxRate) {
            $rate = [
                'percentage' => (float) $taxRate->percentage,
                'displayName' => isset($taxRate->display_name) ? $taxRate->display_name : '',
            ];
            if (!empty($taxRate->inclusive)) {
                $inclusiveRates[] = $rate;
            } else {
                $exclusiveRates[] = $rate;
            }
        }
        $automaticTax = isset($subscription->automatic_tax) && !empty($subscription->automatic_tax->enabled);
        $taxBehavior = isset($price->tax_behavior) ? $price->tax_behavior : null;

        $label = $priceLabel;
        if (!empty($inclusiveRates)) {
            $label = sprintf(
                /* translators: %1$s: price and billing interval, e.g. "$20.00 / month"; %2$s: tax rate(s) included in the price, e.g. "10%" */
                __('%1$s (incl. %2$s tax)', 'wp-full-stripe-free'),
                $label,
                self::formatRates($inclusiveRates)
            );
        }
        if (!empty($exclusiveRates)) {
            $label = sprintf(
                /* translators: %1$s: price and billing interval, e.g. "$20.00 / month"; %2$s: tax rate(s) added to the price, e.g. "12%" */
                __('%1$s + %2$s tax', 'wp-full-stripe-free'),
                $label,
                self::formatRates($exclusiveRates)
            );
        }
        // Under Stripe Tax the rate depends on the customer's address, so only the behavior is known.
        if (empty($inclusiveRates) && empty($exclusiveRates) && $automaticTax) {
            if ('inclusive' === $taxBehavior) {
                /* translators: %s: price and billing interval, e.g. "$20.00 / month", whose price includes tax */
                $label = sprintf(__('%s (incl. tax)', 'wp-full-stripe-free'), $label);
            } elseif ('exclusive' === $taxBehavior) {
                /* translators: %s: price and billing interval, e.g. "$20.00 / month", to which tax is added */
                $label = sprintf(__('%s + tax', 'wp-full-stripe-free'), $label);
            }
        }

        return apply_filters(
            Main::FILTER_NAME_CUSTOMER_PORTAL_PRICE_TAX_LABEL,
            $label,
            [
                'priceLabel' => $priceLabel,
                'inclusiveRates' => $inclusiveRates,
                'exclusiveRates' => $exclusiveRates,
                'automaticTax' => $automaticTax,
                'taxBehavior' => $taxBehavior,
            ]
        );
    }

    /**
     * The item's tax rates, or the subscription's default rates when the item has none.
     *
     * @param object{default_tax_rates?: array<int, TaxRateData|string>|null} $subscription
     * @param object{tax_rates?: array<int, TaxRateData|string>|null} $subscriptionItem
     *
     * @return array<int, TaxRateData>
     */
    private static function getTaxRates($subscription, $subscriptionItem)
    {
        $taxRates = empty($subscriptionItem->tax_rates) ? [] : $subscriptionItem->tax_rates;
        if (empty($taxRates) && !empty($subscription->default_tax_rates)) {
            $taxRates = $subscription->default_tax_rates;
        }

        return array_filter((array) $taxRates, function ($taxRate) {
            return is_object($taxRate) && isset($taxRate->percentage);
        });
    }

    /**
     * @param list<LabelRate> $rates
     *
     * @return string e.g. "5% + 9.975%"
     */
    private static function formatRates(array $rates)
    {
        return implode(' + ', array_map(function ($rate) {
            return self::formatPercentage($rate['percentage']);
        }, $rates));
    }

    /**
     * Shows only the decimals the rate has: 10%, 9.975%. Stripe keeps up to four.
     *
     * @param float $percentage
     *
     * @return string
     */
    private static function formatPercentage($percentage)
    {
        $fraction = substr(strrchr(rtrim(sprintf('%.4F', $percentage), '0'), '.'), 1);

        return number_format_i18n($percentage, strlen((string) $fraction)) . '%';
    }
}
