<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class OnetimePaymentTestTransactionDataCreator extends TestFormTransactionDataCreator implements TestTransactionGeneratorInvoiceToolsConstants, TestTransactionGeneratorTaxToolsConstants {
    const TRANSACTION_ID = 'pi_CV2Fky5bD8GlaEC23';
    const FORM_NAME = 'paymentForm';

    const PRODUCT_NAME = 'Gold package';
    const NET_AMOUNT = 4999;
    const TAX_AMOUNT = 499;
    const GROSS_AMOUNT = 5498;
    const RECOVERY_FEE = 196;

    const COUPON_CODE = '20PERCENTOFF';

    const CURRENCY = 'USD';

    use TestTransactionGeneratorInvoiceTools;
    use TestTransactionGeneratorTaxTools;

    /**
     * @param OneTimePaymentTransactionData $result
     */
    protected function setFormSpecificFields( $result ) {
        $result->setFormName( self::FORM_NAME );
        $result->setTransactionId( self::TRANSACTION_ID );

        $result->setProductName( self::PRODUCT_NAME );

        $result->setProductAmountNet( self::NET_AMOUNT );
        $result->setProductAmountTax( self::TAX_AMOUNT );
        $result->setProductAmountGross( self::GROSS_AMOUNT );
        $result->setProductAmountDiscount( 0 );
        $result->setAmount( self::GROSS_AMOUNT );
        $result->setRecoveryFee( self::RECOVERY_FEE );
        $result->setChargedTotal( self::GROSS_AMOUNT + self::RECOVERY_FEE );

        $result->setCouponCode( self::COUPON_CODE );

        $result->setCurrency( self::CURRENCY );
    }

    public function create( $recipient ) {
        $result = TransactionDataService::createOneTimePaymentDataByEmail( $recipient );

        $this->setCommonFields( $result );
        $this->setFormCommonFields( $result );
        $this->setFormSpecificFields( $result );
        $this->setInvoiceFields( $result );
        $this->setTaxFields( $result );

        return $result;
    }
}
