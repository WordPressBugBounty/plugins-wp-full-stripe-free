<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

abstract class SaveCardFormView extends FormView implements SaveCardFormViewConstants {

	public function __construct( $form, $fieldConfiguration, $loggerService ) {
		parent::__construct( $form, $fieldConfiguration, $loggerService );

		$this->attributes = $this->getFormAttributes();
	}

	protected function getFormAttributes() {
		$attributes = [];
		return array_merge( $attributes, parent::getFormAttributes() );
	}

	public static function getFields() {
		$fields = [];

		return array_merge( $fields, parent::getFields() );
	}
}
