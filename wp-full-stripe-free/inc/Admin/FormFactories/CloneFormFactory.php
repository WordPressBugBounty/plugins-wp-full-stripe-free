<?php

namespace ThemeIsle\WPFullPay\Admin\FormFactories;

use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Exceptions\UserFriendlyException;
use ThemeIsle\WPFullPay\Forms\FormTypes;

class CloneFormFactory {
	/** @var Database */
	private $db = null;

	public function __construct() {
		$this->db = new Database();
	}

	/**
	 * Copies a form under a new name. Clones nothing when the type and layout name none of the form types.
	 *
	 * @param string $id
	 * @param string $type
	 * @param string $layout
	 * @param string $newFormName
	 * @param string $newFormDisplayName
	 * @return numeric-string|null The new form's id.
	 *
	 * @throws UserFriendlyException|\Exception
	 */
	public function cloneForm( $id, $type, $layout, $newFormName, $newFormDisplayName ) {
		$formType = FormTypes::find( $type, $layout );
		if ( null === $formType ) {
			return null;
		}

		// Names are unique across every form table, as when a form is created.
		if ( count( $this->db->getFormIdsByName( $newFormName ) ) > 0 ) {
			throw new UserFriendlyException(
				/* translators: Error message for not being able to clone a form. %s: form name */
				sprintf( __( "Cannot clone form because a form with id %s already exists.", 'wp-full-stripe-free' ), $newFormName )
			);
		}

		$idColumn = FormTypes::get( $formType )->idColumn;
		$form = $this->db->getFormAsArrayById( $formType, $id );
		if ( null === $form ) {
			throw new UserFriendlyException(
				/* translators: Error message for cloning a form that was deleted after the forms page listed it */
				__( 'Cannot clone the form because it no longer exists. Reload the page to see the current forms.', 'wp-full-stripe-free' )
			);
		}

		unset( $form[ $idColumn ] );
		$form['name'] = $newFormName;
		$form['displayName'] = $newFormDisplayName;

		$this->db->insertForm( $formType, $form );

		return $this->db->getFormByName( $formType, $newFormName )->{$idColumn};
	}
}
