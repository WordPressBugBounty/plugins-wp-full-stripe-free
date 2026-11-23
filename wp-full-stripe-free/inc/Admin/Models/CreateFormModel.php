<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\CreateFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\CreateFormViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class CreateFormModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $name;
	protected $displayName;
	protected $type;
	protected $layout;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new CreateFormValidator( $this->loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function getDisplayName() {
		return $this->displayName;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return mixed
	 */
	public function getLayout() {
		return $this->layout;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->displayName = $this->getSanitizedArrayParam( $postData, CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME );
		$this->name = $this->getSanitizedArrayParam( $postData, CreateFormViewConstants::FIELD_FORM_NAME );
		$this->type = $this->getSanitizedArrayParam( $postData, CreateFormViewConstants::FIELD_FORM_TYPE );
		$this->layout = $this->getSanitizedArrayParam( $postData, CreateFormViewConstants::FIELD_FORM_LAYOUT );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'displayName' => $this->displayName,
			'name' => $this->name,
			'type' => $this->type,
			'layout' => $this->layout
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
