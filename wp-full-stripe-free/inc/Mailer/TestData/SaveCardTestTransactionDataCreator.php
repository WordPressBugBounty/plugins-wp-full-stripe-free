<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class SaveCardTestTransactionDataCreator extends TestFormTransactionDataCreator {
    const TRANSACTION_ID = 'cus_Bdfhg54Dkj2XZ9Nfgu65';
    const FORM_NAME = 'saveCardForm';

    /**
     * @param SaveCardTransactionData $result
     */
    protected function setFormSpecificFields( $result ) {
        $result->setFormName( self::FORM_NAME );
        $result->setTransactionId( self::TRANSACTION_ID );
    }

    public function create( $recipient ) {
        $result = TransactionDataService::createSaveCardDataByEmail( $recipient );

        $this->setCommonFields( $result );
        $this->setFormCommonFields( $result );
        $this->setFormSpecificFields( $result );

        return $result;
    }
}
