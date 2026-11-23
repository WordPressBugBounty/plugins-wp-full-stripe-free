<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Utils;

class SavedCardsTable extends TransactionsTable {
	const COLUMN_PAYMENT_METHOD = 'paymentMethod';
	const COLUMN_ID_DATE = 'idDate';
	const COLUMN_CUSTOMER_FORM = 'customerForm';
	const COLUMN_MODE = 'mode';
	const COLUMN_ACTIONS = 'actions';

	const HEADER_CLASSES = [
		self::COLUMN_PAYMENT_METHOD => 'wpfs-data-table__th--w40',
	];

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService, [
			'singular' =>
				/* translators: Singular version of the expression 'Saved card', used on the save card list page */
				__( 'Saved card', 'wp-full-stripe-free' ),
			'plural' =>
				/* translators: Plural version of the expression 'Saved card', used on the save card list page */
				__( 'Saved cards', 'wp-full-stripe-free' ),
			'ajax' => false
		] );
	}

	/**
	 * @return array
	 */
	protected function getForms(): array {
		return $this->db->getSaveCardFormNames();
	}

	/**
	 * @return array
	 */
	protected function getFilterParameters(): array {
		$searchText = $this->requestFilter( Menu::PARAM_NAME_SAVED_CARDS_TEXT_FILTER );
		$mode = $this->requestFilter( Menu::PARAM_NAME_SAVED_CARDS_MODE_FILTER );

		return [ $searchText, $mode ];
	}

	/**
	 * @return string
	 */
	protected function getQuery() {
		$dbPrefix = $this->db->getDatabasePrefix();
		$whereStatement = null;

		$query = "
            SELECT
                captureID,
                stripeCustomerID,
                email,
                created,
                livemode,
                formId,
                formName
            FROM
               {$dbPrefix}fullstripe_card_captures
        ";

		list( $searchText, $apiMode ) = $this->getFilterParameters();

		if ( isset( $searchText ) ) {
			$escapedSearchText = esc_sql( $searchText );

			$this->extendWhereStatement(
				$whereStatement,
				sprintf( "(LOWER(name) LIKE LOWER('%s') OR LOWER(email) LIKE LOWER('%s') OR stripeCustomerID LIKE LOWER('%s'))", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%" )
			);
		}

		if ( isset( $apiMode ) && $apiMode !== Menu::PARAM_VALUE_API_MODE_ALL ) {
			$this->extendWhereStatement(
				$whereStatement,
				sprintf( '(livemode = %d)', $apiMode === Menu::PARAM_VALUE_API_MODE_LIVE ? 1 : 0 )
			);
		}

		if ( isset( $whereStatement ) ) {
			$query .= $whereStatement;
		}

		$this->addOrderByStatementToQuery( $query );

		return $query;
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			self::COLUMN_PAYMENT_METHOD => '',
			self::COLUMN_ID_DATE => $this->createCompositeColumnLabel(
				/* translators: Name of the 'ID' column */
				__( 'ID', 'wp-full-stripe-free' ),
				/* translators: Name of the 'Date' column */
				__( 'Date', 'wp-full-stripe-free' )
			),
			self::COLUMN_CUSTOMER_FORM => $this->createCompositeColumnLabel(
				/* translators: Name of the 'Customer' column */
				__( 'Customer', 'wp-full-stripe-free' ),
				/* translators: Name of the 'Form' column */
				__( 'Form', 'wp-full-stripe-free' )
			),
			self::COLUMN_MODE =>
				/* translators: Name of the 'Mode' column */
				__( 'Mode', 'wp-full-stripe-free' ),
			self::COLUMN_ACTIONS => ''
		];
	}

	/**
	 * @return array
	 */
	protected function get_sortable_columns() {
		return [];
	}

	/**
	 *
	 */
	public function no_items() {
		esc_html_e( 'No saved card found.', 'wp-full-stripe-free' );
	}

	/**
	 * @param $savedCard
	 *
	 * @return string
	 */
	protected function renderIdDateCell( $savedCard ): string {
		$dateLabel = Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $savedCard->created ) );

		$html = "
         <td class='wpfs-data-table__td'>
            <a class='wpfs-btn wpfs-btn-link js-open-saved-card-details'>" . esc_html( $savedCard->stripeCustomerID ) . "</a>
            <br>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $dateLabel ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $savedCard
	 * @return string
	 */
	protected function renderCustomerFormCell( $savedCard ) {
		$formDisplayName = $this->lookupFormDisplayName( $savedCard->formName );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-typo-body wpfs-typo-body--gunmetal'>" . esc_html( $savedCard->email ) . "</div>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $formDisplayName ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $savedCard
	 * @return string
	 */
	protected function renderModeCell( $savedCard ): string {
		$modeLabel = Admin::getApiModeLabelFromInteger( $savedCard->livemode );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-tags'>
                <span class='wpfs-tag wpfs-tag--outline'>" . esc_html( $modeLabel ) . "</span>
            </div>
        </td>
        ";

		return $html;
	}

	protected function renderActionsCell( $payment ) {
		$html = "<td class='wpfs-data-table__td wpfs-data-table__td--right wpfs-data-table__td--actions'>";

		$deleteLabel =
			/* translators: 'Delete' action label for saved cards */
			__( 'Delete saved card', 'wp-full-stripe-free' );
		$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-saved-card' data-tooltip-content='delete-saved-card-tooltip'>
                <span class='wpfs-icon-trash'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='delete-saved-card-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $deleteLabel ) . "</div>
            </div>";

		$html .= '</td>';

		return $html;
	}

	protected function rowIds( $row ): array {
		return [ $row->captureID, $row->stripeCustomerID ];
	}

	protected function cellRenderers(): array {
		return [
			self::COLUMN_PAYMENT_METHOD => 'renderPaymentMethodCell',
			self::COLUMN_ID_DATE => 'renderIdDateCell',
			self::COLUMN_CUSTOMER_FORM => 'renderCustomerFormCell',
			self::COLUMN_MODE => 'renderModeCell',
			self::COLUMN_ACTIONS => 'renderActionsCell',
		];
	}

}
