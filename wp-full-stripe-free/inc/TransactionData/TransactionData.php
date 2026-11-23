<?php

namespace ThemeIsle\WPFullPay\TransactionData;

abstract class TransactionData {
    protected $customerName;
    protected $customerEmail;

    /**
     * @return mixed
     */
    public function getCustomerName() {
        return $this->customerName;
    }

    /**
     * @param mixed $customerName
     */
    public function setCustomerName( $customerName ) {
        $this->customerName = $customerName;
    }

    /**
     * @return mixed
     */
    public function getCustomerEmail() {
        return $this->customerEmail;
    }

    /**
     * @param mixed $customerEmail
     */
    public function setCustomerEmail( $customerEmail ) {
        $this->customerEmail = $customerEmail;
    }
}
