<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

use ThemeIsle\WPFullPay\Utils;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
class ControlUtils {

	const FIELD_TYPE_INPUT = 'input';
	const FIELD_TYPE_INPUT_CUSTOM = 'input-custom';
	const FIELD_TYPE_INPUT_GROUP = 'input-group';
	const FIELD_TYPE_INPUT_GROUP_MINMAX = 'input-group-minmax';
	const FIELD_TYPE_DROPDOWN = 'dropdown';
	const FIELD_TYPE_CHECKBOX = 'checkbox';
	const FIELD_TYPE_CHECKLIST = 'checklist';
	const FIELD_TYPE_PRODUCTS = 'products';
	const FIELD_TYPE_CARD = 'card';
	const FIELD_TYPE_CAPTCHA = 'captcha';
	const FIELD_TYPE_TAGS = 'tags';

	public static final function customInput( $name ) {
		$class = 'wpfs-form-control';
		$selector = "#{fieldId}";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_INPUT, $name, $class, $selector, $errorClass, $errorSelector );
	}

	/**
	 * @param $type
	 * @param $name
	 * @param $class
	 * @param $selector
	 * @param $errorClass
	 * @param $errorSelector
	 * @param bool $hidden
	 *
	 * @return array
	 */
	public static final function descriptor( $type, $name, $class, $selector, $errorClass, $errorSelector, $hidden = false ) {
		return [
			'type' => $type,
			'name' => $name,
			'class' => $class,
			'selector' => $selector,
			'errorClass' => $errorClass,
			'errorSelector' => $errorSelector,
			'hidden' => $hidden
		];
	}

	/**
	 * Used by WPFSM, WPFP_Mailchimp
	 */
	public static final function input( $name ) {
		$class = 'wpfs-form-control';
		$selector = ".{$class}[name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_INPUT, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function inputTags( $name ) {
		$class = 'wpfs-tags-input';
		$selector = ".{$class}[name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_TAGS, $name, $class, $selector, $errorClass, $errorSelector );
	}

	/**
	 * Used by WPFSM, WPFP_Mailchimp
	 */
	public static final function inputHidden( $name ) {
		return self::descriptor( self::FIELD_TYPE_INPUT, $name, null, null, null, null, true );
	}

	public static final function selectMenu( $name ) {
		$class = 'wpfs-form-control';
		$selector = "select[name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = '#{fieldId}-button,#{fieldId}-menu';

		return self::descriptor( self::FIELD_TYPE_DROPDOWN, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function checkbox( $name ) {
		$class = 'wpfs-form-check-input';
		$selector = ".{$class}[name='{$name}']";
		$errorClass = 'wpfs-form-check-input--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_CHECKBOX, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function checklist( $name ) {
		$class = 'wpfs-form-check-list';
		$selector = ".{$class}[data-field-name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_CHECKLIST, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function products( $name ) {
		$class = 'wpfs-field-list';
		$selector = ".{$class}[data-field-name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}[data-field-name='{$name}']";

		return self::descriptor( self::FIELD_TYPE_PRODUCTS, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function captcha( $name ) {
		$class = 'wpfs-form-captcha';
		$selector = ".{$class}[data-wpfs-field-name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_CAPTCHA, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function inputGroup( $name ) {
		$class = 'wpfs-input-group-form-control';
		$selector = ".{$class}[name='{$name}']";
		$errorClass = 'wpfs-input-group--error';
		$errorSelector = '.' . 'wpfs-input-group';

		return self::descriptor( self::FIELD_TYPE_INPUT_GROUP, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function inputGroupMinMax( $name ) {
		$class = 'wpfs-input-group-form-control';
		$selector = ".{$class}[name='{$name}']";
		$errorClass = 'wpfs-input-group--error';
		$errorSelector = '.' . 'wpfs-input-group';

		return self::descriptor( self::FIELD_TYPE_INPUT_GROUP_MINMAX, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function cardInput( $name ) {
		$class = 'wpfs-form-card';
		$selector = "input[name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_CARD, $name, $class, $selector, $errorClass, $errorSelector );
	}

	public static final function stepper( $name ) {
		$class = 'wpfs-stepper';
		$selector = "input[name='{$name}']";
		$errorClass = 'wpfs-form-control--error';
		$errorSelector = ".{$class}";

		return self::descriptor( self::FIELD_TYPE_CARD, $name, $class, $selector, $errorClass, $errorSelector );
	}

	/*
	 * @param $formHash
	 * @param $name
	 * @param $placeholder
	 * @param $caption
	 * @param $label
	 * @param null $index
	 *
	 * @return Control
	 *
	 * Used by WPFSM, WPFP_Mailchimp
	 */
	public static final function createControl( $formHash, $name, $placeholder, $caption, $label, $index ) {
		$control = new Control(
			Utils::generateFormElementId( $name, $formHash, $index ),
			$name,
			$placeholder
		);
		$control->setCaption( $caption );
		$control->setLabel( $label );
		$control->setIndex( $index );

		return $control;
	}

}
