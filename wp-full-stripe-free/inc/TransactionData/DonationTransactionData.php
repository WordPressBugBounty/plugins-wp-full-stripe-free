<?php

namespace ThemeIsle\WPFullPay\TransactionData;

class DonationTransactionData extends PaymentTransactionData {
    protected $donationFrequency;

    /**
     * @return mixed
     */
    public function getDonationFrequency() {
        return $this->donationFrequency;
    }

    /**
     * @param mixed $donationFrequency
     */
    public function setDonationFrequency( $donationFrequency ) {
        $this->donationFrequency = $donationFrequency;
    }
}
