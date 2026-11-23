<?php

namespace ThemeIsle\WPFullPay\Forms;

use ThemeIsle\WPFullPay\Admin\Models\FormModel;
use ThemeIsle\WPFullPay\Admin\Views\FormView;
use ThemeIsle\WPFullPay\Main;

/**
 * Where one of the eight form types is stored. Built by FormTypes.
 *
 * @phpstan-import-type FormTypeKey from FormTypes
 */
class FormType {
	/** @var FormTypeKey */
	public $formType;
	/** @var Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD */
	public $type;
	/** @var Main::FORM_LAYOUT_* */
	public $layout;
	/** @var string The form table, without the WordPress prefix. */
	public $table;
	/** @var string The table's primary key. */
	public $idColumn;
	/** @var '%s'|'%d' The placeholder getFormById() binds the id with. */
	public $idPlaceholder;
	/** @var list<string> Columns the admin models post that the table doesn't have; updates drop them. */
	public $ignoredOnUpdate;
	/** @var bool|null Whether the type's rows have customAmount 'card_capture'; null when the table holds only this type. */
	public $cardCapture;
	/** @var string The table its transactions are recorded in, without the WordPress prefix. */
	public $transactionTable;
	/** @var class-string<FormModel> The admin model that binds the form editor's save. */
	public $modelClass;
	/** @var class-string<FormView> The admin view that renders the form editor. */
	public $viewClass;

	/**
	 * @param FormTypeKey $formType
	 * @param array{type:Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD,layout:Main::FORM_LAYOUT_*,table:string,idColumn:string,idPlaceholder:'%s'|'%d',ignoredOnUpdate:list<string>,cardCapture:bool|null,transactionTable:string,modelClass:class-string<FormModel>,viewClass:class-string<FormView>} $definition
	 */
	public function __construct( string $formType, array $definition ) {
		$this->formType = $formType;
		$this->type = $definition['type'];
		$this->layout = $definition['layout'];
		$this->table = $definition['table'];
		$this->idColumn = $definition['idColumn'];
		$this->idPlaceholder = $definition['idPlaceholder'];
		$this->ignoredOnUpdate = $definition['ignoredOnUpdate'];
		$this->cardCapture = $definition['cardCapture'];
		$this->transactionTable = $definition['transactionTable'];
		$this->modelClass = $definition['modelClass'];
		$this->viewClass = $definition['viewClass'];
	}
}
