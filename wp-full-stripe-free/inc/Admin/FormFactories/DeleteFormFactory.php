<?php

namespace ThemeIsle\WPFullPay\Admin\FormFactories;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\FormTypes;

class DeleteFormFactory {
	/** @var Database */
	private $db = null;

	public function __construct() {
		$this->db = new Database();
	}

	/**
	 * Deletes nothing when the type and layout name none of the form types.
	 *
	 * @param string $id
	 * @param string $type
	 * @param string $layout
	 *
	 * @throws \Exception
	 */
	public function deleteForm( $id, $type, $layout ) {
		$formType = FormTypes::find( $type, $layout );

		if ( null !== $formType ) {
			$this->db->deleteForm( $formType, $id );
		}
	}
}
