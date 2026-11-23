<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\FormsOptionsValidator;
use ThemeIsle\WPFullPay\Admin\Views\FormsOptionsViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;

class FormsOptionsModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $defaultBillingCountry;
	protected $fillInEmail;
	protected $setFormFieldsViaUrlParameters;
	/** @var string */
	protected $showPaymentDetail;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new FormsOptionsValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getDefaultBillingCountry() {
		return $this->defaultBillingCountry;
	}

	/**
	 * @return mixed
	 */
	public function getFillInEmail() {
		return $this->fillInEmail;
	}

	public function getSetFormFieldsViaUrlParameters() {
		return $this->setFormFieldsViaUrlParameters;
	}

	/**
	 * @return string
	 */
	public function getShowPaymentDetail() {
		return $this->showPaymentDetail;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->defaultBillingCountry = $this->getSanitizedArrayParam( $postData, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_DEFAULT_BILLING_COUNTRY, Main::DEFAULT_BILLING_COUNTRY_INITIAL_VALUE );
		$this->fillInEmail = $this->getSanitizedArrayParam( $postData, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_FILL_IN_EMAIL, 0 );
		$this->setFormFieldsViaUrlParameters = $this->getSanitizedArrayParam( $postData, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SET_FIELDS_VIA_URL_PARAMETERS, 0 );
		$this->showPaymentDetail = $this->getSanitizedArrayParam( $postData, FormsOptionsViewConstants::FIELD_FORMS_OPTIONS_SHOW_PAYMENT_DETAIL, '1' );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'defaultBillingCountry' => $this->defaultBillingCountry,
			'fillInEmail' => $this->fillInEmail,
			'showPaymentDetail' => $this->showPaymentDetail
		];

		return $data;
	}

	public function getPostData() {
		return [];
	}

	public function afterBind() {
		// noop
	}
}
