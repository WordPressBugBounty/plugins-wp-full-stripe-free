<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\SecurityValidator;
use ThemeIsle\WPFullPay\Admin\Views\SecurityViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class SecurityModel implements Binder {
	use LoggerAddOn;
	use Model;

	protected $secureInlineForms;
	protected $secureCheckoutForms;
	protected $secureCustomerPortal;
	protected $reCaptchaSecretKey;
	protected $reCaptchaSiteKey;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new SecurityValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getSecureInlineForms() {
		return $this->secureInlineForms;
	}

	/**
	 * @return mixed
	 */
	public function getSecureCheckoutForms() {
		return $this->secureCheckoutForms;
	}

	/**
	 * @return mixed
	 */
	public function getSecureCustomerPortal() {
		return $this->secureCustomerPortal;
	}

	/**
	 * @return mixed
	 */
	public function getReCaptchaSecretKey() {
		return $this->reCaptchaSecretKey;
	}

	/**
	 * @return mixed
	 */
	public function getReCaptchaSiteKey() {
		return $this->reCaptchaSiteKey;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->secureInlineForms = $this->getSanitizedArrayParam( $postData, SecurityViewConstants::FIELD_SECURITY_SECURE_INLINE_FORMS, 0 );
		$this->secureCheckoutForms = $this->getSanitizedArrayParam( $postData, SecurityViewConstants::FIELD_SECURITY_SECURE_CHECKOUT_FORMS, 0 );
		$this->secureCustomerPortal = $this->getSanitizedArrayParam( $postData, SecurityViewConstants::FIELD_SECURITY_SECURE_CUSTOMER_PORTAL, 0 );
		$this->reCaptchaSecretKey = $this->getSanitizedArrayParam( $postData, SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SECRET_KEY );
		$this->reCaptchaSiteKey = $this->getSanitizedArrayParam( $postData, SecurityViewConstants::FIELD_SECURITY_RECAPTCHA_SITE_KEY );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'secureInlineForms' => $this->secureInlineForms,
			'secureCheckoutForms' => $this->secureCheckoutForms,
			'secureCustomerPortal' => $this->secureCustomerPortal,
			'reCaptchaSecretKey' => $this->reCaptchaSecretKey,
			'reCaptchaSiteKey' => $this->reCaptchaSiteKey,
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
