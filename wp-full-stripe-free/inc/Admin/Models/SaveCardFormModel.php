<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Views\SaveCardFormViewConstants;
use ThemeIsle\WPFullPay\Main;

abstract class SaveCardFormModel extends FormModel {
	protected $transactionDescription;

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService );
	}

	public function bindByArray( $postData ) {
		$bindingResult = parent::bindByArray( $postData );

		$this->transactionDescription = $this->getSanitizedArrayParam( $postData, SaveCardFormViewConstants::FIELD_FORM_TRANSACTION_DESCRIPTION );

		return $bindingResult;
	}

	public function getData() {
		$parentData = parent::getData();

		$data = [
			'stripeDescription' => $this->transactionDescription,
			// The fields below are required because save card forms and payment forms are in the same database table
			'currency' => Main::CURRENCY_USD,
			'amount' => 0,
			'customAmount' => Main::PAYMENT_TYPE_CARD_CAPTURE,
			'listOfAmounts' => null,
			'allowListOfAmountsCustom' => 0,
			'showButtonAmount' => 0,
			'amountSelectorStyle' => Main::SELECTOR_STYLE_DROPDOWN,
		];

		$data = array_merge( $data, $parentData );

		return $data;
	}

	/**
	 * @return mixed
	 */
	public function getTransactionDescription() {
		return $this->transactionDescription;
	}
}
