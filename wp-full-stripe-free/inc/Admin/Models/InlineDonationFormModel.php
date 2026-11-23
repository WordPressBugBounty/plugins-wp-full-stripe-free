<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\InlineDonationFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlineDonationFormViewConstants;

class InlineDonationFormModel extends DonationFormModel implements InlineForm {
	use InlineFormModel;

	protected $defaultProductName;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_DONATION_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new InlineDonationFormValidator( $loggerService );
	}

	private function bindInlineDonationParams( $postData ) {
		$this->defaultProductName = $this->getSanitizedArrayParam( $postData, InlineDonationFormViewConstants::FIELD_FORM_DEFAULT_PRODUCT_NAME );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindInlineParams( $postData );
		$this->bindInlineDonationParams( $postData );

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$inlineData = $this->getInlineDataArray();

		$data = [
			'productDesc' => $this->defaultProductName
		];

		$data = array_merge( $data, $inlineData, $parentData );

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getDefaultProductName() {
		return $this->defaultProductName;
	}
}
