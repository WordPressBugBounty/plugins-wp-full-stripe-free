<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\FormsAppearanceValidator;
use ThemeIsle\WPFullPay\Admin\Views\FormsAppearanceViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class FormsAppearanceModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $customCss;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new FormsAppearanceValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getCustomCss() {
		return $this->customCss;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->customCss = $this->getSanitizedArrayParam( $postData, FormsAppearanceViewConstants::FIELD_FORMS_APPEARANCE_CUSTOM_CSS_HIDDEN, '' );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'customCss' => $this->customCss
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
