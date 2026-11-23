<?php

namespace ThemeIsle\WPFullPay\Webhooks\Events;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;
use ThemeIsle\WPFullPay\Webhooks\ChargeEventUtils;
use ThemeIsle\WPFullPay\Webhooks\EventProcessor;
use ThemeIsle\WPFullPay\Webhooks\LiveModeAwareEventProcessorContext;

class ChargeSucceeded extends EventProcessor
{
	use ChargeEventUtils;

	public function getType()
	{
		return \StripeWPFS\Stripe\Event::CHARGE_SUCCEEDED;
	}

	protected function processEvent($event, LiveModeAwareEventProcessorContext $context)
	{
		/** @var \StripeWPFS\Stripe\Charge|null $charge */
		$charge = $this->getDataObject($event);
		if (!is_null($charge)) {
			if ( ! empty( $charge->payment_intent ) ) {
				$this->insertDonationFromWebhookIfNeeded( $charge, $context );
			}
			// The report too: the donation's fallback above wrote it from the event's charge.
			$this->updatePaymentAndReportFromStripe($charge, $context);

		}
	}

	/**
	 * Fallback insert for donations when AJAX insert failed or never fired.
	 * Uses metadata stored on the PaymentIntent to reconstruct the donation row.
	 *
	 * @param mixed $charge
	 * @param LiveModeAwareEventProcessorContext $context
	 * @return void
	 */
	protected function insertDonationFromWebhookIfNeeded( $charge, $context ) {
		if ( ! isset( $charge->payment_intent ) ) {
			return;
		}

		// Not atomic: the charge request can record the donation between this lookup and the
		// insert below, leaving two rows for one PaymentIntent. Tracked in #740.
		$existingDonation = $this->db->getDonationByPaymentIntentId( $charge->payment_intent );
		if ( $existingDonation ) {
			return;
		}

		try {
			$paymentIntent = $context->getStripe()->retrievePaymentIntent( $charge->payment_intent );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error in webhook fallback donation insert', $ex );

			// So that Stripe sends the event again: a donation its own request never recorded would stay unrecorded.
			throw $ex;
		}
		if ( ! isset( $paymentIntent->metadata ) ) {
			return;
		}

		/** @var \stdClass $metadata */
		$metadata = $paymentIntent->metadata;

		// Check if this is a donation by looking for donation_frequency metadata.
		if ( ! isset( $metadata->donation_frequency ) ) {
			return;
		}

		// Recorded from the payment's latest charge while holding it: a refund made before has no row for its webhook to
		// update. Another delivery holding the payment too long, or Stripe not answering, fails this one, so that Stripe
		// sends it again.
		$this->updateFromLatestCharge(
			$charge->payment_intent,
			$context,
			function ( $latestCharge ) use ( $charge, $paymentIntent, $metadata ) {
				$this->insertDonationFromCharge( $latestCharge ?? $charge, $charge, $paymentIntent, $metadata );
			}
		);
	}

	/**
	 * @param \StripeWPFS\Stripe\Charge|\stdClass $charge The payment's latest charge.
	 * @param \StripeWPFS\Stripe\Charge $eventCharge The event's charge, which names the payment and its customer.
	 * @param \StripeWPFS\Stripe\PaymentIntent|\stdClass $paymentIntent
	 * @param \stdClass $metadata The PaymentIntent's, which describes the donation.
	 */
	private function insertDonationFromCharge( $charge, $eventCharge, $paymentIntent, $metadata ): void {
		// The charge request can have recorded it while this delivery waited for the payment.
		if ( $this->db->getDonationByPaymentIntentId( $eventCharge->payment_intent ) ) {
			return;
		}

		try {
			$customer_id = $charge->customer ?? $eventCharge->customer ?? null;
			if ( ! $customer_id ) {
				$customer_id = isset( $metadata->customer_id ) ? $metadata->customer_id : null;
			}
			$billing_address = explode( '|', $metadata->billing_address ?? '' );
			$shipping_address = explode( '|', $metadata->shipping_address ?? '' );
			$description = isset( $paymentIntent->description ) ? $paymentIntent->description : '';
			$description = Utils::truncateString( $description, 255 );

			// Reconstruct donation data from metadata and charge
			$data = [
				'stripeCustomerID' => $customer_id,
				'stripeSubscriptionID' => null,
				'stripePaymentIntentID' => $eventCharge->payment_intent,
				'stripeSetupIntentID' => null,
				'stripePlanID' => null,
				'description' => $description,
				'paymentMethod' => 'card',
				'paid' => $charge->paid,
				'captured' => $charge->captured,
				'refunded' => $charge->refunded,
				'expired' => false,
				'failureCode' => $charge->failure_code,
				'failureMessage' => $charge->failure_message,
				'lastChargeStatus' => $charge->status,
				'currency' => isset( $paymentIntent->currency ) ? $paymentIntent->currency : '',
				'amount' => isset( $paymentIntent->amount ) ? $paymentIntent->amount : 0,
				'donationFrequency' => isset( $metadata->donation_frequency ) ? $metadata->donation_frequency : '',
				'subscriptionStatus' => null,
				'name' => isset( $metadata->customer_name ) ? $metadata->customer_name : '',
				'email' => isset( $metadata->customer_email ) ? $metadata->customer_email : '',
				'billingName' => isset( $metadata->billing_name ) ? $metadata->billing_name : '',
				'addressLine1' => $billing_address[0],
				'addressLine2' => isset( $billing_address[1] ) ? $billing_address[1] : '',
				'addressZip' => isset( $billing_address[2] ) ? $billing_address[2] : '',
				'addressCity' => isset( $billing_address[3] ) ? $billing_address[3] : '',
				'addressState' => isset( $billing_address[4] ) ? $billing_address[4] : '',
				'addressCountry' => isset( $billing_address[5] ) ? $billing_address[5] : '',
				'addressCountryCode' => isset( $billing_address[6] ) ? $billing_address[6] : '',
				'shippingName' => isset( $metadata->shipping_name ) ? $metadata->shipping_name : '',
				'shippingAddressLine1' => $shipping_address[0],
				'shippingAddressLine2' => isset( $shipping_address[1] ) ? $shipping_address[1] : '',
				'shippingAddressZip' => isset( $shipping_address[2] ) ? $shipping_address[2] : '',
				'shippingAddressCity' => isset( $shipping_address[3] ) ? $shipping_address[3] : '',
				'shippingAddressState' => isset( $shipping_address[4] ) ? $shipping_address[4] : '',
				'shippingAddressCountry' => isset( $shipping_address[5] ) ? $shipping_address[5] : '',
				'shippingAddressCountryCode' => isset( $shipping_address[6] ) ? $shipping_address[6] : '',
				'created' => date( 'Y-m-d H:i:s', $paymentIntent->created ),
				'livemode' => $paymentIntent->livemode ? 1 : 0,
				'formId' => isset( $metadata->form_id ) ? $metadata->form_id : null,
				'formType' => isset( $metadata->form_type ) ? $metadata->form_type : Main::FORM_TYPE_INLINE_DONATION,
				'formName' => isset( $metadata->form_name ) ? $metadata->form_name : '',
				'ipAddressSubmit' => isset( $metadata->ip_address ) ? $metadata->ip_address : '',
				'termsOfUseAcceptedAt' => Database::termsOfUseAcceptedAtFromMetadata( $metadata->{FormModel::METADATA_KEY_TERMS_OF_USE_ACCEPTED_AT} ?? null ),
				'customFields' => isset( $metadata->custom_fields ) ? $metadata->custom_fields : null,
				'phoneNumber' => ! empty( $metadata->cardholder_phone ) ? $metadata->cardholder_phone : null,
				'afterChargeActionFired' => 0,
			];

			global $wpdb;
			$insertResult = $wpdb->insert(
				$wpdb->prefix . 'fullstripe_donations',
				apply_filters( 'fullstripe_insert_donation_data', $data )
			);

			if ( $insertResult ) {
				$this->logger->debug( __FUNCTION__, 'Donation inserted via webhook fallback for PaymentIntent: ' . $eventCharge->payment_intent );

				// From the latest charge: a refund processed before this found no report to update.
				$this->db->saveReport(
					[
						'created_at' => date( 'Y-m-d H:i:s', $eventCharge->created ),
						'updated_at' => date( 'Y-m-d H:i:s', $eventCharge->created ),
						'currency' => $data['currency'],
						'amount' => $data['amount'],
						'formId' => $data['formId'],
						'formType' => $data['formType'],
						'stripePaymentIntentID' => $data['stripePaymentIntentID'],
						'stripeCustomerID' => $data['stripeCustomerID'],
						'stripeSubscriptionID' => $data['stripeSubscriptionID'],
						'amount_refunded' => (int) ( $charge->amount_refunded ?? 0 ),
						'status' => $this->db->getPaymentStatus( $charge ),
						'mode' => $eventCharge->livemode ? 'live' : 'test',
					],
					[
						'updated_at' => date( 'Y-m-d H:i:s', time() ),
						'currency' => $data['currency'],
						'amount' => $data['amount'],
						'stripeCustomerID' => $data['stripeCustomerID'],
						'status' => $this->db->getPaymentStatus( $charge ),
						'mode' => $eventCharge->livemode ? 'live' : 'test',
					]
				);
				// A report saved before this keeps its refund, which only rises.
				if ( ! empty( $charge->amount_refunded ) ) {
					$this->db->updateReportRefund( $eventCharge->payment_intent, (int) $charge->amount_refunded, $this->db->getPaymentStatus( $charge ) );
				}
			} else {
				$this->logger->error( __FUNCTION__, 'Failed to insert donation via webhook fallback for PaymentIntent: ' . $eventCharge->payment_intent );
			}
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, 'Error in webhook fallback donation insert', $ex );
		}
	}
}
