<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Views\FormViewInlineTaxAddOnConstants;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Locale\States;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Utils;

trait FormValidatorTaxAddOn {
	protected function validateInlineTaxFields( $bindingResult, $formModelObject ) {
		if ( $formModelObject->getForm()->vatRateType !== Main::FIELD_VALUE_TAX_RATE_NO_TAX ) {
			if ( $formModelObject->getBuyingAsBusiness() == 1 ) {
				if ( empty( $formModelObject->getTaxId() ) ) {
					$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_ID;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error =
						__( 'Please enter your business\'s tax id', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}

				if ( $formModelObject->getForm()->showAddress == 0 &&
					empty( $formModelObject->getBusinessName() ) ) {
					$fieldName = FormViewInlineTaxAddOnConstants::FIELD_BUSINESS_NAME;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error =
						__( 'Please enter your business\'s name', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
			}

			if ( $formModelObject->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX ||
				$formModelObject->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_DYNAMIC ||
				( $formModelObject->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_FIXED && $formModelObject->getForm()->collectCustomerTaxId == 1 ) ) {
				if ( $formModelObject->getForm()->showAddress == 0 ) {
					$countries = Countries::getAvailableCountries();

					if ( ! array_key_exists( $formModelObject->getTaxCountry(), $countries ) ) {
						$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_COUNTRY;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error =
							__( 'Please select the country your business\'s seat is in', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}

					if ( $formModelObject->getForm()->vatRateType === Main::FIELD_VALUE_TAX_RATE_STRIPE_TAX ) {
						// TODO: make sure full address is populated, not just country

						if ( false !== array_search( $formModelObject->getTaxCountry(), Pricing::getCountryCodesRequiringPostalCodeForTax() ) &&
							empty( $formModelObject->getTaxZip() ) ) {
							$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_ZIP;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error =
								__( 'Please enter your postal code', 'wp-full-stripe-free' );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					} else {
						if ( $formModelObject->getTaxCountry() === Main::COUNTRY_CODE_UNITED_STATES ) {
							$states = States::getAvailableStates();

							if ( ! array_key_exists( $formModelObject->getTaxState(), $states ) ) {
								$fieldName = FormViewInlineTaxAddOnConstants::FIELD_TAX_STATE;
								$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
								$error =
									__( 'Please select the state your business\'s seat is in', 'wp-full-stripe-free' );
								$bindingResult->addFieldError( $fieldName, $fieldId, $error );
							}
						}
					}

				}
			}
		}
	}
}
