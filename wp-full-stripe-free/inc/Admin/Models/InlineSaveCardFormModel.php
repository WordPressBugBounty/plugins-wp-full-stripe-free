<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\InlineSaveCardFormValidator;
use ThemeIsle\WPFullPay\Admin\Views\AdminViewConstants;
use ThemeIsle\WPFullPay\Admin\Views\InlineSaveCardFormViewConstants;
use ThemeIsle\WPFullPay\Main;

class InlineSaveCardFormModel extends SaveCardFormModel implements InlineForm {
	protected $title;
	/** @var string|array<array-key, mixed> '0' when the admin turned Stripe Link off */
	protected $stripeLink;

	use InlineFormModel;

	public function __construct( $loggerService ) {
		$this->formHash = AdminViewConstants::FORM_HASH_ADMIN_INLINE_SAVE_CARD_FORM;
		parent::__construct( $loggerService );

		$this->__validator = new InlineSaveCardFormValidator( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->bindInlineParams( $postData );
		$this->stripeLink = $this->getSanitizedArrayParam( $postData, InlineSaveCardFormViewConstants::FIELD_FORM_STRIPE_LINK, 1 );
		// Legacy fields
		$this->title = '';

		return $this->finishBind( $bindingResult );
	}

	public function getData() {
		$parentData = parent::getData();

		$inlineData = $this->getInlineDataArray();

		$data = [
			'formTitle' => $this->title,
			// A list without Link hides it; Link on stores nothing, like forms saved before the setting existed.
			'paymentMethods' => '0' === (string) $this->stripeLink ? json_encode( [ Main::PAYMENT_METHOD_CARD ] ) : null,
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
