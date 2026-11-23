<?php

namespace ThemeIsle\WPFullPay\Forms\Validators;

use ThemeIsle\WPFullPay\Forms\Fields\CustomFields;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineForm;
use ThemeIsle\WPFullPay\Forms\Models\InlinePaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\InlineSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\PopupForm;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Localization;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\ReCaptcha;
use ThemeIsle\WPFullPay\Utils;

class FormValidator extends Validator {

	/**
	 * @var array
	 */
	protected $fieldsToIgnore;

	/**
	 * FormValidator constructor.
	 */
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );

		$this->fieldsToIgnore = [];
	}

	public final function validate( $bindingResult, $formModelObject ) {
		$this->validateForm( $bindingResult, $formModelObject );
		if ( ! $bindingResult->hasErrors() ) {
			$this->validateFields( $bindingResult, $formModelObject );
		}
	}

	/**
	 * @param BindingResult $bindingResult
	 * @param FormModel $formModelObject
	 */
	protected function validateForm( $bindingResult, $formModelObject ) {
		// Binding sanitizes a missing form name into an empty string.
		if ( is_null( $formModelObject->getFormName() ) || '' === $formModelObject->getFormName() ) {
			$error =
				/* translators: Form error when a submitted request names no form */
				__( 'Invalid form name', 'wp-full-stripe-free' );
			$bindingResult->addGlobalError( $error );
		} else {
			$formObject = $formModelObject->getForm();

			if ( is_null( $formObject ) ) {
				if ( $formModelObject instanceof InlinePaymentFormModel ) {
					$formObject = $formModelObject->getDb()->getInlinePaymentFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof CheckoutPaymentFormModel ) {
					$formObject = $formModelObject->getDb()->getCheckoutPaymentFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof InlineDonationFormModel ) {
					$formObject = $formModelObject->getDb()->getInlineDonationFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof CheckoutDonationFormModel ) {
					$formObject = $formModelObject->getDb()->getCheckoutDonationFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof CheckoutDonationFormModel ) {
					$formObject = $formModelObject->getDb()->getCheckoutDonationFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof InlineSubscriptionFormModel ) {
					$formObject = $formModelObject->getDb()->getInlineSubscriptionFormByName( $formModelObject->getFormName() );
				}
				if ( $formModelObject instanceof CheckoutSubscriptionFormModel ) {
					$formObject = $formModelObject->getDb()->getCheckoutSubscriptionFormByName( $formModelObject->getFormName() );
				}

				if ( is_null( $formObject ) ) {
					$bindingResult->addGlobalError(
						/* translators: Form error when a submitted form no longer exists, for example because it was deleted */
						__( 'Invalid form name or form not found', 'wp-full-stripe-free' )
					);
				} else {
					$formModelObject->setForm( $formObject );
				}
			}

			$bindingResult->setFormHash( $formModelObject->getFormHash() );
		}
	}

	/**
	 * @param BindingResult $bindingResult
	 * @param FormModel $formModelObject
	 */
	protected function validateFields( $bindingResult, $formModelObject ) {
		if ( $formModelObject instanceof FormModel ) {
			if ( isset( $formModelObject->getForm()->showCouponInput ) && 1 == $formModelObject->getForm()->showCouponInput ) {
				if ( $formModelObject->getStripeCoupon() ) {
					if ( false === $formModelObject->getStripeCoupon()->valid ) {
						$fieldName = FormModel::PARAM_WPFS_COUPON;
						$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
						$error = Utils::getDefaultCouponInvalidErrorMessage();
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					} else {
						$appliesTo = true;
						if ( isset( $formModelObject->getStripeCoupon()->applies_to ) && isset( $formModelObject->getStripeCoupon()->applies_to->products ) ) {
							$form = $formModelObject->getForm();
							if ( isset( $form->decoratedProducts ) ) {
								$savedProducts = Utils::decodeJsonArray( $form->decoratedProducts );
							} elseif ( isset( $form->decoratedPlans ) ) {
								$savedProducts = Utils::decodeJsonArray( $form->decoratedPlans );
							} else {
								$savedProducts = [];
							}

							if ( isset( $savedProducts ) && ! empty( $savedProducts ) ) {
								$priceIds = Pricing::extractPriceIdsFromProductsStatic( $savedProducts );

								if ( isset( $priceIds ) && ! empty( $priceIds ) ) {
									$productIds = $formModelObject->getStripe()->retrieveProductIdsByPriceIds( $priceIds );
									$intersect = array_intersect( $formModelObject->getStripeCoupon()->applies_to->products, $productIds );
									if ( count( $intersect ) === 0 ) {
										$appliesTo = false;
									} else {
										$appliesTo = true;
									}
								}
							}
						}

						if ( false === $appliesTo ) {
							$fieldName = FormModel::PARAM_WPFS_COUPON;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = Utils::getDefaultCouponDoesNotApplyToErrorMessage();
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					}

					if ( $formModelObject instanceof PaymentFormModel ) {
						if ( ! is_null( $formModelObject->getStripeCoupon()->amount_off ) && $formModelObject->getForm()->currency != $formModelObject->getStripeCoupon()->currency ) {
							$fieldName = FormModel::PARAM_WPFS_COUPON;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = Utils::getDefaultInvalidCouponCurrencyErrorMessage();
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					}
				} elseif ( ! empty( $formModelObject->getCouponCode() ) ) {
					// Stripe knows no coupon or promotion code by that name.
					$fieldName = FormModel::PARAM_WPFS_COUPON;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$bindingResult->addFieldError( $fieldName, $fieldId, Utils::getDefaultCouponInvalidErrorMessage() );
				}
			} else {
				$formModelObject->setStripeCoupon( null );
			}
			if ( isset( $formModelObject->getForm()->showTermsOfUse ) && 1 == $formModelObject->getForm()->showTermsOfUse ) {
				if ( 0 == $formModelObject->getTermsOfUseAccepted() ) {
					$fieldName = FormModel::PARAM_WPFS_TERMS_OF_USE_ACCEPTED;
					$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
					$error = Utils::getDefaultTermsOfUseNotCheckedErrorMessage();
					$bindingResult->addFieldError( $fieldName, $fieldId, $error );
				}
			}
			if ( ! $this->isIgnored( FormModel::PARAM_WPFS_CUSTOM_INPUT ) ) {
				if ( 1 == $formModelObject->getForm()->showCustomInput ) {
					$form = $formModelObject->getForm();
					if ( is_null( $form->customInputs ) ) {
						// tnagy legacy single custom input field
						$customInputValue = $formModelObject->getCustomInputvalues();
						if ( 1 == $form->customInputRequired
							&& ( is_null( $customInputValue ) || ( false == trim( $customInputValue ) ) ) ) {
							$fieldName = FormModel::PARAM_WPFS_CUSTOM_INPUT;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = sprintf(
								/* translators: Error message for required fields when empty.
								 * p1: custom input field label
								 */
								__( "Please enter a value for '%s'", 'wp-full-stripe-free' ), Localization::translateLabel( $form->customInputTitle ) );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
						if ( is_string( $customInputValue ) && strlen( $customInputValue ) > Utils::STRIPE_METADATA_VALUE_MAX_LENGTH ) {
							$fieldName = FormModel::PARAM_WPFS_CUSTOM_INPUT;
							$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
							$error = sprintf(
								/* translators: Form field validation error for custom fields */
								__( "The value for '%s' is too long", 'wp-full-stripe-free' ), Localization::translateLabel( $form->customInputTitle ) );
							$bindingResult->addFieldError( $fieldName, $fieldId, $error );
						}
					} else {
						// Typed custom fields (legacy {{ list or JSON v1 config) validated against the saved schema.
						$isJson = CustomFields::isJsonConfig( $form->customInputs );
						$defs   = CustomFields::parse( $form->customInputs, $form->customInputRequired );
						$values = $formModelObject->getCustomInputvalues();
						foreach ( $defs as $index => $def ) {
							if ( CustomFields::TYPE_HTML === $def->type ) {
								continue;
							}
							if ( $isJson ) {
								$raw = ( is_array( $values ) && isset( $values[ $def->id ] ) ) ? $values[ $def->id ] : null;
							} else {
								$raw = ( is_array( $values ) && isset( $values[ $index ] ) ) ? $values[ $index ] : null;
							}
							$error = CustomFields::validateSubmittedValue( $def, $raw );
							if ( ! is_null( $error ) ) {
								$fieldName = FormModel::PARAM_WPFS_CUSTOM_INPUT;
								$fieldId   = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash(), $isJson ? $def->id : $index );
								$bindingResult->addFieldError( $fieldName, $fieldId, $error );
							}
						}
					}
				}
			}
		}
	}

	protected function isIgnored( $fieldName ) {
		return array_key_exists( $fieldName, $this->fieldsToIgnore );
	}

	protected function ignore( $fieldName ) {
		if ( ! array_key_exists( $fieldName, $this->fieldsToIgnore ) ) {
			$this->fieldsToIgnore[ $fieldName ] = true;
		}
	}

	/**
	 * @param mixed $customAmount Custom amount as submitted.
	 *
	 * @return bool
	 */
	protected function isCustomAmountEmpty( $customAmount ) {
		return is_null( $customAmount ) || '' === trim( (string) $customAmount );
	}

	/**
	 * @param \StdClass $form
	 *
	 * @return string Error message showing the amount format the form accepts.
	 */
	protected function getInvalidCustomAmountMessage( $form ) {
		return sprintf(
			/* translators: Form field validation error message when the custom amount is malformed. p1: example amount, e.g. 12,34 */
			__( 'Please enter a valid amount, for example %s', 'wp-full-stripe-free' ),
			Currencies::formatExampleAmountByForm( $form, $form->currency )
		);
	}

	/**
	 * @param BindingResult $bindingResult
	 * @param FormModel $formModelObject
	 */
	protected function validateGoogleReCaptcha( $bindingResult, $formModelObject ) {
		$validateGoogleReCaptcha = false;
		if ( $formModelObject instanceof InlineForm ) {
			if ( ReCaptcha::getSecureInlineForms( $this->staticContext ) ) {
				$validateGoogleReCaptcha = true;
			}
		} elseif ( $formModelObject instanceof PopupForm ) {
			if ( ReCaptcha::getSecureCheckoutForms( $this->staticContext ) ) {
				$validateGoogleReCaptcha = true;
			}
		}
		if ( $validateGoogleReCaptcha ) {
			$fieldName = $formModelObject::PARAM_GOOGLE_RECAPTCHA_RESPONSE;
			$fieldId = Utils::generateFormElementId( $fieldName, $formModelObject->getFormHash() );
			$error =
				/* translators: Captcha validation error message displayed when the form is submitted without completing the captcha challenge */
				__( 'Please prove that you are not a robot.', 'wp-full-stripe-free' );
			if ( is_null( $formModelObject->getGoogleReCaptchaResponse() ) ) {
				$bindingResult->addFieldError( $fieldName, $fieldId, $error );
			} else {
				// Set by the handler; do not trust the bound action.
				if ( $formModelObject->getRequireFreshCaptcha() || empty( $formModelObject->getNonce() ) ) {
					$googleReCaptchaVerificationResult = ReCaptcha::verifyReCAPTCHA( $this->staticContext, $formModelObject->getGoogleReCaptchaResponse() );
					if ( $googleReCaptchaVerificationResult === false ) {
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					} elseif ( ! isset( $googleReCaptchaVerificationResult->success ) || $googleReCaptchaVerificationResult->success === false ) {
						$bindingResult->addFieldError( $fieldName, $fieldId, $error );
					} else {
						$formModelObject->setNonce( Utils::encrypt( Utils::generateFormNonce( $formModelObject ) ) );
					}
				} else {
					$this->validateFormNonce( $bindingResult, $formModelObject );
				}
			}
		}
	}

	/**
	 * @param BindingResult $bindingResult
	 * @param FormModel $formModelObject
	 */
	private function validateFormNonce( $bindingResult, $formModelObject ) {
		$decryptedText = Utils::decrypt( $formModelObject->getNonce() );
		$error =
			/* translators: Form error when the submitted form data fails its integrity check */
			__( 'Invalid form data', 'wp-full-stripe-free' );
		if ( false === $decryptedText ) {
			$bindingResult->addGlobalError( $error );
		} else {
			$nonceObject = Utils::decodeFormNonce( $decryptedText );
			if ( false === $nonceObject ) {
				$this->logger->debug( __FUNCTION__, 'NonceObject is false' );

				$bindingResult->addGlobalError( $error );
			} else {
				if ( ! isset( $nonceObject->formHash ) || $formModelObject->getFormHash() !== $nonceObject->formHash ) {
					$this->logger->debug( __FUNCTION__, 'FormHash error' );
					$bindingResult->addGlobalError( $error );
				} elseif ( ! isset( $nonceObject->created ) || $this->olderThan( $nonceObject->created, 10 ) ) {
					$this->logger->debug( __FUNCTION__, 'Creation time error' );
					$bindingResult->addGlobalError( $error );
				} elseif ( ! isset( $nonceObject->fieldHash ) || md5( json_encode( $formModelObject ) ) !== $nonceObject->fieldHash ) {
					$this->logger->debug( __FUNCTION__, 'FieldHash error' );
					$bindingResult->addGlobalError( $error );
				}
			}
		}
	}

	private function olderThan( $aTime, $minutes ) {
		$expiration = time() - $minutes * 60;

		$this->logger->debug( __FUNCTION__, 'olderThan(): time=' . print_r( $aTime, true ) . ', expiration=' . print_r( $expiration, true ) );

		return $aTime < $expiration;
	}

}
