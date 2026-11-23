<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

use ThemeIsle\WPFullPay\Admin\Admin;
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\Utils;

class SubscriptionsTable extends TransactionsTable {
	const COLUMN_PAYMENT_METHOD = 'paymentMethod';
	const COLUMN_ID_DATE = 'idDate';
	const COLUMN_SUBSCRIBER_FORM = 'subscriberForm';
	const COLUMN_AMOUNT_FREQUENCY = 'amountFrequency';
	const COLUMN_STATUS_MODE = 'statusMode';
	const COLUMN_ACTIONS = 'actions';

	const HEADER_CLASSES = [
		self::COLUMN_PAYMENT_METHOD => 'wpfs-data-table__th--w40',
		self::COLUMN_AMOUNT_FREQUENCY => 'wpfs-data-table__th--right',
	];

	/** @var Stripe|null $stripeDev */
	private $stripeDev = null;
	/** @var Stripe|null $stripeLive */
	private $stripeLive = null;

	/**
	 * @param LoggerService $loggerService
	 */
	public function __construct( $loggerService ) {
		parent::__construct( $loggerService, [
			'singular' =>
				/* translators: Singular version of the word 'subscription', used on the subscription list page */
				__( 'Subscription', 'wp-full-stripe-free' ),
			'plural' =>
				/* translators: Plural version of the word 'subscription', used on the subscription list page */
				__( 'Subscriptions', 'wp-full-stripe-free' ),
			'ajax' => false
		] );
	}

	/**
	 * @return array
	 */
	protected function getForms(): array {
		return $this->db->getSubscriptionFormNames();
	}

	/**
	 * @return array
	 */
	protected function getFilterParameters(): array {
		$searchText = $this->requestFilter( Menu::PARAM_NAME_SUBSCRIPTIONS_TEXT_FILTER );
		$status = $this->requestFilter( Menu::PARAM_NAME_SUBSCRIPTIONS_STATUS_FILTER );
		$mode = $this->requestFilter( Menu::PARAM_NAME_SUBSCRIPTIONS_MODE_FILTER );

		return [ $searchText, $status, $mode ];
	}

	protected function getQuery() {
		$dbPrefix = $this->db->getDatabasePrefix();
		$whereStatement = null;

		$query = "
    		SELECT
    		    subscriberID,
    		    stripeCustomerID,
    		    stripeSubscriptionID,
    		    chargeMaximumCount,
    		    chargeCurrentCount,
    		    status,
    		    email,
    		    planID,
    		    quantity,
    		    created,
    		    cancelled,
    		    livemode,
    		    formId,
    		    formName
    		FROM
    		    {$dbPrefix}fullstripe_subscribers
        ";

		list( $searchText, $subscriptionStatus, $apiMode ) = $this->getFilterParameters();

		if ( isset( $searchText ) ) {
			$escapedSearchText = esc_sql( $searchText );

			$this->extendWhereStatement(
				$whereStatement,
				sprintf( "(LOWER(name) LIKE LOWER('%s') OR LOWER(email) LIKE LOWER('%s') OR LOWER(stripeSubscriptionID) like LOWER('%s') OR stripeCustomerID LIKE LOWER('%s'))", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%", "%{$escapedSearchText}%" )
			);
		}

		if ( isset( $subscriptionStatus ) && $subscriptionStatus !== Menu::PARAM_VALUE_SUBSCRIPTION_STATUS_ALL ) {
			$condition = sprintf( "(status = '%s')", esc_sql( $subscriptionStatus ) );

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
			self::COLUMN_SUBSCRIBER_FORM => $this->createCompositeColumnLabel(
				/* translators: Name of the 'Subscriber' column */
				__( 'Subscriber', 'wp-full-stripe-free' ),
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
		esc_html_e( 'No subscriptions found.', 'wp-full-stripe-free' );
	}

	/**
	 * @param $subscription
	 *
	 * @return string
	 */
	protected function renderIdDateCell( $subscription ): string {
		$dateLabel = Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $subscription->created ) );

		$html = "
         <td class='wpfs-data-table__td'>
            <a class='wpfs-btn wpfs-btn-link js-open-subscription-details'>" . esc_html( $subscription->stripeSubscriptionID ) . "</a>
            <br>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $dateLabel ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $subscription
	 * @return string
	 */
	protected function renderSubscriberFormCell( $subscription ) {
		$formDisplayName = $this->lookupFormDisplayName( $subscription->formName );

		$html = "
        <td class='wpfs-data-table__td'>
            <div class='wpfs-typo-body wpfs-typo-body--gunmetal'>" . esc_html( $subscription->email ) . "</div>
            <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $formDisplayName ) . "</div>
        </td>";

		return $html;
	}

	/**
	 * @param $subscription
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function renderAmountFrequencyCell( $subscription ): string {
		$stripePlan = $this->retrievePlanByMode( $subscription->planID, $subscription->livemode );
		$frequencyLabel = '';
		$amountLabel = Admin::getSubscriptionAmountLabel( $this->staticContext, $subscription, $stripePlan );

		if ( ! is_null( $stripePlan ) ) {
			$frequencyLabel = Admin::getSubscriptionIntervalLabel( $stripePlan->recurring->interval, $stripePlan->recurring->interval_count );
		}

		$html = "
            <td class='wpfs-data-table__td wpfs-data-table__td--right'>
              <strong>" . esc_html( $amountLabel ) . "</strong>
              <div class='wpfs-typo-body wpfs-typo-body--sm'>" . esc_html( $frequencyLabel ) . "</div>
            </td>";

		return $html;
	}

	protected function determineSubscriptionStatusCss( $subscriptionStatus ) {
		$cssStyle = '';

		switch ( $subscriptionStatus ) {
			case Main::SUBSCRIBER_STATUS_RUNNING:
				$cssStyle = 'wpfs-tag--filled-green';
				break;

			case Main::SUBSCRIBER_STATUS_CANCELLED:
			case Main::SUBSCRIBER_STATUS_ENDED:
				$cssStyle = 'wpfs-tag--filled-blue';
				break;

			case Main::SUBSCRIBER_STATUS_INCOMPLETE:
			default:
				$cssStyle = 'wpfs-tag--filled-grey';
				break;
		}

		return $cssStyle;
	}

	/**
	 * @param $subscription
	 * @return string
	 */
	protected function renderStatusModeCell( $subscription ): string {
		$statusCssStyle = $this->determineSubscriptionStatusCss( $subscription->status );
		$modeLabel = Admin::getApiModeLabelFromInteger( $subscription->livemode );
		$statusLabel = Admin::getSubscriberStatusLabelByForm( $subscription );

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
	 * @param $subscription
	 *
	 * @return string
	 */
	protected function renderActionsCell( $subscription ): string {
		$html = "<td class='wpfs-data-table__td wpfs-data-table__td--right wpfs-data-table__td--actions'>";

		if ( Main::SUBSCRIBER_STATUS_RUNNING == $subscription->status ) {
			$cancelLabel =
				/* translators: 'Cancel' action label for subscriptions */
				__( 'Cancel', 'wp-full-stripe-free' );

			$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-cancel-subscription' data-tooltip-content='cancel-subscription-tooltip'>
                <span class='wpfs-icon-cancel'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='cancel-subscription-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $cancelLabel ) . "</div>
            </div>";
		}

		$deleteLabel =
			/* translators: 'Delete' action label for subscriptions */
			__( 'Delete subscription', 'wp-full-stripe-free' );
		$html .= "<a class='wpfs-btn wpfs-btn-icon wpfs-btn-icon--20 js-tooltip js-delete-subscription' data-tooltip-content='delete-subscription-tooltip'>
                <span class='wpfs-icon-trash'></span>
            </a>
            <div class='wpfs-tooltip-content' data-tooltip-id='delete-subscription-tooltip'>
                <div class='wpfs-info-tooltip'>" . esc_html( $deleteLabel ) . "</div>
            </div>";

		$html .= '</td>';

		return $html;
	}

	/**
	 * @param string $planId
	 * @param bool $liveMode
	 *
	 * @return \StripeWPFS\Stripe\Price|null
	 * @throws \Exception
	 */
	protected function retrievePlanByMode( $planId, $liveMode ) {
		$plan = null;

		if ( $liveMode ) {
			if ( is_null( $this->stripeLive ) ) {
				$this->stripeLive = new Stripe( Stripe::getStripeAuthenticationTokenByMode( $this->staticContext, $liveMode ), $this->loggerService );
			}
			$plan = $this->stripeLive->retrievePlan( $planId );
		} else {
			if ( is_null( $this->stripeDev ) ) {
				$this->stripeDev = new Stripe( Stripe::getStripeAuthenticationTokenByMode( $this->staticContext, $liveMode ), $this->loggerService );
			}
			$plan = $this->stripeDev->retrievePlan( $planId );
		}

		return $plan;
	}

	protected function rowIds( $row ): array {
		return [ $row->subscriberID, $row->stripeSubscriptionID ];
	}

	protected function cellRenderers(): array {
		return [
			self::COLUMN_PAYMENT_METHOD => 'renderPaymentMethodCell',
			self::COLUMN_ID_DATE => 'renderIdDateCell',
			self::COLUMN_SUBSCRIBER_FORM => 'renderSubscriberFormCell',
			self::COLUMN_AMOUNT_FREQUENCY => 'renderAmountFrequencyCell',
			self::COLUMN_STATUS_MODE => 'renderStatusModeCell',
			self::COLUMN_ACTIONS => 'renderActionsCell',
		];
	}
}
