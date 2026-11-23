<?php

namespace ThemeIsle\WPFullPay\Api;

class PhonyTransactionData {
    protected $customerEmail;

    public function __construct( $emailAddress ) {
        $this->customerEmail = $emailAddress;
    }

    /**
     * @return mixed
     */
    public function getCustomerEmail() {
        return $this->customerEmail;
    }
}
