<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\Utils;

class SubscriptionContextCreator {
	use DonationToolsAddOn;

	/** @var SubscriptionFormModel */
	protected $formModel;
	/** @var SubscriptionTransactionData */
	protected $transactionData;
	/** @var Stripe */
	protected $stripe;

	public function __construct( $formModel, $transactionData, $stripe ) {
		$this->formModel = $formModel;
		$this->transactionData = $transactionData;
		$this->stripe = $stripe;
	}

	public function getContext() {
		$result = new CreateSubscriptionContext();

		$transactionData = $this->transactionData;

		$result->stripeCustomerId = $transactionData->getStripeCustomerId();
		$result->stripePriceId = $transactionData->getPlanId();
		$result->stripePaymentMethodId = $transactionData->getStripePaymentMethodId();
		$result->setupFee = $transactionData->getSetupFeeNetAmount();
		$result->trialPeriodDays = $transactionData->getTrialPeriodDays();
		$result->stripePlanQuantity = $transactionData->getPlanQuantity();
		$result->billingCycleAnchorDay = $transactionData->getBillingCycleAnchorDay();
		$result->prorateUntilAnchorDay = $transactionData->getProrateUntilAnchorDay();
		$result->metadata = $transactionData->getMetadata();
		if ( ! is_array( $result->metadata ) ) {
			$result->metadata = [];
		}
		// Completion and later receipts report this fee, not one changed on the form since.
		$result->metadata[ SubscriptionFormModel::METADATA_KEY_SETUP_FEE ] = (string) (int) round( (float) $result->setupFee );
		$result->discountId = $transactionData->getDiscountId();
		$result->discountType = $transactionData->getDiscountType();
		$result->productName = $transactionData->getProductName();
		$result->isStripeTax = ( $this->formModel->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX );

		$recoveryFee = $this->formModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $this->formModel->getForm() );

		if ( $recoveryFee && ! empty( $recoveryFeeData ) ) {
			$amount = $this->formModel->getStripePlan()->unit_amount * $this->formModel->getStripePlanQuantity();
			$currency = $this->formModel->getStripePlan()->currency;
			$recurring = $this->formModel->getStripePlan()->recurring;
			$intervalCount = isset( $recurring->interval_count ) ? (int) $recurring->interval_count : 1;
			$plan = $this->createSubscriptionForRecoveryFee( $currency, $recurring->interval, $intervalCount );
			$quantity = Utils::calculateRecoveryFee( $amount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $currency );

			$recoveryFeeLineItem = [
				'price' => $plan->id,
				'quantity' => $quantity,
			];
			
			$result->feeRecoveryLineItem = $recoveryFeeLineItem;
		}

		return $result;
	}
}
