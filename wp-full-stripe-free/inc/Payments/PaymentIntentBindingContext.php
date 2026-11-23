<?php

namespace ThemeIsle\WPFullPay\Payments;

/**
 * The server-side transaction a client-supplied PaymentIntent has to be bound to.
 */
class PaymentIntentBindingContext {
	/** @var string|null Form type*/
	public $formType = null;

	/** @var string|null Name of the form. */
	public $formName = null;

	/** @var int|string|null Numeric id of the form record, when available. */
	public $formId = null;

	/** @var string|null Currency of the form record. */
	public $currency = null;

	/** @var string[]|null Price ids configured on the form, or null when they cannot be determined. */
	public $allowedPriceIds = null;

	/** @var bool Allows custom amount. */
	public $allowsCustomAmount = false;

	/** @var string|null Price id the client claims is selected. */
	public $selectedPriceId = null;
}
