<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\CustomerPortal\CustomerPortalService;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;

class MyAccountTestTransactionDataCreator extends TestTransactionDataCreator {
    public function create( $recipient ) {
        $result = TransactionDataService::createMyAccountLoginDataByEmail( $recipient );

        $this->setCommonFields( $result );

        $result->setSecurityCode( CustomerPortalService::generateSecurityCode()  );
        $result->setSessionHash( wp_generate_password( 16, false ) );

        return $result;
    }
}
