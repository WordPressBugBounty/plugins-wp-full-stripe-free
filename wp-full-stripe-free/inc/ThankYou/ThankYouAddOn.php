<?php

namespace ThemeIsle\WPFullPay\ThankYou;

use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Macros\FormMacroReplacer;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Results\TransactionResult;
use ThemeIsle\WPFullPay\TransactionData\DonationTransactionData;
use ThemeIsle\WPFullPay\TransactionData\OneTimePaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;
use ThemeIsle\WPFullPay\Utils;

trait ThankYouAddOn {

    /**
     * @param FormModel $formModel
     * @param OneTimePaymentTransactionData|SubscriptionTransactionData|DonationTransactionData $transactionData
     * @return FormMacroReplacer
     */
    protected function getMacroReplacer( $formModel, $transactionData ) {
        $result = null;

        $formType = Utils::getFormType( $formModel->getForm() );
        switch( $formType ) {
            case Main::FORM_TYPE_INLINE_PAYMENT:
            case Main::FORM_TYPE_CHECKOUT_PAYMENT:
                $result = new OneTimePaymentMacroReplacer( $formModel->getForm(), $transactionData, $this->loggerService );
                break;

            case Main::FORM_TYPE_INLINE_SUBSCRIPTION:
            case Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION:
                $result = new SubscriptionMacroReplacer( $formModel->getForm(), $transactionData, $this->loggerService );
                break;

            case Main::FORM_TYPE_INLINE_DONATION:
            case Main::FORM_TYPE_CHECKOUT_DONATION:
                $result = new DonationMacroReplacer( $formModel->getForm(), $transactionData, $this->loggerService );
                break;

            case Main::FORM_TYPE_INLINE_SAVE_CARD:
            case Main::FORM_TYPE_CHECKOUT_SAVE_CARD:
                $result = new SaveCardMacroReplacer( $formModel->getForm(), $transactionData, $this->loggerService );
                break;

            default:
                throw new \Exception( __CLASS__ . '.' . __FUNCTION__ . ": Unsupported form type: {$formType}" );
        }

        return $result;
    }

    /**
     * @param FormModel $formModel
     * @param $transactionData OneTimePaymentTransactionData|SubscriptionTransactionData|DonationTransactionData
     * @param TransactionResult $transactionResult
     */
    protected function handleRedirect( $formModel, $transactionData, $transactionResult ) {
        if ( $transactionResult->isSuccess() ) {
            if ( 1 == $formModel->getForm()->redirectOnSuccess ) {
                $transactionDataKey = null;
                $redirectUrl = null;

                if ( 1 == $formModel->getForm()->redirectToPageOrPost ) {
                    if ( 0 != $formModel->getForm()->redirectPostID ) {
                        // store the transction data as a transient to be able to retrieve it again
                        // on the thank you page
                        $transactionDataKey = $this->transactionDataService->store( $transactionData );
                        $redirectUrl = get_page_link( $formModel->getForm()->redirectPostID );
                    } else {
                        Utils::log( "handleRedirect(): Inconsistent form data: formName={$formModel->getFormName()}, doRedirect={$formModel->getForm()->redirectOnSuccess}, redirectPostID={$formModel->getForm()->redirectPostID}" );
                    }
                } else {
                    $redirectUrl = $formModel->getForm()->redirectUrl;
                }

                if ( !empty( $redirectUrl ) ) {
                    // macro replacer handles checkout type forms to get additional data
                   $replacer = $this->getMacroReplacer( $formModel, $transactionData );

                    $params = [
                        'formName'                => $formModel->getFormName(),
                        'formType'                => Utils::getFormType( $formModel->getForm() ),
                        'rawPlaceholders'         => $replacer->getRawKeyValuePairs(),
                        'decoratedPlaceholders'   => $replacer->getDecoratedKeyValuePairs()
                    ];

                    $transactionResult->setRedirect( true );
                    $transactionResult->setRedirectURL( ThankYou::getRedirectUrl( $redirectUrl, $transactionDataKey, $params ) );
                }
            }
        }
    }
}
