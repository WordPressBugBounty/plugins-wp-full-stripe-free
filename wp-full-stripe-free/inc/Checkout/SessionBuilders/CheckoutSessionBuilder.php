<?php

namespace ThemeIsle\WPFullPay\Checkout\SessionBuilders;

use ThemeIsle\WPFullPay\Checkout\CheckoutSubmissionService;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;
use ThemeIsle\WPFullPay\Forms\Fields\FormFieldConfiguration;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutDonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutPaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\CheckoutSubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

abstract class CheckoutSessionBuilder {
	use LoggerAddOn;
	use StaticContextAddOn;
	protected $submitHash;
	/** @var CheckoutPaymentFormModel|CheckoutDonationFormModel|CheckoutSubscriptionFormModel */
	protected $formModel;
	/** @var Options */
	protected $options;
	/** @var Stripe */
	protected $stripe;
	/** @var FormFieldConfiguration[] $fieldConfiguration */
	protected $fieldConfiguration;

	public function __construct( $submitHash, $formModel, $fieldConfiguration, $stripe, $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_CHECKOUT_SUBMISSION );
		$this->stripe = $stripe;
		$this->options = new Options();

		$this->initStaticContext();

		$this->submitHash = $submitHash;
		$this->formModel = $formModel;
		$this->fieldConfiguration = $fieldConfiguration;
	}

	/**
	 * @param $submitHash
	 *
	 * @return string
	 */
	protected function buildCheckoutSessionSuccessURL( $submitHash ) {
		return $this->buildCheckoutSessionStatusURL( $submitHash, CheckoutSubmissionService::CHECKOUT_SESSION_STATUS_SUCCESS );
	}

	/**
	 * @param $submitHash
	 *
	 * @return string
	 */
	protected function buildCheckoutSessionCancelURL( $submitHash ) {
		return $this->buildCheckoutSessionStatusURL( $submitHash, CheckoutSubmissionService::CHECKOUT_SESSION_STATUS_CANCELLED );
	}

	/**
	 * @param $submitHash
	 * @param $status
	 *
	 * @return string
	 */
	private function buildCheckoutSessionStatusURL( $submitHash, $status ) {
		return add_query_arg(
			[
				'action' => 'wp_full_stripe_handle_checkout_session',
				CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_POPUP_FORM_SUBMIT_HASH => $submitHash,
				CheckoutSubmissionService::STRIPE_CALLBACK_PARAM_WPFS_STATUS => $status
			],
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * @param $formModel FormModel
	 * @return array
	 */
	protected function prepareCountryFilterParams() {
		return [ 
			'formName' => $this->formModel->getForm()->name,
			'formType' => Utils::getFormType( $this->formModel->getForm() ),
		];
	}

	/**
	 * @param $formModel
	 * @return string[]
	 */
	private function getShippingCountryCodes() {
		$countryCodes = Countries::getAvailableCheckoutCountryCodes();

		try {
			$countryCodes = apply_filters(
				'fullstripe_shipping_countries',
				$countryCodes,
				$this->prepareCountryFilterParams()
			);
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Error while filtering shipping countries', $ex );
		}

		return $countryCodes;
	}

	public function build() {
		$collectBillingAddress = isset( $this->formModel->getForm()->showBillingAddress ) && 1 == $this->formModel->getForm()->showBillingAddress ? 'required' : 'auto';

		$sessionData = [ 
			'client_reference_id' => $this->submitHash,
			'billing_address_collection' => $collectBillingAddress,
			'success_url' => $this->buildCheckoutSessionSuccessURL( $this->submitHash ),
			'cancel_url' => $this->buildCheckoutSessionCancelURL( $this->submitHash ),
		];

		if ( $this->formModel->getForm()->showShippingAddress ) {
			$sessionData['shipping_address_collection'] = [
				'allowed_countries' => $this->getShippingCountryCodes()
			];
		}

		$customerIdField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_CUSTOMER_ID ];
		$isCustomerIdConfigurable = $customerIdField->isConfigurable() && ! is_null( $customerIdField->getValue() );
		$emailField = $this->fieldConfiguration[ ConfigurableFormFields::FIELD_EMAIL ];
		$isEmailConfigurable = $emailField->isConfigurable() && ! is_null( $emailField->getValue() );

		// A configured customer ID names the customer outright, so its email isn't compared with the payer's, unlike
		// a customer found by email (#791).
		if ( $isCustomerIdConfigurable ) {
			$sessionData['customer'] = $customerIdField->getValue();
		} else if ( $isEmailConfigurable ) {
			$sessionData['customer_email'] = $emailField->getValue();
		}

		if ( isset( $this->formModel->getForm()->preferredLanguage ) ) {
			$sessionData['locale'] = $this->formModel->getForm()->preferredLanguage;
		}

		return $sessionData;
	}
}
