<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Forms\Fields\ConfigurableFormFields;

trait InlineFormView {

	/** @var Control */
	protected $cardHolderName;
	/** @var Control */
	protected $cardHolderEmail;
	/** @var Control */
	protected $card;

	public static function getInlineFields() {
		$fields = [
			InlineFormViewConstants::FIELD_CARD_HOLDER_NAME => ControlUtils::input( InlineFormViewConstants::FIELD_CARD_HOLDER_NAME ),
			InlineFormViewConstants::FIELD_CARD_HOLDER_EMAIL => ControlUtils::input( InlineFormViewConstants::FIELD_CARD_HOLDER_EMAIL ),
			InlineFormViewConstants::FIELD_CARD_NUMBER => ControlUtils::cardInput( InlineFormViewConstants::FIELD_CARD_NUMBER )
		];

		return $fields;
	}

	public function cardHolderEmail() {
		return $this->cardHolderEmail;
	}

	public function cardHolderName() {
		return $this->cardHolderName;
	}

	public function card() {
		return $this->card;
	}

	protected function initInlineFields( $formHash ) {
		$this->cardHolderName = ControlUtils::createControl(
			$formHash,
			InlineFormViewConstants::FIELD_CARD_HOLDER_NAME,
			null,
			null,
			/* translators: Form field label for card holder's name */
			__( 'Cardholder’s name', 'wp-full-stripe-free' ),
			null
		);
		$this->configureTextControl( $this->cardHolderName, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_CARDHOLDERS_NAME ] );

		$this->cardHolderEmail = ControlUtils::createControl(
			$formHash,
			InlineFormViewConstants::FIELD_CARD_HOLDER_EMAIL,
			null,
			null,
			/* translators: Form field label for email address */
			__( 'E-mail address', 'wp-full-stripe-free' ),
			null
		);
		$this->configureTextControl( $this->cardHolderEmail, $this->fieldConfiguration[ ConfigurableFormFields::FIELD_EMAIL ] );
		// The email is always validated on the server, so let the browser prompt for it before submitting.
		$emailAttributes = $this->cardHolderEmail->attributes( false );
		// Browsers skip `required` on read-only inputs, so only keep the lock on an email the server will accept.
		// Decode first, as the browser does, because value() returns the escaped attribute.
		$renderedEmail = html_entity_decode( (string) $this->cardHolderEmail->value( false ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( false === filter_var( sanitize_email( $renderedEmail ), FILTER_VALIDATE_EMAIL ) ) {
			unset( $emailAttributes['readonly'] );
		}
		$emailAttributes['required'] = 'required';
		$this->cardHolderEmail->setAttributes( $emailAttributes );

		$this->card = ControlUtils::createControl(
			$formHash,
			InlineFormViewConstants::FIELD_CARD_NUMBER,
			null,
			null,
			/* translators: Form field label for credit card data */
			__( 'Card info', 'wp-full-stripe-free' ),
			null
		);
	}

	/**
	 * @return array
	 */
	protected function getInlineFormAttributes( $form ) {
		$attributes = [];

		$attributes[ InlineFormViewConstants::ATTR_DATA_WPFS_PREFERRED_LANGUAGE ] = $form->preferredLanguage;

		return $attributes;
	}

}
