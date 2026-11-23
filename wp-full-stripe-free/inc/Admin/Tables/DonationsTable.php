<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Utils;

class DonationsTable extends TransactionsTable {
	const COLUMN_PAYMENT_METHOD = 'paymentMethod';
	const COLUMN_ID_DATE = 'idDate';
	const COLUMN_DONOR_FORM = 'donorForm';
	const COLUMN_AMOUNT_FREQUENCY = 'amountFrequency';
	const COLUMN_STATUS_MODE = 'statusMode';
	const COLUMN_ACTIONS = 'actions';

	const HEADER_CLASSES = [
		self::COLUMN_PAYMENT_METHOD => 'wpfs-data-table__th--w40',
		self::COLUMN_AMOUNT_FREQUENCY => 'wpfs-data-table__th--right',
	];

	public function __construct( $loggerService ) {
		parent::__construct( $loggerService, [
			'singular' =>
				/* translators: Singular version of the word 'Donation', used on the donation list page */
				__( 'Donation', 'wp-full-stripe-free' ),
			'plural' =>
				/* translators: Plural version of the word 'Donation', used on the donation list page */
				__( 'Donations', 'wp-full-stripe-free' ),
			'ajax' => false
		] );
	}

	/**
	 * @return array
	 */
	protected function getForms(): array {
		return $this->db->getDonationFormNames();
	}

	/**
	 * @return array
	 */
	protected function getFilterParameters(): array {
		$searchText = $this->requestFilter( Menu::PARAM_NAME_DONATIONS_TEXT_FILTER );
		$mode = $this->requestFilter( Menu::PARAM_NAME_DONATIONS_MODE_FILTER );

		return [ $searchText, $mode ];
	}

	protected function getQuery() {
		$dbPrefix = $this->db->getDatabasePrefix();
		$whereStatement = null;

		$query = "
            SELECT
                donationID,
                stripeCustomerID,
                stripeSubscriptionID,
                stripePaymentIntentID,
                paid,
                captured,
                refunded,
                expired,
                lastChargeStatus,
                subscriptionStatus,
                currency,
                amount,
                donationFrequency,
                email,
                created,
                cancelled,
                livemode,
                formId,
                formName
            FROM
               {$dbPrefix}fullstripe_donations
        ";

		list( $searchText, $apiMode ) = $this->getFilterParameters();

		if ( isset( $searchText ) ) {
			$escapedSearchText = esc_sql( $searchText );

			$this->extendWhereStatement(
				$whereStatement,
				sprintf( "(LOWER(name) LIKE LOWER('%s') OR LOWER(email) LIKE LOWER('%s') OR LOWER(stripeSubscriptionID) like LOWER('%s') OR LOWER(stripePaymentIntentID) like LOWER('%s') OR stripeCustomerID LIKE LOWER('%s'))", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%" )
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
			self::COLUMN_DONOR_FORM => $this->createCompositeColumnLabel(
				/* translators: Name of the 'Donor' column */
				__( 'Donor', 'wp-full-stripe-free' ),
				/* translators: Name of the 'Form' column */
				__( 'Form', 'wp-full-stripe-free' )
			),
			self::COLUMN_AMOUNT_FREQUENCY => $this->createCompositeColumnLabel(
				/* translators: Name of the 'Amount' column */
				__( 'Amount', 'wp-full-stripe-free' ),
				/* translators: Name of the 'Frequency' column */
				__( 'Frequency', 'wp-full-stripe-free' )
			),
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
		esc_html_e( 'No donation found.', 'wp-full-stripe-free' );
	}

	/**
	 * @param $donation
	 *
	 * @return string
	 */
	protected function renderIdDateCell( $donation ): string {
		$dateLabel = Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $donation->created ) );

		$html = "
         <td class='wpfs-data-table__td'>
            <a class='wpfs-btn wpfs-btn-link js-open-donation-details'>" . esc_html( $donation->stripePaymentIntentID ) . "</a>
            <br>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $dateLabel ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $donation
	 *
	 * @return string
	 */
	protected function renderDonorFormCell( $donation ) {
		$formDisplayName = $this->lookupFormDisplayName( $donation->formName );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-typo-body wpfs-typo-body--gunmetal'>" . esc_html( $donation->email ) . "</div>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $formDisplayName ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $donationFrequency
	 *
	 * @return string
	 */
	protected function generateDonationFrequencyLabel( $donationFrequency ) {
		$label = "Unknown";
		switch ( $donationFrequency ) {
			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME:
				$label =
					/* translators: Donation frequency 'one-time' displayed in the donation list */
					__( "one-time", 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_DAILY:
				$label =
					/* translators: Donation frequency 'daily' displayed in the donation list */
					__( "daily", 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_WEEKLY:
				$label =
					/* translators: Donation frequency 'weekly' displayed in the donation list */
					__( "weekly", 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_MONTHLY:
				$label =
					/* translators: Donation frequency 'monthly' displayed in the donation list */
					__( "monthly", 'wp-full-stripe-free' );
				break;

			case DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ANNUAL:
				$label =
					/* translators: Donation frequency 'annual' displayed in the donation list */
					__( "annual", 'wp-full-stripe-free' );
				break;
		}

		return $label;
	}

	/**
	 * @param $donation
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function renderAmountFrequencyCell( $donation ): string {
		$amountLabel = Currencies::formatAndEscape( $this->staticContext, $donation->currency, $donation->amount );
		$frequencyLabel = $this->generateDonationFrequencyLabel( $donation->donationFrequency );

		$html = "
            <td class='wpfs-data-table__td wpfs-data-table__td--right'>
              <strong>" . esc_html( $amountLabel ) . "</strong>
              <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $frequencyLabel ) . "</div>
            </td>";

		return $html;
	}

	/**
	 * @param $donationStatus
	 *
	 * @return string
	 */
	protected function determineDonationStatusCss( $donationStatus ): string {
		$cssStyle = '';

		switch ( $donationStatus ) {
			case Main::DONATION_STATUS_PAID:
			case Main::DONATION_STATUS_RUNNING:
				$cssStyle = 'wpfs-tag--filled-green';
				break;

			case Main::DONATION_STATUS_REFUNDED:
			case Main::DONATION_STATUS_UNKNOWN:
			default:
				$cssStyle = 'wpfs-tag--filled-grey';
				break;
		}

		return $cssStyle;
	}

	/**
	 * @param $donation
	 * @return string
	 */
	protected function renderStatusModeCell( $donation ): string {
		$status = Utils::getDonationStatus( $donation );

		$statusLabel = Admin::getDonationStatusLabel( $status );
		$statusCssStyle = $this->determineDonationStatusCss( $status );
		$modeLabel = Admin::getApiModeLabelFromInteger( $donation->livemode );

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

	/**
	 * @param $donation
	 *
	 * @return string
	 */
	protected function renderActionsCell( $donation ): string {
		$status = Utils::getDonationPaymentStatus( $donation );

		$html = "<td class='wpfs-data-table__td wpfs-data-table__td--right wpfs-data-table__td--actions'>";

		if ( Main::PAYMENT_STATUS_PAID === $status ) {
			$refundLabel =
				/* translators: 'Refund' action label for donations */
				__( 'Refund', 'wp-full-stripe-free' );

			$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-refund-donation' data-tooltip-content='refund-donation-tooltip'>
                <span class='wpfs-icon-sync'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='refund-donation-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $refundLabel ) . "</div>
            </div>";

		}

		if ( \StripeWPFS\Stripe\Subscription::STATUS_ACTIVE === $donation->subscriptionStatus ) {
			$cancelLabel =
				/* translators: 'Cancel' action label for donations */
				__( 'Cancel', 'wp-full-stripe-free' );

			$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-cancel-donation' data-tooltip-content='cancel-donation-tooltip'>
                <span class='wpfs-icon-cancel'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='cancel-donation-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $cancelLabel ) . "</div>
            </div>";
		}

		$deleteLabel =
			/* translators: 'Delete' action label for donations */
			__( 'Delete donation', 'wp-full-stripe-free' );
		$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-donation' data-tooltip-content='delete-donation-tooltip'>
                <span class='wpfs-icon-trash'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='delete-donation-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $deleteLabel ) . "</div>
            </div>";

		$html .= '</td>';

		return $html;
	}

	protected function rowIds( $row ): array {
		return [ $row->donationID, $row->stripePaymentIntentID ];
	}

	protected function cellRenderers(): array {
		return [
			self::COLUMN_PAYMENT_METHOD => 'renderPaymentMethodCell',
			self::COLUMN_ID_DATE => 'renderIdDateCell',
			self::COLUMN_DONOR_FORM => 'renderDonorFormCell',
			self::COLUMN_AMOUNT_FREQUENCY => 'renderAmountFrequencyCell',
			self::COLUMN_STATUS_MODE => 'renderStatusModeCell',
			self::COLUMN_ACTIONS => 'renderActionsCell',
		];
	}
}
