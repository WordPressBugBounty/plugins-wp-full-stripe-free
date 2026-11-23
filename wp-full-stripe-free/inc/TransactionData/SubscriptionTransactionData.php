<?php

namespace ThemeIsle\WPFullPay\TransactionData;

class SubscriptionTransactionData extends PaymentTransactionData {
	protected $planId;
	protected $planName;
	protected $planCurrency;
	protected $planNetAmount;
	protected $planFutureNetAmount;
	protected $planFutureTaxAmount;
	protected $planFutureGrossAmount;
	protected $planGrossAmount;
	protected $planTaxAmount;
	protected $planAmountVATRate;
	protected $planNetAmountTotal;
	protected $planGrossAmountTotal;
	protected $planTaxAmountTotal;
	protected $setupFeeNetAmount;
	protected $setupFeeGrossAmount;
	protected $setupFeeTaxAmount;
	protected $planSetupFeeVATRate;
	protected $setupFeeNetAmountTotal;
	protected $setupFeeGrossAmountTotal;
	protected $setupFeeTaxAmountTotal;
	protected $planQuantity;
	protected $billingCycleAnchorDay;
	protected $prorateUntilAnchorDay;
	protected $trialPeriodDays;
    protected $stripeInvoiceId;
    protected $discountId;
    protected $discountType;

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
    public function getTrialPeriodDays() {
        return $this->trialPeriodDays;
    }

    /**
     * @param mixed $trialPeriodDays
     */
    public function setTrialPeriodDays( $trialPeriodDays ) {
        $this->trialPeriodDays = $trialPeriodDays;
    }

	/**
	 * @return integer
	 */
	public function getProrateUntilAnchorDay() {
		return $this->prorateUntilAnchorDay;
	}

	/**
	 * @param integer $prorateUntilAnchorDay
	 */
	public function setProrateUntilAnchorDay( $prorateUntilAnchorDay ) {
		$this->prorateUntilAnchorDay = $prorateUntilAnchorDay;
	}

	/**
	 * @return integer
	 */
	public function getBillingCycleAnchorDay() {
		return $this->billingCycleAnchorDay;
	}

	/**
	 * @param integer $billingCycleAnchorDay
	 */
	public function setBillingCycleAnchorDay( $billingCycleAnchorDay ) {
		$this->billingCycleAnchorDay = $billingCycleAnchorDay;
	}

	public function getPlanGrossAmountAndGrossSetupFeeTotal() {
		return $this->planGrossAmountTotal + $this->setupFeeGrossAmountTotal;
	}

	/**
	 * The plan and the setup fee, as %AMOUNT% reports them.
	 *
	 * @return int|float
	 */
	protected function getAmountWithoutRecoveryFee() {
		return $this->getPlanGrossAmountAndGrossSetupFeeTotal();
	}

	/**
	 * @return mixed
	 */
	public function getPlanNetAmountTotal() {
		return $this->planNetAmountTotal;
	}

	/**
	 * @param mixed $planNetAmountTotal
	 */
	public function setPlanNetAmountTotal( $planNetAmountTotal ) {
		$this->planNetAmountTotal = $planNetAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getPlanGrossAmountTotal() {
		return $this->planGrossAmountTotal;
	}

	/**
	 * @param mixed $planGrossAmountTotal
	 */
	public function setPlanGrossAmountTotal( $planGrossAmountTotal ) {
		$this->planGrossAmountTotal = $planGrossAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getPlanTaxAmountTotal() {
		return $this->planTaxAmountTotal;
	}

	/**
	 * @param mixed $planTaxAmountTotal
	 */
	public function setPlanTaxAmountTotal($planTaxAmountTotal ) {
		$this->planTaxAmountTotal = $planTaxAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeNetAmountTotal() {
		return $this->setupFeeNetAmountTotal;
	}

	/**
	 * @param mixed $setupFeeNetAmountTotal
	 */
	public function setSetupFeeNetAmountTotal($setupFeeNetAmountTotal ) {
		$this->setupFeeNetAmountTotal = $setupFeeNetAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeGrossAmountTotal() {
		return $this->setupFeeGrossAmountTotal;
	}

	/**
	 * @param mixed $setupFeeGrossAmountTotal
	 */
	public function setSetupFeeGrossAmountTotal($setupFeeGrossAmountTotal ) {
		$this->setupFeeGrossAmountTotal = $setupFeeGrossAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeTaxAmountTotal() {
		return $this->setupFeeTaxAmountTotal;
	}

	/**
	 * @param mixed $setupFeeTaxAmountTotal
	 */
	public function setSetupFeeTaxAmountTotal($setupFeeTaxAmountTotal ) {
		$this->setupFeeTaxAmountTotal = $setupFeeTaxAmountTotal;
	}

	/**
	 * @return mixed
	 */
	public function getPlanId() {
		return $this->planId;
	}

	/**
	 * @param mixed $planId
	 */
	public function setPlanId( $planId ) {
		$this->planId = $planId;
	}

	/**
	 * @return mixed
	 */
	public function getPlanName() {
		return $this->planName;
	}

	/**
	 * @param mixed $planName
	 */
	public function setPlanName( $planName ) {
		$this->planName = $planName;
	}

	/**
	 * @return mixed
	 */
	public function getPlanCurrency() {
		return $this->planCurrency;
	}

	/**
	 * @param mixed $planCurrency
	 */
	public function setPlanCurrency( $planCurrency ) {
		$this->planCurrency = $planCurrency;
	}

	/**
	 * @return mixed
	 */
	public function getPlanNetAmount() {
		return $this->planNetAmount;
	}

	/**
	 * @param mixed $planNetAmount
	 */
	public function setPlanNetAmount( $planNetAmount ) {
		$this->planNetAmount = $planNetAmount;
	}

	/**
	 * @return mixed
	 */
	public function getPlanFutureNetAmount() {
		return $this->planFutureNetAmount;
	}

	/**
	 * @param mixed $planFutureNetAmount
	 */
	public function setPlanFutureNetAmount( $planFutureNetAmount ) {
		$this->planFutureNetAmount = $planFutureNetAmount;
	}

	/**
	 * @return mixed
	 */
	public function getPlanFutureTaxAmount() {
		return $this->planFutureTaxAmount;
	}
	/**
	 * @param mixed $planFutureTaxAmount
	 */
	public function setPlanFutureTaxAmount( $planFutureTaxAmount ) {
		$this->planFutureTaxAmount = $planFutureTaxAmount;
	}

	/**
	 * @return mixed
	 */
	public function getPlanFutureGrossAmount() {
		return $this->planFutureGrossAmount;
	}
	/**
	 * @param mixed $planFutureGrossAmount
	 */
	public function setPlanFutureGrossAmount( $planFutureGrossAmount ) {
		$this->planFutureGrossAmount = $planFutureGrossAmount;
	}

	/**
	 * @return float|null The tax rate the plan was charged, in percent, when it is known exactly.
	 */
	public function getPlanAmountVATRate(): ?float {
		return $this->planAmountVATRate;
	}

	/**
	 * @param float|null $planAmountVATRate
	 */
	public function setPlanAmountVATRate( ?float $planAmountVATRate ): void {
		$this->planAmountVATRate = $planAmountVATRate;
	}

	/**
	 * @return float|null The tax rate the setup fee was charged, in percent, when it is known exactly.
	 */
	public function getPlanSetupFeeVATRate(): ?float {
		return $this->planSetupFeeVATRate;
	}

	/**
	 * @param float|null $planSetupFeeVATRate
	 */
	public function setPlanSetupFeeVATRate( ?float $planSetupFeeVATRate ): void {
		$this->planSetupFeeVATRate = $planSetupFeeVATRate;
	}

	/**
	 * @return mixed
	 */
	public function getPlanGrossAmount() {
		return $this->planGrossAmount;
	}

	/**
	 * @param mixed $planGrossAmount
	 */
	public function setPlanGrossAmount( $planGrossAmount ) {
		$this->planGrossAmount = $planGrossAmount;
	}

	/**
	 * @return mixed
	 */
	public function getPlanTaxAmount() {
		return $this->planTaxAmount;
	}

	/**
	 * @param mixed $planTaxAmount
	 */
	public function setPlanTaxAmount($planTaxAmount ) {
		$this->planTaxAmount = $planTaxAmount;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeNetAmount() {
		return $this->setupFeeNetAmount;
	}

	/**
	 * @param mixed $SetupFeeNetAmount
	 */
	public function setSetupFeeNetAmount($SetupFeeNetAmount ) {
		$this->setupFeeNetAmount = $SetupFeeNetAmount;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeGrossAmount() {
		return $this->setupFeeGrossAmount;
	}

	/**
	 * @param mixed $setupFeeGrossAmount
	 */
	public function setSetupFeeGrossAmount($setupFeeGrossAmount ) {
		$this->setupFeeGrossAmount = $setupFeeGrossAmount;
	}

	/**
	 * @return mixed
	 */
	public function getSetupFeeTaxAmount() {
		return $this->setupFeeTaxAmount;
	}

	/**
	 * @param mixed $setupFeeTaxAmount
	 */
	public function setSetupFeeTaxAmount($setupFeeTaxAmount ) {
		$this->setupFeeTaxAmount = $setupFeeTaxAmount;
	}

	/**
	 * @return mixed
	 */
	public function getPlanQuantity() {
		return $this->planQuantity;
	}

	/**
	 * @param mixed $planQuantity
	 */
	public function setPlanQuantity( $planQuantity ) {
		$this->planQuantity = $planQuantity;
	}

    /**
     * @return mixed
     */
    public function getDiscountId() {
        return $this->discountId;
    }

    /**
     * @param mixed $discountId
     */
    public function setDiscountId($discountId ) {
        $this->discountId = $discountId;
    }

    /**
     * @return mixed
     */
    public function getDiscountType() {
        return $this->discountType;
    }

    /**
     * @param mixed $discountType
     */
    public function setDiscountType( $discountType ) {
        $this->discountType = $discountType;
    }

	/**
	 * Get string with JSON of transaction data
	 * @return string
	 */
	public function getJSONString() {
		$data = [
			'planId' => $this->planId,
			'planName' => $this->planName,
			'planCurrency' => $this->planCurrency,
			'planNetAmount' => $this->planNetAmount,
			'planGrossAmount' => $this->planGrossAmount,
			'planTaxAmount' => $this->planTaxAmount,
			'planFutureNetAmount' => $this->planFutureNetAmount,
			'planFutureTaxAmount' => $this->planFutureTaxAmount,
			'planFutureGrossAmount' => $this->planFutureGrossAmount,
			'planAmountVATRate' => $this->planAmountVATRate,
			'planNetAmountTotal' => $this->planNetAmountTotal,
			'planGrossAmountTotal' => $this->planGrossAmountTotal,
			'planTaxAmountTotal' => $this->planTaxAmountTotal,
			'setupFeeNetAmount' => $this->setupFeeNetAmount,
			'setupFeeGrossAmount' => $this->setupFeeGrossAmount,
			'setupFeeTaxAmount' => $this->setupFeeTaxAmount,
			'planSetupFeeVATRate' => $this->planSetupFeeVATRate,
			'setupFeeNetAmountTotal' => $this->setupFeeNetAmountTotal,
			'setupFeeGrossAmountTotal' => $this->setupFeeGrossAmountTotal,
			'setupFeeTaxAmountTotal' => $this->setupFeeTaxAmountTotal,
			'planQuantity' => $this->planQuantity,
			'billingCycleAnchorDay' => $this->billingCycleAnchorDay,
			'prorateUntilAnchorDay' => $this->prorateUntilAnchorDay,
			'trialPeriodDays' => $this->trialPeriodDays,
			'stripeInvoiceId' => $this->stripeInvoiceId,
			'discountId' => $this->discountId,
			'discountType' => $this->discountType
		];
		return json_encode($data);
	}
}
