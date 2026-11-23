<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\EmailOptionsValidator;
use ThemeIsle\WPFullPay\Admin\Views\EmailOptionsViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class EmailOptionsModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $fromAddress;
	protected $fromAddressCustom;
	protected $sendCopyToAdmin;
	protected $sendCopyToList;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new EmailOptionsValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getFromAddress() {
		return $this->fromAddress;
	}

	/**
	 * @return mixed
	 */
	public function getFromAddressCustom() {
		return $this->fromAddressCustom;
	}

	/**
	 * @return mixed
	 */
	public function getSendCopyToAdmin() {
		return $this->sendCopyToAdmin;
	}

	/**
	 * @return mixed
	 */
	public function getSendCopyToList() {
		return $this->sendCopyToList;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->fromAddress = $this->getSanitizedArrayParam( $postData, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS );
		$this->fromAddressCustom = $this->getSanitizedArrayParam( $postData, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_FROM_ADDRESS_CUSTOM );
		$this->sendCopyToAdmin = $this->getSanitizedArrayParam( $postData, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_ADMIN );
		$this->sendCopyToList = $this->getJSONDecodedArrayParam( $postData, EmailOptionsViewConstants::FIELD_EMAIL_OPTIONS_SEND_COPY_TO_LIST_HIDDEN );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'fromAddress' => $this->fromAddress,
			'fromAddressCustom' => $this->fromAddressCustom,
			'sendCopyToAdmin' => $this->sendCopyToAdmin,
			'sendCopyToList' => $this->getSendCopyToList()
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
