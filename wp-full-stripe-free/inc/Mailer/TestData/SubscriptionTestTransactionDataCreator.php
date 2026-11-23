<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class SubscriptionTestTransactionDataCreator extends TestFormTransactionDataCreator implements TestTransactionGeneratorInvoiceToolsConstants, TestTransactionGeneratorTaxToolsConstants {
    const TRANSACTION_ID = 'sub_Bdfhg54Dkj2XZ9Nfgu65';
    const FORM_NAME = 'subscriptionForm';

    const PLAN_QUANTITY = 1;
    const PLAN_NAME = 'Gold plan';
    const PLAN_ID = 'price_bFGhbrtyg4gJsxe3';
    const SETUP_FEE_NET = 1000;
    const SETUP_FEE_TAX = 150;
    const SETUP_FEE_GROSS = 1150;
    const PLAN_AMOUNT_NET = 4000;
    const PLAN_AMOUNT_TAX = 600;
    const PLAN_AMOUNT_GROSS = 4600;
    const RECOVERY_FEE = 202;

    const COUPON_CODE = '20PERCENTOFF';

    const CURRENCY = 'USD';

    use TestTransactionGeneratorInvoiceTools;
    use TestTransactionGeneratorTaxTools;

    /**
     * @param SubscriptionTransactionData $result
     */
    protected function setFormSpecificFields( $result ) {
        $result->setFormName( self::FORM_NAME );
        $result->setTransactionId( self::TRANSACTION_ID );

        $result->setPlanName( self::PLAN_NAME );
        $result->setProductName( self::PLAN_NAME );
        $result->setPlanId( self::PLAN_ID );

        $result->setPlanQuantity( self::PLAN_QUANTITY );
        $result->setAmount( self::PLAN_AMOUNT_GROSS + self::SETUP_FEE_GROSS );
        $result->setRecoveryFee( self::RECOVERY_FEE );
        $result->setChargedTotal( self::PLAN_AMOUNT_GROSS + self::SETUP_FEE_GROSS + self::RECOVERY_FEE );
        $result->setPlanNetAmount( self::PLAN_AMOUNT_NET );
        $result->setPlanTaxAmount( self::PLAN_AMOUNT_TAX );
        $result->setPlanGrossAmount( self::PLAN_AMOUNT_GROSS );
        $result->setPlanNetAmountTotal( self::PLAN_AMOUNT_NET );
        $result->setPlanTaxAmountTotal( self::PLAN_AMOUNT_TAX );
        $result->setPlanGrossAmountTotal( self::PLAN_AMOUNT_GROSS );
        $result->setSetupFeeNetAmount( self::SETUP_FEE_NET );
        $result->setSetupFeeTaxAmount( self::SETUP_FEE_TAX );
        $result->setSetupFeeGrossAmount( self::SETUP_FEE_GROSS );
        $result->setSetupFeeNetAmountTotal( self::SETUP_FEE_NET );
        $result->setSetupFeeTaxAmountTotal( self::SETUP_FEE_TAX );
        $result->setSetupFeeGrossAmountTotal( self::SETUP_FEE_GROSS );
        $result->setPlanFutureNetAmount( self::PLAN_AMOUNT_NET );
        $result->setPlanFutureTaxAmount( self::PLAN_AMOUNT_TAX );
        $result->setPlanFutureGrossAmount( self::PLAN_AMOUNT_GROSS );

        $result->setCouponCode( self::COUPON_CODE );

        $result->setPlanCurrency( self::CURRENCY );
    }

    public function create( $recipient ) {
        $result = TransactionDataService::createSubscriptionDataByEmail( $recipient );

        $this->setCommonFields( $result );
        $this->setFormCommonFields( $result );
        $this->setFormSpecificFields( $result );
        $this->setInvoiceFields( $result );
        $this->setTaxFields( $result );

        return $result;
    }
}
