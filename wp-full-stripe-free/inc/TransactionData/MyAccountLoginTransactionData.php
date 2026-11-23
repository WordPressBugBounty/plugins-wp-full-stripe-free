<?php

namespace ThemeIsle\WPFullPay\TransactionData;

class MyAccountLoginTransactionData extends TransactionData {
    protected $securityCode;
    protected $sessionHash;

    /**
     * @return mixed
     */
    public function getSecurityCode() {
        return $this->securityCode;
    }

    /**
     * @param mixed $securityCode
     */
    public function setSecurityCode($securityCode) {
        $this->securityCode = $securityCode;
    }

    /**
     * @return mixed
     */
    public function getSessionHash() {
        return $this->sessionHash;
    }

    /**
     * @param mixed $sessionHash
     */
    public function setSessionHash($sessionHash) {
        $this->sessionHash = $sessionHash;
    }
}
