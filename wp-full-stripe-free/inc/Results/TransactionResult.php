<?php

namespace ThemeIsle\WPFullPay\Results;

class TransactionResult {

	/**
	 * @var boolean
	 */
	protected $success = false;
	/**
	 * @var string
	 */
	protected $messageTitle;
	/**
	 * @var string
	 */
	protected $message;
	/**
	 * @var boolean
	 */
	protected $redirect = false;
	/**
	 * @var string
	 */
	protected $redirectURL;
	/**
	 * @var boolean
	 */
	protected $requiresAction = false;
	/**
	 * @var string
	 */
	protected $paymentIntentClientSecret;
	/**
	 * @var string
	 */
	protected $setupIntentClientSecret;
	/**
	 * @var string
	 */
	protected $formType;
	/**
	 * @var string
	 */
	protected $nonce;

	/**
	 * @var boolean
	 */
	protected $isManualConfirmation = false;

	/**
	 * @param boolean $success
	 */
	public function setSuccess( $success ) {
		$this->success = $success;
	}

	/**
	 * @return string
	 */
	public function getMessageTitle() {
		return $this->messageTitle;
	}

	/**
	 * @param string $messageTitle
	 */
	public function setMessageTitle( $messageTitle ) {
		$this->messageTitle = $messageTitle;
	}

	/**
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @param string $message
	 */
	public function setMessage( $message ) {
		$this->message = $message;
	}

	/**
	 * @return boolean
	 */
	public function isRedirect() {
		return $this->redirect;
	}

	/**
	 * @param boolean $redirect
	 */
	public function setRedirect( $redirect ) {
		$this->redirect = $redirect;
	}

	/**
	 * @return string
	 */
	public function getRedirectURL() {
		return $this->redirectURL;
	}

	/**
	 * @param string $redirectURL
	 */
	public function setRedirectURL( $redirectURL ) {
		$this->redirectURL = $redirectURL;
	}

	/**
	 * @return boolean
	 */
	public function isRequiresAction() {
		return $this->requiresAction;
	}

	/**
	 * @param boolean $requiresAction
	 */
	public function setRequiresAction( $requiresAction ) {
		$this->requiresAction = $requiresAction;
	}

	/**
	 * @return mixed
	 */
	public function getPaymentIntentClientSecret() {
		return $this->paymentIntentClientSecret;
	}

	/**
	 * @param mixed $paymentIntentClientSecret
	 */
	public function setPaymentIntentClientSecret( $paymentIntentClientSecret ) {
		$this->paymentIntentClientSecret = $paymentIntentClientSecret;
	}

	/**
	 * @return string
	 */
	public function getSetupIntentClientSecret() {
		return $this->setupIntentClientSecret;
	}

	/**
	 * @param string $setupIntentClientSecret
	 */
	public function setSetupIntentClientSecret( $setupIntentClientSecret ) {
		$this->setupIntentClientSecret = $setupIntentClientSecret;
	}

	/**
	 * @return string
	 */
	public function getFormType() {
		return $this->formType;
	}

	/**
	 * @return string
	 */
	public function getNonce() {
		return $this->nonce;
	}

	/**
	 * @param string $nonce
	 */
	public function setNonce( $nonce ) {
		$this->nonce = $nonce;
	}

	/**
	 * @return bool
	 */
	public function isManualConfirmation(): bool {
		return $this->isManualConfirmation;
	}

	/**
	 * @param bool $isManualConfirmation
	 */
	public function setIsManualConfirmation( bool $isManualConfirmation ) {
		$this->isManualConfirmation = $isManualConfirmation;
	}

	/**
	 * @return boolean
	 */
	public function isSuccess() {
		return $this->success;
	}

	/**
	 * The response of the inline form AJAX handlers; wpfs.js reads these keys.
	 *
	 * @return array{success: bool, messageTitle: string|null, message: string|null, redirect: bool, redirectURL: string|null, requiresAction: bool, paymentIntentClientSecret: string|null, setupIntentClientSecret: string|null, formType: string|null, nonce: string|null, isManualConfirmation: bool}
	 */
	public function getAsArray() {
		return [
			'success' => $this->success,
			'messageTitle' => $this->messageTitle,
			'message' => $this->message,
			'redirect' => $this->redirect,
			'redirectURL' => $this->redirectURL,
			'requiresAction' => $this->requiresAction,
			'paymentIntentClientSecret' => $this->paymentIntentClientSecret,
			'setupIntentClientSecret' => $this->setupIntentClientSecret,
			'formType' => $this->formType,
			'nonce' => $this->nonce,
			'isManualConfirmation' => $this->isManualConfirmation,
		];
	}
}
