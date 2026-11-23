<?php

namespace ThemeIsle\WPFullPay\TransactionData;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Utils;

class TransactionDataService {

	const KEY_PREFIX = 'wpfs_td_';
	const REQUEST_PARAM_NAME_WPFS_TRANSACTION_DATA_KEY = 'wpfs_td_key';

	/**
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 * @param string $sessionHash
	 * @param string $securityCode
	 *
	 * @return MyAccountLoginTransactionData
	 */
	public static function createMyAccountLoginData( $stripeCustomer, $sessionHash, $securityCode ) {
	    $data = new MyAccountLoginTransactionData();

	    $data->setCustomerName( $stripeCustomer->name );
        $data->setCustomerEmail( $stripeCustomer->email );
        $data->setSecurityCode( $securityCode );
        $data->setSessionHash( $sessionHash );

        return $data;
    }

	/**
	 * @param string $emailAddress
	 *
	 * @return MyAccountLoginTransactionData
	 */
    public static function createMyAccountLoginDataByEmail( $emailAddress ) {
        $data = new MyAccountLoginTransactionData();

        $data->setCustomerEmail( $emailAddress );

        return $data;
    }

    /**
     * @param string $emailAddress
     *
     * @return DonationTransactionData
     */
    public static function createDonationDataByEmail( $emailAddress ) {
        $data = new DonationTransactionData();

        $data->setCustomerEmail( $emailAddress );

        return $data;
    }

    /**
     * @param DonationFormModel $donationFormModel
     *
     * @return DonationTransactionData
     */
	public static function createDonationDataByFormModel( $donationFormModel ) {
        $data = new DonationTransactionData();

        $stripeCustomerId = ! is_null( $donationFormModel->getStripeCustomer() ) ? $donationFormModel->getStripeCustomer()->id : null;

        $data->setIpAddress( $donationFormModel->getIpAddress() );
        $data->setFormName( $donationFormModel->getFormName() );
        $data->setStripePaymentIntentId( $donationFormModel->getStripePaymentIntentId() );
        $data->setStripePaymentMethodId( $donationFormModel->getStripePaymentMethodId() );
        $data->setStripeCustomerId( $stripeCustomerId );
        $data->setCustomerName( $donationFormModel->getCardHolderName() );
        $data->setCustomerEmail( $donationFormModel->getCardHolderEmail() );
        $data->setCustomerPhone( $donationFormModel->getCardHolderPhone() );
        $data->setCurrency( $donationFormModel->getForm()->currency );
        $data->setAmount( $donationFormModel->getAmount() );
        $data->setDonationFrequency( $donationFormModel->getDonationFrequency() );
        $data->setProductName( $donationFormModel->getProductName() );
        $data->setBillingName( $donationFormModel->getBillingName() );
        $data->setBillingAddress( $donationFormModel->getBillingAddress() );
        $data->setShippingName( $donationFormModel->getShippingName() );
        $data->setShippingAddress( $donationFormModel->getShippingAddress() );
        $data->setCustomInputValues( $donationFormModel->getCustomInputvalues() );
        $data->setTransactionId( $donationFormModel->getTransactionId() );
		$data->setCustomFieldsJSON( $donationFormModel->getCustomFieldsJSON() );

        return $data;
    }

    /**
     * @param string $emailAddress
     *
     * @return OneTimePaymentTransactionData
     */
    public static function createOneTimePaymentDataByEmail( $emailAddress ) {
        $transactionData = new OneTimePaymentTransactionData();

        $transactionData->setCustomerEmail( $emailAddress );

        return $transactionData;
    }

    /**
	 * @param PaymentFormModel $paymentFormModel
	 *
	 * @return OneTimePaymentTransactionData
	 */
	public static function createOneTimePaymentDataByModel( $paymentFormModel ) {
        $transactionData = new OneTimePaymentTransactionData();

        $transactionData->setIpAddress( $paymentFormModel->getIpAddress() );
        $transactionData->setFormName( $paymentFormModel->getFormName() );
        $transactionData->setStripePaymentMethodId( $paymentFormModel->getStripePaymentMethodId() );
        $transactionData->setStripePaymentIntentId( $paymentFormModel->getStripePaymentIntentId() );
        $transactionData->setStripeCustomerId( ! is_null( $paymentFormModel->getStripeCustomer() ) ? $paymentFormModel->getStripeCustomer()->id : null );
        $transactionData->setCustomerEmail( $paymentFormModel->getCardHolderEmail() );
        $transactionData->setCustomerPhone( $paymentFormModel->getCardHolderPhone() );
        $transactionData->setCustomerName( $paymentFormModel->getCardHolderName() );
        $transactionData->setCurrency( $paymentFormModel->getForm()->currency );
        $transactionData->setAmount( $paymentFormModel->getAmount() );
        $transactionData->setCouponCode( $paymentFormModel->getCouponCode() );
        $transactionData->setCouponId( ! is_null( $paymentFormModel->getStripeCoupon() ) ? $paymentFormModel->getStripeCoupon()->id : null );
        $transactionData->setProductName( $paymentFormModel->getProductName() );
        $transactionData->setBillingName( $paymentFormModel->getBillingName() );
        $transactionData->setBillingAddress( $paymentFormModel->getBillingAddress() );
        $transactionData->setShippingName( $paymentFormModel->getShippingName() );
        $transactionData->setShippingAddress( $paymentFormModel->getShippingAddress() );
        $transactionData->setCustomInputValues( $paymentFormModel->getCustomInputvalues() );
        $transactionData->setTransactionId( $paymentFormModel->getTransactionId() );
        $transactionData->setCustomerTaxId( $paymentFormModel->getTaxId() );
        // Untaxed and undiscounted until a tax or coupon preview, or the payment's invoice, prices it.
        $transactionData->setProductAmountGross( (int) $paymentFormModel->getAmount() );
        $transactionData->setProductAmountNet( (int) $paymentFormModel->getAmount() );
        $transactionData->setProductAmountTax( 0 );
        $transactionData->setProductAmountDiscount( 0 );
		$transactionData->setCustomFieldsJSON( $paymentFormModel->getCustomFieldsJSON() );

		return $transactionData;
	}

	/**
	 * @param string $emailAddress
	 *
	 * @return SaveCardTransactionData
	 */
    public static function createSaveCardDataByEmail( $emailAddress) {
        $transactionData = new SaveCardTransactionData();

        $transactionData->setCustomerEmail( $emailAddress );

        return $transactionData;
    }

	/**
	 * @param PaymentFormModel $paymentFormModel
	 *
	 * @return SaveCardTransactionData
	 */
	public static function createSaveCardDataByModel( $paymentFormModel ) {
        $transactionData = new SaveCardTransactionData();

        $transactionData->setIpAddress( $paymentFormModel->getIpAddress() );
        $transactionData->setFormName( $paymentFormModel->getFormName() );
        $transactionData->setStripePaymentMethodId( $paymentFormModel->getStripePaymentMethodId() );
        $transactionData->setStripePaymentIntentId( $paymentFormModel->getStripePaymentIntentId() );
        $transactionData->setStripeCustomerId( ! is_null( $paymentFormModel->getStripeCustomer() ) ? $paymentFormModel->getStripeCustomer()->id : null );
        $transactionData->setCustomerEmail( $paymentFormModel->getCardHolderEmail() );
        $transactionData->setCustomerPhone( $paymentFormModel->getCardHolderPhone() );
        $transactionData->setCustomerName( $paymentFormModel->getCardHolderName() );
        $transactionData->setBillingName( $paymentFormModel->getBillingName() );
        $transactionData->setBillingAddress( $paymentFormModel->getBillingAddress() );
        $transactionData->setShippingName( $paymentFormModel->getShippingName() );
        $transactionData->setShippingAddress( $paymentFormModel->getShippingAddress() );
        $transactionData->setCustomInputValues( $paymentFormModel->getCustomInputvalues() );
        $transactionData->setTransactionId( $paymentFormModel->getTransactionId() );
		$transactionData->setCustomFieldsJSON( $paymentFormModel->getCustomFieldsJSON() );

        return $transactionData;
    }

    /**
     * @param string $emailAddress
     *
     * @return SubscriptionTransactionData
     */
    public static function createSubscriptionDataByEmail( $emailAddress ) {
        $transactionData = new SubscriptionTransactionData();

        $transactionData->setCustomerEmail( $emailAddress );

        return $transactionData;
    }

    /**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 *
	 * @return SubscriptionTransactionData
	 */
	public static function createSubscriptionDataByModel( $subscriptionFormModel) {
		$form = $subscriptionFormModel->getForm();

		$billingAddress  = null;
		$shippingAddress = null;
		if ( isset( $form->showAddress ) ) {
			$billingAddress  = 1 == $form->showAddress ? $subscriptionFormModel->getBillingAddress() : null;
			$shippingAddress = 1 == $form->showAddress ? $subscriptionFormModel->getShippingAddress() : null;
		}
		if ( isset( $form->showBillingAddress ) ) {
			$billingAddress = 1 == $form->showBillingAddress ? $subscriptionFormModel->getBillingAddress() : null;
		}
		if ( isset( $form->showShippingAddress ) ) {
			$shippingAddress = 1 == $form->showShippingAddress ? $subscriptionFormModel->getShippingAddress() : null;
		}

		$stripePlanAmount = $subscriptionFormModel->getPlanAmount();
		$stripePlanSetupFee = $subscriptionFormModel->getSetupFee();
		$stripePlanQuantity = $subscriptionFormModel->getStripePlanQuantity();

		$vatPercent                      = 0;
        $planAmountGrossComposite        = Utils::calculateGrossFromNet( $stripePlanAmount, $vatPercent );
        $planSetupFeeGrossComposite      = Utils::calculateGrossFromNet( $stripePlanSetupFee, $vatPercent );
        $planAmountGrossTotalComposite   = Utils::calculateGrossFromNet( $stripePlanQuantity * $stripePlanAmount, $vatPercent );
        $planSetupFeeGrossTotalComposite = Utils::calculateGrossFromNet( $stripePlanQuantity * $stripePlanSetupFee, $vatPercent );

        $transactionData = new SubscriptionTransactionData();

        $transactionData->setIpAddress( $subscriptionFormModel->getIpAddress() );
        $transactionData->setFormName( $subscriptionFormModel->getFormName() );
        $transactionData->setStripePaymentMethodId( $subscriptionFormModel->getStripePaymentMethodId() );
        $transactionData->setStripeCustomerId( ! is_null( $subscriptionFormModel->getStripeCustomer() ) ? $subscriptionFormModel->getStripeCustomer()->id : null );
        $transactionData->setCustomerName( $subscriptionFormModel->getCardHolderName() );
        $transactionData->setCustomerEmail( $subscriptionFormModel->getCardHolderEmail() );
        $transactionData->setCustomerPhone( $subscriptionFormModel->getCardHolderPhone() );
        $transactionData->setPlanId( $subscriptionFormModel->getStripePlanId() );
        $transactionData->setPlanName( $subscriptionFormModel->getStripePlan()->product->name );
        $transactionData->setPlanCurrency( $subscriptionFormModel->getStripePlan()->currency );
        $transactionData->setSetupFeeNetAmount( $stripePlanSetupFee );
        $transactionData->setSetupFeeGrossAmount( $planSetupFeeGrossComposite['gross'] );
        $transactionData->setSetupFeeTaxAmount( $transactionData->getSetupFeeGrossAmount() - $transactionData->getSetupFeeNetAmount() );
        $transactionData->setSetupFeeNetAmountTotal( $planSetupFeeGrossTotalComposite['net'] );
        $transactionData->setSetupFeeGrossAmountTotal( $planSetupFeeGrossTotalComposite['gross'] );
        $transactionData->setSetupFeeTaxAmountTotal( $planSetupFeeGrossTotalComposite['taxValue'] );
        $transactionData->setPlanNetAmount( $stripePlanAmount );
        $transactionData->setPlanGrossAmount( $planAmountGrossComposite['gross'] );
        $transactionData->setPlanTaxAmount( $transactionData->getPlanGrossAmount() - $transactionData->getPlanNetAmount() );
        $transactionData->setPlanFutureNetAmount( $stripePlanAmount );
        $transactionData->setPlanFutureGrossAmount( $planAmountGrossComposite['gross'] );
        $transactionData->setPlanFutureTaxAmount( $transactionData->getPlanGrossAmount() - $transactionData->getPlanNetAmount() );
        $transactionData->setPlanQuantity( $stripePlanQuantity );
        $transactionData->setPlanNetAmountTotal( $planAmountGrossTotalComposite['net'] );
        $transactionData->setPlanGrossAmountTotal( $planAmountGrossTotalComposite['gross'] );
        $transactionData->setPlanTaxAmountTotal( $planAmountGrossTotalComposite['taxValue'] );
        $transactionData->setProductName( $subscriptionFormModel->getStripePlan()->product->name );
        $transactionData->setBillingName( $subscriptionFormModel->getBillingName() );
        $transactionData->setBillingAddress( $billingAddress );
        $transactionData->setShippingName( $subscriptionFormModel->getShippingName() );
        $transactionData->setShippingAddress( $shippingAddress );
        $transactionData->setCustomInputValues( $subscriptionFormModel->getCustomInputvalues() );
        $transactionData->setCouponCode( $subscriptionFormModel->getCouponCode() );
        $transactionData->setDiscountId( $subscriptionFormModel->getStripeDiscountId() );
        $transactionData->setDiscountType( $subscriptionFormModel->getStripeDiscountType() );
        $transactionData->setMetadata( $subscriptionFormModel->getMetadata() );
        $transactionData->setBillingCycleAnchorDay( $subscriptionFormModel->getBillingAnchorDay() );
        $transactionData->setProrateUntilAnchorDay( $subscriptionFormModel->getProrateUntilAnchorDay() );
        $transactionData->setTrialPeriodDays( $subscriptionFormModel->getTrialPeriodDays() );
        $transactionData->setTransactionId( $subscriptionFormModel->getTransactionId() );
        $transactionData->setCustomerTaxId( $subscriptionFormModel->getTaxId() );
        $transactionData->setBusinessName( $subscriptionFormModel->getBuyingAsBusiness() == 1 ? $subscriptionFormModel->getBusinessName() : null );
		$transactionData->setCustomFieldsJSON( $subscriptionFormModel->getCustomFieldsJSON() );

		return $transactionData;
	}

	/**
	 * Store transaction data as a transient.
	 *
	 * @param FormTransactionData $data
	 *
	 * @return null|string
	 */
	public function store( $data ) {
		$key = $this->generateKey();
		set_transient( $key, $data, 1 * HOUR_IN_SECONDS );

		return rawurlencode( $key );
	}

	/**
	 * Generates a random key currently not in use as a transient key.
	 */
	private function generateKey() {
		$key = null;
		do {
			$key = self::KEY_PREFIX . crypt( strval( round( microtime( true ) * 1000 ) ), strval( rand() ) );
		} while ( get_transient( $key ) !== false );

		return $key;
	}

	/**
	 * @param $data_key
	 *
	 * @return bool|FormTransactionData
	 */
	public function retrieve( $data_key ) {
		if ( is_null( $data_key ) ) {
			return false;
		}
		$prefix_position = strpos( $data_key, self::KEY_PREFIX );
		if ( $prefix_position === false ) {
			return false;
		}
		if ( $prefix_position == 0 ) {
			$data = get_transient( $data_key );

			if ( $data !== false ) {
				delete_transient( $data_key );
			}

			return $data;
		} else {
			return false;
		}
	}

}
