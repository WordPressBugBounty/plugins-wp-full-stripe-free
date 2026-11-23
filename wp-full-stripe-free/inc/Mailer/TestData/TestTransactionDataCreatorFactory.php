<?php

namespace ThemeIsle\WPFullPay\Mailer\TestData;

use ThemeIsle\WPFullPay\Main;

class TestTransactionDataCreatorFactory {
    public static function createByEmailTemplateType( $recipient, $emailTemplateType ) {
        $result = null;

        switch( $emailTemplateType ) {
            case Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT:
                $result = (new OnetimePaymentTestTransactionDataCreator())->create( $recipient );
                break;

            case Main::EMAIL_TEMPLATE_ID_CARD_SAVED:
                $result = (new SaveCardTestTransactionDataCreator())->create( $recipient );
                break;

            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT:
            case Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED:
                $result = (new SubscriptionTestTransactionDataCreator())->create( $recipient );
                break;

            case Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT:
                $result = (new DonationTestTransactionDataCreator())->create( $recipient );
                break;

            case Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE:
                $result = (new MyAccountTestTransactionDataCreator())->create( $recipient );
                break;

            default:
                throw new \Exception( __CLASS__ . '::' . __FUNCTION__ . "() - invalid email template type: " . $emailTemplateType );
        }

        return $result;
    }
}
