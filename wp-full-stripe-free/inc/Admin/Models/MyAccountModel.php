<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\MyAccountValidator;
use ThemeIsle\WPFullPay\Admin\Views\CustomerPortalViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;

class MyAccountModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $showSubscriptions;
	protected $cancelSubscriptions;
	protected $whenCancelSubscriptions;
	protected $updowngradeSubscriptions;
	protected $showInvoices;
	protected $scrollingPaneIntoView;
	protected $useStripeCustomerPortal;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new MyAccountValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getShowSubscriptions() {
		return $this->showSubscriptions;
	}

	/**
	 * @return mixed
	 */
	public function getCancelSubscriptions() {
		return $this->cancelSubscriptions;
	}

	/**
	 * @return mixed
	 */
	public function getWhenCancelSubscriptions() {
		return $this->whenCancelSubscriptions;
	}

	/**
	 * @return mixed
	 */
	public function getUpdowngradeSubscriptions() {
		return $this->updowngradeSubscriptions;
	}

	/**
	 * @return mixed
	 */
	public function getShowInvoices() {
		return $this->showInvoices;
	}

	/**
	 * @return mixed
	 */
	public function getScrollingPaneIntoView() {
		return $this->scrollingPaneIntoView;
	}

	/**
	 * @return mixed
	 */
	public function useStripeCustomerPortal() {
		return $this->useStripeCustomerPortal;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->showSubscriptions = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_SUBSCRIPTIONS, 0 );
		$this->cancelSubscriptions = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_CANCEL_SUBSCRIPTIONS, 0 );
		$this->whenCancelSubscriptions = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_WHEN_CANCEL_SUBSCRIPTONS, Main::CANCEL_SUBSCRIPTION_IMMEDIATELY );
		$this->updowngradeSubscriptions = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_UPDOWNGRADE_SUBSCRIPTIONS, 0 );
		$this->showInvoices = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SHOW_INVOICES, 0 );
		$this->scrollingPaneIntoView = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_SCROLLING_PANE_INTO_VIEW, 0 );
		$this->useStripeCustomerPortal = $this->getSanitizedArrayParam( $postData, CustomerPortalViewConstants::FIELD_MY_ACCOUNT_USE_STRIPE_CUSTOMER_PORTAL, 0 );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'showSubscriptionsToCustomers' => $this->showSubscriptions,
			'letCustomersCancelSubscriptions' => $this->cancelSubscriptions,
			'whenToCancelSubscriptions' => $this->whenCancelSubscriptions,
			'letCustomersUpdowngradeSubscriptions' => $this->updowngradeSubscriptions,
			'showInvoicesToCustomers' => $this->showInvoices,
			'scrollingPaneIntoView' => $this->scrollingPaneIntoView,
			'useStripeCustomerPortal' => $this->useStripeCustomerPortal
		];

		return $data;
	}

	public function getPostData() {
		return [];
	}

	public function afterBind() {
		// noop
	}
}
