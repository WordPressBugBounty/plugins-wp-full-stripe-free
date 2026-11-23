<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class DonationTestTransactionDataCreator extends TestFormTransactionDataCreator implements TestTransactionGeneratorInvoiceToolsConstants{
    use TestTransactionGeneratorInvoiceTools;

    const TRANSACTION_ID = 'pi_Vfdg9Kef3Bga8Ge4rPhk';
    const FORM_NAME = 'donationForm';
    const PRODUCT_NAME = 'Donation';

    const DONATION_AMOUNT = 777;
    const RECOVERY_FEE = 54;
    const DONATION_FREQUENCY = 'monthly';

    const CURRENCY = 'USD';

    /**
     * @param DonationTransactionData $result
     */
    protected function setFormSpecificFields( $result ) {
        $result->setFormName( self::FORM_NAME );
        $result->setTransactionId( self::TRANSACTION_ID );
        $result->setProductName( self::PRODUCT_NAME );

        $result->setAmount( self::DONATION_AMOUNT );
        $result->setRecoveryFee( self::RECOVERY_FEE );
        $result->setChargedTotal( self::DONATION_AMOUNT + self::RECOVERY_FEE );
        $result->setDonationFrequency( self::DONATION_FREQUENCY );

        $result->setCurrency( self::CURRENCY );
    }

    public function create( $recipient ) {
        $result = TransactionDataService::createDonationDataByEmail( $recipient );

        $this->setCommonFields( $result );
        $this->setFormCommonFields( $result );
        $this->setFormSpecificFields( $result );
        $this->setInvoiceFields( $result );

        return $result;
    }
}
