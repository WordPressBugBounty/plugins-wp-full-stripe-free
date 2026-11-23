<?php

namespace ThemeIsle\WPFullPay\Checkout;

/**
 * What a Checkout session charged and taxed, read from its expanded line items, and the customer's tax ID.
 */
trait CheckoutTaxTools
{
    /**
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     *
     * @return \StdClass
     */
    abstract protected function getInvoiceDataFromCheckoutSession($checkoutSession);

    /**
     * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession
     * @param \StripeWPFS\Stripe\Customer $stripeCustomer
     *
     * @return \StdClass
     */
    protected function getContextDataFromStripeObjects($checkoutSession, $stripeCustomer)
    {
        $ctx = new \StdClass;
        $ctx->customerTaxId = $this->getTaxIdFromCustomer($stripeCustomer);
        $ctx->invoiceData = $this->getInvoiceDataFromCheckoutSession($checkoutSession);

        return $ctx;
    }

    /**
     * @param $stripeCustomer \StripeWPFS\Stripe\Customer
     */
    protected function getTaxIdFromCustomer($stripeCustomer)
    {
        $customerTaxId = null;
        if (isset($stripeCustomer) && isset($stripeCustomer->tax_ids) && isset($stripeCustomer->tax_ids->data)) {
            $taxIds = $stripeCustomer->tax_ids->data;   
            if (count($taxIds) > 0) {
                $customerTaxId = $taxIds[0]->value;
            }
        }
        return $customerTaxId;
    }

    /**
     * @param \StripeWPFS\Stripe\LineItem $lineItem An expanded line item of a Checkout session.
     *
     * @return \StdClass
     */
    protected function extractPricingDataFromLineItem($lineItem)
    {
        $result = new \StdClass;

        $result->amount = $lineItem->amount_total;
        $result->currency = $lineItem->currency;
        $result->description = $lineItem->description;

        if (count($lineItem->discounts) > 0) {
            $discountItem = $lineItem->discounts[0];

            $result->discountAmount = $discountItem->amount;
            $result->couponCode = $discountItem->discount->coupon->name;
        } else {
            $result->discountAmount = 0;
            $result->couponCode = '';
        }

        $result->unitAmount = $lineItem->price->unit_amount;
        $result->quantity = $lineItem->quantity;

        // Stripe rounds the tax amounts, so the percentage is the rates' own; several rates add up. Stripe Tax's
        // effective percentage is the one charged, its percentage can count jurisdictions that taxed nothing.
        $result->taxPercentage = null;
        if (count($lineItem->taxes) > 0) {
            $taxRates = [];
            $taxAmount = 0;
            $taxPercentage = 0.0;

            foreach ($lineItem->taxes as $tax) {
                $taxAmount += $tax->amount;
                array_push($taxRates, $tax->rate->id);
                $ratePercentage = isset($tax->rate->effective_percentage) ? $tax->rate->effective_percentage : (isset($tax->rate->percentage) ? $tax->rate->percentage : null);
                $taxPercentage = (is_null($taxPercentage) || is_null($ratePercentage)) ? null : $taxPercentage + (float) $ratePercentage;
            }

            $result->taxRates = $taxRates;
            $result->taxAmount = $taxAmount;
            $result->taxPercentage = $taxPercentage;
        } else {
            $result->taxRates = [];
            $result->taxAmount = 0;
        }

        return $result;
    }
}
