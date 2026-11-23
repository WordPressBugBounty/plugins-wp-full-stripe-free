<div class="wrap">
	<?php
use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Admin\Tables\DonationsTable;
use ThemeIsle\WPFullPay\Admin\Tables\OneTimePaymentsTable;
use ThemeIsle\WPFullPay\Admin\Tables\SavedCardsTable;
use ThemeIsle\WPFullPay\Admin\Tables\SubscriptionsTable;

	/** @var $tabId */
	/** @var $transactionCount */
	/** @var $formCount */

	$pageStyle = 'wpfs-page-one-time-payments';
	if ( $tabId === Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ) {
		$pageStyle = 'wpfs-page-subscriptions';
	} elseif ( $tabId === Menu::PARAM_VALUE_TAB_DONATIONS ) {
		$pageStyle = 'wpfs-page-donations';
	} elseif ( $tabId === Menu::PARAM_VALUE_TAB_SAVED_CARDS ) {
		$pageStyle = 'wpfs-page-saved-cards';
	}
	?>
    <div class="wpfs-page <?php echo $pageStyle; ?>">
		<?php include( 'partials/wpfs-header-with-tabs.php' ); ?>
		<?php include( 'partials/wpfs-announcement.php' ); ?>
		<?php include( 'partials/wpfs-transaction-volume-notice.php' ); ?>

		<?php
		if ( $transactionCount == 0 ) {
			include( 'partials/wpfs-transactions-empty.php' );
		} else {
			$pageUrl = add_query_arg(
				[
					'page' => Menu::SLUG_TRANSACTIONS,
					'tab'  => $tabId
				],
				admin_url( 'admin.php' )
			);

			if ( $tabId === Menu::PARAM_VALUE_TAB_PAYMENTS ) {
				$textFilter   = array_key_exists( Menu::PARAM_NAME_PAYMENTS_TEXT_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_PAYMENTS_TEXT_FILTER ] : null;
				$statusFilter = array_key_exists( Menu::PARAM_NAME_PAYMENTS_STATUS_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_PAYMENTS_STATUS_FILTER ] : Menu::PARAM_VALUE_PAYMENT_STATUS_ALL;
				$modeFilter   = array_key_exists( Menu::PARAM_NAME_PAYMENTS_MODE_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_PAYMENTS_MODE_FILTER ] : Menu::PARAM_VALUE_API_MODE_ALL;

				$paymentsTable = new OneTimePaymentsTable( $this->loggerService );
				$paymentsTable->prepare_items();

				include( 'partials/wpfs-transactions-payments.php' );
			} elseif ( $tabId === Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ) {
				$textFilter   = array_key_exists( Menu::PARAM_NAME_SUBSCRIPTIONS_TEXT_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_SUBSCRIPTIONS_TEXT_FILTER ] : null;
				$statusFilter = array_key_exists( Menu::PARAM_NAME_SUBSCRIPTIONS_STATUS_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_SUBSCRIPTIONS_STATUS_FILTER ] : Menu::PARAM_VALUE_SUBSCRIPTION_STATUS_ALL;
				$modeFilter   = array_key_exists( Menu::PARAM_NAME_SUBSCRIPTIONS_MODE_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_SUBSCRIPTIONS_MODE_FILTER ] : Menu::PARAM_VALUE_API_MODE_ALL;

				$subscriptionsTable = new SubscriptionsTable( $this->loggerService );
				$subscriptionsTable->prepare_items();

				include( 'partials/wpfs-transactions-subscriptions.php' );
			} elseif ( $tabId === Menu::PARAM_VALUE_TAB_DONATIONS ) {
				$textFilter = array_key_exists( Menu::PARAM_NAME_DONATIONS_TEXT_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_DONATIONS_TEXT_FILTER ] : null;
				$modeFilter = array_key_exists( Menu::PARAM_NAME_DONATIONS_MODE_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_DONATIONS_MODE_FILTER ] : Menu::PARAM_VALUE_API_MODE_ALL;

				$donationsTable = new DonationsTable( $this->loggerService );
				$donationsTable->prepare_items();

				include( 'partials/wpfs-transactions-donations.php' );
			} elseif ( $tabId === Menu::PARAM_VALUE_TAB_SAVED_CARDS ) {
				$textFilter = array_key_exists( Menu::PARAM_NAME_SAVED_CARDS_TEXT_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_SAVED_CARDS_TEXT_FILTER ] : null;
				$modeFilter = array_key_exists( Menu::PARAM_NAME_SAVED_CARDS_MODE_FILTER, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_SAVED_CARDS_MODE_FILTER ] : Menu::PARAM_VALUE_API_MODE_ALL;

				$savedCardsTable = new SavedCardsTable( $this->loggerService );
				$savedCardsTable->prepare_items();

				include( 'partials/wpfs-transactions-saved-cards.php' );
			}
		}
		?>
        <div id="wpfs-success-message-container"></div>
    </div>
    <div id="wpfs-dialog-container"></div>
    <script type="text/template" id="wpfs-modal-dialog-error">
        <div class="wpfs-dialog-scrollable">
            <div class="wpfs-inline-message wpfs-inline-message--error">
                <div class="wpfs-inline-message__inner">
                    <strong><%- errorMessage %></strong>
                </div>
            </div>
        </div>
        <div class="wpfs-dialog-content-actions">
            <a class="wpfs-btn wpfs-btn-text js-close-this-dialog"><?php esc_html_e( 'Close', 'wp-full-stripe-free' ); ?></a>
        </div>
    </script>
    <script type="text/template" id="wpfs-success-message">
        <div class="wpfs-floating-message__inner">
            <div class="wpfs-floating-message__message"><%- successMessage %></div>
            <button class="wpfs-btn wpfs-btn-icon js-hide-flash-message">
                <span class="wpfs-icon-close"></span>
            </button>
        </div>
    </script>
    <?php
    if ( $tabId === Menu::PARAM_VALUE_TAB_PAYMENTS ) {
        include 'partials/wpfs-transactions-payment-templates.php';
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ) {
        include 'partials/wpfs-transactions-subscription-templates.php';
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_DONATIONS ) {
        include 'partials/wpfs-transactions-donation-templates.php';
    } elseif ( $tabId === Menu::PARAM_VALUE_TAB_SAVED_CARDS ) {
        include 'partials/wpfs-transactions-saved-card-templates.php';
    }
    ?>
	<?php include( 'partials/wpfs-demo-mode.php' ); ?>
</div>
