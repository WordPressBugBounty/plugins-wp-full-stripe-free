<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
class BindingResult
{

	protected $formHash = null;
	protected $globalErrors = [];
	protected $fieldErrors = [];

	/**
	 * BindingResult constructor.
	 *
	 * @param $formHash
	 */
	public function __construct($formHash = null)
	{
		$this->formHash = $formHash;
	}

	public function hasErrors()
	{
		return !empty($this->globalErrors) || !empty($this->fieldErrors);
	}

	public function hasFieldErrors($field = null)
	{
		if (is_null($field)) {
			return !empty($this->fieldErrors);
		} else {
			return array_key_exists($field, $this->fieldErrors);
		}
	}

	public function addFieldError($fieldName, $fieldId, $error)
	{
		if (is_null($fieldName)) {
			return;
		}
		if (!array_key_exists($fieldName, $this->fieldErrors)) {
			$this->fieldErrors[$fieldName] = [];
		}
		array_push(
			$this->fieldErrors[$fieldName],
			[
				'id' => $fieldId,
				'name' => $fieldName,
				'message' => $error
			]
		);
	}

	public function getFieldErrors($field = null)
	{
		if (is_null($field)) {
			$fieldErrors = [];
			foreach (array_values($this->fieldErrors) as $errors) {
				$fieldErrors = array_merge($fieldErrors, $errors);
			}

			return $fieldErrors;
		}
		if (array_key_exists($field, $this->fieldErrors)) {
			return $this->fieldErrors[$field];
		} else {
			return [];
		}
	}

	public function getGlobalErrors()
	{
		return $this->globalErrors;
	}

	public function hasGlobalErrors()
	{
		return !empty($this->globalErrors);
	}

	public function addGlobalError($error)
	{
		array_push($this->globalErrors, $error);
	}

	/**
	 * @return null
	 */
	public function getFormHash()
	{
		return $this->formHash;
	}

	/**
	 * @param $formHash
	 */
	public function setFormHash($formHash)
	{
		$this->formHash = $formHash;
	}

}
