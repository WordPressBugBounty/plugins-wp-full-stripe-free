<?php

namespace ThemeIsle\WPFullPay\TransactionData;

abstract class PaymentTransactionData extends FormTransactionData {
	protected $vatPercent;
    protected $couponCode;
    protected $invoiceUrl;
    protected $invoiceNumber;
    protected $receiptUrl;
    protected $productName;
    protected $amount;
    protected $currency;
    protected $customerTaxId;
    protected $businessName;
    protected $stripeInvoiceId;
    /** @var int The recovery fee Stripe charged, in the smallest currency unit. */
    protected $recoveryFee = 0;
    /** @var int|null What Stripe charged, recovery fee included, in the smallest currency unit. */
    protected $chargedTotal;

    /**
     * @return mixed
     */
    public function getStripeInvoiceId() {
        return $this->stripeInvoiceId;
    }

    /**
     * @param mixed $stripeInvoiceId
     */
    public function setStripeInvoiceId( $stripeInvoiceId ) {
        $this->stripeInvoiceId = $stripeInvoiceId;
    }

    /**
     * @return mixed
     */
    public function getCurrency() {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     */
    public function setCurrency( $currency ) {
        $this->currency = $currency;
    }

    /**
     * @return mixed
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount( $amount ) {
        $this->amount = $amount;
    }

    /**
     * @return mixed
     */
    public function getProductName() {
        return $this->productName;
    }

    /**
     * @param mixed $productName
     */
    public function setProductName( $productName ) {
        $this->productName = $productName;
    }

    /**
     * @return mixed
     */
    public function getInvoiceUrl() {
        return $this->invoiceUrl;
    }

    /**
     * @param mixed $invoiceUrl
     */
    public function setInvoiceUrl( $invoiceUrl ) {
        $this->invoiceUrl = $invoiceUrl;
    }

	/**
	 * @return mixed
	 */
	public function getInvoiceNumber() {
		return $this->invoiceNumber;
	}

	/**
	 * @param mixed $invoiceNumber
	 */
	public function setInvoiceNumber( $invoiceNumber ) {
		$this->invoiceNumber = $invoiceNumber;
	}

	/**
	 * @return mixed
	 */
	public function getReceiptUrl() {
		return $this->receiptUrl;
	}

	/**
	 * @param mixed $receiptUrl
	 */
	public function setReceiptUrl( $receiptUrl ) {
		$this->receiptUrl = $receiptUrl;
	}

    /**
     * @return mixed
     */
    public function getCouponCode() {
        return $this->couponCode;
    }

    /**
     * @param mixed $couponCode
     */
    public function setCouponCode( $couponCode ) {
        $this->couponCode = $couponCode;
    }

    /**
     * @return mixed
     */
    public function getCustomerTaxId() {
        return $this->customerTaxId;
    }

    /**
     * @param mixed $customerTaxId
     */
    public function setCustomerTaxId($customerTaxId) {
        $this->customerTaxId = $customerTaxId;
    }

    /**
     * @return mixed
     */
    public function getBusinessName() {
        return $this->businessName;
    }

    /**
     * @param mixed $businessName
     */
    public function setBusinessName( $businessName ) {
        $this->businessName = $businessName;
    }

    /**
     * The recovery fee Stripe charged, after its share of a coupon and with any tax on it. The amount leaves it out,
     * so the amount and the fee add up to the charged total.
     *
     * @return int In the smallest currency unit.
     */
    public function getRecoveryFee(): int {
        return (int) $this->recoveryFee;
    }

    /**
     * @param int $recoveryFee In the smallest currency unit.
     */
    public function setRecoveryFee( $recoveryFee ): void {
        $this->recoveryFee = (int) $recoveryFee;
    }

    /**
     * What Stripe charged, recovery fee included: the PaymentIntent's amount, the Checkout session's total, or the
     * invoice's total. Without one, the amount and the fee.
     *
     * @return int In the smallest currency unit.
     */
    public function getChargedTotal(): int {
        return is_null( $this->chargedTotal ) ? (int) round( (float) $this->getAmountWithoutRecoveryFee() ) + $this->getRecoveryFee() : (int) $this->chargedTotal;
    }

    /**
     * @param int $chargedTotal In the smallest currency unit.
     */
    public function setChargedTotal( $chargedTotal ): void {
        $this->chargedTotal = (int) $chargedTotal;
    }

    /**
     * The amount the recovery fee is added to.
     *
     * @return int|float|null
     */
    protected function getAmountWithoutRecoveryFee() {
        return $this->amount;
    }

}
