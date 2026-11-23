<?php

namespace ThemeIsle\WPFullPay\Customer;

use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Pricing\RecoveryFee;

trait OneTimeInvoiceCreatorAddOn {
	protected function createOneTimeInvoiceContext( $formModel ) {
		if ( $formModel instanceof PaymentFormModel ) {
			return ( new OneTimeInvoiceContextCreatorPaymentForm( $formModel ) )->getContext();
		} else if ( $formModel instanceof DonationFormModel ) {
			return ( new OneTimeInvoiceContextCreatorDonationForm( $formModel ) )->getContext();
		} else {
			throw new \Exception( __CLASS__ . '::' . __FUNCTION__ . '(): form model type not supported.' );
		}
	}

	protected function createInvoiceForOneTimePaymentByFormModel( $formModel, $options ) {
		return $this->stripe->createInvoiceForOneTimePayment( $this->createOneTimeInvoiceContext( $formModel ), $options );
	}

	/**
	 * The invoice of a completed Checkout session lists what the session charged, not the form as edited since
	 * (#758): its product or donation line at the price or amount charged, its recovery fee lines, and the coupon
	 * Checkout applied, which the customer may have typed on Stripe's page.
	 *
	 * @param PaymentFormModel|DonationFormModel $formModel
	 * @param \StripeWPFS\Stripe\Checkout\Session $checkoutSession Retrieved with its line items and their discounts.
	 *
	 * @return CreateOneTimeInvoiceContext
	 * @throws \Exception
	 */
	protected function createOneTimeInvoiceContextOfCheckoutSession( $formModel, $checkoutSession ) {
		$ctx = $this->createOneTimeInvoiceContext( $formModel );
		$lineItems = isset( $checkoutSession->line_items->data ) ? array_values( (array) $checkoutSession->line_items->data ) : [];
		if ( empty( $lineItems ) ) {
			return $ctx;
		}

		$productLineItem = RecoveryFee::findProductLineItemOfCheckoutSession( $checkoutSession );
		if ( is_null( $productLineItem ) ) {
			return $ctx;
		}

		// The invoice applies the coupon and tax again, so its lines use the amounts before them.
		$recoveryFee = 0;
		foreach ( RecoveryFee::findFeeLineItemsOfCheckoutSession( $checkoutSession ) as $lineItem ) {
			$recoveryFee += (int) $lineItem->amount_subtotal;
		}

		if ( ! empty( $checkoutSession->currency ) ) {
			$ctx->currency = $checkoutSession->currency;
		}

		// A price the session created from price_data is inactive, and Stripe refuses it on an invoice line.
		$ctx->stripePriceId = empty( $productLineItem->price->active ) ? null : $productLineItem->price->id;
		$ctx->amount = (int) $productLineItem->amount_subtotal;
		$ctx->productName = $productLineItem->description;
		$ctx->recoveryFee = $recoveryFee;

		$ctx->stripeCouponId = null;
		foreach ( $lineItems as $lineItem ) {
			foreach ( isset( $lineItem->discounts ) ? $lineItem->discounts : [] as $discountAmount ) {
				if ( isset( $discountAmount->discount->coupon->id ) && '' !== (string) $discountAmount->discount->coupon->id ) {
					$ctx->stripeCouponId = $discountAmount->discount->coupon->id;
					break 2;
				}
			}
		}

		return $ctx;
	}

	/**
	 * Creates the invoice of a charged PaymentIntent and marks it paid. A request sent again for the same
	 * PaymentIntent, such as the final request of the form, gets the invoice the first one created back.
	 *
	 * @param CreateOneTimeInvoiceContext $ctx What the PaymentIntent charged.
	 * @param CreateOneTimeInvoiceOptions $options
	 * @param string|null $paymentIntentId
	 *
	 * @return \stdClass The paid invoice, decoded from Stripe's JSON.
	 * @throws \Exception
	 */
	protected function createPaidInvoiceForPaymentIntent( $ctx, $options, $paymentIntentId ) {
		$options->idempotencyKey = empty( $paymentIntentId ) ? null : 'wpfs-invoice-' . $paymentIntentId;
		$stripeInvoice = $this->stripe->createInvoiceForOneTimePayment( $ctx, $options );

		return $this->stripe->payInvoiceOutOfBand( $stripeInvoice->id, empty( $paymentIntentId ) ? null : 'wpfs-invoice-pay-' . $paymentIntentId );
	}

	/**
	 * Invoices a payment already recorded. A failure is logged and the completion goes on without the invoice: a
	 * retry would find the payment recorded, and would send neither the receipt nor the after-payment action.
	 *
	 * @param CreateOneTimeInvoiceContext $ctx
	 * @param CreateOneTimeInvoiceOptions $options
	 * @param string $paymentIntentId
	 *
	 * @return \stdClass|null The paid invoice, null when it could not be created.
	 */
	protected function createPaidInvoiceForRecordedPayment( $ctx, $options, $paymentIntentId ) {
		try {
			return $this->createPaidInvoiceForPaymentIntent( $ctx, $options, $paymentIntentId );
		} catch ( \Exception $ex ) {
			$this->logger->error( __FUNCTION__, "Cannot invoice the recorded payment '{$paymentIntentId}'.", $ex );

			return null;
		}
	}

	protected function createPreviewInvoiceForOneTimePaymentByFormModel( $formModel, $options ) {
		return $this->stripe->createPreviewInvoiceForOneTimePayment( $this->createOneTimeInvoiceContext( $formModel ), $options );
	}
}
