<?php

namespace ThemeIsle\WPFullPay\CustomerPortal;

use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Utils;

class ManagedInvoiceEntry
{
    /**
     * @var \StripeWPFS\Stripe\Invoice
     */
    private $invoice;

    /**
     * @var ProductLookup
     */
    private $products;

    /**
     * ManagedInvoiceEntry constructor.
     *
     * @param \StripeWPFS\Stripe\Invoice $invoice
     * @param ProductLookup $products
     */
    public function __construct($invoice, $products)
    {
        $this->invoice = $invoice;
        $this->products = $products;
    }

    /**
     * When the invoice was paid, or created when Stripe has no payment time for it.
     *
     * @param \StripeWPFS\Stripe\Invoice $invoice
     *
     * @return int
     */
    public static function getPaymentTimestamp($invoice)
    {
        if (isset($invoice->status_transitions) && !empty($invoice->status_transitions->paid_at)) {
            return (int) $invoice->status_transitions->paid_at;
        }

        return (int) $invoice->created;
    }

    /**
     * Convert this object to a standard class instance for Backbone.js
     */
    public function toModel()
    {
        $model = new \stdClass();
        $model->id = $this->getValue();
        $model->priceLabel = $this->getPriceLabel();
        // The template and script read the date as 'created'.
        $model->created = $this->getPaymentDate();
        $model->invoiceNumber = $this->getInvoiceNumber();
        $model->invoiceUrl = $this->geInvoiceUrl();
        $model->lineItemsLabel = $this->getLineItemsLabel();

        return $model;
    }

    public function getLineItemsLabel()
    {
        $result = '';

        if ($this->invoice && $this->invoice->lines && $this->invoice->lines->data) {
            $first = true;
            foreach ($this->invoice->lines->data as $lineItem) {
                if (!$first) {
                    $result .= '• ';
                }
                if ($lineItem->quantity > 1) {
                    $result .= $lineItem->quantity . 'x ';
                }
                $result .= $this->getLineItemName($lineItem);
                $result .= ' ';

                $first = false;
            }
        }

        return $result;
    }

    /**
     * The line's product name, or the line's own description when the product can't be loaded.
     *
     * @param object $lineItem An invoice line item (API 2025-06-30.basil).
     *
     * @return string
     */
    private function getLineItemName($lineItem)
    {
        if (isset($lineItem->pricing->price_details->product)) {
            $product = $this->products->find($lineItem->pricing->price_details->product);
            if (isset($product->name)) {
                return $product->name;
            }
        }

        return (string) $lineItem->description;
    }

    public function getValue()
    {
        return $this->invoice->id;
    }

    public function getPriceLabel()
    {
        $formattedAmount = Currencies::formatAndEscapeByMyAccount(
            $this->invoice->currency,
            $this->invoice->total,
            true,
            true
        );

        return $formattedAmount;
    }

    /**
     * @return false|string
     */
    public function getPaymentDate()
    {
        return Utils::formatTimestampWithWordpressDateFormat(self::getPaymentTimestamp($this->invoice));
    }

    public function getInvoiceNumber()
    {
        $invoiceNumber = $this->invoice->number;

        return $invoiceNumber;
    }

    public function geInvoiceUrl()
    {
        $invoiceUrl = $this->invoice->invoice_pdf;

        return $invoiceUrl;
    }

}
