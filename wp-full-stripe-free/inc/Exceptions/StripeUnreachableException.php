<?php

namespace ThemeIsle\WPFullPay\Exceptions;

/**
 * Stripe couldn't be reached to tell whether a customer portal session still belongs to its customer, so the request
 * is refused for now, without ending the session.
 */
class StripeUnreachableException extends UserFriendlyException {
}
