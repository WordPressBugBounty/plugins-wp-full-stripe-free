<?php

// File generated from our OpenAPI spec

namespace StripeWPFS;

/**
 * PaymentMethod objects represent your customer's payment instruments. They can be
 * used with <a
 * href="https://stripe.com/docs/payments/payment-intents">PaymentIntents</a> to
 * collect payments or saved to Customer objects to store instrument details for
 * future payments.
 *
 * Related guides: <a
 * href="https://stripe.com/docs/payments/payment-methods">Payment Methods</a> and
 * <a href="https://stripe.com/docs/payments/more-payment-scenarios">More Payment
 * Scenarios</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \StripeWPFS\StripeObject $acss_debit
 * @property \StripeWPFS\StripeObject $afterpay_clearpay
 * @property \StripeWPFS\StripeObject $alipay
 * @property \StripeWPFS\StripeObject $au_becs_debit
 * @property \StripeWPFS\StripeObject $bacs_debit
 * @property \StripeWPFS\StripeObject $bancontact
 * @property \StripeWPFS\StripeObject $billing_details
 * @property \StripeWPFS\StripeObject $boleto
 * @property \StripeWPFS\StripeObject $card
 * @property \StripeWPFS\StripeObject $card_present
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|string|\StripeWPFS\Customer $customer The ID of the Customer to which this PaymentMethod is saved. This will not be set when the PaymentMethod has not been saved to a Customer.
 * @property \StripeWPFS\StripeObject $eps
 * @property \StripeWPFS\StripeObject $fpx
 * @property \StripeWPFS\StripeObject $giropay
 * @property \StripeWPFS\StripeObject $grabpay
 * @property \StripeWPFS\StripeObject $ideal
 * @property \StripeWPFS\StripeObject $interac_present
 * @property \StripeWPFS\StripeObject $klarna
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|\StripeWPFS\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property \StripeWPFS\StripeObject $oxxo
 * @property \StripeWPFS\StripeObject $p24
 * @property \StripeWPFS\StripeObject $sepa_debit
 * @property \StripeWPFS\StripeObject $sofort
 * @property string $type The type of the PaymentMethod. An additional hash is included on the PaymentMethod with a name matching this value. It contains additional information specific to the PaymentMethod type.
 * @property \StripeWPFS\StripeObject $wechat_pay
 */
class PaymentMethod extends ApiResource
{
    const OBJECT_NAME = 'payment_method';

    use ApiOperations\All;
    use ApiOperations\Create;
    use ApiOperations\Retrieve;
    use ApiOperations\Update;

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\PaymentMethod the attached payment method
     */
    public function attach($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/attach';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \StripeWPFS\Exception\ApiErrorException if the request fails
     *
     * @return \StripeWPFS\PaymentMethod the detached payment method
     */
    public function detach($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/detach';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
