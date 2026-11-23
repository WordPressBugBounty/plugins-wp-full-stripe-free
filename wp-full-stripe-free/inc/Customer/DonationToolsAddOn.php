<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Views\SubscriptionFormViewConstants;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\Utils;

trait DonationToolsAddOn {
	/* @var stripe Database */
	/* @var mailer Mailer */

	/**
	 * @param DonationFormModel $donationFormModel
	 *
	 * @return boolean
	 */
	private function isRecurringDonation( $donationFormModel ) {
		$res = false;

		$donationFrequencies = [
			DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY,
			DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY,
			DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY,
			DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL
		];

		if ( false === array_search( $donationFormModel->getDonationFrequency(), $donationFrequencies ) ) {
			$res = false;
		} else {
			$res = true;
		}

		return $res;
	}

	/**
	 * @param $donationFormModel DonationFormModel
	 * @param $transactionData DonationTransactionData
	 */
	protected function sendDonationEmailReceipt( $donationFormModel, $transactionData ) {
		$this->mailer->sendDonationEmailReceipt( $donationFormModel->getForm(), $transactionData );
	}

	/**
	 * @param string $currency
	 * @param string $donationFrequency
	 *
	 * @return string
	 */
	protected function constructDonationPlanID( $currency, $donationFrequency ) {
		return Main::DONATION_PLAN_ID_PREFIX . ucfirst( $currency ) . ucfirst( $donationFrequency );
	}

	protected function localizeDonationPlanName( $frequency ) {
		$res = "";

		switch ( $frequency ) {
			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY:
				/* translators: %s: formatted donation amount with currency symbol */
				$res = __( 'Daily donation (%s)', 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY:
				/* translators: %s: formatted donation amount with currency symbol */
				$res = __( 'Weekly donation (%s)', 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY:
				/* translators: %s: formatted donation amount with currency symbol */
				$res = __( 'Monthly donation (%s)', 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL:
				/* translators: %s: formatted donation amount with currency symbol */
				$res = __( 'Annual donation (%s)', 'wp-full-stripe-free' );
				break;
		}

		return $res;

	}

	/**
	 * @param $donationInterval string
	 *
	 * @return string
	 */
	protected function translateFrequencyToInterval( $donationFrequency ) {
		$res = 'month';

		switch ( $donationFrequency ) {
			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY:
				$res = 'day';
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY:
				$res = 'week';
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY:
				$res = 'month';
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL:
				$res = 'year';
				break;
		}

		return $res;
	}

	/**
	 * The donation frequency a donation plan's billing interval stands for.
	 *
	 * @param string $interval The billing interval: day, week, month or year.
	 *
	 * @return string
	 */
	protected function translateIntervalToFrequency( $interval ) {
		$frequencies = [
			'day' => DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY,
			'week' => DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY,
			'month' => DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY,
			'year' => DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL,
		];

		return isset( $frequencies[ $interval ] ) ? $frequencies[ $interval ] : DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY;
	}

	/**
	 * @param string $planID
	 * @param string $currency
	 * @param string $donationFrequency
	 *
	 * @return \StripeWPFS\Stripe\Price
	 * @throws \Exception
	 */
	protected function createDonationPlan( $planID, $currency, $donationFrequency ) {
		$planName = sprintf( $this->localizeDonationPlanName( $donationFrequency ), strtoupper( $currency ) );
		$interval = $this->translateFrequencyToInterval( $donationFrequency );
		$plan = $this->stripe->createRecurringPlan( $planID, $planName, $currency, $interval, 1 );

		return $plan;
	}

	/**
	 * A shared price the plugin bills recurring donations, recovery fees or setup fees with, by its lookup key, reactivated with
	 * its product if the site owner archived either in Stripe: the form still takes them, and Stripe refuses new
	 * subscriptions and prices on an archived price or product. A new price can't take an archived price's lookup
	 * key, and moving the key would leave existing fee lines unrecognised.
	 *
	 * @param string $planId
	 *
	 * @return object{id: string, active: bool, product: string|object{id: string, active: bool}}|null The price.
	 * @throws \Exception
	 */
	protected function retrieveDonationPlan( $planId ) {
		$plans = $this->stripe->retrievePlansWithLookupKey( $planId );
		if ( empty( $plans->data ) ) {
			// Stripe lists archived prices only when asked for them.
			$plans = $this->stripe->retrievePlansWithLookupKey( $planId, false );
		}
		if ( empty( $plans->data ) ) {
			return null;
		}

		$plan = $plans->data[0];
		if ( empty( $plan->active ) ) {
			$plan = $this->stripe->updatePrice( $plan->id, [ 'active' => true ] );
		}
		$product = is_object( $plan->product ) ? $plan->product : $this->stripe->retrieveProduct( $plan->product );
		if ( empty( $product->active ) ) {
			$this->stripe->updateProduct( $product->id, [ 'active' => true ] );
		}

		return $plan;
	}

	/**
	 * @param string $currency
	 * @param string $donationFrequency
	 *
	 * @return \StripeWPFS\Stripe\Price|object{id: string, active: bool, product: string|object{id: string, active: bool}}
	 * @throws \Exception
	 */
	protected function createOrRetrieveDonationPlan( $currency, $donationFrequency ) {
		$planId = $this->constructDonationPlanID( $currency, $donationFrequency );

		$plan = $this->retrieveDonationPlan( $planId );
		if ( is_null( $plan ) ) {
			$plan = $this->createDonationPlan( $planId, $currency, $donationFrequency );
		}

		return $plan;
	}

	/**
	 * @param DonationFormModel $donationFormModel
	 *
	 * @return \StripeWPFS\Stripe\Subscription
	 * @throws \Exception
	 */
	protected function createSubscriptionForDonation( $donationFormModel ) {
		$plan = $this->createOrRetrieveDonationPlan( $donationFormModel->getForm()->currency, $donationFormModel->getDonationFrequency() );
		$amount = $donationFormModel->getAmount();

		$recoveryFee = $donationFormModel->getFeeRecoveryAccepted();
		$recoveryFeeData = Utils::getFeeRecoveryData( $donationFormModel->getForm() );

		// The accepted recovery fee is charged every period, as a line item on the fee price inline subscriptions use.
		$additionalItems = [];
		if ( $recoveryFee && ! empty( $recoveryFeeData ) ) {
			$feePlan = $this->createSubscriptionForRecoveryFee(
				$recoveryFeeData[ Options::OPTION_FEE_RECOVERY_CURRENCY ],
				$this->translateFrequencyToInterval( $donationFormModel->getDonationFrequency() )
			);
			$additionalItems[] = [
				'price' => $feePlan->id,
				'quantity' => Utils::calculateRecoveryFee( $amount, $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_PERCENTAGE ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_FEE_ADDITIONAL_AMOUNT ], $recoveryFeeData[ Options::OPTION_FEE_RECOVERY_CURRENCY ] ),
			];
		}

		$customerId = $donationFormModel->getStripeCustomer()->id;
		$metadata = $donationFormModel->getMetadata();

		// The browser saves a new PaymentMethod for every donation, so only a request sent again, such as a
		// retry, repeats the key: Stripe answers it with the subscription the first one created (#738). The
		// metadata is left out: every request stamps the time the donor accepted the terms of use, and an
		// integration can add values that change on every request too.
		$paymentMethodId = $donationFormModel->getStripePaymentMethodId();
		$idempotencyKey = empty( $paymentMethodId ) ?
			null :
			'wpfs-donation-subscription-' . hash( 'sha256', (string) wp_json_encode( [ $customerId, $paymentMethodId, $plan->id, $amount, $additionalItems ] ) );

		return $this->stripe->subscribeCustomerToPlan( $customerId, $plan->id, $amount, $metadata, $additionalItems, $idempotencyKey );
	}

	/**
	 * The shared price whose product inline subscriptions bill their setup fee on, marked as the setup fee. Stripe
	 * needs an existing product for a line a subscription adds to its first invoice, and the line is named after it.
	 * Uses only the Stripe client, not the form model or transaction data.
	 *
	 * @param string $currency The currency of the price, if it has to be created; the product has none.
	 *
	 * @return object{id: string, active: bool, product: string|object{id: string, active: bool}}
	 * @throws \Exception
	 */
	public function createOrRetrieveSetupFeePrice( $currency ) {
		$price = $this->retrieveDonationPlan( Main::SETUP_FEE_PRICE_LOOKUP_KEY );
		if ( ! is_null( $price ) ) {
			return $price;
		}

		try {
			return $this->stripe->createSetupFeePrice(
				Main::SETUP_FEE_PRICE_LOOKUP_KEY,
				/* translators: Name of the invoice line that charges a subscription's one-time setup fee */
				__( 'One-time setup fee', 'wp-full-stripe-free' ),
				$currency
			);
		} catch ( \Exception $e ) {
			// Stripe gives a lookup key to one price only: a subscription created at the same time made it first.
			$price = $this->retrieveDonationPlan( Main::SETUP_FEE_PRICE_LOOKUP_KEY );
			if ( is_null( $price ) ) {
				throw $e;
			}

			return $price;
		}
	}

	/**
	 * The shared price recovery fees are billed on: one minor unit, charged the fee as its quantity. Public because
	 * plan changes reprice fees on its product too (`Stripe::findFeeProductId()`). Uses only the Stripe client, not
	 * the form model or transaction data.
	 *
	 * @param string $currency
	 * @param string $frequency The billing interval: day, week, month or year.
	 * @param int $intervalCount The plan's billing period in intervals; Stripe refuses prices billed at different periods.
	 *
	 * @return \StripeWPFS\Stripe\Price|object{id: string, active: bool, product: string|object{id: string, active: bool}}
	 * @throws \Exception
	 */
	public function createSubscriptionForRecoveryFee( $currency, $frequency, $intervalCount = 1 ) {
		$plan = $this->createOrRetrieveSubscriptionPlan( $currency, $frequency, $intervalCount );
		return $plan;
	}

	/**
	 * @param string $currency
	 * @param string $frequency
	 * @param int $intervalCount
	 *
	 * @return \StripeWPFS\Stripe\Price|object{id: string, active: bool, product: string|object{id: string, active: bool}}
	 * @throws \Exception
	 */
	protected function createOrRetrieveSubscriptionPlan( $currency, $frequency, $intervalCount = 1 ) {
		$planId = $this->constructSubscriptionPlanID( $currency, $frequency, $intervalCount );

		$plan = $this->retrieveDonationPlan( $planId );

		if ( is_null( $plan ) ) {
			$plan = $this->createSubscriptionPlan( $planId, $currency, $frequency, $intervalCount );
		}

		return $plan;
	}

	/**
	 * Plans billed every interval keep the key their fee price always had, so existing fee lines stay recognised.
	 *
	 * @param string $currency
	 * @param string $frequency
	 * @param int $intervalCount
	 *
	 * @return string
	 */
	protected function constructSubscriptionPlanID( $currency, $frequency, $intervalCount = 1 ) {
		$planId = Main::SUBSCRIPTION_PLAN_ID_PREFIX . ucfirst( $currency ) . ucfirst( $frequency );

		return $intervalCount > 1 ? $planId . $intervalCount : $planId;
	}

	/**
	 * @param string $planID
	 * @param string $currency
	 * @param string $frequency
	 * @param int $intervalCount
	 *
	 * @return \StripeWPFS\Stripe\Price
	 * @throws \Exception
	 */
	protected function createSubscriptionPlan( $planID, $currency, $frequency, $intervalCount = 1 ) {
		$planName = $intervalCount > 1 ?
			sprintf( $this->localizeSubscriptionPlanNameForPeriods( $frequency ), $intervalCount, strtoupper( $currency ) ) :
			sprintf( $this->localizeSubscriptionPlanName( $frequency ), strtoupper( $currency ) );
		$plan = $this->stripe->createRecurringPlan( $planID, $planName, $currency, $frequency, $intervalCount, 'licensed' );

		return $plan;
	}

	/**
	 * @param string $frequency
	 *
	 * @return string The name of a fee price billed every few intervals, with the interval count and currency placeholders.
	 */
	protected function localizeSubscriptionPlanNameForPeriods( $frequency ) {
		switch ( $frequency ) {
			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_DAILY:
				/* translators: %1$d: number of days between payments, at least 2, %2$s: currency code */
				return __( 'Transaction fee every %1$d days (%2$s)', 'wp-full-stripe-free' );

			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_WEEKLY:
				/* translators: %1$d: number of weeks between payments, at least 2, %2$s: currency code */
				return __( 'Transaction fee every %1$d weeks (%2$s)', 'wp-full-stripe-free' );

			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_MONTHLY:
				/* translators: %1$d: number of months between payments, at least 2, %2$s: currency code */
				return __( 'Transaction fee every %1$d months (%2$s)', 'wp-full-stripe-free' );

			default:
				/* translators: %1$d: number of years between payments, at least 2, %2$s: currency code */
				return __( 'Transaction fee every %1$d years (%2$s)', 'wp-full-stripe-free' );
		}
	}

	/**
	 * @param string $frequency
	 *
	 * @return string
	 */
	protected function localizeSubscriptionPlanName( $frequency ) {
		$res = "";

		switch ( $frequency ) {
			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_DAILY:
				/* translators: %s: formatted transaction fee amount with currency symbol */
				$res = __( 'Daily transaction fee (%s)', 'wp-full-stripe-free' );
				break;

			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_WEEKLY:
				/* translators: %s: formatted transaction fee amount with currency symbol */
				$res = __( 'Weekly transaction fee (%s)', 'wp-full-stripe-free' );
				break;

			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_MONTHLY:
				/* translators: %s: formatted transaction fee amount with currency symbol */
				$res = __( 'Monthly transaction fee (%s)', 'wp-full-stripe-free' );
				break;

			case SubscriptionFormViewConstants::FIELD_VALUE_SUBSCRIPTION_FREQUENCY_ANNUAL:
				/* translators: %s: formatted transaction fee amount with currency symbol */
				$res = __( 'Annual transaction fee (%s)', 'wp-full-stripe-free' );
				break;
		}

		return $res;

	}
}
