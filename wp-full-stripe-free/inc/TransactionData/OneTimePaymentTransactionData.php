<?php

namespace ThemeIsle\WPFullPay\TransactionData;

class OneTimePaymentTransactionData extends PaymentTransactionData {
    protected $productAmountGross;
    protected $productAmountNet;
    protected $productAmountTax;
    protected $productAmountDiscount;
    protected $couponId;

    /**
     * @param mixed $couponId
     */
    public function setCouponId( $couponId ) {
        $this->couponId = $couponId;
    }

    /**
     * @return mixed
     */
    public function getProductAmountGross() {
        return $this->productAmountGross;
    }

    /**
     * @param mixed $productAmountGross
     */
    public function setProductAmountGross( $productAmountGross)  {
        $this->productAmountGross = $productAmountGross;
    }

    /**
     * @return mixed
     */
    public function getProductAmountNet() {
        return $this->productAmountNet;
    }

    /**
     * @param mixed $productAmountNet
     */
    public function setProductAmountNet($productAmountNet) {
        $this->productAmountNet = $productAmountNet;
    }

    /**
     * @return mixed
     */
    public function getProductAmountTax() {
        return $this->productAmountTax;
    }

    /**
     * @param mixed $productAmountTax
     */
    public function setProductAmountTax($productAmountTax ) {
        $this->productAmountTax = $productAmountTax;
    }

    /**
     * @return mixed
     */
    public function getProductAmountDiscount() {
        return $this->productAmountDiscount;
    }

    /**
     * @param mixed $productAmountDiscount
     */
    public function setProductAmountDiscount($productAmountDiscount) {
        $this->productAmountDiscount = $productAmountDiscount;
    }

}
