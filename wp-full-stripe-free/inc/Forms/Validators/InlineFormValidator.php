<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineForm;
use ThemeIsle\WPFullPay\Locale\States;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

trait InlineFormValidator {

	/**
	 * @param BindingResult $bindingResult
	 * @param FormModel $formModelObject
	 */
	protected function validateInlineFields( $bindingResult, $formModelObject ) {
		if ( $formModelObject instanceof InlineForm ) {
			if ( empty( $formModelObject->getCardHolderName() ) ) {
				$fieldName = FormModel::PARAM_WPFS_CARD_HOLDER_NAME;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error = __( 'Please enter the cardholder\'s name', 'wp-full-stripe-free' );
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			}
			if ( ! filter_var( $formModelObject->getCardHolderEmail(), FILTER_VALIDATE_EMAIL ) ) {
				$fieldName = FormModel::PARAM_WPFS_CARD_HOLDER_EMAIL;
				$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
				$error = __( 'Please enter a valid email address', 'wp-full-stripe-free' );
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			}
			if ( $this->showBillingAddress( $formModelObject ) ) {
				if ( empty( $formModelObject->getBillingName() ) ) {
					$fieldName = FormModel::PARAM_WPFS_BILLING_NAME;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error = __( 'Please enter a billing name', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
				if ( empty( $formModelObject->getBillingAddressLine1() ) ) {
					$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_LINE_1;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error = __( 'Please enter a billing address', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
				if ( empty( $formModelObject->getBillingAddressCity() ) ) {
					$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_CITY;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error = __( 'Please enter a city', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
				if ( empty( $formModelObject->getBillingAddressCountry() ) ) {
					$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_COUNTRY;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error = __( 'Please select a country', 'wp-full-stripe-free' );
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
				if ( $formModelObject->getBillingAddressCountry() === Main::COUNTRY_CODE_UNITED_STATES ) {
					$states = States::getAvailableStates();

					if ( ! array_key_exists( $formModelObject->getBillingAddressState(), $states ) ) {
						$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_STATE_SELECT;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please select a state', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
				}
				// tnagy WPFS-886 fix: some countries do not have or do not use postcodes
				$validateBillingAddressZip = null;
				if ( empty( $formModelObject->getBillingAddressCountryComposite() ) ) {
					$validateBillingAddressZip = false;
					if ( ! $bindingResult->hasFieldErrors( $formModelObject::PARAM_WPFS_BILLING_ADDRESS_COUNTRY ) ) {
						$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_COUNTRY;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please select a country', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
				} else {
					$billingAddressCountryComposite = $formModelObject->getBillingAddressCountryComposite();
					if ( true === $billingAddressCountryComposite['usePostCode'] ) {
						$validateBillingAddressZip = true;
					} else {
						$validateBillingAddressZip = false;
					}
				}
				if ( $validateBillingAddressZip ) {
					if ( empty( $formModelObject->getBillingAddressZip() ) ) {
						$fieldName = FormModel::PARAM_WPFS_BILLING_ADDRESS_ZIP;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please enter a zip/postal code', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
				}
			}
			if ( 0 == $formModelObject->getSameBillingAndShippingAddress() ) {
				if ( $this->showShippingAddress( $formModelObject ) ) {
					if ( empty( $formModelObject->getShippingName() ) ) {
						$fieldName = FormModel::PARAM_WPFS_SHIPPING_NAME;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please enter a shipping name', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
					if ( empty( $formModelObject->getShippingAddressLine1() ) ) {
						$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_LINE_1;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please enter a shipping address', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
					if ( empty( $formModelObject->getShippingAddressCity() ) ) {
						$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_CITY;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please enter a city', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
					if ( empty( $formModelObject->getShippingAddressCountry() ) ) {
						$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = __( 'Please select a country', 'wp-full-stripe-free' );
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					}
					if ( $formModelObject->getShippingAddressCountry() === Main::COUNTRY_CODE_UNITED_STATES ) {
						$states = States::getAvailableStates();

						if ( ! array_key_exists( $formModelObject->getShippingAddressState(), $states ) ) {
							$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_STATE_SELECT;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = __( 'Please select a state', 'wp-full-stripe-free' );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					}
					// tnagy WPFS-886 fix: some countries do not have or do not use postcodes
					$validateShippingAddressZip = null;
					if ( empty( $formModelObject->getShippingAddressCountryComposite() ) ) {
						$validateShippingAddressZip = false;
						if ( ! $bindingResult->hasFieldErrors( $formModelObject::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY ) ) {
							$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_COUNTRY;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = __( 'Please select a country', 'wp-full-stripe-free' );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					} else {
						$shippingAddressCountryComposite = $formModelObject->getShippingAddressCountryComposite();
						if ( true === $shippingAddressCountryComposite['usePostCode'] ) {
							$validateShippingAddressZip = true;
						} else {
							$validateShippingAddressZip = false;
						}
					}
					if ( $validateShippingAddressZip ) {
						if ( empty( $formModelObject->getShippingAddressZip() ) ) {
							$fieldName = FormModel::PARAM_WPFS_SHIPPING_ADDRESS_ZIP;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = __( 'Please enter a zip/postal code', 'wp-full-stripe-free' );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					}
				}
			}
		}
	}

	/**
	 * @param FormModel $formModelObject
	 *
	 * @return bool
	 */
	protected function showBillingAddress( $formModelObject ) {
		$showBillingAddress = false;
		if ( isset( $formModelObject->getForm()->showAddress ) ) {
			$showBillingAddress = 1 == $formModelObject->getForm()->showAddress;

			return $showBillingAddress;
		} elseif ( isset( $formModelObject->getForm()->showBillingAddress ) ) {
			$showBillingAddress = 1 == $formModelObject->getForm()->showBillingAddress;

			return $showBillingAddress;
		}

		return $showBillingAddress;
	}

	/**
	 * @param FormModel $formModelObject
	 *
	 * @return bool
	 */
	protected function showShippingAddress( $formModelObject ) {
		$showShippingAddress = false;
		if ( isset( $formModelObject->getForm()->showShippingAddress ) ) {
			$showShippingAddress = 1 == $formModelObject->getForm()->showShippingAddress;

			return $showShippingAddress;
		}

		return $showShippingAddress;
	}

}
