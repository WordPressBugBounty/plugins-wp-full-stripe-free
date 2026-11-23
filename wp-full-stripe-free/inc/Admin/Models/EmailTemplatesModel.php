<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\EmailTemplatesViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class EmailTemplatesModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $emailTemplates;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
	}

	/**
	 * @return mixed
	 */
	public function getEmailTemplates() {
		return $this->emailTemplates;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->emailTemplates = $this->getJSONDecodedArrayParam( $postData, EmailTemplatesViewConstants::FIELD_EMAIL_TEMPLATES_HIDDEN );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'emailTemplates' => $this->emailTemplates,
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
