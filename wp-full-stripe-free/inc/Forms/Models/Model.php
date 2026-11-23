<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

use ThemeIsle\WPFullPay\Forms\Validators\Validator;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
trait Model
{

	/**
	 * @var Validator
	 */
	protected $__validator;

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 * @param $defaultValue
	 * @param string $sanitationType
	 *
	 * @return array|string
	 */
	public function getSanitizedArrayParam($dataArray, $parameterName, $defaultValue = null, $sanitationType = ModelConstants::SANITATION_TYPE_TEXT_FIELD)
	{
		$parameterValue = $this->getArrayParam($dataArray, $parameterName, $defaultValue);

		return $this->sanitizeValue($parameterValue, $sanitationType);
	}

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 * @param $defaultValue
	 *
	 * @return array|string
	 */
	public function getArrayParam($dataArray, $parameterName, $defaultValue = null)
	{
		if (array_key_exists($parameterName, $dataArray) && isset($dataArray[$parameterName])) {
			$value = wp_unslash($dataArray[$parameterName]);
		} else {
			$value = wp_unslash($defaultValue);
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $sanitationType
	 *
	 * @return array|string
	 */
	public function sanitizeValue($value, $sanitationType = ModelConstants::SANITATION_TYPE_TEXT_FIELD)
	{
		if (is_array($value)) {
			if (ModelConstants::SANITATION_TYPE_TEXT_FIELD === $sanitationType) {
				$functionName = 'sanitize_text_field';
			} elseif (ModelConstants::SANITATION_TYPE_EMAIL === $sanitationType) {
				$functionName = 'sanitize_email';
			} elseif (ModelConstants::SANITATION_TYPE_KEY === $sanitationType) {
				$functionName = 'sanitize_key';
			} else {
				$functionName = 'sanitize_text_field';
			}

			array_walk_recursive($value, $functionName);

			return $value;
		} else {
			if (ModelConstants::SANITATION_TYPE_TEXT_FIELD === $sanitationType) {
				return sanitize_text_field($value);
			} elseif (ModelConstants::SANITATION_TYPE_EMAIL === $sanitationType) {
				return sanitize_email($value);
			} elseif (ModelConstants::SANITATION_TYPE_KEY === $sanitationType) {
				return sanitize_key($value);
			} else {
				return sanitize_text_field($value);
			}
		}
	}

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 * @param $defaultValue
	 *
	 * @return array|string
	 */
	public function getNumericArrayParam($dataArray, $parameterName, $defaultValue = null)
	{
		if (isset($dataArray[$parameterName]) && is_numeric($dataArray[$parameterName])) {
			$value = wp_unslash($dataArray[$parameterName]);
		} else {
			$value = wp_unslash($defaultValue);
		}

		return $value;
	}

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 * @param $defaultValue
	 *
	 * @return string
	 */
	public function getHTMLDecodedArrayParam($dataArray, $parameterName, $defaultValue = null)
	{
		return html_entity_decode($this->getArrayParam($dataArray, $parameterName, $defaultValue));
	}

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 *
	 * @return array|mixed|object
	 */
	public function getJSONDecodedArrayParam($dataArray, $parameterName)
	{
		return json_decode(rawurldecode(stripslashes($dataArray[$parameterName])));
	}

	/**
	 * @param array $dataArray
	 * @param $parameterName
	 * @param string $sanitationType
	 *
	 * @return array|string
	 */
	public function getURLDecodedArrayParam($dataArray, $parameterName, $sanitationType = ModelConstants::SANITATION_TYPE_TEXT_FIELD)
	{
		return $this->sanitizeValue(urldecode($this->getArrayParam($dataArray, $parameterName)), $sanitationType);
	}

}
