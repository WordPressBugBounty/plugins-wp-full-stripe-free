<?php

namespace ThemeIsle\WPFullPay\Help;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Main;

class HelpRepositoryFactory {

	const HELP_ARTICLE_BASE_URL = 'https://docs.themeisle.com';

	/**
	 * @return HelpRepository
	 */
	public static function create( $loggerService ) {
		$helpRepository = new HelpRepository( $loggerService );

		// tnagy default
		self::addEntry( $helpRepository, self::createDefaultPageHelp() );

		// tnagy forms
		self::addEntry( $helpRepository, self::createFormsPageHelp() );
		// tnagy create form
		self::addEntry( $helpRepository, self::createCreateFormPageHelp() );
		// tnagy edit forms by type
		self::addEntry( $helpRepository, self::createEditPaymentFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditCheckoutFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditSubscriptionFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditCheckoutSubscriptionFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditInlineDonationFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditCheckoutDonationFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditInlineSaveCardFormPageHelp() );
		self::addEntry( $helpRepository, self::createEditCheckoutSaveCardFormPageHelp() );
		// tnagy transactions
		self::addEntry( $helpRepository, self::createTransactionsPageHelp() );
		self::addEntry( $helpRepository, self::createOneTimePaymentsPageHelp() );
		self::addEntry( $helpRepository, self::createSubscriptionsPageHelp() );
		self::addEntry( $helpRepository, self::createDonationsPageHelp() );
		self::addEntry( $helpRepository, self::createSavedCardsPageHelp() );
		// tnagy settings
		self::addEntry( $helpRepository, self::createSettingsPageHelp() );
		self::addEntry( $helpRepository, self::createStripeSettingsPageHelp() );
		self::addEntry( $helpRepository, self::createFormsSettingsPageHelp() );
		self::addEntry( $helpRepository, self::createEmailSettingsOptionsPageHelp() );
        self::addEntry( $helpRepository, self::createEmailSettingsTemplatesPageHelp() );
		self::addEntry( $helpRepository, self::createSecuritySettingsPageHelp() );
		self::addEntry( $helpRepository, self::createCustomerPortalSettingsPageHelp() );
		self::addEntry( $helpRepository, self::createWordpressDashboardSettingsPageHelp() );

		return $helpRepository;
	}

	/**
	 * @param HelpRepository $helpRepository
	 * @param ContextHelp $contextHelp
	 */
	protected static function addEntry( $helpRepository, $contextHelp ) {
		$helpRepository->addHelp( $contextHelp, $contextHelp->getPage(), $contextHelp->getSection() );
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createDefaultPageHelp() {
		$page    = '';
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @param $page
	 *
	 * @return HelpArticle[]
	 */
	protected static function getDefaultRelatedArticlesArray( $page ) {
		return [
			self::createArticle(
				__( 'Set up Stripe account', 'wp-full-stripe-free' ),
				'/article/16-configuring-the-stripe-api-keys',
				HelpArticleVisualType::bookmark(),
				$page
			),
			self::createArticle(
				__( 'Set up Stripe webhook', 'wp-full-stripe-free' ),
				'article/2096-setting-up-webhooks',
				HelpArticleVisualType::bookmark(),
				$page
			)
		];
	}

	/**
	 * @param $caption
	 * @param $path
	 * @param $visualType
	 * @param $page
	 * @param string|null $campaign utm_campaign for docs links tracked with tsdk_utmify()
	 *
	 * @return HelpArticle
	 */
	protected static function createArticle( $caption, $path, $visualType, $page, $campaign = null ) {
		$articleUrl = self::buildArticleURL( $path, $page, $campaign );

		return new HelpArticle( $caption, $articleUrl, $visualType );
	}

	/**
	 * @param $path
	 * @param $utmContent
	 * @param string|null $campaign utm_campaign for docs links tracked with tsdk_utmify()
	 *
	 * @return string
	 */
	protected static function buildArticleURL( $path, $utmContent, $campaign = null ) {
		// Check if path is already a full URL
		$baseUrl = ( strpos( $path, 'http://' ) === 0 || strpos( $path, 'https://' ) === 0 )
			? $path
			: self::HELP_ARTICLE_BASE_URL . $path;

		// Docs links use the shared Themeisle UTM convention (wpadmin / help-menu / <campaign>).
		if ( $campaign !== null ) {
			return tsdk_utmify( $baseUrl, $campaign, 'help-menu' );
		}

		$articleUrl = add_query_arg(
			[
				'utm_source'   => 'plugin-wpfs',
				'utm_medium'   => 'help',
				'utm_campaign' => 'v' . Main::VERSION,
				'utm_content'  => $utmContent
			],
			$baseUrl
		);

		return $articleUrl;
	}

	/**
	 * @param $page
	 *
	 * @return HelpArticle[]
	 */
	protected static function getDefaultGlobalArticlesArray( $page ) {
		return [
			self::createArticle(
				__( 'Getting started guide', 'wp-full-stripe-free' ),
				'/wp-full-pay/installing-wp-full-pay',
				HelpArticleVisualType::gettingStarted(),
				$page,
				'getting-started'
			),
			self::createArticle(
				__( 'Search the documentation', 'wp-full-stripe-free' ),
				'/wp-full-pay/',
				HelpArticleVisualType::knowledgeBase(),
				$page,
				'search-docs'
			),
			self::createArticle(
				__( 'Leave feedback', 'wp-full-stripe-free' ),
				'https://paymentsplugin.com/contact/',
				HelpArticleVisualType::feedback(),
				$page
			)
		];
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createFormsPageHelp() {
		$page    = Menu::SLUG_FORMS;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'How to use shortcodes', 'wp-full-stripe-free' ),
                '/article/27-how-to-use-form-shortcodes',
                HelpArticleVisualType::bookmark(),
                $page
            ),
            self::createArticle(
                __( 'Introducing form types', 'wp-full-stripe-free' ),
                '/article/21-introducing-form-types',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createCreateFormPageHelp() {
		$page    = Menu::SLUG_CREATE_FORM;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

        $relatedArticles = [
            self::createArticle(
                __( 'Introducing form types', 'wp-full-stripe-free' ),
                '/article/21-introducing-form-types',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditPaymentFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_INLINE_PAYMENT;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditCheckoutFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_CHECKOUT_PAYMENT;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditSubscriptionFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_INLINE_SUBSCRIPTION;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditCheckoutSubscriptionFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditInlineDonationFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_INLINE_DONATION;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditCheckoutDonationFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_CHECKOUT_DONATION;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditInlineSaveCardFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
		$section = Main::FORM_TYPE_INLINE_SAVE_CARD;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEditCheckoutSaveCardFormPageHelp() {
		$page    = Menu::SLUG_EDIT_FORM;
        $section = Main::FORM_TYPE_CHECKOUT_SAVE_CARD;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createTransactionsPageHelp() {
		$page    = Menu::SLUG_TRANSACTIONS;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createOneTimePaymentsPageHelp() {
		$page    = Menu::SLUG_TRANSACTIONS;
		$section = Menu::PARAM_VALUE_TAB_PAYMENTS;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createSubscriptionsPageHelp() {
		$page    = Menu::SLUG_TRANSACTIONS;
		$section = Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createDonationsPageHelp() {
		$page    = Menu::SLUG_TRANSACTIONS;
		$section = Menu::PARAM_VALUE_TAB_DONATIONS;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createSavedCardsPageHelp() {
		$page    = Menu::SLUG_TRANSACTIONS;
		$section = Menu::PARAM_VALUE_TAB_SAVED_CARDS;

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createSettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createStripeSettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_STRIPE;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'Set up Stripe account', 'wp-full-stripe-free' ),
                '/article/16-configuring-the-stripe-api-keys',
                HelpArticleVisualType::bookmark(),
                $page
            ),
            self::createArticle(
                __( 'Set up Stripe webhook', 'wp-full-stripe-free' ),
                '/article/2096-setting-up-webhooks',
                HelpArticleVisualType::bookmark(),
                $page
            ),
            self::createArticle(
                __( 'Why plans disappear when changing API mode?', 'wp-full-stripe-free' ),
                '/article/52-all-subscription-plans-disappear-when-going-live',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createFormsSettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_FORMS;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'Customizing form styles', 'wp-full-stripe-free' ),
                '/article/2114-customizing-forms-with-css',
                HelpArticleVisualType::bookmark(),
                $page
            ),
            self::createArticle(
                __( 'How to translate labels', 'wp-full-stripe-free' ),
                '/article/44-translating-the-plugin-to-other-languages',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createEmailSettingsOptionsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS;
		$section = 'options';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'Configuring email notifications', 'wp-full-stripe-free' ),
                '/article/2101-configuring-email-notifications',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

    /**
     * @return ContextHelp
     */
    protected static function createEmailSettingsTemplatesPageHelp() {
        $page    = Menu::SLUG_SETTINGS_EMAIL_NOTIFICATIONS;
        $section = 'templates';

        $contextHelp = new ContextHelp( $page, $section );

        $relatedArticles = [
            self::createArticle(
                __( 'Using placeholder tokens', 'wp-full-stripe-free' ),
                '/article/29-using-placeholder-tokens',
                HelpArticleVisualType::bookmark(),
                $page
            ),
            self::createArticle(
                __( 'Configuring email notifications', 'wp-full-stripe-free' ),
                '/article/2101-configuring-email-notifications',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
        $globalArticles  = self::getDefaultGlobalArticlesArray( $page );

        $contextHelp->setRelatedArticles( $relatedArticles );
        $contextHelp->setGlobalArticles( $globalArticles );

        return $contextHelp;
    }

    /**
	 * @return ContextHelp
	 */
	protected static function createSecuritySettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_SECURITY;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'Using Google reCaptcha', 'wp-full-stripe-free' ),
                '/article/2099-registering-your-website-for-google-recaptcha',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createCustomerPortalSettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_CUSTOMER_PORTAL;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = [
            self::createArticle(
                __( 'Setting up Customer Portal', 'wp-full-stripe-free' ),
                '/article/2124-customer-portal',
                HelpArticleVisualType::bookmark(),
                $page
            ),
        ];
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

	/**
	 * @return ContextHelp
	 */
	protected static function createWordpressDashboardSettingsPageHelp() {
		$page    = Menu::SLUG_SETTINGS_WORDPRESS_DASHBOARD;
		$section = '';

		$contextHelp = new ContextHelp( $page, $section );

		$relatedArticles = self::getDefaultRelatedArticlesArray( $page );
		$globalArticles  = self::getDefaultGlobalArticlesArray( $page );

		$contextHelp->setRelatedArticles( $relatedArticles );
		$contextHelp->setGlobalArticles( $globalArticles );

		return $contextHelp;
	}

}
