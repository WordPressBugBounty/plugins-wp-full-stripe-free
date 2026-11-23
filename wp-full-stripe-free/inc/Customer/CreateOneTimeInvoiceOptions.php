<?php

namespace ThemeIsle\WPFullPay\Customer;

class CreateOneTimeInvoiceOptions {
	public $autoAdvance;
	public $taxRateIds = null;
	/** @var string|null Sent with the invoice, and with "-item" or "-fee" appended with its lines, so a request sent again gets them back. */
	public $idempotencyKey = null;
}
