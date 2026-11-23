<?php

namespace ThemeIsle\WPFullPay\Exceptions;

/**
 * Stripe Tax refused a price without a tax behavior, because the Stripe account has no default one (#795).
 *
 * Its message is for the customer; the admin is told how to fix it. Stripe's error stays as the previous exception.
 */
class StripeTaxBehaviorException extends UserFriendlyException {

	/** Part of Stripe's error, which comes without an error code. */
	const STRIPE_ERROR_TEXT = 'does not have a tax behavior set';

	public function __construct( \Throwable $previous ) {
		parent::__construct(
			/* translators: Shown to a customer when Stripe can't work out the tax of the form's prices */
			__( "This form can't calculate tax right now. Please contact the website administrator.", 'wp-full-stripe-free' ),
			0,
			$previous
		);
		$this->setTitle(
			/* translators: Banner title of error returned by Stripe */
			__( 'Stripe Error', 'wp-full-stripe-free' )
		);
	}

	/**
	 * @param \Exception $ex An error of a Stripe call, directly or through the platform.
	 * @return \Exception This exception for Stripe's tax behavior refusal, the error itself otherwise.
	 */
	public static function fromStripeError( \Exception $ex ): \Exception {
		return false === strpos( $ex->getMessage(), self::STRIPE_ERROR_TEXT ) ? $ex : new self( $ex );
	}

	public static function getAdminMessage(): string {
		/* translators: Shown to the site admin when Stripe Tax refuses prices because the Stripe account has no default tax behavior */
		return __( 'Stripe Tax needs a default tax behavior. Set one in your Stripe Tax settings, or set a tax behavior on each price.', 'wp-full-stripe-free' );
	}
}
