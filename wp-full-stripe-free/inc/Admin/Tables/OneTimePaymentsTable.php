<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class OneTimePaymentsTable extends TransactionsTable {
	const COLUMN_PAYMENT_METHOD = 'paymentMethod';
	const COLUMN_ID_DATE = 'idDate';
	const COLUMN_CUSTOMER_FORM = 'customerForm';
	const COLUMN_AMOUNT = 'amount';
	const COLUMN_STATUS_MODE = 'statusMode';
	const COLUMN_ACTIONS = 'actions';

	const HEADER_CLASSES = [
		self::COLUMN_PAYMENT_METHOD => 'wpfs-data-table__th--w40',
		self::COLUMN_AMOUNT => 'wpfs-data-table__th--right',
	];

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService, [
			'singular' =>
				/* translators: Singular version of the word 'Payment', used on the payment list page */
				__( 'Payment', 'wp-full-stripe-free' ),
			'plural' =>
				/* translators: Plural version of the word 'Payment', used on the payment list page */
				__( 'Payments', 'wp-full-stripe-free' ),
			'ajax' => false
		] );
	}

	/**
	 * @return array
	 */
	protected function getForms(): array {
		return $this->db->getOneTimePaymentFormNames();
	}

	/**
	 * @return array
	 */
	protected function getFilterParameters(): array {
		$searchText = $this->requestFilter( Menu::PARAM_NAME_PAYMENTS_TEXT_FILTER );
		$status = $this->requestFilter( Menu::PARAM_NAME_PAYMENTS_STATUS_FILTER );
		$mode = $this->requestFilter( Menu::PARAM_NAME_PAYMENTS_MODE_FILTER );

		return [ $searchText, $status, $mode ];
	}

	protected function getQuery() {
		$dbPrefix = $this->db->getDatabasePrefix();
		$whereStatement = null;

		$query = "
            SELECT
                paymentID,
                eventID,
                stripeCustomerID,
                payment_method,
                paid,
                captured,
                refunded,
                expired,
                failure_code,
                failure_message,
                livemode,
                last_charge_status,
                currency,
                amount,
                name,
                email,
                formId,
                formType,
                formName,
                created
            FROM
                {$dbPrefix}fullstripe_payments
        ";

		list( $searchText, $paymentStatus, $apiMode ) = $this->getFilterParameters();

		if ( isset( $searchText ) ) {
			$escapedSearchText = esc_sql( $searchText );

			$this->extendWhereStatement(
				$whereStatement,
				sprintf( "(LOWER(name) LIKE LOWER('%s') OR LOWER(email) LIKE LOWER('%s') OR LOWER(eventID) like LOWER('%s') OR stripeCustomerID LIKE LOWER('%s'))", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%" )
			);
		}

		if ( isset( $paymentStatus ) && $paymentStatus !== Menu::PARAM_VALUE_PAYMENT_STATUS_ALL ) {
			$condition = '';

			if ( Main::PAYMENT_STATUS_FAILED === $paymentStatus ) {
				$condition .= sprintf( "last_charge_status='%s'", Main::STRIPE_CHARGE_STATUS_FAILED );
			} elseif ( Main::PAYMENT_STATUS_PENDING === $paymentStatus ) {
				$condition .= sprintf( "last_charge_status='%s'", Main::STRIPE_CHARGE_STATUS_PENDING );
			} elseif ( Main::PAYMENT_STATUS_EXPIRED === $paymentStatus ) {
				$condition .= sprintf( "expired=%d", 1 );
			} elseif ( Main::PAYMENT_STATUS_REFUNDED === $paymentStatus ) {
				$condition .= sprintf( "refunded=%d", 1 );
			} elseif ( Main::PAYMENT_STATUS_RELEASED === $paymentStatus ) {
				$condition .= sprintf( "(refunded=%d AND captured=%d)", 1, 0 );
			} elseif ( Main::PAYMENT_STATUS_PAID === $paymentStatus ) {
				$condition .= sprintf( "(last_charge_status='%s' AND paid=%d AND captured=%d AND expired=%d AND refunded=%d)", Main::STRIPE_CHARGE_STATUS_SUCCEEDED, 1, 1, 0, 0 );
			} elseif ( Main::PAYMENT_STATUS_AUTHORIZED === $paymentStatus ) {
				$condition .= sprintf( "(last_charge_status='%s' AND paid=%d AND captured=%d AND expired=%d AND refunded=%d)", Main::STRIPE_CHARGE_STATUS_SUCCEEDED, 1, 0, 0, 0 );
			}

			$this->extendWhereStatement( $whereStatement, $condition );
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
			self::COLUMN_AMOUNT =>
				/* translators: Name of the 'Amount' column */
				__( 'Amount', 'wp-full-stripe-free' ),
			self::COLUMN_STATUS_MODE => $this->createCompositeColumnLabel(
				/* translators: Name of the 'Status' column */
				__( 'Status', 'wp-full-stripe-free' ),
				/* translators: Name of the 'Mode' column */
				__( 'Mode', 'wp-full-stripe-free' )
			),
			self::COLUMN_ACTIONS => '',
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
		esc_html_e( 'No payment found.', 'wp-full-stripe-free' );
	}

	/**
	 * @param $payment
	 *
	 * @return string
	 */
	protected function renderIdDateCell( $payment ): string {
		$dateLabel = Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $payment->created ) );

		$html = "
         <td class='wpfs-data-table__td'>
            <a class='wpfs-btn wpfs-btn-link js-open-payment-details'>" . esc_html( $payment->eventID ) . "</a>
            <br>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $dateLabel ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $payment
	 * @return string
	 */
	protected function renderCustomerFormCell( $payment ) {
		$formDisplayName = $this->lookupFormDisplayName( $payment->formName );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-typo-body wpfs-typo-body--gunmetal'>" . esc_html( $payment->email ) . "</div>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $formDisplayName ) . "</div>
        </td>";

		return $html;
	}

	protected function renderAmountCell( $payment ) {
		$amountLabel = Currencies::formatAndEscape( $this->staticContext, $payment->currency, $payment->amount );

		$html = "
        <td class='wpfs-data-table__td wpfs-data-table__td--right'>
            <strong>" . esc_html( $amountLabel ) . "</strong>
        </td>";

		return $html;
	}

	protected function determinePaymentStatusCss( $paymentStatus ) {
		$cssStyle = '';

		switch ( $paymentStatus ) {
			case Main::PAYMENT_STATUS_PAID:
				$cssStyle = 'wpfs-tag--filled-green';
				break;

			case Main::PAYMENT_STATUS_AUTHORIZED:
				$cssStyle = 'wpfs-tag--filled-blue';
				break;

			case Main::PAYMENT_STATUS_FAILED:
			case Main::PAYMENT_STATUS_UNKNOWN:
				$cssStyle = 'wpfs-tag--filled-red';
				break;

			case Main::PAYMENT_STATUS_PENDING:
			case Main::PAYMENT_STATUS_EXPIRED:
			case Main::PAYMENT_STATUS_REFUNDED:
			case Main::PAYMENT_STATUS_RELEASED:
			default:
				$cssStyle = 'wpfs-tag--filled-grey';
				break;
		}

		return $cssStyle;
	}

	/**
	 * @param $payment
	 * @return string
	 */
	protected function renderStatusModeCell( $payment ) {
		$paymentStatus = Utils::getPaymentStatus( $payment );

		$statusCssStyle = $this->determinePaymentStatusCss( $paymentStatus );
		$statusLabel = Admin::getPaymentStatusLabel( $paymentStatus );
		$modeLabel = Admin::getApiModeLabelFromInteger( $payment->livemode );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-tags'>
                <span class='wpfs-tag {$statusCssStyle}'>" . esc_html( $statusLabel ) . "</span>
                <span class='wpfs-tag wpfs-tag--outline'>" . esc_html( $modeLabel ) . "</span>
            </div>
        </td>
        ";

		return $html;
	}

	protected function renderActionsCell( $payment ) {
		$paymentStatus = Utils::getPaymentStatus( $payment );

		$html = "<td class='wpfs-data-table__td wpfs-data-table__td--right wpfs-data-table__td--actions'>";

		if ( Main::PAYMENT_STATUS_AUTHORIZED == $paymentStatus ) {
			$captureLabel =
				/* translators: 'Capture' action label for payments */
				__( 'Capture', 'wp-full-stripe-free' );

			$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-capture-payment' data-tooltip-content='authorize-tooltip'>
                <span class='wpfs-icon-card'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='authorize-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $captureLabel ) . "</div>
            </div>";
		}

		if ( Main::PAYMENT_STATUS_PAID === $paymentStatus ||
			Main::PAYMENT_STATUS_AUTHORIZED === $paymentStatus ) {
			$refundLabel =
				/* translators: 'Refund' action label for payments */
				__( 'Refund', 'wp-full-stripe-free' );

			$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-refund-payment' data-tooltip-content='refund-tooltip'>
                <span class='wpfs-icon-sync'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='refund-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $refundLabel ) . "</div>
            </div>";
		}

		$deleteLabel =
			/* translators: 'Delete' action label for payments */
			__( 'Delete payment', 'wp-full-stripe-free' );
		$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-payment' data-tooltip-content='delete-payment-tooltip'>
                <span class='wpfs-icon-trash'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='delete-payment-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $deleteLabel ) . "</div>
            </div>";

		$html .= '</td>';

		return $html;
	}

	protected function rowIds( $row ): array {
		return [ $row->paymentID, $row->eventID ];
	}

	protected function cellRenderers(): array {
		return [
			self::COLUMN_PAYMENT_METHOD => 'renderPaymentMethodCell',
			self::COLUMN_ID_DATE => 'renderIdDateCell',
			self::COLUMN_CUSTOMER_FORM => 'renderCustomerFormCell',
			self::COLUMN_AMOUNT => 'renderAmountCell',
			self::COLUMN_STATUS_MODE => 'renderStatusModeCell',
			self::COLUMN_ACTIONS => 'renderActionsCell',
		];
	}
}
