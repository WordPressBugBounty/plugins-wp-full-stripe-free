<?php

namespace ThemeIsle\WPFullPay\Admin;

use ThemeIsle\WPFullPay\Admin\FormTabCreators\FormTabCreatorFactory;
use ThemeIsle\WPFullPay\Admin\Localizers\AdminScriptLocalizerFactory;
use ThemeIsle\WPFullPay\Admin\Tables\DonationsTable;
use ThemeIsle\WPFullPay\Admin\Tables\OneTimePaymentsTable;
use ThemeIsle\WPFullPay\Admin\Tables\SavedCardsTable;
use ThemeIsle\WPFullPay\Admin\Tables\SubscriptionsTable;
use ThemeIsle\WPFullPay\Admin\Views\CreateFormView;
use ThemeIsle\WPFullPay\Admin\Views\CustomerPortalView;
use ThemeIsle\WPFullPay\Admin\Views\LogEmptyView;
use ThemeIsle\WPFullPay\Admin\Views\LogLevelView;
use ThemeIsle\WPFullPay\Admin\Views\SecurityView;
use ThemeIsle\WPFullPay\Admin\Views\WordpressDashboardView;
use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Forms\Fields\CustomFields;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\Help\HelpService;
use ThemeIsle\WPFullPay\License;
use ThemeIsle\WPFullPay\Locale\Countries;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\Locale\Languages;
use ThemeIsle\WPFullPay\Locale\States;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Logging\TrackingActivationEndpoint;
use ThemeIsle\WPFullPay\Macros\DonationMacroReplacer;
use ThemeIsle\WPFullPay\Macros\MacroHelperTools;
use ThemeIsle\WPFullPay\Macros\MyAccountLoginMacroReplacer;
use ThemeIsle\WPFullPay\Macros\OneTimePaymentMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SaveCardMacroReplacer;
use ThemeIsle\WPFullPay\Macros\SubscriptionMacroReplacer;
use ThemeIsle\WPFullPay\Mailer\Mailer;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\Pricing\Pricing;
use ThemeIsle\WPFullPay\Shortcode;
use ThemeIsle\WPFullPay\Stripe;
use ThemeIsle\WPFullPay\ThankYou\ThankYou;
use ThemeIsle\WPFullPay\Utils;
use ThemeIsle\WPFullPay\Webhooks\EventHandler;

/**
 * @phpstan-import-type FormTypeKey from FormTypes
 */
class Menu {
	use LoggerAddOn;
	use StaticContextAddOn;

	/** @var array<string,mixed> Connect accounts looked up for the account status, keyed by mode. */
	private $retrievedAccounts = [];

	/** @var Database */
	private $db = null;
	/** @var Stripe */
	private $stripe = null;
	/** @var HelpService */
	private $helpService;
	/** @var Options */
	private $options = null;

	private $capability = 'manage_options';

	const UPDATE_INTERVAL_4_HOURS = 14400;
	const UPDATE_INTERVAL_30_MINUTES = 1800;

	const SLUG_FORMS = 'wpfs-forms';
	const SLUG_TRANSACTIONS = 'wpfs-transactions';
	const SLUG_CREATE_FORM = 'wpfs-create-form';
	const SLUG_EDIT_FORM = 'wpfs-edit-form';
	const SLUG_REPORTS = 'wpfs-reports';
	const SLUG_SETTINGS = 'wpfs-settings';
	const SLUG_UPGRADE_PRO = 'wpfs-upgrade-pro';
	const SLUG_SETTINGS_STRIPE = 'wpfs-settings-stripe';
	const SLUG_SETTINGS_FORMS = 'wpfs-settings-forms';
	const SLUG_SETTINGS_CUSTOMER_PORTAL = 'wpfs-settings-customer-portal';
	const SLUG_SETTINGS_SECURITY = 'wpfs-settings-security';
	const SLUG_SETTINGS_EMAIL_NOTIFICATIONS = 'wpfs-settings-email';
	const SLUG_SETTINGS_WORDPRESS_DASHBOARD = 'wpfs-settings-wp-dashboard';
	const SLUG_SETTINGS_LOGS = 'wpfs-settings-logs';
	const SLUG_SETTINGS_LICENSE = 'wpfs-settings-license';
	const SLUG_ADDONS = 'wpfs-transactions-addons';

	const PARAM_NAME_FORM_TEXT_FILTER = 'wpfs-form-text-filter';
	const PARAM_NAME_FORM_MODE_FILTER = 'wpfs-form-mode-filter';
	const PARAM_NAME_PAYMENTS_TEXT_FILTER = 'wpfs-payments-text-filter';
	const PARAM_NAME_PAYMENTS_STATUS_FILTER = 'wpfs-payments-status-filter';
	const PARAM_NAME_PAYMENTS_MODE_FILTER = 'wpfs-payments-mode-filter';
	const PARAM_NAME_SUBSCRIPTIONS_TEXT_FILTER = 'wpfs-subscriptions-text-filter';
	const PARAM_NAME_SUBSCRIPTIONS_STATUS_FILTER = 'wpfs-subscriptions-status-filter';
	const PARAM_NAME_SUBSCRIPTIONS_MODE_FILTER = 'wpfs-subscriptions-mode-filter';
	const PARAM_NAME_DONATIONS_TEXT_FILTER = 'wpfs-donation-text-filter';
	const PARAM_NAME_DONATIONS_MODE_FILTER = 'wpfs-donation-mode-filter';
	const PARAM_NAME_SAVED_CARDS_TEXT_FILTER = 'wpfs-saved-cards-text-filter';
	const PARAM_NAME_SAVED_CARDS_MODE_FILTER = 'wpfs-saved-cards-mode-filter';
	const PARAM_NAME_TAB = 'tab';
	const PARAM_NAME_TYPE = 'type';
	const PARAM_NAME_ORDER_BY = 'orderby';
	const PARAM_NAME_ORDER = 'order';
	const PARAM_NAME_FORM = 'form';
	const PARAM_NAME_REPORTS_FILTER = 'wpfs-reports-filter-range';
	const PARAM_NAME_REPORTS_START_DATE = 'wpfs-reports-filter-start-date';
	const PARAM_NAME_REPORTS_END_DATE = 'wpfs-reports-filter-end-date';
	const PARAM_NAME_REPORTS_CURRENCY = 'wpfs-reports-filter-currency';

	const PARAM_VALUE_API_MODE_ALL = 'all';
	const PARAM_VALUE_API_MODE_TEST = 'test';
	const PARAM_VALUE_API_MODE_LIVE = 'live';
	const PARAM_VALUE_PAYMENT_STATUS_ALL = 'all';
	const PARAM_VALUE_SUBSCRIPTION_STATUS_ALL = 'all';
	const PARAM_VALUE_TAB_CONNECTION = 'connection';
	const PARAM_VALUE_TAB_WEBHOOK = 'webhook';
	const PARAM_VALUE_TAB_PAYMENTS = 'payments';
	const PARAM_VALUE_TAB_SUBSCRIPTIONS = 'subscriptions';
	const PARAM_VALUE_TAB_DONATIONS = 'donations';
	const PARAM_VALUE_TAB_SAVED_CARDS = 'saved-cards';
	const PARAM_VALUE_TAB_OPTIONS = 'options';
	const PARAM_VALUE_TAB_TEMPLATES = 'templates';
	const PARAM_VALUE_TAB_APPEARANCE = 'appearance';
	const PARAM_VALUE_TAB_GENERAL = 'general';
	const PARAM_VALUE_TAB_PAYMENT = 'payment';
	const PARAM_VALUE_TAB_PAYMENT_AND_TAX = 'payment-and-tax';
	const PARAM_VALUE_TAB_FORM_LAYOUT = 'form-layout';
	const PARAM_VALUE_TAB_NOTIFICATIONS_AND_INTEGRATION = 'notifications-and-integration';
	const PARAM_VALUE_RANGE_TODAY = 'today';
	const PARAM_VALUE_RANGE_YESTERDAY = 'yesterday';
	const PARAM_VALUE_RANGE_LAST_7_DAYS = 'last_7_days';
	const PARAM_VALUE_RANGE_LAST_30_DAYS = 'last_30_days';
	const PARAM_VALUE_RANGE_THIS_MONTH = 'this_month';
	const PARAM_VALUE_RANGE_LAST_MONTH = 'last_month';
	const PARAM_VALUE_RANGE_CUSTOM = 'custom';

	const COOKIE_NAME_TAB_ID = 'wpfsTabId';

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->db = new Database();
		$this->helpService = new HelpService( $this->loggerService );
		$this->options = new Options();
		$this->initStaticContext();

		$this->stripe = new Stripe( Stripe::getStripeAuthenticationToken( $this->staticContext ), $this->loggerService );

		$this->initStaticContext();
		$this->initActionHooks();
		$this->initDemoMode();

		add_filter( 'wpfs_admin_nav_bar_data', [ $this, 'get_nav_bar_data' ] );
	}

	private function initActionHooks() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_menu', [ $this, 'menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_global_css' ] );
	}

	private function initDemoMode() {
		if ( Utils::isDemoMode() ) {
			$this->capability = 'read';
		}
	}

	/**
	 * @param \StdClass $form
	 * @return array
	 */
	protected function prepareOnetimeProducts( $form ) {
		$savedProducts = Utils::decodeJsonArray( $form->decoratedProducts );
		$priceIds = Pricing::extractPriceIdsFromProductsStatic( $savedProducts );

		$products = [];
		$productsLookup = [];

		$stripePrices = [];
		try {
			$stripePrices = $this->stripe->getOnetimePrices();
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot retrieve Stripe one-time prices', $ex );
		}
		foreach ( $stripePrices as $stripePrice ) {
			if ( array_search( $stripePrice->id, $priceIds ) !== false ) {
				$productsLookup[ $stripePrice->id ] = [
					'name' => $stripePrice->product->name,
					'price' => $stripePrice->unit_amount,
					'currency' => $stripePrice->currency,
					'stripePriceId' => $stripePrice->id
				];
			}
		}

		foreach ( $priceIds as $priceId ) {
			if ( array_key_exists( $priceId, $productsLookup ) ) {
				array_push( $products, $productsLookup[ $priceId ] );
			}
		}

		return $products;
	}

	/**
	 * @param \StdClass $form
	 * @return array
	 */
	protected function prepareTaxRates( $form ) {
		$savedTaxRates = [];
		if ( ! is_null( $form->vatRates ) ) {
			$savedTaxRates = json_decode( $form->vatRates );
		}
		$taxRateIds = Pricing::extractTaxRateIdsStatic( $savedTaxRates );

		$taxRates = [];
		$taxRatesLookup = [];

		$stripeTaxRates = [];
		try {
			$stripeTaxRates = $this->stripe->getTaxRates();
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot retrieve Stripe tax rates', $ex );
		}
		foreach ( $stripeTaxRates as $stripeTaxRate ) {
			if ( array_search( $stripeTaxRate->id, $taxRateIds ) !== false ) {
				$taxRatesLookup[ $stripeTaxRate->id ] = [
					'displayName' => $stripeTaxRate->display_name,
					'inclusive' => $stripeTaxRate->inclusive,
					'country' => $stripeTaxRate->country,
					'countryLabel' => Countries::getCountryLabel( $stripeTaxRate->country ),
					'state' => $stripeTaxRate->state,
					'stateLabel' => States::getStateNameFor( $stripeTaxRate->state ),
					'percentage' => $stripeTaxRate->percentage,
					'jurisdiction' => $stripeTaxRate->jurisdiction,
					'taxRateId' => $stripeTaxRate->id
				];
			}
		}

		foreach ( $taxRateIds as $taxRateId ) {
			if ( array_key_exists( $taxRateId, $taxRatesLookup ) ) {
				array_push( $taxRates, $taxRatesLookup[ $taxRateId ] );
			}
		}

		return $taxRates;
	}

	/**
	 * @param $classes
	 *
	 * @return string
	 */
	function wpfs_admin_body_class( $classes ) {
		return "$classes wpfs-body";
	}

	function admin_init() {
		wp_register_style( 'fullstripe-admin-revamped-css', Assets::css( 'wpfs-admin.css' ), [], Main::VERSION );
		wp_register_style( 'fullstripe-admin-revamped-css-extra', Assets::css( 'wpfs-admin-extra.css' ), [], Main::VERSION );
		wp_register_style( 'fullstripe-slick-css', Assets::css( 'slick.css' ), [], Main::VERSION );

		add_filter( 'admin_body_class', [ $this, 'wpfs_admin_body_class' ] );
	}

	function menu_pages() {

		$page_title =
			/* translators: Browser page title of the "Transactions" page in WordPress admin */
			__( 'Full Pay - Transactions', 'wp-full-stripe-free' );
		$menu_title =
			/* translators: Menu title of the plugin in WordPress admin */
			esc_html__( 'Full Pay', 'wp-full-stripe-free' );
		$menu_slug = self::SLUG_TRANSACTIONS;
		$capability = $this->capability;
		$function = [ $this, 'fullstripe_transactions' ];
		$menu_icon = Assets::images( 'wpfs-admin-icon.svg' );
		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $menu_icon );

		// Add submenu page with same slug as parent to ensure no duplicates
		$this->addSubmenuPage( $menu_slug, $menu_slug, 'fullstripe_transactions', $page_title,
			/* translators: Submenu title of the "Transactions" page in WordPress admin */
			esc_html__( 'Transactions', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( $menu_slug, self::SLUG_FORMS, 'fullstripe_forms',
			/* translators: Browser page title of the "Manage forms" page in WordPress admin */
			__( 'Full Pay - Manage forms', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Manage forms" page in WordPress admin */
			esc_html__( 'Payment Forms', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_FORMS, self::SLUG_CREATE_FORM, 'fullstripe_create_form',
			/* translators: Browser page title of the "Create form" page in WordPress admin */
			__( 'Full Pay - Create form', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Create form" page in WordPress admin */
			esc_html__( 'Create form', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( $menu_slug, self::SLUG_REPORTS, 'fullstripe_reports',
			/* translators: Browser page title of "Settings" page in WordPress admin */
			__( 'Full Pay - Reports', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Settings" page in WordPress admin */
			esc_html__( 'Reports', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( $menu_slug, self::SLUG_SETTINGS, 'fullstripe_settings',
			/* translators: Browser page title of "Settings" page in WordPress admin */
			__( 'Full Pay - Settings', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Settings" page in WordPress admin */
			esc_html__( 'Settings', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_STRIPE, 'fullstripe_settings_stripe',
			/* translators: Browser page title of the "Configure Stripe account" page in WordPress admin */
			__( 'Full Pay - Configure Stripe account', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Configure Stripe account" page in WordPress admin */
			esc_html__( 'Stripe account', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_FORMS, 'fullstripe_settings_forms',
			/* translators: Browser page title of the "Customize forms" page in WordPress admin */
			__( 'Full Pay - Customize forms', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Customize forms" page in WordPress admin */
			esc_html__( 'Forms', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_EMAIL_NOTIFICATIONS, 'fullstripe_settings_email',
			/* translators: Browser page title of the "Email notifications" page in WordPress admin */
			__( 'Full Pay - Email notifications', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Email notifications" page in WordPress admin */
			esc_html__( 'Email notifications', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_SECURITY, 'fullstripe_settings_security',
			/* translators: Browser page title of the "Security settings" page in WordPress admin */
			__( 'Full Pay - Security settings', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Security settings" page in WordPress admin */
			esc_html__( 'Security', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_CUSTOMER_PORTAL, 'fullstripe_settings_my_account',
			/* translators: Browser page title of the "Customer portal" page in WordPress admin */
			__( 'Full Pay - Customer portal', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Customer portal" page in WordPress admin */
			esc_html__( 'Customer portal', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_WORDPRESS_DASHBOARD, 'fullstripe_settings_wp_dashboard',
			/* translators: Browser page title of the "WordPress dashboard settings" page in WordPress admin */
			__( 'Full Pay - WordPress dashboard settings', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "WordPress dashboard settings" page in WordPress admin */
			esc_html__( 'WordPress dashboard', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_LOGS, 'fullstripe_settings_logs',
			/* translators: Browser page title of the "Error logging" settings page in WordPress admin */
			__( 'Full Pay - Error logging', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Error logging" settings page in WordPress admin */
			esc_html__( 'Error logging', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_SETTINGS, self::SLUG_SETTINGS_LICENSE, 'fullstripe_settings_license',
			/* translators: Browser page title of the "License" settings page in WordPress admin */
			__( 'Full Pay - License', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "License" settings page in WordPress admin */
			esc_html__( 'License', 'wp-full-stripe-free' ) );

		$this->addSubmenuPage( self::SLUG_FORMS, self::SLUG_EDIT_FORM, 'fullstripe_edit_form',
			/* translators: Browser page title of the "Edit form" page in WordPress admin */
			__( 'Full Pay - Edit form', 'wp-full-stripe-free' ),
			/* translators: Submenu title of the "Edit form" page in WordPress admin */
			esc_html__( 'Edit form', 'wp-full-stripe-free' ) );

		do_action( 'fullstripe_admin_menus', $menu_slug );
	}

	/**
	 * Registers one of the plugin's admin pages, and loads the admin scripts on it alone.
	 *
	 * @param string $callback The Menu method that renders the page.
	 */
	private function addSubmenuPage( string $parentSlug, string $slug, string $callback, string $pageTitle, string $menuTitle ): void {
		$hook = add_submenu_page( $parentSlug, $pageTitle, $menuTitle, $this->capability, $slug, [ $this, $callback ] );
		add_action( 'admin_print_scripts-' . $hook, [ $this, 'fullstripe_admin_scripts' ] );
	}

	/**
	 * Stops a page from rendering for a user without the plugin's capability.
	 */
	private function requireCapability(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-full-stripe-free' ) );
		}
	}

	/**
	 * @return array{0: string, 1: string} The settings pages' back link: its URL and label.
	 */
	private function backToSettingsLink(): array {
		return [
			Menu::getAdminUrlBySlug( self::SLUG_SETTINGS ),
			/* translators: Link label which takes back to the "Settings" page  */
			__( 'Back to Settings', 'wp-full-stripe-free' ),
		];
	}

	/**
	 * @return array{0: string, 1: string} The form pages' back link: its URL and label.
	 */
	private function backToFormsLink(): array {
		return [
			Menu::getAdminUrlBySlug( self::SLUG_FORMS ),
			/* translators: Link label which takes back to the "Manage forms" page  */
			__( 'Back to Manage forms', 'wp-full-stripe-free' ),
		];
	}

	function compileFrontendAdminOptions() {
		$options = $this->options->getSeveral( [ 
			Options::OPTION_DECIMAL_SEPARATOR_SYMBOL,
			Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE,
			Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION,
			Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT
		] );

		// todo: refactor magic strings
		$result = [
			'customInputFieldCount' => Main::getCustomFieldMaxCount( $this->staticContext ),
			'currencyDecimalSeparatorSymbol' => $options[ Options::OPTION_DECIMAL_SEPARATOR_SYMBOL ],
			'currencyShowSymbolInsteadOfCode' => $options[ Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE ],
			'currencyShowIdentifierOnLeft' => $options[ Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION ],
			'currencyPutSpaceBetweenCurrencyAndAmount' => $options[ Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT ]
		];

		return $result;
	}

	/**
	 * @return \stdClass
	 */
	public static function createMacroData() {
		$data = new \StdClass;

		$data->templateIds = apply_filters( 'fullstripe_email_template_ids', [
			Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT,
			Main::EMAIL_TEMPLATE_ID_CARD_SAVED,
			Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT,
			Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED,
			Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT,
			Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE
		] );
		$data->macroLists = apply_filters( 'fullstripe_email_template_macros', [
			Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT => OneTimePaymentMacroReplacer::getMacroKeys(),
			Main::EMAIL_TEMPLATE_ID_CARD_SAVED => SaveCardMacroReplacer::getMacroKeys(),
			Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT => SubscriptionMacroReplacer::getMacroKeys(),
			Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED => SubscriptionMacroReplacer::getMacroKeys(),
			Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT => DonationMacroReplacer::getMacroKeys(),
			Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE => MyAccountLoginMacroReplacer::getMacroKeys()
		] );
		$data->descriptions = apply_filters(
			'fullstripe_macro_descriptions',
			MacroHelperTools::getMacroDescriptions()
		);

		return $data;
	}

	function enqueueGlobalScripts() {
		wp_enqueue_script( 'slick-js', Assets::scripts( 'slick.min.js' ), [], Main::VERSION );
		wp_enqueue_script( 'wp-full-stripe-utils-js', Assets::scripts( 'wpfs-utils.js' ), [ 'wp-i18n' ], Main::VERSION );
	}

	function enqueue_global_css( $hook ) {
		if ( strpos( $hook, 'wpfs-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'fullstripe-admin-revamped-css' );
		wp_enqueue_style( 'fullstripe-admin-revamped-css-extra' );
		wp_enqueue_style( 'fullstripe-slick-css' );
	}

	/**
	 * Load the survey script.
	 *
	 * @return string|null The resolved script handler, or null if unavailable.
	 */
	function loadSurvey() {
		$survey_handler = apply_filters( 'themeisle_sdk_dependency_script_handler', 'survey' );

		if ( empty( $survey_handler ) ) {
			return null;
		}

		return $survey_handler;
	}

	/**
	 * Generate a unique site ID based on the site URL.
	 *
	 * @param string $url The site URL.
	 *
	 * @return string The generated site ID.
	 */
	function generateSiteID( $url ) {
		// Convert international domain names to ASCII
		$url = idn_to_ascii( $url, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) ?: $url;

		// Remove protocol (http, https)
		$url = preg_replace( '/https?:\/\//', '', $url );

		// Extract host and path while preserving non-www subdomains
		$parts = parse_url( $url );

		$host = $parts['host'] ?? '';
		$path = $parts['path'] ?? '';

		// Remove 'www.' only if present at the beginning of the domain
		$host = preg_replace( '/^www\./', '', $host );

		// Concatenate host and path
		$cleanedUrl = $host . $path;

		// Remove non-alphanumeric characters, but keep forward slashes for paths
		$cleanedUrl = preg_replace( '/[^a-z0-9\/]/i', '', $cleanedUrl );

		// Convert to lowercase
		$cleanedUrl = strtolower( $cleanedUrl );

		// Shorten using CRC32 and encode to base36 for a shorter alphanumeric ID
		$hash = base_convert( (string) crc32( $cleanedUrl ), 10, 36 );

		return $hash;
	}

	function fullstripe_admin_scripts() {
		$page = $_GET['page'];
		$tab = array_key_exists( 'tab', $_GET ) ? $_GET['tab'] : null;

		$survey_handler = $this->loadSurvey();

		wp_enqueue_media();
		$this->enqueueGlobalScripts();

		$localizer = AdminScriptLocalizerFactory::createLocalizer( $this->options, $page, $tab );
		$localizer->enqueueScripts();

		$dependencies = [
			'wp-i18n',
			'wp-full-stripe-utils-js',
			'underscore',
			'backbone',
			'jquery',
			'jquery-ui-selectmenu',
			'jquery-ui-dialog',
			'jquery-ui-tabs',
			'jquery-ui-core',
			'jquery-ui-widget',
			'jquery-ui-autocomplete',
			'jquery-ui-button',
			'jquery-ui-tooltip',
			'jquery-ui-sortable'
		];

		if ( $survey_handler ) {
			array_push( $dependencies, $survey_handler );
		}

		wp_enqueue_script( 'wp-full-stripe-admin-library-js', Assets::scripts( 'wpfs-admin-library.js' ), $dependencies, Main::VERSION );
		wp_set_script_translations( 'wp-full-stripe-admin-library-js', 'wp-full-stripe-free' );

		$dependencies = [
			'wp-i18n',
			'wp-full-stripe-admin-library-js',
		];
		$dependencies = array_merge( $localizer->getScriptDependencies(), $dependencies );
		wp_enqueue_script( 'wp-full-stripe-admin-js', Assets::scripts( 'wpfs-admin.js' ), $dependencies, Main::VERSION );
		wp_set_script_translations( 'wp-full-stripe-admin-js', 'wp-full-stripe-free' );

		$localizer->localizeScripts();
		
		$site_id      = $this->generateSiteID( get_site_url() );
		$stripe_mode  = $this->options->get( Options::OPTION_API_MODE );

		$wpfsAdminSettings = [
			'rootUrl'            => get_site_url(),
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'wp-full-stripe-admin-nonce' ),
			'preferences'        => $this->compileFrontendAdminOptions(),
			'site_id'            => $site_id,
			'has_pro'            => License::is_active() ? 'yes' : 'no',
			'plan'               => License::get_type(),
			'mode'               => $stripe_mode,
			'connected'          => ($stripe_mode === Main::STRIPE_API_MODE_TEST) ? $this->options->get( Options::OPTION_TEST_ACCOUNT_ID ) !== null : $this->options->get( Options::OPTION_LIVE_ACCOUNT_ID ) !== null,
			'transactions'       => $this->getTransactionCount(),
			'connectionPage'     => Menu::getAdminUrlBySlug( self::SLUG_SETTINGS_STRIPE ),
			'trackingEndpoint'   => TrackingActivationEndpoint::get_url(),
		];

		$wpfsAdminSettings = array_merge( $wpfsAdminSettings, $localizer->getSettingsOptions() );
		wp_localize_script( 'wp-full-stripe-admin-js', 'wpfsAdminSettings', $wpfsAdminSettings );

		if ( $survey_handler ) {
			add_filter( 'themeisle-sdk/survey/' . WP_FULL_STRIPE_PRODUCT_SLUG, function( $data, $page_slug ) use( $wpfsAdminSettings ) {
				return $this->get_survey_metadata( $wpfsAdminSettings );
			}, 10, 2);
		}

		$screen = get_current_screen();

		if ( $screen->base === 'full-pay_page_wpfs-reports' ) {
			wp_enqueue_script( 'charts-js', Assets::scripts( 'chartsjs.min.js' ), [], Main::VERSION );
		}
	
		$page_slug = 'transactions';
		if ( 'full-pay_page_wpfs-forms' === $screen->base ) {
			$page_slug = 'forms';
		} elseif ( 'full-pay_page_wpfs-settings' === $screen->base ) {
			$page_slug = 'settings';
		}
		
		do_action( 'themeisle_internal_page', WP_FULL_STRIPE_PRODUCT_SLUG, $page_slug );
		do_action( 'fullstripe_admin_scripts' );
	}

	/**
	 * Get the number of transactions.
	 * 
	 * @return int The number of transactions.
	 */
	private function getTransactionCount() {
		$cache_key = 'wpfs_transaction_count';
		$transaction_count = get_transient( $cache_key );

		if ( false === $transaction_count ) {
			$transaction_count = ( $this->db->getNumberOfOneTimePayments() )->paymentCount + ( $this->db->getNumberOfSubscriptions() )->subscriptionCount + ( $this->db->getNumberOfDonations() )->donationCount;
			set_transient( $cache_key, $transaction_count, DAY_IN_SECONDS );
		}

		return $transaction_count;
	}

	/**
	 * @param $currentTime int
	 * @param $time int
	 *
	 * @return string
	 */
	protected function getFormattedTimeDescription( $currentTime, $time ) {
		$timeDescription = '';

		if ( $currentTime - $time < MINUTE_IN_SECONDS ) {
			$seconds = $currentTime - $time;
			if ( $seconds === 1 ) {
				return __( 'Last event one second ago', 'wp-full-stripe-free' );
			} else {
				$timeDescription = sprintf(
				/* translators: Singular and plural message for time number of seconds passed since the last webhook event
																						   p1: number of seconds
																						*/
					_n( 'Last event %d second ago', 'Last event %d seconds ago', $seconds, 'wp-full-stripe-free' ),
					$seconds
				);
			}
		} elseif ( $currentTime - $time < HOUR_IN_SECONDS ) {
			$minutes = (int) ( ( $currentTime - $time ) / MINUTE_IN_SECONDS );
			if ( $minutes === 1 ) {
				return __( 'Last event one minute ago', 'wp-full-stripe-free' );
			} else {
				$timeDescription = sprintf(
				/* translators: Singular and plural message for time number of minutes passed since the last webhook event
																						   p1: number of minutes
																						*/
					_n( 'Last event %d minute ago', 'Last event %d minutes ago', $minutes, 'wp-full-stripe-free' ),
					$minutes
				);
			}
		} elseif ( $currentTime - $time < DAY_IN_SECONDS ) {
			$hours = (int) ( ( $currentTime - $time ) / HOUR_IN_SECONDS );
			if ( $hours === 1 ) {
				return __( 'Last event one hour ago', 'wp-full-stripe-free' );
			} else {
				$timeDescription = sprintf(
				/* translators: Singular and plural message for time number of hours passed since the last webhook event
																						   p1: number of hours
																						*/
					_n( 'Last event %d hour ago', 'Last event %d hours ago', $hours, 'wp-full-stripe-free' ),
					$hours
				);
			}
		} elseif ( $currentTime - $time >= DAY_IN_SECONDS ) {
			$days = (int) ( ( $currentTime - $time ) / DAY_IN_SECONDS );
			if ( $days === 1 ) {
				return __( 'Last event one day ago', 'wp-full-stripe-free' );
			} else {
				$timeDescription = sprintf(
				/* translators: Singular and plural message for time number of days passed since the last webhook event
																						   p1: number of days
																						*/
					_n( 'Last event %d day ago', 'Last event %d days ago', $days, 'wp-full-stripe-free' ),
					$days
				);
			}
		}

		return $timeDescription;
	}

	/**
	 * @param $currentTime int
	 * @param $time int
	 * @param $isLiveMode boolean
	 *
	 * @return array
	 */
	protected function getLastWebHookEventDescription( $currentTime, $time, $isLiveMode ) {
		$cssStyle = '';
		$title = '';
		$description = '';

		if ( is_null( $time ) ) {
			$cssStyle = 'wpfs-status-bullet--grey';
			$description =
				/* translators: Message stating that this webhook URL haven't received any event yet */
				__( 'Never received event', 'wp-full-stripe-free' );
			if ( $isLiveMode ) {
				$title =
					/* translators: Message stating that live webhooks notifications is not set up in Stripe */
					__( 'Live webhook is not set up', 'wp-full-stripe-free' );
			} else {
				$title =
					/* translators: Message stating that test webhooks notifications is not set up in Stripe */
					__( 'Test webhook is not set up', 'wp-full-stripe-free' );
			}
		} elseif ( $currentTime - $time < WEEK_IN_SECONDS ) {
			$cssStyle = 'wpfs-status-bullet--green';
			$description = $this->getFormattedTimeDescription( $currentTime, $time );
			if ( $isLiveMode ) {
				$title =
					/* translators: Message stating that the live webhooks URL works properly, it's receiving events from Stripe */
					__( 'Live webhook works properly', 'wp-full-stripe-free' );
			} else {
				$title =
					/* translators: Message stating that the test webhooks URL works properly, it's receiving events from Stripe */
					__( 'Test webhook works properly', 'wp-full-stripe-free' );
			}
		} elseif ( $currentTime - $time >= WEEK_IN_SECONDS ) {
			$cssStyle = 'wpfs-status-bullet--red';
			$description = $this->getFormattedTimeDescription( $currentTime, $time );
			if ( $isLiveMode ) {
				/* translators: Message stating that the live webhooks URL may not work properly */
				$title = __( 'Live webhook may not work properly', 'wp-full-stripe-free' );
			} else {
				/* translators: Message stating that the test webhooks URL may not work properly */
				$title = __( 'Test webhook may not work properly', 'wp-full-stripe-free' );
			}
		}

		return [ $cssStyle, $title, $description ];
	}

	protected function getStripeAccountData() {
		$stripeData = new \stdClass;
		$currentTime = time();

		if ( Utils::isDemoMode() ) {
			$stripeData->testSecretKey =
				/* translators: Placeholder text for an API test secret key which is not entered yet */
				__( 'Test secret key here', 'wp-full-stripe-free' );
			$stripeData->testPublishableKey =
				/* translators: Placeholder text for an API test publishable key which is not entered yet */
				__( 'Test publishable key here', 'wp-full-stripe-free' );
			$stripeData->liveSecretKey =
				/* translators: Placeholder text for an API live secret key which is not entered yet */
				__( 'Live secret key here', 'wp-full-stripe-free' );
			$stripeData->livePublishableKey =
				/* translators: Placeholder text for an API live publishable key which is not entered yet */
				__( 'Live publishable key here', 'wp-full-stripe-free' );
			$stripeData->apiMode = Main::STRIPE_API_MODE_TEST;
			$stripeData->testAccountId = Main::TEST_ACCOUNT_ID;
			$stripeData->liveAccountId = Main::LIVE_ACCOUNT_ID;
			$stripeData->useWpTestPlatform = Main::USE_WP_TEST_PLATFORM;
			$stripeData->useWpLivePlatform = Main::USE_WP_LIVE_PLATFORM;
			$stripeData->webHookUrl = esc_attr( EventHandler::getDemoWebhookURL() );
			$stripeData->webHookUrlLegacy = esc_attr( EventHandler::getDemoWebhookURL() );

			list( $liveStyle, $liveTitle, $liveDescription ) = $this->getLastWebHookEventDescription( $currentTime, null, true );
			$stripeData->liveEventStyle = $liveStyle;
			$stripeData->liveEventTitle = $liveTitle;
			$stripeData->liveEventDescription = $liveDescription;

			list( $testStyle, $testTitle, $testDescription ) = $this->getLastWebHookEventDescription( $currentTime, $currentTime - MINUTE_IN_SECONDS * 5, false );
			$stripeData->testEventStyle = $testStyle;
			$stripeData->testEventTitle = $testTitle;
			$stripeData->testEventDescription = $testDescription;
		} else {
			$options = $this->options->getSeveral( [ 
				Options::OPTION_API_TEST_SECRET_KEY,
				Options::OPTION_API_TEST_PUBLISHABLE_KEY,
				Options::OPTION_API_LIVE_SECRET_KEY,
				Options::OPTION_API_LIVE_PUBLISHABLE_KEY,
				Options::OPTION_API_MODE,
				Options::OPTION_TEST_ACCOUNT_ID,
				Options::OPTION_LIVE_ACCOUNT_ID,
				Options::OPTION_TEST_ACCOUNT_STATUS,
				Options::OPTION_LIVE_ACCOUNT_STATUS,
				Options::OPTION_USE_WP_TEST_PLATFORM,
				Options::OPTION_USE_WP_LIVE_PLATFORM,
				Options::OPTION_LAST_WEBHOOK_EVENT_LIVE,
				Options::OPTION_LAST_WEBHOOK_EVENT_TEST
			] );

			$stripeData->testSecretKey = $options[ Options::OPTION_API_TEST_SECRET_KEY ];
			$stripeData->testPublishableKey = $options[ Options::OPTION_API_TEST_PUBLISHABLE_KEY ];
			$stripeData->liveSecretKey = $options[ Options::OPTION_API_LIVE_SECRET_KEY ];
			$stripeData->livePublishableKey = $options[ Options::OPTION_API_LIVE_PUBLISHABLE_KEY ];
			$stripeData->apiMode = $options[ Options::OPTION_API_MODE ];
			if ( isset( $options[ Options::OPTION_TEST_ACCOUNT_ID ] ) ) {
				$stripeData->testAccountId = $options[ Options::OPTION_TEST_ACCOUNT_ID ];
			}
			if ( isset( $options[ Options::OPTION_LIVE_ACCOUNT_ID ] ) ) {
				$stripeData->liveAccountId = $options[ Options::OPTION_LIVE_ACCOUNT_ID ];
			}
			if ( isset( $options[ Options::OPTION_TEST_ACCOUNT_STATUS ] ) ) {
				$stripeData->testAccountStatus = $options[ Options::OPTION_TEST_ACCOUNT_STATUS ];
			}
			if ( isset( $options[ Options::OPTION_LIVE_ACCOUNT_STATUS ] ) ) {
				$stripeData->liveAccountStatus = $options[ Options::OPTION_LIVE_ACCOUNT_STATUS ];
			}
			$stripeData->useWpTestPlatform = isset($options[ Options::OPTION_USE_WP_TEST_PLATFORM ]) ? $options[ Options::OPTION_USE_WP_TEST_PLATFORM ] : null;
			$stripeData->useWpLivePlatform = isset($options[ Options::OPTION_USE_WP_LIVE_PLATFORM ]) ? $options[ Options::OPTION_USE_WP_LIVE_PLATFORM ] : null;
			$stripeData->testAccessRevocable = $this->isAccessRevocable( 'test' );
			$stripeData->liveAccessRevocable = $this->isAccessRevocable( 'live' );
			if ( isset( $stripeData->testAccountId ) && $stripeData->testAccountId !== '' ) {
				try {
					$stripeData->testAccountLink = $this->stripe->getTestAccountLink( $options[ Options::OPTION_TEST_ACCOUNT_ID ], home_url( add_query_arg( null, null ) ), home_url( add_query_arg( null, null ) ) );
				} catch ( \Exception $ex ) {
					$this->logger->error( __FUNCTION__, 'Cannot retrieve test account link', $ex );
					$stripeData->testAccountLink = null;
					// A failed request says nothing reliable about the account, so the connection stays.
					$stripeData->testAccountUnavailable = true;
				}
			}
			if ( isset( $stripeData->liveAccountId ) && $stripeData->liveAccountId !== '' ) {
				try {
					$stripeData->liveAccountLink = $this->stripe->getLiveAccountLink( $options[ Options::OPTION_LIVE_ACCOUNT_ID ], home_url( add_query_arg( null, null ) ), home_url( add_query_arg( null, null ) ) );
				} catch ( \Exception $ex ) {
					$this->logger->error( __FUNCTION__, 'Cannot retrieve live account link', $ex );
					$stripeData->liveAccountLink = null;
					// A failed request says nothing reliable about the account, so the connection stays.
					$stripeData->liveAccountUnavailable = true;
				}
			}

			$stripeData->webHookUrl = esc_attr( EventHandler::getWebhookEndpointURL( $this->staticContext ) );
			$stripeData->webHookUrlLegacy = esc_attr( EventHandler::getLegacyWebhookEndpointURL( $this->staticContext ) );

			if ( isset( $options[ Options::OPTION_LAST_WEBHOOK_EVENT_LIVE ] ) ) {
				list( $liveStyle, $liveTitle, $liveDescription ) = $this->getLastWebHookEventDescription( $currentTime, $options[ Options::OPTION_LAST_WEBHOOK_EVENT_LIVE ], true );
				$stripeData->liveEventStyle = $liveStyle;
				$stripeData->liveEventTitle = $liveTitle;
				$stripeData->liveEventDescription = $liveDescription;
			}
			if ( isset( $options[ Options::OPTION_LAST_WEBHOOK_EVENT_TEST ] ) ) {
				list( $testStyle, $testTitle, $testDescription ) = $this->getLastWebHookEventDescription( $currentTime, $options[ Options::OPTION_LAST_WEBHOOK_EVENT_TEST ], false );
				$stripeData->testEventStyle = $testStyle;
				$stripeData->testEventTitle = $testTitle;
				$stripeData->testEventDescription = $testDescription;
			}
		}

		return $stripeData;
	}

	/**
	 * @return \stdClass
	 */
	protected function getStripeStatus() {
		$stripeStatus = new \stdClass;

		if ( Utils::isDemoMode() ) {
			$stripeStatus->apiModeLabel = Admin::getApiModeLabelFromString( Main::STRIPE_API_MODE_TEST );
		} else {
			$stripeStatus->apiModeLabel = Admin::getApiModeLabelFromString( $this->options->get( Options::OPTION_API_MODE ) );
		}
		$stripeStatus->manageStripeAccountsUrl = Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE );

		return $stripeStatus;
	}


	/**
	 * @return array[]
	 */
	private function prepareStripeSettingsLookupTabIds() {
		$lookupTabs = [
			Menu::PARAM_VALUE_TAB_CONNECTION => [
				'title' =>
					/* translators: "Connection" tab of the "Stripe settings" page  */
					__( 'Connection', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_CONNECTION
			],
			Menu::PARAM_VALUE_TAB_WEBHOOK => [
				'title' =>
					/* translators: "Webhooks" tab of the "Form settings" page  */
					__( 'Webhooks', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_WEBHOOK
			]
		];

		return $lookupTabs;
	}

	function fullstripe_settings_stripe() {
		$this->requireCapability();

		// Move to new connection flow after the merge.
		$testStatus = $this->getTestAccountStatus();
		$liveStatus = $this->getLiveAccountStatus();

		// Check if the URL includes onboarding=true flag.
		$onboarding = isset( $_GET['onboarding'] ) && $_GET['onboarding'] === 'true';

		if ( $onboarding ) {
			/** @noinspection PhpIncludeInspection */
			include Assets::templates( 'admin/wpfs-wizard.php' );
			return;
		}

	    $lookupTabs = $this->prepareStripeSettingsLookupTabIds();
		$tabId = $this->getSettingsTabIdFromRequest( $lookupTabs, Menu::PARAM_VALUE_TAB_CONNECTION );

		$pageTitle =
			/* translators: Page title of the "Configure Stripe account" page in WordPress admin  */
			__( 'Configure Stripe account', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		$pageSlug = Menu::SLUG_SETTINGS_STRIPE;
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		$wpfp_options = $this->options->getSeveral( [ 
			Options::OPTION_LIVE_ACCOUNT_ID,
			Options::OPTION_TEST_ACCOUNT_ID
		] );

		$tabs = [];
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_CONNECTION ] );

		if ( ! empty( array_filter( $wpfp_options ) ) ) {
			array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_WEBHOOK ] );
		}

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-stripe.php' );
	}

	protected function getSecurityData() {
		$securityData = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_SECURE_INLINE_FORMS_WITH_GOOGLE_RE_CAPTCHA,
			Options::OPTION_SECURE_CHECKOUT_FORMS_WITH_GOOGLE_RE_CAPTCHA,
			Options::OPTION_SECURE_CUSTOMER_PORTAL_WITH_GOOGLE_RE_CAPTCHA,
			Options::OPTION_GOOGLE_RE_CAPTCHA_SECRET_KEY,
			Options::OPTION_GOOGLE_RE_CAPTCHA_SITE_KEY
		] );

		$securityData->secureInlineForms = $options[ Options::OPTION_SECURE_INLINE_FORMS_WITH_GOOGLE_RE_CAPTCHA ];
		$securityData->secureCheckoutForms = $options[ Options::OPTION_SECURE_CHECKOUT_FORMS_WITH_GOOGLE_RE_CAPTCHA ];
		$securityData->secureCustomerPortal = $options[ Options::OPTION_SECURE_CUSTOMER_PORTAL_WITH_GOOGLE_RE_CAPTCHA ];

		if ( Utils::isDemoMode() ) {
			// todo: refactor magic strings
			$securityData->recaptchaSecretKey = 'Google_reCaptcha_secret_key_here';
			$securityData->recaptchaSiteKey = 'Google_reCaptcha_site_key_here';
		} else {
			$securityData->recaptchaSecretKey = $options[ Options::OPTION_GOOGLE_RE_CAPTCHA_SECRET_KEY ];
			$securityData->recaptchaSiteKey = $options[ Options::OPTION_GOOGLE_RE_CAPTCHA_SITE_KEY ];
		}

		return $securityData;
	}

	function fullstripe_settings_security() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "Security settings" page in WordPress admin  */
			__( 'Security settings', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$view = new SecurityView();
		$securityData = $this->getSecurityData();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$pageSlug = Menu::SLUG_SETTINGS_SECURITY;
		$data = $this->prepareSettingsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-security.php' );
	}

	/**
	 * @return \stdClass
	 */
	protected function getCustomerPortalData() {
		$result = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS,
			Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS,
			Options::OPTION_CUSTOMER_PORTAL_WHEN_CANCEL_SUBSCRIPTIONS,
			Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_UPDOWNGRADE_SUBSCRIPTIONS,
			Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION,
			Options::OPTION_CUSTOMER_PORTAL_SCROLLING_PANE_INTO_VIEW,
			Options::OPTION_CUSTOMER_PORTAL_USE_STRIPE_CUSTOMER_PORTAL
		] );

		$result->showSubscriptions = $options[ Options::OPTION_CUSTOMER_PORTAL_SHOW_SUBSCRIPTIONS_TO_CUSTOMERS ];
		$result->cancelSubscriptions = $options[ Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_CANCEL_SUBSCRIPTIONS ];
		$result->whenCancelSubscriptions = $options[ Options::OPTION_CUSTOMER_PORTAL_WHEN_CANCEL_SUBSCRIPTIONS ];
		$result->updowngradeSubscriptions = $options[ Options::OPTION_CUSTOMER_PORTAL_LET_SUBSCRIBERS_UPDOWNGRADE_SUBSCRIPTIONS ];
		$result->showInvoices = $options[ Options::OPTION_CUSTOMER_PORTAL_SHOW_INVOICES_SECTION ];
		$result->scrollingPaneIntoView = $options[ Options::OPTION_CUSTOMER_PORTAL_SCROLLING_PANE_INTO_VIEW ];
		$result->useStripeCustomerPortal = $options[ Options::OPTION_CUSTOMER_PORTAL_USE_STRIPE_CUSTOMER_PORTAL ];

		return $result;
	}

	function fullstripe_settings_my_account() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "Customer portal settings" page in WordPress admin  */
			__( 'Customer portal settings', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$view = new CustomerPortalView();
		$myAccountData = $this->getCustomerPortalData();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$pageSlug = Menu::SLUG_SETTINGS_CUSTOMER_PORTAL;
		$data = $this->prepareSettingsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-my-account.php' );
	}

	/**
	 * @return \stdClass
	 */
	protected function getWordpressDasboardData() {
		$result = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_DECIMAL_SEPARATOR_SYMBOL,
			Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE,
			Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION,
			Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT,
		] );

		$result->decimalSeparator = $options[ Options::OPTION_DECIMAL_SEPARATOR_SYMBOL ];
		$result->useSymbolNotCode = $options[ Options::OPTION_SHOW_CURRENCY_SYMBOL_INSTEAD_OF_CODE ];
		$result->currencySymbolAtFirstPosition = $options[ Options::OPTION_SHOW_CURRENCY_SIGN_AT_FIRST_POSITION ];
		$result->putSpaceBetweenSymbolAndAmount = $options[ Options::OPTION_PUT_WHITESPACE_BETWEEN_CURRENCY_AND_AMOUNT ];

		return $result;
	}

	function fullstripe_settings_wp_dashboard() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "WordPress dashboard settings" page in WordPress admin  */
			__( 'Currency', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$view = new WordpressDashboardView();
		$wpDashboardData = $this->getWordpressDasboardData();
		$pageSlug = Menu::SLUG_SETTINGS_WORDPRESS_DASHBOARD;
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-wp-dashboard.php' );
	}

	protected function getLogsData() {
		$result = new \StdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_LOG_LEVEL,
			Options::OPTION_LOG_TO_WEB_SERVER,
			Options::OPTION_CATCH_UNCAUGHT_ERRORS
		] );
		$logentries = $this->db->getNumberOfLogEntries();
		$result->logEntryCount = $logentries->logCount;
		$result->logLevel = $options[ Options::OPTION_LOG_LEVEL ];
		$result->logToWebServer = $options[ Options::OPTION_LOG_TO_WEB_SERVER ];
		$result->catchUncaughtErrors = $options[ Options::OPTION_CATCH_UNCAUGHT_ERRORS ];

		return $result;
	}

	function fullstripe_settings_logs() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the Error logging" settings page in WordPress admin  */
			__( 'Error Logging', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$pluginsUrl = Menu::getPluginsUrl();
		$logDownloadUrl = Menu::getAdminUrlBySlugAndParams( self::SLUG_SETTINGS, [ 'wpfp-download-log' => 1 ] );

		$pageSlug = Menu::SLUG_SETTINGS_LOGS;
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		$levelView = new LogLevelView();
		$emptyView = new LogEmptyView();

		$logData = $this->getLogsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-logs.php' );
	}

	function fullstripe_settings_license() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "License" settings page in WordPress admin  */
			__( 'License', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$pluginsUrl = Menu::getPluginsUrl();

		$pageSlug = Menu::SLUG_SETTINGS_LICENSE;
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings-license.php' );
	}

	/**
	 * @return \stdClass
	 */
	protected function prepareReportsData( $currencyFilter, $rangeFilter ) {
		$result = new \StdClass;
		$forms  = $this->db->getAllForms();

		$payment_forms = array_filter( $forms, function ( $form ) {
			return $form->type === Main::FORM_TYPE_PAYMENT || $form->type === Main::FORM_TYPE_SUBSCRIPTION;
		} );

		$donation_forms = array_filter( $forms, function ( $form ) {
			return $form->type === Main::FORM_TYPE_DONATION;
		} );

		$result->payment_forms        = $payment_forms;
		$result->donation_forms       = $donation_forms;
		$result->total_donations      = $this->db->getTotalDonationAmount( $currencyFilter, $rangeFilter );
		$result->average_donation     = $this->db->getAverageDonationAmount( $currencyFilter, $rangeFilter );
		$result->donations_count      = $this->db->getTotalNumberOfDonations( $currencyFilter, $rangeFilter );
		$result->total_revenue        = $this->db->getTotalRevenueAmount( $currencyFilter, $rangeFilter );
		$result->average_transaction  = $this->db->getAverageTransactionAmount( $currencyFilter, $rangeFilter );
		$result->total_transactions   = $this->db->getTotalNumberOfPayments( $currencyFilter, $rangeFilter );
		$result->active_subscriptions = $this->db->getTotalActiveSubscriptions( $currencyFilter, $rangeFilter );
		$result->donations            = $this->db->getDonations( $currencyFilter, $rangeFilter );
		$result->top_donors           = $this->db->getTopDoners( $currencyFilter, $rangeFilter );
		$result->payments             = $this->db->getPayments( $currencyFilter, $rangeFilter );
		$result->top_customers        = $this->db->getTopCustomers( $currencyFilter, $rangeFilter );
		$result->revenue_data         = $this->db->getRevenueAndSalesData( $currencyFilter, $rangeFilter );
		$result->refunds_data         = $this->db->getRefundsData( $currencyFilter, $rangeFilter );
		$result->first_view           = (int) $result->donations_count > 1 ? 'donations' : 'payments';

		return $result;
	}

	function fullstripe_reports() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "Settings" page in WordPress admin  */
			__( 'Reports', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$pageUrl = Menu::getAdminUrlBySlug( self::SLUG_REPORTS );

		if ( ! Utils::isConnected() ) {
			/** @noinspection PhpIncludeInspection */
			include Assets::templates( 'admin/wpfs-forms-connect.php' );
			return;
		}

		$rangeFilter = array_key_exists( self::PARAM_NAME_REPORTS_FILTER, $_POST ) ? $_POST[ self::PARAM_NAME_REPORTS_FILTER ] : self::PARAM_VALUE_RANGE_LAST_30_DAYS;
		$currencyFilter = array_key_exists( self::PARAM_NAME_REPORTS_CURRENCY, $_POST ) ? $_POST[ self::PARAM_NAME_REPORTS_CURRENCY ] : $this->db->getMostUsedCurrency();
		$startDate = array_key_exists( self::PARAM_NAME_REPORTS_START_DATE, $_POST ) ? $_POST[ self::PARAM_NAME_REPORTS_START_DATE ] : null;
		$endDate = array_key_exists( self::PARAM_NAME_REPORTS_END_DATE, $_POST ) ? $_POST[ self::PARAM_NAME_REPORTS_END_DATE ] : null;

		$options = $this->options->getSeveral( [ 
			Options::OPTION_LAST_WEBHOOK_EVENT_LIVE,
			Options::OPTION_LAST_WEBHOOK_EVENT_TEST
		] );

		$lastWebhookEvent = isset( $options[ Options::OPTION_LAST_WEBHOOK_EVENT_TEST ] ) ? $options[ Options::OPTION_LAST_WEBHOOK_EVENT_TEST ] : null;

		if ( Utils::isLiveMode() ) {
			$lastWebhookEvent = isset( $options[ Options::OPTION_LAST_WEBHOOK_EVENT_LIVE ] ) ? $options[ Options::OPTION_LAST_WEBHOOK_EVENT_LIVE ] : null;
		}

		if ( $rangeFilter === self::PARAM_VALUE_RANGE_CUSTOM ) {
			$range = [
				'start' => $startDate,
				'end'   => $endDate
			];
		} else {
			$range = $rangeFilter;
		}

		$data = $this->prepareReportsData( $currencyFilter, $range );

		// Check if both payment and donation forms count is zero.
		$noForms = count( $data->payment_forms ) === 0 && count( $data->donation_forms ) === 0;

		if ( $noForms ) {
			$createButtonLabel =
				/* translators: Button label of the "Add form" button  */
				__( 'Add form', 'wp-full-stripe-free' );
			$createButtonUrl = Menu::getAdminUrlBySlug( self::SLUG_CREATE_FORM );
			include Assets::templates( 'admin/wpfs-forms-empty.php' );
			return;
		}

		$db = $this->db;
		$stripe = $this->stripe;
		$currencies = Currencies::getAvailableCurrencies();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-reports.php' );
	}

	/**
	 * @return \stdClass
	 */
	protected function prepareSettingsData() {
		$result = new \StdClass;

		$testStatus = $this->getTestAccountStatus();

		$liveStatus = $this->getLiveAccountStatus();

		$disabledLive = $liveStatus === Options::OPTION_ACCOUNT_STATUS_COMPLETE || $liveStatus === Options::OPTION_ACCOUNT_STATUS_ENABLED ? false : true;
		$disabledTest = $testStatus === Options::OPTION_ACCOUNT_STATUS_COMPLETE || $testStatus === Options::OPTION_ACCOUNT_STATUS_ENABLED ? false : true;
		$disabled = $disabledLive && $disabledTest ? true : false;
		$isNewFlow = false;
		if ($this->options->get(Options::OPTION_USE_WP_LIVE_PLATFORM) == true || $this->options->get(Options::OPTION_USE_WP_TEST_PLATFORM) == true) {
			$isNewFlow = true;
		}
		$disabled = $disabled && $isNewFlow;

		$settingsItems = [
			[
				'slug' => Menu::SLUG_SETTINGS_STRIPE,
				'cssClasses' => 'wpfs-illu-stripe',
				'url' => $this->getAdminUrlBySlugAndParams(Menu::SLUG_SETTINGS_STRIPE, [Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_CONNECTION]),
				'title' => __('Stripe Account', 'wp-full-stripe-free'),
				'description' => __('Configure your Stripe API keys, and set up webhooks', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'payments',
					'title' => __('Payment Setup', 'wp-full-stripe-free'),
				],
				'disabled' => false,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_FORMS,
				'cssClasses' => 'wpfs-illu-form',
				'url' => $this->getAdminUrlBySlugAndParams(Menu::SLUG_SETTINGS_FORMS, [Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_OPTIONS]),
				'title' => __('Forms', 'wp-full-stripe-free'),
				'description' => __('Set global settings & styles for your payment forms', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'payments',
					'title' => __('Payment Setup', 'wp-full-stripe-free'),
				],
				'disabled' => $disabled,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS,
				'cssClasses' => 'wpfs-illu-email',
				'url' => $this->getAdminUrlBySlugAndParams(Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS, [Menu::PARAM_NAME_TAB => Menu::PARAM_VALUE_TAB_OPTIONS]),
				'title' => __('Email Notifications', 'wp-full-stripe-free'),
				'description' => __('Customize and align your e-mails to your brand', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'customer_experience',
					'title' => __('Customer Experience', 'wp-full-stripe-free'),
				],
				'disabled' => $disabled,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_CUSTOMER_PORTAL,
				'cssClasses' => 'wpfs-illu-customer-portal',
				'url' => $this->getAdminUrlBySlug(Menu::SLUG_SETTINGS_CUSTOMER_PORTAL),
				'title' => __('Customer Portal', 'wp-full-stripe-free'),
				'description' => __('Configure how your customers can manage their cards, subscriptions, and invoices', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'customer_experience',
					'title' => __('Customer Experience', 'wp-full-stripe-free'),
				],
				'disabled' => $disabled,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_SECURITY,
				'cssClasses' => 'wpfs-illu-security',
				'url' => $this->getAdminUrlBySlug(Menu::SLUG_SETTINGS_SECURITY),
				'title' => __('Security', 'wp-full-stripe-free'),
				'description' => __('Keep your payment forms secure', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'configuration',
					'title' => __('Configuration', 'wp-full-stripe-free'),
				],
				'disabled' => $disabled,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_WORDPRESS_DASHBOARD,
				'cssClasses' => 'wpfs-illu-credit-card',
				'url' => $this->getAdminUrlBySlug(Menu::SLUG_SETTINGS_WORDPRESS_DASHBOARD),
				'title' => __('Currency', 'wp-full-stripe-free'),
				'description' => __('Set your currency format', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'configuration',
					'title' => __('Configuration', 'wp-full-stripe-free'),
				],
				'disabled' => $disabled,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_LOGS,
				'cssClasses' => 'wpfs-illu-logs',
				'url' => $this->getAdminUrlBySlug(Menu::SLUG_SETTINGS_LOGS),
				'title' => __('Error Logging', 'wp-full-stripe-free'),
				'description' => __('Help the developers debug plugin issues', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'configuration',
					'title' => __('Configuration', 'wp-full-stripe-free'),
				],
				'disabled' => false,
			],
			[
				'slug' => Menu::SLUG_SETTINGS_LICENSE,
				'cssClasses' => 'wpfs-illu-lock',
				'url' => $this->getAdminUrlBySlug(Menu::SLUG_SETTINGS_LICENSE),
				'title' => __('License', 'wp-full-stripe-free'),
				'description' => __('Enter or manage your license key to enable unlimited transactions without fees.', 'wp-full-stripe-free'),
				'group' => [
					'slug' => 'configuration',
					'title' => __('Configuration', 'wp-full-stripe-free'),
				],
				'disabled' => false,
			],
		];

		$result->settingsItems = $settingsItems;
		$result->disabled = $disabled;

		return $result;
	}

	function fullstripe_settings() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "Settings" page in WordPress admin  */
			__( 'Settings', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-settings.php' );
	}

	/**
	 * @return string
	 */
	private function getStripeApiMode() {
		return $this->options->get( Options::OPTION_API_MODE );
	}

	/**
	 * @param $timestamp
	 *
	 * @return string
	 */
	private function formatTimestamp( $timestamp ) {
		return is_null( $timestamp ) ?
			/* translators: When was the last time this form was used? This is the label for 'never'  */
			__( 'Never', 'wp-full-stripe-free' ) :
			Utils::formatTimestampWithWordpressDateTimeFormat( strtotime( $timestamp ) );
	}

	/**
	 * @return array
	 */
	private function getForms() {
		$forms = $this->db->getAllForms();
		$stripeApiMode = $this->getStripeApiMode();
		for ( $idx = 0; $idx < count( $forms ); $idx++ ) {
			$form = $forms[ $idx ];
			$form->editUrl = Utils::getFormEditUrl( $form->id, $form->type, $form->layout );
			$form->stripeApiMode = $stripeApiMode;
			$form->shortCode = Shortcode::createShortCodeByForm( $form );
			$form->lastUsedAt = $this->formatTimestamp( $form->created );
			$forms[ $idx ] = $form;
		}
		return $forms;
	}

	/**
	 * @param string $slug
	 * @param array $params
	 *
	 * @return string
	 */
	public static function getAdminUrlBySlugAndParams( $slug, $params = [] ): string {
		$allParams = array_merge(
			[
				'page' => $slug
			],
			$params
		);

		$url = add_query_arg(
			$allParams,
			admin_url( "admin.php" )
		);
		$url = add_query_arg( 'nonce', wp_create_nonce( 'wp-full-stripe-admin-nonce' ), $url );

		return $url;
	}

	/**
	 * @param $slug
	 *
	 * @return string
	 */
	public static function getAdminUrlBySlug( $slug ): string {
		return Menu::getAdminUrlBySlugAndParams( $slug );
	}

	/**
	 * @return string|null
	 */
	public function getLiveAccountStatus() {
		$accountId = $this->options->get( Options::OPTION_LIVE_ACCOUNT_ID );
		if ( isset( $accountId ) && $accountId !== '' ) {
			$account = null;
			try {
				$account = $this->stripe->getLiveAccount( $accountId );
				$this->retrievedAccounts['live'] = $account;
			} catch ( \Exception $ex ) {
				return null;
			}
			return $this->determineAccountStatus( $account, 'live' );
		} else {
			return null;
		}
	}

	/**
	 * @return string|null
	 */
	public function getTestAccountStatus() {
		$accountId = $this->options->get( Options::OPTION_TEST_ACCOUNT_ID );
		if ( isset( $accountId ) && $accountId !== '' ) {
			$account = null;
			try {
				$account = $this->stripe->getTestAccount( $accountId );
				$this->retrievedAccounts['test'] = $account;
			} catch ( \Exception $ex ) {
				return null;
			}
			return $this->determineAccountStatus( $account, 'test' );
		} else {
			return null;
		}
	}

	/**
	 * @param string $mode
	 * @return bool Whether the Disconnect dialog offers removing WP Full Pay's access.
	 */
	private function isAccessRevocable( string $mode ): bool {
		$account = $this->retrievedAccounts[ $mode ] ?? null;

		// An account that could not be looked up is offered too: starting the removal looks it up again.
		return ! is_object( $account ) || StripeAccessRevocation::isRevocableAccount( $account );
	}

	public function determineAccountStatus( $account, $accountMode ) {
		$state = null;
		if ( isset( $account ) ) {
			if ( $this->isRejected( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_REJECTED;
			} else if ( $this->isRestricted( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_RESTRICTED;
			} else if ( $this->isRestrictedSoon( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_RESTRICTED_SOON;
			} else if ( $this->isPendingEnabled( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_PENDING_ENABLED;
			} else if ( $this->isPendingDisabled( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_PENDING_DISABLED;
			} else if ( $this->isEnabled( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_ENABLED;
			} else if ( $this->isComplete( $account ) ) {
				$state = Options::OPTION_ACCOUNT_STATUS_COMPLETE;
			} else {
				$state = Options::OPTION_ACCOUNT_STATUS_NULL;
			}
		}
		if ( $accountMode === 'live' ) {
			$this->options->set( Options::OPTION_LIVE_ACCOUNT_STATUS, $state );
		} else {
			$this->options->set( Options::OPTION_TEST_ACCOUNT_STATUS, $state );
		}
		return $state;
	}

	/**
	 * @return string
	 */
	public static function getPluginsUrl(): string {
		return add_query_arg(
			[],
			admin_url( "plugins.php" )
		);
	}

	/**
	 * @return bool
	 */
	private function isRestricted( $account ): bool {

		return $account->payouts_enabled === false;
	}

	/**
	 * @return bool
	 */
	private function isRejectedReason( $reason ): bool {
		return $reason && str_starts_with( $reason, 'rejected' );
	}

	/**
	 * @return bool
	 */
	private function isRestrictedSoon( $account ): bool {
		if ( isset( $account->requirements ) && isset( $account->requirements->current_deadline ) )
			return $account->requirements->current_deadline !== null;
		else
			return false;
	}

	/**
	 * @return bool
	 */
	private function isRejected( $account ): bool {
		if ( isset( $account->requirements ) )
			return $this->isRejectedReason( $account->requirements->disabled_reason );
		else
			return false;
	}

	/**
	 * @return bool
	 */
	private function isComplete( $account ): bool {
		$eventuallyDue = [];
		if ( isset( $account->requirements ) && isset( $account->requirements->eventually_due ) ) {
			$eventuallyDue = $account->requirements->eventually_due;
		}
		return count( $eventuallyDue ) === 0;
	}

	/**
	 * @return bool
	 */
	private function isEnabled( $account ): bool {
		$eventuallyDue = [];
		if ( isset( $account->requirements ) && isset( $account->requirements->eventually_due ) ) {
			$eventuallyDue = $account->requirements->eventually_due;
		}
		$current_deadline = null;
		if ( isset( $account->requirements ) ) {
			$current_deadline = $account->requirements->current_deadline;
		}

		return (
			$account->payouts_enabled === true &&
			count( $eventuallyDue ) > 0 &&
			$current_deadline === null
		);
	}

	/**
	 * @return bool
	 */
	private function isPendingEnabled( $account ): bool {

		$pendingVerification = [];
		if ( isset( $account->requirements ) && isset( $account->requirements->pending_verification ) ) {
			$pendingVerification = $account->requirements->pending_verification;
		}
		$disabledReason = null;
		if ( isset( $account->requirements ) ) {
			$disabledReason = $account->requirements->disabled_reason;
		}

		return (
			count( $pendingVerification ) > 0 &&
			$disabledReason === null
		);
	}

	/**
	 * @return bool
	 */
	private function isPendingDisabled( $account ): bool {
		$pendingVerification = [];
		if ( isset( $account->requirements ) && isset( $account->requirements->pending_verification ) ) {
			$pendingVerification = $account->requirements->pending_verification;
		}

		$disabledReason = null;
		if ( isset( $account->requirements ) ) {
			$disabledReason = $account->requirements->disabled_reason;
		}
		return (
			count( $pendingVerification ) > 0 &&
			$disabledReason !== null
		);

	}

	/**
	 * @param $allForms
	 * @param $textFilter
	 * @param $modeFilter
	 *
	 * @return array
	 */
	private function filterFormList( $allForms, $textFilter, $modeFilter ) {
		$forms = [];

		if ( empty( $textFilter ) && $modeFilter === self::PARAM_VALUE_API_MODE_ALL ) {
			$forms = $allForms;
		} else {
			$forms = array_filter( $allForms, function ($form, $key) use ($textFilter, $modeFilter) {
				$match = true;

				if ( ! empty( $textFilter ) ) {
					$lowerCaseTextFilter = strtolower( $textFilter );

					if (
						strpos( strtolower( $form->displayName ), $lowerCaseTextFilter ) === false &&
						strpos( strtolower( $form->name ), $lowerCaseTextFilter ) === false
					) {
						$match = false;
					}
				}

				if ( $modeFilter !== self::PARAM_VALUE_API_MODE_ALL ) {
					if ( $form->stripeApiMode != $modeFilter ) {
						$match = false;
					}
				}

				return $match;
			}, ARRAY_FILTER_USE_BOTH );
		}

		return $forms;
	}

	/**
	 * @param $tab
	 *
	 * @return string
	 */
	private function validateTabId( $tabs, $tab, $defaultTab ): string {
		return key_exists( $tab, $tabs ) !== false ? $tab : $defaultTab;
	}

	/**
	 * The settings page tab the request asks for, or the page's default tab.
	 *
	 * @param array<string, array{title: string, tab: string}> $lookupTabs The page's tabs, by tab id.
	 */
	private function getSettingsTabIdFromRequest( array $lookupTabs, string $defaultTab ): string {
		$tabId = array_key_exists( Menu::PARAM_NAME_TAB, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_TAB ] : null;

		return $this->validateTabId( $lookupTabs, $tabId, $defaultTab );
	}


	/**
	 * @return string|null
	 */
	protected function getTabIdFromCookie() {
		return array_key_exists( self::COOKIE_NAME_TAB_ID, $_COOKIE ) ? $_COOKIE[ self::COOKIE_NAME_TAB_ID ] : null;
	}

	/**
	 * Get the number of payments, subscriptions, donations and saved cards.
	 * 
	 * @return array
	 */
	protected function getTransactionCounts() {
		$transactions = [
			Menu::PARAM_VALUE_TAB_PAYMENTS => $this->db->getNumberOfPaymentsByMethod( Menu::PARAM_VALUE_TAB_PAYMENTS ),
			Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS => $this->db->getNumberOfPaymentsByMethod( Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ),
			Menu::PARAM_VALUE_TAB_DONATIONS => $this->db->getNumberOfPaymentsByMethod( Menu::PARAM_VALUE_TAB_DONATIONS ),
			Menu::PARAM_VALUE_TAB_SAVED_CARDS => $this->db->getNumberOfPaymentsByMethod( Menu::PARAM_VALUE_TAB_SAVED_CARDS )
		];

		return $transactions;
	}

	/**
	 * Get the forms counts.
	 *
	 * @return array<string, int>
	 */
	protected function getFormCounts() {
		$onetime_payment_forms = $this->db->getNumberOfOneTimePaymentForms();
		$subscription_forms    = $this->db->getNumberOfSubscriptionForms();
		$donation_forms        = $this->db->getNumberOfDonationForms();
		$save_card_forms       = $this->db->getNumberOfSaveCardForms();

		$forms = [
			Menu::PARAM_VALUE_TAB_PAYMENTS      => isset( $onetime_payment_forms->formCount ) ? $onetime_payment_forms->formCount : 0,
			Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS => isset( $subscription_forms->formCount ) ? $subscription_forms->formCount : 0,
			Menu::PARAM_VALUE_TAB_DONATIONS     => isset( $donation_forms->formCount ) ? $donation_forms->formCount : 0,
			Menu::PARAM_VALUE_TAB_SAVED_CARDS   => isset( $save_card_forms->formCount ) ? $save_card_forms->formCount : 0,
		];

		return $forms;
	}

	/**
	 * @param $lookupTabs array
	 * @return string
	 */
	private function getTransactionTabIdFromRequest( &$lookupTabs ): string {
		$tabId = array_key_exists( Menu::PARAM_NAME_TAB, $_REQUEST ) ? $_REQUEST[ Menu::PARAM_NAME_TAB ] : null;

		$transactions = $this->getTransactionCounts();

		$default = Menu::PARAM_VALUE_TAB_PAYMENTS;
		foreach ( $transactions as $key => $value ) {
			if ( $value > 0 ) {
				$default = $key;
				$tabId = is_null( $tabId ) ? $key : $tabId;
				break;
			}
		}

		if ( is_null( $tabId ) ) {
			$tabId = $this->getTabIdFromFormCountsOrCookie();
		}

		$tabId = $this->validateTabId( $lookupTabs, $tabId, $default );

		return $tabId;
	}

	/**
	 * Get the Tab ID from the form counts or cookies.
	 *
	 * @return string
	 */
	private function getTabIdFromFormCountsOrCookie() {
		$forms = $this->getFormCounts();
		$tabId = '';

		foreach ( $forms as $key => $value ) {
			if ( $value > 0 ) {
				$tabId = $key;
				break;
			}
		}

		if ( empty( $tabId ) ) {
			return $this->getTabIdFromCookie();
		}

		return $tabId;
	}

	/**
	 * @return array[]
	 */
	private function prepareTransactionLookupTabIds() {
		$transactions = $this->getTransactionCounts();

		$lookupTabs = [
			Menu::PARAM_VALUE_TAB_PAYMENTS => [
				'title' => sprintf(
					/* translators: %s is the number of payments */
					__( 'One-time payments %s', 'wp-full-stripe-free' ),
					$transactions[ Menu::PARAM_VALUE_TAB_PAYMENTS ] > 0 ? '<span class="wpfs-tag wpfs-tag--outline">' . ( $transactions[ Menu::PARAM_VALUE_TAB_PAYMENTS ] > 100 ? '100+' : $transactions[ Menu::PARAM_VALUE_TAB_PAYMENTS ] ) . '</span>' : '',
				),
				'tab' => Menu::PARAM_VALUE_TAB_PAYMENTS
			],
			Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS => [
				'title' => sprintf(
					/* translators: %s is the number of payments */
					__( 'Subscriptions %s', 'wp-full-stripe-free' ),
					$transactions[ Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ] > 0 ? '<span class="wpfs-tag wpfs-tag--outline">' . ( $transactions[ Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ] > 100 ? '100+' : $transactions[ Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ] ) . '</span>' : '',
				),
				'tab' => Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS
			],
			Menu::PARAM_VALUE_TAB_DONATIONS => [
				'title' => sprintf(
					/* translators: %s is the number of payments */
					__( 'Donations %s', 'wp-full-stripe-free' ),
					$transactions[ Menu::PARAM_VALUE_TAB_DONATIONS ] > 0 ? '<span class="wpfs-tag wpfs-tag--outline">' . ( $transactions[ Menu::PARAM_VALUE_TAB_DONATIONS ] > 100 ? '100+' : $transactions[ Menu::PARAM_VALUE_TAB_DONATIONS ] ) . '</span>' : '',
				),
				'tab' => Menu::PARAM_VALUE_TAB_DONATIONS
			],
			Menu::PARAM_VALUE_TAB_SAVED_CARDS => [
				'title' => sprintf(
					/* translators: %s is the number of payments */
					__( 'Saved cards %s', 'wp-full-stripe-free' ),
					$transactions[ Menu::PARAM_VALUE_TAB_SAVED_CARDS ] > 0 ? '<span class="wpfs-tag wpfs-tag--outline">' . ( $transactions[ Menu::PARAM_VALUE_TAB_SAVED_CARDS ] > 100 ? '100+' : $transactions[ Menu::PARAM_VALUE_TAB_SAVED_CARDS ] ) . '</span>' : '',
				),
				'tab' => Menu::PARAM_VALUE_TAB_SAVED_CARDS
			]
		];

		return $lookupTabs;
	}

	/**
	 * @param $tabId
	 *
	 * @return array
	 */
	private function prepareTransactionData( $tabId ): array {
		$transactionCount = 0;
		$formCount = 0;
		$transactionTable = null;

		switch ( $tabId ) {
			case Menu::PARAM_VALUE_TAB_PAYMENTS: {
				$transactionCount = ( $this->db->getNumberOfOneTimePayments() )->paymentCount;
				$formCount = ( $this->db->getNumberOfOneTimePaymentForms() )->formCount;

				if ( $transactionCount > 0 ) {
					$transactionTable = new OneTimePaymentsTable( $this->loggerService );
					$transactionTable->prepare_items();
				}

				break;
			}

			case Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS: {
				$transactionCount = ( $this->db->getNumberOfSubscriptions() )->subscriptionCount;
				$formCount = ( $this->db->getNumberOfSubscriptionForms() )->formCount;

				if ( $transactionCount > 0 ) {
					$transactionTable = new SubscriptionsTable( $this->loggerService );
					$transactionTable->prepare_items();
				}

				break;
			}

			case Menu::PARAM_VALUE_TAB_DONATIONS: {
				$transactionCount = ( $this->db->getNumberOfDonations() )->donationCount;
				$formCount = ( $this->db->getNumberOfDonationForms() )->formCount;

				if ( $transactionCount > 0 ) {
					$transactionTable = new DonationsTable( $this->loggerService );
					$transactionTable->prepare_items();
				}

				break;
			}

			case Menu::PARAM_VALUE_TAB_SAVED_CARDS: {
				$transactionCount = ( $this->db->getNumberOfSavedCards() )->savedCardCount;
				$formCount = ( $this->db->getNumberOfSaveCardForms() )->formCount;

				if ( $transactionCount > 0 ) {
					$transactionTable = new SavedCardsTable( $this->loggerService );
					$transactionTable->prepare_items();
				}

				break;
			}
		}

		return [ $transactionCount, $formCount, $transactionTable ];
	}

	function fullstripe_transactions() {
		$this->requireCapability();

		$lookupTabs = $this->prepareTransactionLookupTabIds();
		$tabId = $this->getTransactionTabIdFromRequest( $lookupTabs );

		$pageTitle =
			/* translators: Page title of the "Transactions" page  */
			__( 'Transactions', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		$pageSlug = Menu::SLUG_TRANSACTIONS;
		$createButtonLabel =
			/* translators: Button label of the "Add form" button  */
			__( 'Add form', 'wp-full-stripe-free' );
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );

		$tabs = [];
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_PAYMENTS ] );
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS ] );
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_DONATIONS ] );
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_SAVED_CARDS ] );

		list( $transactionCount, $formCount, $transactionTable ) = $this->prepareTransactionData( $tabId );

		include Assets::templates( 'admin/wpfs-transactions.php' );
	}

	/**
	 * @return array[]
	 */
	private function prepareEmailSettingsLookupTabIds() {
		$lookupTabs = [
			Menu::PARAM_VALUE_TAB_OPTIONS => [
				'title' =>
					/* translators: The "Options" tab of the "Email settings" page  */
					__( 'Options', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_OPTIONS
			],
			Menu::PARAM_VALUE_TAB_TEMPLATES => [
				'title' =>
					/* translators: The "Templates" tab of the "Email settings" page  */
					__( 'Templates', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_TEMPLATES
			]
		];

		return $lookupTabs;
	}

	protected function getEmailTemplatesData() {
		$emailTemplatesData = new \stdClass;
		$emailTemplates = [];

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_PAYMENT_RECEIPT;
		$template->caption =
			/* translators: Name of the one-time payment receipt email  */
			__( 'Payment receipt', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_RECEIPT;
		$template->caption =
			/* translators: Name of the subscription receipt email */
			__( 'Subscription receipt', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_SUBSCRIPTION_ENDED;
		$template->caption =
			/* translators: Name of the email notification sent when a payment-in-installments subscription is ended  */
			__( 'Subscription ended', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_DONATION_RECEIPT;
		$template->caption =
			/* translators: Name of the donation receipt email */
			__( 'Donation receipt', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_CARD_SAVED;
		$template->caption =
			/* translators: Name of the email which is sent when customer information together with payment details are saved in Stripe */
			__( 'Card saved', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$template = new \stdClass;
		$template->id = Main::EMAIL_TEMPLATE_ID_CUSTOMER_PORTAL_SECURITY_CODE;
		$template->caption =
			/* translators: Name of the email which is sent when customer initiates a login process to the Customer portal */
			__( 'Customer portal login code', 'wp-full-stripe-free' );
		array_push( $emailTemplates, $template );

		$emailTemplatesData->templates = apply_filters( 'fullstripe_email_template_descriptions', $emailTemplates );

		return $emailTemplatesData;
	}

	/**
	 * @return \stdClass
	 */
	protected function getEmailOptionsData() {
		$emailOptionsData = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_EMAIL_NOTIFICATION_SENDER_ADDRESS,
			Options::OPTION_EMAIL_NOTIFICATION_BCC_ADDRESSES
		] );

		$emailOptionsData->adminName = get_bloginfo( 'name' );
		$emailOptionsData->adminEmail = get_bloginfo( 'admin_email' );

		$emailOptionsData->senderEmail = $options[ Options::OPTION_EMAIL_NOTIFICATION_SENDER_ADDRESS ];
		$emailOptionsData->bccEmails = json_decode( $options[ Options::OPTION_EMAIL_NOTIFICATION_BCC_ADDRESSES ] );

		return $emailOptionsData;
	}

	function fullstripe_settings_email() {
		$this->requireCapability();

		$lookupTabs = $this->prepareEmailSettingsLookupTabIds();
		$tabId = $this->getSettingsTabIdFromRequest( $lookupTabs, Menu::PARAM_VALUE_TAB_OPTIONS );

		$pageTitle =
			/* translators: Page title of the "Email notifications" page  */
			__( 'Email notifications', 'wp-full-stripe-free' );
		$pageSlug = Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS;
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$stripeStatus = $this->getStripeStatus();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		$tabs = [];
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_OPTIONS ] );
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_TEMPLATES ] );

		include Assets::templates( 'admin/wpfs-settings-email-notifications.php' );
	}

	/**
	 * @return array[]
	 */
	private function prepareFormsSettingsLookupTabIds() {
		$lookupTabs = [
			Menu::PARAM_VALUE_TAB_OPTIONS => [
				'title' =>
					/* translators: "Options" tab of the "Form settings" page  */
					__( 'Options', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_OPTIONS
			],
			Menu::PARAM_VALUE_TAB_APPEARANCE => [
				'title' =>
					/* translators: "Appearance" tab of the "Form settings" page  */
					__( 'Appearance', 'wp-full-stripe-free' ),
				'tab' => Menu::PARAM_VALUE_TAB_APPEARANCE
			]
		];

		return $lookupTabs;
	}

	/**
	 * @return \stdClass
	 */
	protected function getFormsOptionsData() {
		$result = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_FILL_IN_EMAIL_FOR_LOGGED_IN_USERS,
			Options::OPTION_SET_FORM_FIELDS_VIA_URL_PARAMETERS,
			Options::OPTION_DEFAULT_BILLING_COUNTRY,
			Options::OPTION_DEFAULT_SHOW_PAYMENT_DETAIL
		] );

		$result->fillInEmailForUsers = $options[ Options::OPTION_FILL_IN_EMAIL_FOR_LOGGED_IN_USERS ];
		$result->setFormFieldsViaUrlParameters = $options[ Options::OPTION_SET_FORM_FIELDS_VIA_URL_PARAMETERS ];
		$result->defaultBillingCountry = $options[ Options::OPTION_DEFAULT_BILLING_COUNTRY ];
		$result->showPaymentDetail = isset( $options[ Options::OPTION_DEFAULT_SHOW_PAYMENT_DETAIL ] ) ? $options[ Options::OPTION_DEFAULT_SHOW_PAYMENT_DETAIL ] : '1';

		return $result;
	}

	/**
	 * @return \stdClass
	 */
	protected function getFormsAppearanceData() {
		$result = new \stdClass;
		$options = $this->options->getSeveral( [ 
			Options::OPTION_FORM_CUSTOM_CSS
		] );

		$result->customCss = $options[ Options::OPTION_FORM_CUSTOM_CSS ];

		return $result;
	}

	function fullstripe_settings_forms() {
		$this->requireCapability();

		$lookupTabs = $this->prepareFormsSettingsLookupTabIds();
		$tabId = $this->getSettingsTabIdFromRequest( $lookupTabs, Menu::PARAM_VALUE_TAB_OPTIONS );

		$pageTitle =
			/* translators: Page title of the "Form settings" page */
			__( 'Forms', 'wp-full-stripe-free' );
		$pageSlug = Menu::SLUG_SETTINGS_FORMS;
		[ $backLinkUrl, $backLinkLabel ] = $this->backToSettingsLink();
		$stripeStatus = $this->getStripeStatus();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		$data = $this->prepareSettingsData();

		$tabs = [];
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_OPTIONS ] );
		array_push( $tabs, $lookupTabs[ Menu::PARAM_VALUE_TAB_APPEARANCE ] );

		include Assets::templates( 'admin/wpfs-settings-forms.php' );
	}

	function fullstripe_forms() {
		$this->requireCapability();
		// log the request
		$textFilter = array_key_exists( self::PARAM_NAME_FORM_TEXT_FILTER, $_POST ) ? $_POST[ self::PARAM_NAME_FORM_TEXT_FILTER ] : null;
		$modeFilter = array_key_exists( self::PARAM_NAME_FORM_MODE_FILTER, $_POST ) ? $_POST[ self::PARAM_NAME_FORM_MODE_FILTER ] : self::PARAM_VALUE_API_MODE_ALL;

		$pageTitle =
			/* translators: Page title of the "Manage forms" page  */
			__( 'Manage forms', 'wp-full-stripe-free' );

		$stripeStatus = $this->getStripeStatus();
		$createButtonLabel =
			/* translators: Button label of the "Add form" button  */
			__( 'Add form', 'wp-full-stripe-free' );
		$createButtonUrl = Menu::getAdminUrlBySlug( self::SLUG_CREATE_FORM );
		$pageUrl = Menu::getAdminUrlBySlug( self::SLUG_FORMS );
		$allForms = $this->getForms();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );

		if ( ! Utils::isConnected() ) {
			/** @noinspection PhpIncludeInspection */
			include Assets::templates( 'admin/wpfs-forms-connect.php' );
			return;
		}

		if ( count( $allForms ) > 0 ) {
			$forms = $this->filterFormList( $allForms, $textFilter, $modeFilter );
			/** @noinspection PhpIncludeInspection */
			include Assets::templates( 'admin/wpfs-forms.php' );
		} else {
			/** @noinspection PhpIncludeInspection */
			include Assets::templates( 'admin/wpfs-forms-empty.php' );
		}
	}

	function fullstripe_create_form() {
		$this->requireCapability();

		$pageTitle =
			/* translators: Page title of the "Add new form" page  */
			__( 'Add new form', 'wp-full-stripe-free' );
		$stripeStatus = $this->getStripeStatus();
		[ $backLinkUrl, $backLinkLabel ] = $this->backToFormsLink();
		$view = new CreateFormView( $_GET );
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-create-form.php' );
	}


	/**
	 * @param string $formType
	 * @return string
	 */
	protected function getEditFormTitle( $formType ) {
		$title = '';

		if ( $formType === Main::FORM_TYPE_INLINE_SAVE_CARD ) {
			$title =
				/* translators: Page title of the "Edit inline save card form" page  */
				__( 'Inline save card form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_CHECKOUT_SAVE_CARD ) {
			$title =
				/* translators: Page title of the "Edit checkout save card form" page  */
				__( 'Checkout save card form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_INLINE_DONATION ) {
			$title =
				/* translators: Page title of the "Edit inline donation form" page  */
				__( 'Inline donation form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_CHECKOUT_DONATION ) {
			$title =
				/* translators: Page title of the "Edit checkout donation form" page  */
				__( 'Checkout donation form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_INLINE_PAYMENT ) {
			$title =
				/* translators: Page title of the "Edit inline one-time payment form" page  */
				__( 'Inline one-time payment form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_CHECKOUT_PAYMENT ) {
			$title =
				/* translators: Page title of the "Edit checkout one-time payment form" page  */
				__( 'Checkout one-time payment form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_INLINE_SUBSCRIPTION ) {
			$title =
				/* translators: Page title of the "Edit inline subscription form" page  */
				__( 'Inline subscription form', 'wp-full-stripe-free' );
		} elseif ( $formType === Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION ) {
			$title =
				/* translators: Page title of the "Edit checkout subscription form" page  */
				__( 'Checkout subscription form', 'wp-full-stripe-free' );
		}

		return $title;
	}

	/**
	 * @param $formType
	 *
	 * @return array[]
	 */
	protected function prepareEmailTemplates( $formType, $form ) {
		$templates = Mailer::extractEmailTemplates( $this->staticContext, $formType, $form->emailTemplates );
		$templateDescriptors = Mailer::getEmailTemplateDescriptors( $formType );
		$templateResult = [];

		foreach ( $templateDescriptors as $descriptor ) {
			$result = $descriptor;

			$type = (string) $descriptor->type;
			if ( property_exists( $templates, $type ) ) {
				$result->enabled = $templates->{$type}->enabled;
			} else {
				$result->enabled = false;
			}
			// Per-form subject/body customization is only available for plugin-generated templates; Stripe receipts keep their enabled-only behavior.
			$result->editable = Mailer::isPluginEmailTemplate( $type );
			$result->subject  = '';
			$result->body     = '';
			if ( $result->editable ) {
				$content = Mailer::getFormTemplateContent( $form, $type );
				if ( $content !== null ) {
					$result->subject = isset( $content->subject ) ? $content->subject : '';
					$result->body    = isset( $content->body ) ? $content->body : '';
				}
			}

			array_push( $templateResult, $result );
		}

		return $templateResult;
	}

	/**
	 * The data the form editor's templates read. The properties a type doesn't use are left unset.
	 *
	 * @param FormTypeKey $formType
	 * @param \stdClass $form
	 */
	protected function prepareFormData( string $formType, $form ): \StdClass {
		$formTypeInfo = FormTypes::get( $formType );
		$data = new \StdClass;

		$data->thankYouPages = ThankYou::getPagesAndPosts( $form );
		$data->cssSelector = Utils::generateFormHash( Utils::getFormType( $form ), Utils::getFormId( $form ), $form->name );
		if ( Main::FORM_LAYOUT_INLINE === $formTypeInfo->layout ) {
			$data->cardFieldLanguages = Languages::getStripeElementsLanguages();
		} else {
			$data->checkoutFormLanguages = Languages::getCheckoutLanguages();
		}
		$data->customFieldMaxCount = Main::getCustomFieldMaxCount( $this->staticContext );
		$data->customFieldLabels = Utils::decodeCustomFieldLabels( $form->customInputs );
		$data->customFields = CustomFields::toConfigJson( CustomFields::parse( $form->customInputs, $form->customInputRequired ) );
		$data->emailTemplates = $this->prepareEmailTemplates( $formType, $form );

		$type = $formTypeInfo->type;
		if ( Main::FORM_TYPE_PAYMENT === $type || Main::FORM_TYPE_DONATION === $type ) {
			$data->currencies = Currencies::getAvailableCurrencies();
		}
		if ( Main::FORM_TYPE_PAYMENT === $type ) {
			$data->products = $this->prepareOnetimeProducts( $form );
		}
		if ( Main::FORM_TYPE_SUBSCRIPTION === $type ) {
			$data->plans = $this->prepareRecurringProducts( $form );
		}
		if ( Main::FORM_TYPE_PAYMENT === $type || Main::FORM_TYPE_SUBSCRIPTION === $type ) {
			$data->stripeApiModeInteger = Admin::getApiModeIntegerFromString( $this->getStripeApiMode() );
			$data->taxRates = $this->prepareTaxRates( $form );
		}

		return $data;
	}

	/**
	 * @param $products array
	 *
	 * @return array
	 */
	protected function createProductsLookup( $products ) {
		$productsLookup = [];

		foreach ( $products as $product ) {
			$productsLookup[ $product->stripePriceId ] = $product;
		}

		return $productsLookup;
	}

	/**
	 * @param \StdClass $form
	 * @return array
	 */
	protected function prepareRecurringProducts( $form ) {
		$savedProducts = Utils::decodeJsonArray( $form->decoratedPlans );
		$priceIds = Pricing::extractPriceIdsFromProductsStatic( $savedProducts );
		$savedProductsLookup = $this->createProductsLookup( $savedProducts );

		$products = [];
		$productsLookup = [];

		$stripePrices = [];
		try {
			$stripePrices = $this->stripe->getRecurringPrices();
		} catch (\Exception $ex) {
			$this->logger->error( __FUNCTION__, 'Cannot retrieve Stripe recurring prices', $ex );
		}
		foreach ( $stripePrices as $stripePrice ) {
			if ( array_search( $stripePrice->id, $priceIds ) !== false ) {
				$mergedProduct = $savedProductsLookup[ $stripePrice->id ];

				$mergedProduct->name = $stripePrice->product->name;
				$mergedProduct->price = $stripePrice->unit_amount;
				$mergedProduct->currency = $stripePrice->currency;
				$mergedProduct->interval = $stripePrice->recurring->interval;
				$mergedProduct->intervalCount = $stripePrice->recurring->interval_count;

				$productsLookup[ $stripePrice->id ] = $mergedProduct;
			}
		}

		foreach ( $priceIds as $priceId ) {
			if ( array_key_exists( $priceId, $productsLookup ) ) {
				array_push( $products, $productsLookup[ $priceId ] );
			}
		}

		return $products;
	}

	function fullstripe_edit_form() {
		$this->requireCapability();

		$formType = $_GET[ Menu::PARAM_NAME_TYPE ];
		$formId = $_GET[ Menu::PARAM_NAME_FORM ];

		$pageTitle = $this->getEditFormTitle( $formType );
		$tabs = FormTabCreatorFactory::createFormTabCreator( $formType )->getTabs();
		$form = $this->db->getFormById( $formType, $formId );
		$pageSlug = Menu::SLUG_EDIT_FORM;
		$tabId = $tabs[0]['tab'];

		$stripeStatus = $this->getStripeStatus();
		$help = $this->helpService->getContextSensitiveHelp( $_REQUEST );
		[ $backLinkUrl, $backLinkLabel ] = $this->backToFormsLink();
		$formTypeInfo = FormTypes::get( $formType );
		$view = new $formTypeInfo->viewClass();
		$data = $this->prepareFormData( $formType, $form );

		$amountButtonDescription = null;
		if ( Main::FORM_TYPE_DONATION === $formTypeInfo->type ) {
			$amountButtonDescription =
				/* translators: Helper label describing how the {{amount}} placeholder token can be used for donation button labels */
				__( 'Insert {{amount}} to display the donation amount on the button', 'wp-full-stripe-free' );
		} elseif ( Main::FORM_TYPE_PAYMENT === $formTypeInfo->type ) {
			$amountButtonDescription =
				/* translators: Helper label describing how the {{amount}} placeholder token can be used for payment button labels */
				__( 'Insert {{amount}} to display the payment amount on the button', 'wp-full-stripe-free' );
		}
		// Inline forms describe their submit button, Checkout forms the button that opens Checkout.
		if ( null !== $amountButtonDescription ) {
			if ( Main::FORM_LAYOUT_INLINE === $formTypeInfo->layout ) {
				$submitButtonDescription = $amountButtonDescription;
			} else {
				$openButtonDescription = $amountButtonDescription;
			}
		}

		/** @noinspection PhpIncludeInspection */
		include Assets::templates( 'admin/wpfs-edit-form.php' );
	}

	/**
	 * Get the metadata for survey in Formbricks format.
	 * 
	 * @param array $dash_data The dashboard information.
	 * 
	 * @return array The metadata in Formbricks format.
	 */
	function get_survey_metadata( $dash_data ) {
		
		$option_name_prefix  = str_replace( '-', '_', strtolower( trim( WP_FULL_STRIPE_PRODUCT_SLUG ) ) );
		
		$install_days_number = intval( ( time() - get_option( $option_name_prefix . '_install', time() ) ) / DAY_IN_SECONDS );
		
		$install_category = 0;

		if ( 1 < $install_days_number && 8 > $install_days_number ) {
			$install_category = '7';
		} elseif ( 8 <= $install_days_number && 31 > $install_days_number ) {
			$install_category = '30';
		} elseif ( 30 < $install_days_number && 90 > $install_days_number ) {
			$install_category = '90';
		} elseif ( 90 <= $install_days_number ) {
			$install_category = '91';
		}

		$data = [
			'environmentId' => 'cm67ylr58000cky030t6vaa9b',
			'attributes'    => [
				'install_days_number' => (string) $install_days_number,
				'days_since_install'  => $install_category,
				'has_pro'             => $dash_data['has_pro'],
				'plan'                => intval( $dash_data['plan'] ),
				'stripe_connect'      => boolval( $dash_data['connected'] ) ? 'yes' : 'no',
				'stripe_mode'         => $dash_data['mode'],
				'stripe_transactions' => intval( $dash_data['transactions'] )
			]
		];

		$license_key = License::get_key();
		if ( ! empty( $license_key) ) {
			$data['attributes']['license_key'] = apply_filters( 'themeisle_sdk_secret_masking', $license_key );
		}

		return $data;
	}

	/**
	 * Get the navigation bar data.
	 * 
	 * @param array<string, array<string, array<string, string>>|string>|mixed $data The existing navigation bar data.
	 * @return array<string, array<string, array<string, string>>|string> The modified navigation bar data.
	 */
	public function get_nav_bar_data( $data ) {
		if ( ! is_array( $data ) ) {
			$data = [];
		}
		
		$current_screen = get_current_screen();
		
		$data['version_label'] = sprintf( 'v%s', Main::VERSION );
		$data['plan_type'] = License::is_active() ? 'pro' : 'free';
		$data['product_type_label'] = 'pro' === $data['plan_type'] ? 'Pro' : 'Free';
		$data['pages'] = [
			Menu::SLUG_FORMS => [
				'label' => __( 'Forms', 'wp-full-stripe-free' ),
				'url'   => Menu::getAdminUrlBySlug( Menu::SLUG_FORMS ),
			],
			Menu::SLUG_TRANSACTIONS => [
				'label' => __( 'Transactions', 'wp-full-stripe-free' ),
				'url'   => Menu::getAdminUrlBySlug( Menu::SLUG_TRANSACTIONS ),
			],
			Menu::SLUG_REPORTS => [
				'label' => __( 'Reports', 'wp-full-stripe-free' ),
				'url'   => Menu::getAdminUrlBySlug( Menu::SLUG_REPORTS ),
			],
			Menu::SLUG_SETTINGS => [
				'label' => __( 'Settings', 'wp-full-stripe-free' ),
				'url'   => Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS ),
			]
		];
		$data['current_page'] = $current_screen ? $current_screen->id : '';
		$data['stripe_mode'] = $this->getStripeApiMode() === 'live' ? __('Live', 'wp-full-stripe-free') : __('Test', 'wp-full-stripe-free');
		$data['change_log_url'] = 'https://wordpress.org/plugins/wp-full-stripe-free/#developers';
		$data['stripe_settings_url'] = Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE );

		return $data;
	}
}
