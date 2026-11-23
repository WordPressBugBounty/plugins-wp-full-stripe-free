<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\InlinePaymentFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;

class InlinePaymentFormModel extends PaymentFormModel implements InlineForm {
	protected $title;

	use InlineFormModel;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_PAYMENT_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new InlinePaymentFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindInlineParams( $postData );
		// Legacy fields
		$this->title = '';

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$inlineData = $this->getInlineDataArray();

		$data = [
			'formTitle' => $this->title,
		];

		$data = array_merge( $data, $inlineData, $parentData );

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getTitle() {
		return $this->title;
	}
}
