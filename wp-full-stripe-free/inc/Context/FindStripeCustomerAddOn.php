<?php

namespace ThemeIsle\WPFullPay\Context;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\Logger;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;

trait FindStripeCustomerAddOn {
	/** @var Database */
	protected $db;
	/** @var Stripe */
	protected $stripe;
	/** @var Options */
	protected $options;
	/** @var Logger */
	protected $logger;

	/**
	 * The customer of this email. A customer the plugin recorded for the email is used only while its email in Stripe
	 * is still this one: once it was changed there, the address may belong to someone else (#791). Otherwise it is a
	 * customer Stripe lists with the email.
	 *
	 * @param string $email
	 * @return \StripeWPFS\Stripe\Customer|null
	 */
	protected function findStripeCustomerByEmail( $email ) {
		foreach ( $this->retrieveRecordedStripeCustomers( $email ) as $stripeCustomer ) {
			if ( $this->isStripeCustomerEmail( $stripeCustomer, $email ) ) {
				return $stripeCustomer;
			}
		}

		foreach ( $this->stripe->getCustomersByEmail( $email ) as $stripeCustomer ) {
			if ( isset( $stripeCustomer ) && ( ! isset( $stripeCustomer->deleted ) || ! $stripeCustomer->deleted ) ) {
				return $stripeCustomer;
			}
		}

		return null;
	}

	/**
	 * Whether the plugin recorded a customer for this email that Stripe now has with another email.
	 *
	 * @param string $email
	 */
	protected function hasRecordedStripeCustomerWithAnotherEmail( $email ): bool {
		foreach ( $this->retrieveRecordedStripeCustomers( $email ) as $stripeCustomer ) {
			if ( ! $this->isStripeCustomerEmail( $stripeCustomer, $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this is the customer's email in Stripe, compared without regard to case as the plugin's records are.
	 *
	 * @param \StripeWPFS\Stripe\Customer $stripeCustomer
	 * @param string $email
	 */
	protected function isStripeCustomerEmail( $stripeCustomer, $email ): bool {
		return isset( $stripeCustomer->email ) && 0 === strcasecmp( $stripeCustomer->email, (string) $email );
	}

	/**
	 * The customers the plugin recorded for the email that still exist in Stripe, retrieved one at a time.
	 *
	 * @param string $email
	 * @return \Generator<int, \StripeWPFS\Stripe\Customer>
	 * @noinspection PhpUndefinedFieldInspection
	 */
	private function retrieveRecordedStripeCustomers( $email ): \Generator {
		$liveMode = $this->options->get( Options::OPTION_API_MODE ) === Main::STRIPE_API_MODE_LIVE;

		foreach ( $this->db->getExistingStripeCustomersByEmail( $email, $liveMode ) as $customer ) {
			if ( empty( $customer['stripeCustomerID'] ) ) {
				continue;
			}
			try {
				$stripeCustomer = $this->stripe->retrieveCustomer( $customer['stripeCustomerID'] );
			} catch (\Exception $ex) {
				$this->logger->error( __FUNCTION__, "Stripe customer doesn't exist, which might be fine in this case. We'll try searching by email instead.", $ex );
				continue;
			}

			if ( isset( $stripeCustomer ) && ( ! isset( $stripeCustomer->deleted ) || ! $stripeCustomer->deleted ) ) {
				yield $stripeCustomer;
			}
		}
	}
}
