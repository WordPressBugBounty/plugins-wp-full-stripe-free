<?php

namespace ThemeIsle\WPFullPay\TransactionData;

abstract class FormTransactionData extends TransactionData {
    protected $ipAddress;

	protected $formName;
	protected $stripeCustomerId;
	protected $customerPhone;
	protected $billingName;
	/**
	 * @var array|null
	 */
	protected $billingAddress;
	protected $shippingName;
	/**
	 * @var array|null
	 */
	protected $shippingAddress;
	protected $customInputValues;
	protected $stripePaymentMethodId;
	protected $stripePaymentIntentId;
	/**
	 * @var array|null
	 */
	protected $metadata;
	protected $transactionId;
	/**
	 * @var string
	 */
	protected $customFieldsJSON;

    /**
     * @return mixed
     */
    public function getIpAddress() {
        return $this->ipAddress;
    }

    /**
     * @param mixed $ipAddress
     */
    public function setIpAddress( $ipAddress ) {
        $this->ipAddress = $ipAddress;
    }

	/**
	 * @return mixed
	 */
	public function getFormName() {
		return $this->formName;
	}

	/**
	 * @param mixed $formName
	 */
	public function setFormName( $formName ) {
		$this->formName = $formName;
	}

	/**
	 * @return mixed
	 */
	public function getStripeCustomerId() {
		return $this->stripeCustomerId;
	}

	/**
	 * @param mixed $stripeCustomerId
	 */
	public function setStripeCustomerId( $stripeCustomerId ) {
		$this->stripeCustomerId = $stripeCustomerId;
	}

	/**
	 * @return mixed
	 */
	public function getCustomerPhone() {
		return $this->customerPhone;
	}

	/**
	 * @param mixed $customerPhone
	 */
	public function setCustomerPhone( $customerPhone ) {
		$this->customerPhone = $customerPhone;
	}

	/**
	 * @return mixed
	 */
	public function getBillingName() {
		return $this->billingName;
	}

	/**
	 * @param mixed $billingName
	 */
	public function setBillingName( $billingName ) {
		$this->billingName = $billingName;
	}

	/**
	 * @return mixed
	 */
	public function getBillingAddress() {
		return $this->billingAddress;
	}

	/**
	 * @param array|null $billingAddress
	 */
	public function setBillingAddress( $billingAddress ) {
		$this->billingAddress = $billingAddress;
	}

	/**
	 * @return mixed
	 */
	public function getShippingName() {
		return $this->shippingName;
	}

	/**
	 * @param mixed $shippingName
	 */
	public function setShippingName( $shippingName ) {
		$this->shippingName = $shippingName;
	}

	/**
	 * @return mixed
	 */
	public function getShippingAddress() {
		return $this->shippingAddress;
	}

	/**
	 * @param mixed $shippingAddress
	 */
	public function setShippingAddress( $shippingAddress ) {
		$this->shippingAddress = $shippingAddress;
	}

	/**
	 * @return mixed
	 */
	public function getCustomInputValues() {
		return $this->customInputValues;
	}

	/**
	 * @param mixed $customInputValues
	 */
	public function setCustomInputValues( $customInputValues ) {
		$this->customInputValues = $customInputValues;
	}

	/**
	 * @return mixed
	 */
	public function getStripePaymentMethodId() {
		return $this->stripePaymentMethodId;
	}

	/**
	 * @param mixed $stripePaymentMethodId
	 */
	public function setStripePaymentMethodId( $stripePaymentMethodId ) {
		$this->stripePaymentMethodId = $stripePaymentMethodId;
	}

	/**
	 * @return mixed
	 */
	public function getStripePaymentIntentId() {
		return $this->stripePaymentIntentId;
	}

	/**
	 * @param mixed $stripePaymentIntentId
	 */
	public function setStripePaymentIntentId( $stripePaymentIntentId ) {
		$this->stripePaymentIntentId = $stripePaymentIntentId;
	}

	/**
	 * @return mixed
	 */
	public function getMetadata() {
		return $this->metadata;
	}

	/**
	 * @param mixed $metadata
	 */
	public function setMetadata( $metadata ) {
		$this->metadata = $metadata;
	}

	/**
	 * @return mixed
	 */
	public function getTransactionId() {
		return $this->transactionId;
	}

	/**
	 * @param mixed $transactionId
	 */
	public function setTransactionId( $transactionId ) {
		$this->transactionId = $transactionId;
	}

	/**
	 * @return string
	 */
	public function getCustomFieldsJSON() {
		return $this->customFieldsJSON;
	}

	/**
	 * @param string $customFieldsJSON
	 */
	public function setCustomFieldsJSON( $customFieldsJSON ) {
		$this->customFieldsJSON = $customFieldsJSON;
	}
	
}
