<?php

namespace ThemeIsle\WPFullPay\Forms\Models;

/**
 * Interface Binder contains the necessary functions for implementation to bind data to properties.
 *
 * Used by WPFSM, WPFP_Mailchimp
 */
interface Binder
{

	const EMPTY_STR = '';

	/**
	 * Performs property binding by he $_POST superglobal.
	 *
	 * @return BindingResult
	 */
	public function bind();

	/**
	 * Performs property binding by the given array.
	 *
	 * @param $postData
	 *
	 * @return BindingResult
	 */
	public function bindByArray($postData);

	/**
	 * This function can be overridden/implemented to call functions for specific operations that need to be run after
	 * bind().
	 *
	 * @return mixed
	 */
	public function afterBind();

	/**
	 * Returns an array with property names as keys to save this instance to database
	 *
	 * @return array
	 */
	public function getData();

	/**
	 * Returns an array with POST parameters as keys to serialize this instance. This array should be used as a
	 * parameter for bindByArray() later.
	 *
	 * @return array
	 * @see Binder::bindByArray()
	 */
	public function getPostData();

}
