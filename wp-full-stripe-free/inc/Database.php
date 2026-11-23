<?php

namespace ThemeIsle\WPFullPay;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Checkout\CheckoutSubmissionService;
use ThemeIsle\WPFullPay\CustomerPortal\CustomerPortalService;
use ThemeIsle\WPFullPay\Forms\FormType;
use ThemeIsle\WPFullPay\Forms\FormTypes;
use ThemeIsle\WPFullPay\Forms\Models\DonationFormModel;
use ThemeIsle\WPFullPay\Forms\Models\FormModel;
use ThemeIsle\WPFullPay\Forms\Models\PaymentFormModel;
use ThemeIsle\WPFullPay\Forms\Models\SubscriptionFormModel;
use ThemeIsle\WPFullPay\Forms\Views\DonationFormViewConstants;
use ThemeIsle\WPFullPay\Locale\Currencies;
use ThemeIsle\WPFullPay\TransactionData\PaymentTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SaveCardTransactionData;
use ThemeIsle\WPFullPay\TransactionData\SubscriptionTransactionData;

/**
 * @phpstan-import-type FormTypeKey from FormTypes
 * @phpstan-type ReportColumns array{created_at?: string, updated_at?: string, currency?: string|null, amount?: int|string|null, amount_refunded?: int, formId?: int|string|null, formType?: string|null, stripeSubscriptionID?: string|null, stripePaymentIntentID?: string|null, stripeCustomerID?: string|null, status?: string, mode?: 'live'|'test'}
 * @phpstan-type ReportRow object{id: numeric-string, created_at: string, updated_at: string, currency: string, amount: numeric-string, amount_refunded: numeric-string, formId: numeric-string|null, formType: string|null, stripeSubscriptionID: string|null, stripePaymentIntentID: string|null, stripeCustomerID: string|null, status: string, mode: string, net_amount: numeric-string}
 * @phpstan-type Address array{line1: string, line2: string, city: string, state: string, country: string, country_code: string, zip: string}
 * @phpstan-type AddressColumns array{billingName: string|null, addressLine1: string|null, addressLine2: string|null, addressCity: string|null, addressState: string|null, addressCountry: string|null, addressCountryCode: string|null, addressZip: string|null, shippingName: string|null, shippingAddressLine1: string|null, shippingAddressLine2: string|null, shippingAddressCity: string|null, shippingAddressState: string|null, shippingAddressCountry: string|null, shippingAddressCountryCode: string|null, shippingAddressZip: string|null}
 * @phpstan-type DonationColumns array{stripeCustomerID: string, stripeSubscriptionID: string|null, stripePaymentIntentID: string, stripeSetupIntentID: string|null, stripePlanID: string|null, description: string, paymentMethod: string, paid: bool, captured: bool, refunded: bool, expired: bool, failureCode: string|null, failureMessage: string|null, lastChargeStatus: string, currency: string, amount: int, donationFrequency: string, subscriptionStatus: string|null, name: string|null, email: string|null, billingName: string|null, addressLine1: string|null, addressLine2: string|null, addressCity: string|null, addressState: string|null, addressCountry: string|null, addressCountryCode: string|null, addressZip: string|null, shippingName: string|null, shippingAddressLine1: string|null, shippingAddressLine2: string|null, shippingAddressCity: string|null, shippingAddressState: string|null, shippingAddressCountry: string|null, shippingAddressCountryCode: string|null, shippingAddressZip: string|null, created: string, livemode: bool, formId: int|string, formType: string, formName: string, ipAddressSubmit: string|null, termsOfUseAcceptedAt: string|null, customFields: string, phoneNumber: string|null, afterChargeActionFired?: int}
 * @phpstan-type CheckoutFormSubmit object{id: string, hash: string, formHash: string, formType: string|null, referrer: string, postData: string, checkoutSessionId: string|null, liveMode: string|null, created: string, status: string, lastMessageTitle: string|null, lastMessage: string|null, processedWithError: string|null, errorMessage: string|null, relatedStripeEventIDs: string|null}
 */
class Database {
	const DATE_FORMAT_DATABASE = 'Y-m-d H:i:s';
	const TRANSIENT_KEY_PREFIX = 'fullstripe_';
	/** Widths of the fullstripe_payments columns; wpdb rejects the whole row when a value is longer. */
	const PAYMENT_BUSINESS_NAME_MAX_LENGTH = 255;
	const PAYMENT_TAX_ID_MAX_LENGTH = 100;

	/** Seconds a request waits for another one updating the same payment. */
	const PAYMENT_LOCK_TIMEOUT = 5;

	/** Columns saveReport() writes; identifiers cannot be prepared before WordPress 6.2. */
	const REPORT_COLUMNS = [ 'created_at', 'updated_at', 'currency', 'amount', 'amount_refunded', 'formId', 'formType', 'stripeSubscriptionID', 'stripePaymentIntentID', 'stripeCustomerID', 'status', 'mode' ];
	/** Form types the donation and payment reports count. */
	const REPORT_DONATION_FORM_TYPES = [ Main::FORM_TYPE_INLINE_DONATION, Main::FORM_TYPE_CHECKOUT_DONATION ];
	const REPORT_PAYMENT_FORM_TYPES = [ Main::FORM_TYPE_INLINE_PAYMENT, Main::FORM_TYPE_CHECKOUT_PAYMENT, Main::FORM_TYPE_INLINE_SUBSCRIPTION, Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION ];
	/** Statuses of the reports that count as revenue, and of the reports with a refund. */
	const REPORT_REVENUE_STATUSES = [ 'succeeded', 'partially_refunded' ];
	const REPORT_REFUND_STATUSES = [ 'partially_refunded', 'refunded' ];
	/** What a report counts as revenue. A subscription's report has no amount until its invoice is paid, but can already be refunded. */
	const REPORT_NET_AMOUNT = 'GREATEST(amount - amount_refunded, 0)';

	/**
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public static function fullstripe_setup_db() {
		// require for dbDelta()
		require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table = $wpdb->prefix . 'fullstripe_payments';

		$sql = "CREATE TABLE " . $table . " (
        paymentID INT NOT NULL AUTO_INCREMENT,
        eventID VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL,
        payment_method VARCHAR(100),
        paid TINYINT(1),
        captured TINYINT(1),
        refunded TINYINT(1),
        expired TINYINT(1),
        failure_code VARCHAR(100),
        failure_message VARCHAR(512),
        livemode TINYINT(1),
        last_charge_status VARCHAR(100),
        currency VARCHAR(3) NOT NULL,
        amount INT NOT NULL,
        fee INT NOT NULL,
        priceId VARCHAR(100),
        coupon VARCHAR(100),
        billingName VARCHAR(100),
        addressLine1 VARCHAR(500) NOT NULL,
        addressLine2 VARCHAR(500) NOT NULL,
        addressCity VARCHAR(500) NOT NULL,
        addressState VARCHAR(255) NOT NULL,
        addressZip VARCHAR(100) NOT NULL,
        addressCountry VARCHAR(100) NOT NULL,
        addressCountryCode VARCHAR(2) NOT NULL,
        shippingName VARCHAR(100),
        shippingAddressLine1 VARCHAR(500) NOT NULL,
        shippingAddressLine2 VARCHAR(500) NOT NULL,
        shippingAddressCity VARCHAR(500) NOT NULL,
        shippingAddressState VARCHAR(255) NOT NULL,
        shippingAddressZip VARCHAR(100) NOT NULL,
        shippingAddressCountry VARCHAR(100) NOT NULL,
        shippingAddressCountryCode VARCHAR(2) NOT NULL,
        created DATETIME NOT NULL,
        stripeCustomerID VARCHAR(100),
        name VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        formId INT,
        formType VARCHAR(30),
        formName VARCHAR(100),
        ipAddressSubmit VARCHAR(64),
        customFields TEXT,
        termsOfUseAcceptedAt DATETIME DEFAULT NULL,
        phoneNumber VARCHAR(64),
        businessName VARCHAR(255),
        taxIdType VARCHAR(100),
        taxId VARCHAR(100),
        PRIMARY KEY (paymentID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_payment_forms';

		$sql = "CREATE TABLE " . $table . " (
        paymentFormID INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        displayName VARCHAR(100),        
        formTitle VARCHAR(100) NOT NULL,
        chargeType VARCHAR(100) NOT NULL,
        amount INT NOT NULL,
        currency VARCHAR(3) NOT NULL,
        customAmount VARCHAR(32) NOT NULL,
        listOfAmounts VARCHAR(1024) DEFAULT NULL,
        decoratedProducts TEXT,
        minimumPaymentAmount INT default 0,
        allowListOfAmountsCustom TINYINT(1) DEFAULT '0',
        vatRateType VARCHAR(32),
        vatRates TEXT,
        paymentMethods TEXT,
        collectCustomerTaxId TINYINT(1) DEFAULT '0',
        generateInvoice TINYINT(1) DEFAULT '0',
        amountSelectorStyle VARCHAR(100) NOT NULL,
        buttonTitle VARCHAR(100) NOT NULL DEFAULT 'Make Payment',
        showButtonAmount TINYINT(1) DEFAULT '1',
        showEmailInput TINYINT(1) DEFAULT '1',
		showCouponInput TINYINT(1) DEFAULT '0',
        showCustomInput TINYINT(1) DEFAULT '0',
        customInputRequired TINYINT(1) DEFAULT '0',
        customInputTitle VARCHAR(100) NOT NULL DEFAULT 'Extra Information',
        customInputs TEXT,
        redirectOnSuccess TINYINT(1) DEFAULT '0',
        redirectPostID INT(5) DEFAULT 0,
        redirectUrl VARCHAR(1024) DEFAULT NULL,
        redirectToPageOrPost TINYINT(1) DEFAULT '1',
        showDetailedSuccessPage TINYINT(1) DEFAULT '0',
        showAddress TINYINT(1) DEFAULT '0',
		defaultBillingCountry VARCHAR(100),
        showShippingAddress TINYINT(1) DEFAULT '0',
        sendEmailReceipt TINYINT(1) DEFAULT '0',
        formStyle INT(5) DEFAULT 0,
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        preferredLanguage VARCHAR(16),
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32) NOT NULL,
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
        emailTemplates TEXT,
		webhook TEXT,
        stripeElementsTheme VARCHAR(32) NOT NULL DEFAULT 'stripe',
        stripeElementsFont VARCHAR(32),
        stripeElementsCustomCss TEXT,
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
		showPaymentDetail TINYINT(1) default '1',
        PRIMARY KEY (paymentFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$paymentType = Main::PAYMENT_TYPE_SPECIFIED_AMOUNT;
		// tnagy migrate old values
		$queryResult = $wpdb->update( $table, [ 'customAmount' => $paymentType ], [ 'customAmount' => '0' ], [ '%s' ], [ '%s' ] );
		self::handleDbError( $queryResult, 'Migration of fullstripe_payment_forms/customAmount failed!' );

		$paymentType = Main::PAYMENT_TYPE_CUSTOM_AMOUNT;
		$queryResult = $wpdb->update( $table, [ 'customAmount' => $paymentType ], [ 'customAmount' => '1' ], [ '%s' ], [ '%s' ] );
		self::handleDbError( $queryResult, 'Migration of fullstripe_payment_forms/customAmount failed!' );

		$table = $wpdb->prefix . 'fullstripe_subscription_forms';

		$sql = "CREATE TABLE " . $table . " (
        subscriptionFormID INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        displayName VARCHAR(100),
        formTitle VARCHAR(100) NOT NULL,
        plans VARCHAR(2048) NOT NULL,
        decoratedPlans TEXT,
        showCouponInput TINYINT(1) DEFAULT '0',
        showCustomInput TINYINT(1) DEFAULT '0',
        customInputRequired TINYINT(1) DEFAULT '0',
        customInputTitle VARCHAR(100) NOT NULL DEFAULT 'Extra Information',
        customInputs TEXT,
        redirectOnSuccess TINYINT(1) DEFAULT '0',
        redirectPostID INT(5) DEFAULT 0,
        redirectUrl VARCHAR(1024) DEFAULT NULL,
        redirectToPageOrPost TINYINT(1) DEFAULT '1',
        showDetailedSuccessPage TINYINT(1) DEFAULT '0',
        showAddress TINYINT(1) DEFAULT '0',
        defaultBillingCountry VARCHAR(100),
        showShippingAddress TINYINT(1) DEFAULT '0',
        sendEmailReceipt TINYINT(1) DEFAULT '0',
        formStyle INT(5) DEFAULT 0,
        buttonTitle VARCHAR(100) NOT NULL DEFAULT 'Subscribe',
        setupFee INT NOT NULL DEFAULT '0',
        vatRateType VARCHAR(32),
        vatRates TEXT,
        collectCustomerTaxId TINYINT(1) DEFAULT '0',
        vatPercent DECIMAL(7,4) DEFAULT 0.0,
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        planSelectorStyle VARCHAR(32) NOT NULL,
        allowMultipleSubscriptions TINYINT(1) DEFAULT '0',
        minimumQuantityOfSubscriptions INT(5) DEFAULT 0,
        maximumQuantityOfSubscriptions INT(5) DEFAULT 0,
        anchorBillingCycle TINYINT(1) DEFAULT '0',
        billingCycleAnchorDay TINYINT(2) DEFAULT '0',
        prorateUntilAnchorDay TINYINT(1) DEFAULT '1',
        preferredLanguage VARCHAR(16),
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32) NOT NULL,
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
        emailTemplates TEXT,
		webhook TEXT,
        stripeElementsTheme VARCHAR(32) NOT NULL DEFAULT 'stripe',
        stripeElementsFont VARCHAR(32),
        stripeElementsCustomCss TEXT,
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
		showPaymentDetail TINYINT(1) default '1',
        PRIMARY KEY (subscriptionFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_subscribers';

		$sql = "CREATE TABLE " . $table . " (
        subscriberID INT NOT NULL AUTO_INCREMENT,
        stripeCustomerID VARCHAR(100) NOT NULL,
        stripeSubscriptionID VARCHAR(100) NOT NULL,
        stripePaymentIntentID VARCHAR(100),
        stripeSetupIntentID VARCHAR(100),
		chargeMaximumCount INT(5) NOT NULL,
		chargeCurrentCount INT(5) NOT NULL,
		invoiceCreatedCount INT(5),		
		status VARCHAR(32) NOT NULL,
		cancelled DATETIME DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        planID VARCHAR(100) NOT NULL,
        quantity INT(5) DEFAULT 1,
        coupon VARCHAR(100),
        billingName VARCHAR(100),
        addressLine1 VARCHAR(500) NOT NULL,
        addressLine2 VARCHAR(500) NOT NULL,
        addressCity VARCHAR(500) NOT NULL,
        addressState VARCHAR(255) NOT NULL,
        addressZip VARCHAR(100) NOT NULL,
        addressCountry VARCHAR(100) NOT NULL,
        addressCountryCode VARCHAR(2) NOT NULL,
        shippingName VARCHAR(100),
        shippingAddressLine1 VARCHAR(500),
        shippingAddressLine2 VARCHAR(500),
        shippingAddressCity VARCHAR(500),
        shippingAddressState VARCHAR(255),
        shippingAddressZip VARCHAR(100),
        shippingAddressCountry VARCHAR(100),
        shippingAddressCountryCode VARCHAR(2),
        created DATETIME NOT NULL,
        livemode TINYINT(1),
        formId INT,
        formName VARCHAR(100),
        vatPercent DECIMAL(7,4) DEFAULT 0.0,
        processedStripeEventIDs TEXT,
        ipAddressSubmit VARCHAR(64),
        customFields TEXT,
        termsOfUseAcceptedAt DATETIME DEFAULT NULL,
        phoneNumber VARCHAR(64),
        PRIMARY KEY (subscriberID),
		KEY stripeSubscriptionID (stripeSubscriptionID),
		KEY stripePaymentIntentID (stripePaymentIntentID),
		KEY stripeSetupIntentID (stripeSetupIntentID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_checkout_forms';

		$sql = "CREATE TABLE " . $table . " (
        checkoutFormID INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        displayName VARCHAR(100),
        companyName VARCHAR(100) NOT NULL,
        productDesc VARCHAR(100) NOT NULL,
        chargeType VARCHAR(100) NOT NULL,
        amount INT NOT NULL,
        currency VARCHAR(3) NOT NULL,
        customAmount VARCHAR(32) NOT NULL,
        listOfAmounts VARCHAR(1024) DEFAULT NULL,
        decoratedProducts TEXT,
        minimumPaymentAmount INT default 0,
        vatRateType VARCHAR(32),
        vatRates TEXT,
        collectCustomerTaxId TINYINT(1) DEFAULT '0',
        generateInvoice TINYINT(1) DEFAULT '0',
        allowListOfAmountsCustom TINYINT(1) DEFAULT '0',
        amountSelectorStyle VARCHAR(100) NOT NULL,
        openButtonTitle VARCHAR(100) NOT NULL DEFAULT 'Pay With Card',
        buttonTitle VARCHAR(100) NOT NULL DEFAULT 'Pay {{amount}}',
        showButtonAmount TINYINT(1) DEFAULT '1',
        showBillingAddress TINYINT(1) DEFAULT '0',
        defaultBillingCountry VARCHAR(100),
        showShippingAddress TINYINT(1) DEFAULT '0',
		showCouponInput TINYINT(1) DEFAULT '0',
        showCustomInput TINYINT(1) DEFAULT '0',
        customInputRequired TINYINT(1) DEFAULT '0',
        customInputTitle VARCHAR(100) NOT NULL DEFAULT 'Extra Information',
        customInputs TEXT,
        sendEmailReceipt TINYINT(1) DEFAULT '0',
        showRememberMe TINYINT(1) DEFAULT '0',
        image VARCHAR(500) NOT NULL,
        redirectOnSuccess TINYINT(1) DEFAULT '0',
        redirectPostID INT(5) DEFAULT 0,
        redirectUrl VARCHAR(1024) DEFAULT NULL,
        redirectToPageOrPost TINYINT(1) DEFAULT '1',
        showDetailedSuccessPage TINYINT(1) DEFAULT '0',
        disableStyling TINYINT(1) DEFAULT 0,
        useBitcoin TINYINT(1) DEFAULT '0',
        useAlipay TINYINT(1) DEFAULT '0',
        preferredLanguage VARCHAR(16),
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32) NOT NULL,
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
        emailTemplates TEXT,
		webhook TEXT,
        collectPhoneNumber TINYINT(1) DEFAULT '0',
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
		showPaymentDetail TINYINT(1) default '1',
        PRIMARY KEY (checkoutFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}fullstripe_patch_info (
		id INT NOT NULL AUTO_INCREMENT,
		patch_id VARCHAR(191) NOT NULL,
		plugin_version VARCHAR(255) NOT NULL,
		applied_at DATETIME NOT NULL,
		description VARCHAR(500),
		PRIMARY KEY (id),
		KEY patch_id (patch_id)
		) $charset_collate;";

		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_checkout_subscription_forms';

		$sql = "CREATE TABLE " . $table . " (
		checkoutSubscriptionFormID INT NOT NULL AUTO_INCREMENT,
		name VARCHAR(100) NOT NULL,
		displayName VARCHAR(100),
		companyName VARCHAR(100) NOT NULL,
		productDesc VARCHAR(100) NOT NULL,
		image VARCHAR(500) NOT NULL,
		plans VARCHAR(2048) NOT NULL,
        decoratedPlans TEXT,
		showCouponInput TINYINT(1) DEFAULT '0',
		showCustomInput TINYINT(1) DEFAULT '0',
		customInputRequired TINYINT(1) DEFAULT '0',
		customInputTitle VARCHAR(100) NOT NULL DEFAULT 'Extra Information',
		customInputs TEXT,
		redirectOnSuccess TINYINT(1) DEFAULT '0',
		redirectPostID INT(5) DEFAULT 0,
		redirectUrl VARCHAR(1024) DEFAULT NULL,
		redirectToPageOrPost TINYINT(1) DEFAULT '1',
		showDetailedSuccessPage TINYINT(1) DEFAULT '0',
		showBillingAddress TINYINT(1) DEFAULT '0',
		showShippingAddress TINYINT(1) DEFAULT '0',
		sendEmailReceipt TINYINT(1) DEFAULT '0',
		disableStyling TINYINT(1) DEFAULT 0,
        openButtonTitle VARCHAR(100) NOT NULL DEFAULT 'Pay With Card',
		buttonTitle VARCHAR(100) NOT NULL DEFAULT 'Subscribe',
		showRememberMe TINYINT(1) DEFAULT '0',
        vatRateType VARCHAR(32),
        vatRates TEXT,
        collectCustomerTaxId TINYINT(1) DEFAULT '0',
        vatPercent DECIMAL(7,4) DEFAULT 0.0,
        defaultBillingCountry VARCHAR(100),
        simpleButtonLayout TINYINT(1) DEFAULT '0',
        preferredLanguage VARCHAR(16),
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        planSelectorStyle VARCHAR(32) NOT NULL,
        allowMultipleSubscriptions TINYINT(1) DEFAULT '0',
        minimumQuantityOfSubscriptions INT(5) DEFAULT 0,
        maximumQuantityOfSubscriptions INT(5) DEFAULT 0,
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32) NOT NULL,
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
		webhook TEXT,
        emailTemplates TEXT,
        collectPhoneNumber TINYINT(1) DEFAULT '0',
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
		showPaymentDetail TINYINT(1) default '1',
        PRIMARY KEY (checkoutSubscriptionFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_card_captures';

		$sql = "CREATE TABLE $table (
        captureID INT NOT NULL AUTO_INCREMENT,
        livemode TINYINT(1),
        billingName VARCHAR(100),
        addressLine1 VARCHAR(500) NOT NULL,
        addressLine2 VARCHAR(500) NOT NULL,
        addressCity VARCHAR(500) NOT NULL,
        addressState VARCHAR(255) NOT NULL,
        addressZip VARCHAR(100) NOT NULL,
        addressCountry VARCHAR(100) NOT NULL,
        addressCountryCode VARCHAR(2) NOT NULL,
        shippingName VARCHAR(100),
        shippingAddressLine1 VARCHAR(500) NOT NULL,
        shippingAddressLine2 VARCHAR(500) NOT NULL,
        shippingAddressCity VARCHAR(500) NOT NULL,
        shippingAddressState VARCHAR(255) NOT NULL,
        shippingAddressZip VARCHAR(100) NOT NULL,
        shippingAddressCountry VARCHAR(100) NOT NULL,
        shippingAddressCountryCode VARCHAR(2) NOT NULL,
        created DATETIME NOT NULL,
        stripeCustomerID VARCHAR(100),
        name VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        formId INT,
        formType VARCHAR(30),
        formName VARCHAR(100),
        ipAddressSubmit VARCHAR(64),
        customFields TEXT,
        termsOfUseAcceptedAt DATETIME DEFAULT NULL,
        stripeSetupIntentID VARCHAR(100),
        PRIMARY KEY (captureID),
        KEY stripeSetupIntentID (stripeSetupIntentID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}fullstripe_card_update_session (
		id INT NOT NULL AUTO_INCREMENT,
		hash VARCHAR(191) NOT NULL,
		email VARCHAR(191) NOT NULL,
		liveMode TINYINT(1),
		stripeCustomerId VARCHAR(100) NOT NULL,
		securityCodeRequest INT DEFAULT 0,
		securityCodeInput INT DEFAULT 0,
		created DATETIME NOT NULL,
		status VARCHAR(32) NOT NULL,
		PRIMARY KEY (id),
		KEY hash (hash),
		KEY email (email),
		KEY stripeCustomerId (stripeCustomerId),
		KEY status (status),
		KEY created (created)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}fullstripe_security_code (
		id INT NOT NULL AUTO_INCREMENT,
		sessionId INT NOT NULL,
		securityCode VARCHAR(191) NOT NULL,
		created DATETIME NOT NULL,
		sent DATETIME,
		consumed DATETIME,
		status VARCHAR(32) NOT NULL,
		PRIMARY KEY (id),
		KEY sessionId (sessionId),
		KEY securityCode (securityCode),
		KEY status (status)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}fullstripe_checkout_form_submit (
		id INT NOT NULL AUTO_INCREMENT,
		hash VARCHAR(191) NOT NULL,
		formHash VARCHAR(64) NOT NULL,
		formType VARCHAR(30),
		referrer VARCHAR(1024) NOT NULL,
		postData TEXT NOT NULL,
		checkoutSessionId VARCHAR(191),
		liveMode TINYINT(1),
		created DATETIME NOT NULL,
		status VARCHAR(32) NOT NULL,
		lastMessageTitle VARCHAR(256),
		lastMessage VARCHAR(1024),
		processedWithError INT DEFAULT 0,
		errorMessage VARCHAR(180),
		relatedStripeEventIDs TEXT,
		PRIMARY KEY (id),
		KEY hash (hash),
		KEY checkoutSessionId (checkoutSessionId),
		KEY status (status),
		KEY liveMode (liveMode),
		KEY liveModeStatus (liveMode, status)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->prefix}fullstripe_log (
		id INT NOT NULL AUTO_INCREMENT,
		created DATETIME NOT NULL,
		`module` VARCHAR(64) NOT NULL,
		`class` VARCHAR(128) NOT NULL,
		`function` VARCHAR(128) NOT NULL,
		`level` VARCHAR(16) NOT NULL,
		`message` VARCHAR(512) NOT NULL,
		`exception` TEXT NOT NULL,
		PRIMARY KEY (id),
		KEY created (created),
		KEY `module` (`module`),
		KEY `class` (`class`),
		KEY `function` (`function`),
		KEY `level` (`level`)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_donations';

		$sql = "CREATE TABLE " . $table . " (
        donationID INT NOT NULL AUTO_INCREMENT,
        stripeCustomerID VARCHAR(100) NOT NULL,
        stripePaymentIntentID VARCHAR(100) NOT NULL,
        stripeSubscriptionID VARCHAR(100),
        stripePlanID VARCHAR(100),
        stripeSetupIntentID VARCHAR(100),
        description VARCHAR(255),
        paymentMethod VARCHAR(100),
        paid TINYINT(1),
        captured TINYINT(1),
        refunded TINYINT(1),
        expired TINYINT(1),
        failureCode VARCHAR(100),
        failureMessage VARCHAR(512),
        lastChargeStatus VARCHAR(100),
        currency VARCHAR(3) NOT NULL,
        amount INT NOT NULL,
        donationFrequency VARCHAR(32),
		subscriptionStatus VARCHAR(32),
		cancelled DATETIME DEFAULT NULL,
        name VARCHAR(100),
        email VARCHAR(255),
        billingName VARCHAR(100),
        addressLine1 VARCHAR(500),
        addressLine2 VARCHAR(500),
        addressCity VARCHAR(500),
        addressState VARCHAR(255),
        addressZip VARCHAR(100),
        addressCountry VARCHAR(100),
        addressCountryCode VARCHAR(2),
        shippingName VARCHAR(100),
        shippingAddressLine1 VARCHAR(500),
        shippingAddressLine2 VARCHAR(500),
        shippingAddressCity VARCHAR(500),
        shippingAddressState VARCHAR(255),
        shippingAddressZip VARCHAR(100),
        shippingAddressCountry VARCHAR(100),
        shippingAddressCountryCode VARCHAR(2),
        created DATETIME NOT NULL,
        livemode TINYINT(1),
        formId INT,
        formType VARCHAR(30),
        formName VARCHAR(100),
        vatPercent DECIMAL(7,4) DEFAULT 0.0,
        processedStripeEventIDs TEXT,
        ipAddressSubmit VARCHAR(64),
        customFields TEXT,
        termsOfUseAcceptedAt DATETIME DEFAULT NULL,
        phoneNumber VARCHAR(64),
        afterChargeActionFired TINYINT(1) NOT NULL DEFAULT '1',
        PRIMARY KEY (donationID),
		KEY stripeSubscriptionID (stripeSubscriptionID),
		KEY stripePaymentIntentID (stripePaymentIntentID),
		KEY stripeSetupIntentID (stripeSetupIntentID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_donation_forms';

		$sql = "CREATE TABLE " . $table . " (
        donationFormID INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100),
        displayName VARCHAR(100),
        currency VARCHAR(3),
        minimumDonationAmount INT default 0,
        donationAmounts VARCHAR(1024) DEFAULT NULL,
        allowOneTimeDonation TINYINT(1) DEFAULT '0',
        allowCustomDonationAmount TINYINT(1) DEFAULT '0',
        allowDailyRecurring TINYINT(1) DEFAULT '0',
        allowWeeklyRecurring TINYINT(1) DEFAULT '0',
        allowMonthlyRecurring TINYINT(1) DEFAULT '0',
        allowAnnualRecurring TINYINT(1) DEFAULT '0',
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        productDesc VARCHAR(100),
        showDonationGoal TINYINT(1) DEFAULT '0',
        donationGoal INT default 0,
        generateInvoice TINYINT(1) DEFAULT '0',
        buttonTitle VARCHAR(100) DEFAULT 'Donate',
        showAddress TINYINT(1) DEFAULT '0',
		defaultBillingCountry VARCHAR(100),
        showShippingAddress TINYINT(1) DEFAULT '0',
        preferredLanguage VARCHAR(16),
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32),
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        showCustomInput TINYINT(1) DEFAULT '0',
        customInputRequired TINYINT(1) DEFAULT '0',
        customInputTitle VARCHAR(100) DEFAULT 'Extra Information',
        customInputs TEXT,
        sendEmailReceipt TINYINT(1) DEFAULT '0',
        redirectOnSuccess TINYINT(1) DEFAULT '0',
        redirectPostID INT(5) DEFAULT 0,
        redirectUrl VARCHAR(1024) DEFAULT NULL,
        redirectToPageOrPost TINYINT(1) DEFAULT '1',
        showDetailedSuccessPage TINYINT(1) DEFAULT '0',
        emailTemplates TEXT,
		webhook TEXT,
        stripeElementsTheme VARCHAR(32) NOT NULL DEFAULT 'stripe',
        stripeElementsFont VARCHAR(32),
        stripeElementsCustomCss TEXT,
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
        PRIMARY KEY (donationFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_checkout_donation_forms';

		$sql = "CREATE TABLE " . $table . " (
        checkoutDonationFormID INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(100),
        displayName VARCHAR(100),
        currency VARCHAR(3),
        minimumDonationAmount INT default 0,
        donationAmounts VARCHAR(1024) DEFAULT NULL,
        allowCustomDonationAmount TINYINT(1) DEFAULT '0',
        allowOneTimeDonation TINYINT(1) DEFAULT '0',
        allowDailyRecurring TINYINT(1) DEFAULT '0',
        allowWeeklyRecurring TINYINT(1) DEFAULT '0',
        allowMonthlyRecurring TINYINT(1) DEFAULT '0',
        allowAnnualRecurring TINYINT(1) DEFAULT '0',
        stripeDescription VARCHAR(1024) DEFAULT NULL,
        generateInvoice TINYINT(1) DEFAULT '0',
        companyName VARCHAR(100),
        productDesc VARCHAR(100),
        image VARCHAR(500),
        showDonationGoal TINYINT(1) DEFAULT '0',
        donationGoal INT default 0,
        openButtonTitle VARCHAR(100) DEFAULT 'Donate',
        buttonTitle VARCHAR(100) DEFAULT 'Donate',
        showBillingAddress TINYINT(1) DEFAULT '0',
		defaultBillingCountry VARCHAR(100),
        showShippingAddress TINYINT(1) DEFAULT '0',
        preferredLanguage VARCHAR(16),
        inheritLocale TINYINT(1) DEFAULT '1',
        decimalSeparator VARCHAR(32),
        showCurrencySymbolInsteadOfCode TINYINT(1) DEFAULT '1',
        showCurrencySignAtFirstPosition TINYINT(1) DEFAULT '1',
        putWhitespaceBetweenCurrencyAndAmount TINYINT(1) DEFAULT '0',
        showTermsOfUse TINYINT(1) DEFAULT '0',
        termsOfUseLabel VARCHAR(1024) DEFAULT NULL,
        termsOfUseNotCheckedErrorMessage VARCHAR(256) DEFAULT NULL,
        showCustomInput TINYINT(1) DEFAULT '0',
        customInputRequired TINYINT(1) DEFAULT '0',
        customInputTitle VARCHAR(100) DEFAULT 'Extra Information',
        customInputs TEXT,
        sendEmailReceipt TINYINT(1) DEFAULT '0',
        redirectOnSuccess TINYINT(1) DEFAULT '0',
        redirectPostID INT(5) DEFAULT 0,
        redirectUrl VARCHAR(1024) DEFAULT NULL,
        redirectToPageOrPost TINYINT(1) DEFAULT '1',
        showDetailedSuccessPage TINYINT(1) DEFAULT '0',
        emailTemplates TEXT,
		webhook TEXT,
        collectPhoneNumber TINYINT(1) DEFAULT '0',
		feeRecovery VARCHAR(32) default 'disable',
		feeRecoveryOptIn TINYINT(1) DEFAULT '1',
		feeRecoveryOptInMessage VARCHAR(1024) DEFAULT NULL,
		feeRecoveryFeePercentage VARCHAR(32) default '2.9',
		feeRecoveryFeeAdditionalAmount VARCHAR(32) default '0.30',
        PRIMARY KEY (checkoutDonationFormID)
        ) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		$table = $wpdb->prefix . 'fullstripe_reports';

		$sql = "CREATE TABLE " . $table . " (
		id INT NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		currency VARCHAR(3) NOT NULL,
		amount INT NOT NULL,
		amount_refunded INT NOT NULL DEFAULT 0,
		formId INT,
		formType VARCHAR(30),
		stripeSubscriptionID VARCHAR(100),
		stripePaymentIntentID VARCHAR(100),
		stripeCustomerID VARCHAR(100),
		status VARCHAR(32) NOT NULL,
		mode VARCHAR(32) NOT NULL,
		PRIMARY KEY (id)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		// A refund processed before its report exists, kept until saveReport() applies it.
		$table = $wpdb->prefix . 'fullstripe_report_refunds';

		$sql = "CREATE TABLE " . $table . " (
		stripePaymentIntentID VARCHAR(100) NOT NULL,
		amount_refunded INT NOT NULL,
		status VARCHAR(32) NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (stripePaymentIntentID),
		KEY created_at (created_at)
		) $charset_collate;";

		// database write/update
		dbDelta( $sql );

		do_action( 'fullstripe_setup_db' );

		return true;
	}

	/**
	 *
	 * @param $result
	 *
	 * @param $message
	 *
	 * @throws \Exception
	 */
	/**
	 * @param FormModel $formModel
	 * @return string|null When the customer accepted the terms of use, as a UTC database date.
	 */
	private static function formatTermsOfUseAcceptedAt( $formModel ) {
		$acceptedAt = $formModel->getTermsOfUseAcceptedAt();

		return is_null( $acceptedAt ) ? null : gmdate( self::DATE_FORMAT_DATABASE, $acceptedAt );
	}

	/**
	 * Converts the terms of use acceptance stamped in Stripe metadata to a UTC database date.
	 *
	 * @param mixed $metadataValue
	 * @return string|null Null when the value is missing or not a UTC ISO 8601 time.
	 */
	public static function termsOfUseAcceptedAtFromMetadata( $metadataValue ) {
		$acceptedAt = FormModel::parseTermsOfUseAcceptedAtMetadataValue( $metadataValue );

		return is_null( $acceptedAt ) ? null : gmdate( self::DATE_FORMAT_DATABASE, $acceptedAt );
	}

	/**
	 * Throws when the query that produced $result failed. Call it right after that query:
	 * a failed read returns null or an empty array, so only `$wpdb->last_error` tells it apart.
	 *
	 * @param mixed  $result
	 * @param string $message
	 *
	 * @throws \Exception
	 */
	public static function handleDbError( $result, $message ) {
		global $wpdb;
		if ( $result === false || '' !== $wpdb->last_error ) {
			Utils::log( sprintf( "%s: Raised exception with message=%s", 'WP Full Pay/Database', $message ) );
			Utils::log( sprintf( "%s: SQL last error=%s", 'WP Full Pay/Database', $wpdb->last_error ) );
			// Patches run while the plugin loads, before translations and where only the logs see the error.
			if ( ! did_action( 'init' ) ) {
				throw new \Exception( $message );
			}

			// After init the message can reach admins and visitors; the detail stays in the logs.
			throw new \Exception(
				/* translators: Error shown when the plugin can't save or read its data in the database */
				__( 'A database error occurred. Please try again later.', 'wp-full-stripe-free' ),
				0,
				new \Exception( $message )
			);
		}
	}

	/**
	 * @return array|null|object|void
	 */
	public static function get_site_ids() {
		global $wpdb;

		return $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = {$wpdb->siteid};" );
	}

	/**
	 * The donation plan of a recurring donation's subscription. One with a recovery fee line has two
	 * items and no plan of its own, so the plan is the item priced with a donation plan.
	 *
	 * @param object $subscription The Stripe subscription.
	 * @return string|null
	 */
	private function findDonationPlanId( $subscription ) {
		if ( isset( $subscription->plan->id ) ) {
			return $subscription->plan->id;
		}

		$items = isset( $subscription->items->data ) ? $subscription->items->data : [];
		foreach ( $items as $item ) {
			if ( isset( $item->price->lookup_key ) && 0 === strpos( $item->price->lookup_key, Main::DONATION_PLAN_ID_PREFIX ) ) {
				return $item->price->id;
			}
		}

		return null;
	}

	/**
	 * @param DonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param \StripeWPFS\Stripe\Subscription|null $subscription
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function insertInlineDonation( $donationFormModel, $paymentIntent, $subscription, $lastCharge ) {
		global $wpdb;

		$existingDonation = $this->getDonationByPaymentIntentId( $paymentIntent->id );
		if ( $existingDonation ) {
			return false;
		}

		$stripePlanID = $donationFormModel->isRecurringDonation() ? $this->findDonationPlanId( $subscription ) : null;
		$data = $this->donationData( $donationFormModel, $paymentIntent, $subscription, $lastCharge, $stripePlanID, $donationFormModel->getForm()->donationFormID, Main::FORM_TYPE_INLINE_DONATION );
		$data['afterChargeActionFired'] = 0;

		$insertResult = $wpdb->insert( $wpdb->prefix . 'fullstripe_donations', apply_filters( 'fullstripe_insert_donation_data', $data ) );
		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		$this->saveDonationReport( $data, $lastCharge );

		return $insertResult;
	}

	/**
	 * Unlike insertInlineDonation(), this doesn't look for the donation first: Checkout completion
	 * does, through isCheckoutPaymentProcessedByPaymentIntent().
	 *
	 * @param DonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param \StripeWPFS\Stripe\Subscription $subscription
	 * @param object|null $lastCharge
	 * @param string|null $donationPriceId The price of the session's donation line, which a recurring donation's item has.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function insertCheckoutDonation( $donationFormModel, $paymentIntent, $subscription, $lastCharge, $donationPriceId = null ) {
		global $wpdb;

		$stripePlanID = null;
		if ( $donationFormModel->isRecurringDonation() ) {
			if ( isset( $subscription->plan->id ) ) {
				$stripePlanID = $subscription->plan->id;
			} elseif ( ! is_null( $donationPriceId ) ) {
				// One with a recovery fee line has no plan of its own, and Stripe may list the fee's item first.
				$stripePlanID = $donationPriceId;
			} elseif ( isset( $subscription->items->data[0]->plan->id ) ) {
				$stripePlanID = $subscription->items->data[0]->plan->id;
			} elseif ( isset( $subscription->items->data[0]->price->id ) ) {
				$stripePlanID = $subscription->items->data[0]->price->id;
			}
		}

		$data = $this->donationData( $donationFormModel, $paymentIntent, $subscription, $lastCharge, $stripePlanID, $donationFormModel->getForm()->checkoutDonationFormID, Main::FORM_TYPE_CHECKOUT_DONATION );

		$insertResult = $wpdb->insert( $wpdb->prefix . 'fullstripe_donations', apply_filters( 'fullstripe_insert_checkout_donation_data', $data ) );
		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		$this->saveDonationReport( $data, $lastCharge );

		return $insertResult;
	}

	/**
	 * The columns of a donation, on either form type.
	 *
	 * @param DonationFormModel $donationFormModel
	 * @param \StripeWPFS\Stripe\PaymentIntent $paymentIntent
	 * @param \StripeWPFS\Stripe\Subscription|null $subscription Null for a one-time donation.
	 * @param object $lastCharge
	 * @param string|null $stripePlanID
	 * @param int|string $formId
	 * @param string $formType
	 *
	 * @return DonationColumns
	 */
	private function donationData( $donationFormModel, $paymentIntent, $subscription, $lastCharge, $stripePlanID, $formId, string $formType ): array {
		$isRecurring = $donationFormModel->isRecurringDonation();

		return [
			'stripeCustomerID' => $donationFormModel->getStripeCustomer()->id,
			'stripeSubscriptionID' => $isRecurring ? $subscription->id : null,
			'stripePaymentIntentID' => $paymentIntent->id,
			'stripeSetupIntentID' => $donationFormModel->getStripeSetupIntentId(),
			'stripePlanID' => $stripePlanID,
			'description' => $this->getTruncatedDescriptionFromPaymentIntent( $paymentIntent ),
			'paymentMethod' => 'card',
			'paid' => $lastCharge->paid,
			'captured' => $lastCharge->captured,
			'refunded' => $lastCharge->refunded,
			'expired' => false,
			'failureCode' => $lastCharge->failure_code,
			'failureMessage' => $lastCharge->failure_message,
			'lastChargeStatus' => $lastCharge->status,
			'currency' => $paymentIntent->currency,
			'amount' => $paymentIntent->amount,
			'donationFrequency' => $donationFormModel->getDonationFrequency(),
			'subscriptionStatus' => $isRecurring ? $subscription->status : null,
			'name' => $donationFormModel->getCardHolderName(),
			'email' => $donationFormModel->getCardHolderEmail(),
		] + self::addressColumns( $donationFormModel->getBillingName(), $donationFormModel->getBillingAddress(), $donationFormModel->getShippingName(), $donationFormModel->getShippingAddress() ) + [
			'created' => date( self::DATE_FORMAT_DATABASE, $paymentIntent->created ),
			'livemode' => $paymentIntent->livemode,
			'formId' => $formId,
			'formType' => $formType,
			'formName' => $donationFormModel->getForm()->name,
			'ipAddressSubmit' => $donationFormModel->getIpAddress(),
			'termsOfUseAcceptedAt' => self::formatTermsOfUseAcceptedAt( $donationFormModel ),
			'customFields' => $donationFormModel->getCustomFieldsJSON(),
			'phoneNumber' => empty( $donationFormModel->getCardHolderPhone() ) ? null : $donationFormModel->getCardHolderPhone(),
		];
	}

	/**
	 * Saves the report of a donation, from its columns before the insert filter changed them.
	 *
	 * @param DonationColumns $data
	 * @param object $lastCharge
	 *
	 * @throws \Exception
	 */
	private function saveDonationReport( array $data, $lastCharge ): void {
		if ( ! isset( $lastCharge->payment_intent ) ) {
			return;
		}

		$this->saveChargeReport(
			[
				'currency'              => $data['currency'],
				'amount'                => $data['amount'],
				'formId'                => $data['formId'],
				'formType'              => $data['formType'],
				'stripePaymentIntentID' => $data['stripePaymentIntentID'],
				'stripeCustomerID'      => $data['stripeCustomerID'],
				'stripeSubscriptionID'  => $data['stripeSubscriptionID'],
			],
			$lastCharge
		);
	}

	/**
	 * The name and address columns of a transaction. A null address, which getBillingAddress() and
	 * getShippingAddress() return for an empty one unless asked not to, stores null columns.
	 *
	 * @param string|null  $billingName
	 * @param Address|null $billingAddress
	 * @param string|null  $shippingName
	 * @param Address|null $shippingAddress
	 *
	 * @return AddressColumns
	 */
	private static function addressColumns( $billingName, ?array $billingAddress, $shippingName, ?array $shippingAddress ): array {
		return [
			'billingName' => $billingName,
			'addressLine1' => $billingAddress['line1'] ?? null,
			'addressLine2' => $billingAddress['line2'] ?? null,
			'addressCity' => $billingAddress['city'] ?? null,
			'addressState' => $billingAddress['state'] ?? null,
			'addressCountry' => $billingAddress['country'] ?? null,
			'addressCountryCode' => $billingAddress['country_code'] ?? null,
			'addressZip' => $billingAddress['zip'] ?? null,
			'shippingName' => $shippingName,
			'shippingAddressLine1' => $shippingAddress['line1'] ?? null,
			'shippingAddressLine2' => $shippingAddress['line2'] ?? null,
			'shippingAddressCity' => $shippingAddress['city'] ?? null,
			'shippingAddressState' => $shippingAddress['state'] ?? null,
			'shippingAddressCountry' => $shippingAddress['country'] ?? null,
			'shippingAddressCountryCode' => $shippingAddress['country_code'] ?? null,
			'shippingAddressZip' => $shippingAddress['zip'] ?? null,
		];
	}

	protected function getTruncatedDescriptionFromPaymentIntent( $paymentIntent ) {
		$description = isset( $paymentIntent->description ) && ! empty( $paymentIntent->description ) ? $paymentIntent->description : '';

		return Utils::truncateString( $description, 255 );
	}

	/**
	 * @param PaymentFormModel $paymentFormModel
	 * @param PaymentTransactionData $transactionData
	 * @return bool|int
	 * @throws \Exception
	 */
	public function insertOrUpdatePayment( $paymentFormModel, $transactionData, $lastCharge ) {
		global $wpdb;

		/** @var \StripeWPFS\Stripe\PaymentIntent */
		$paymentIntent = $paymentFormModel->getStripePaymentIntent();
		$description = $this->getTruncatedDescriptionFromPaymentIntent( $paymentIntent );
		$paymentMethodType = $paymentFormModel->getStripePaymentMethodType() ? $paymentFormModel->getStripePaymentMethodType() : 'card';

		$data = [
			'eventID' => $paymentIntent->id,
			'description' => $description,
			'payment_method' => $paymentMethodType,
			'paid' => $lastCharge->paid,
			'captured' => $lastCharge->captured,
			'refunded' => $lastCharge->refunded,
			'expired' => false,
			'failure_code' => $lastCharge->failure_code,
			'failure_message' => $lastCharge->failure_message,
			'livemode' => $paymentIntent->livemode,
			'last_charge_status' => $lastCharge->status,
			'currency' => $paymentIntent->currency,
			'amount' => $paymentIntent->amount,
			'fee' => ( isset( $paymentIntent->application_fee_amount ) && ! is_null( $paymentIntent->application_fee_amount ) ) ? $paymentIntent->application_fee_amount : 0,
			'priceId' => $paymentFormModel->getPriceId(),
			'coupon' => empty( $transactionData->getCouponCode() ) ? null : $transactionData->getCouponCode(),
		] + self::addressColumns( $paymentFormModel->getBillingName(), $paymentFormModel->getBillingAddress( false ), $paymentFormModel->getShippingName(), $paymentFormModel->getShippingAddress( false ) ) + [
			'created' => date( self::DATE_FORMAT_DATABASE, $paymentIntent->created ),
			'stripeCustomerID' => $paymentFormModel->getStripeCustomer() ? $paymentFormModel->getStripeCustomer()->id : null,
			'name' => $paymentFormModel->getCardHolderName(),
			'email' => $paymentFormModel->getCardHolderEmail(),
			'formId' => Utils::getFormId( $paymentFormModel->getForm() ),
			'formType' => Main::FORM_TYPE_PAYMENT,
			'formName' => $paymentFormModel->getFormName(),
			'ipAddressSubmit' => $paymentFormModel->getIpAddress(),
			'termsOfUseAcceptedAt' => self::formatTermsOfUseAcceptedAt( $paymentFormModel ),
			'customFields' => $paymentFormModel->getCustomFieldsJSON(),
			'phoneNumber' => empty( $paymentFormModel->getCardHolderPhone() ) ? null : $paymentFormModel->getCardHolderPhone(),
			'businessName' => $paymentFormModel->getBusinessName(),
			'taxIdType' => $paymentFormModel->getTaxIdType(),
			'taxId' => $paymentFormModel->getTaxId(),
		];
		// see if a payment with the same event ID already exists
		$existingPayment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fullstripe_payments WHERE eventID=%s",
				$paymentIntent->id
			)
		);
		$result = null;
		if ( $existingPayment ) {
			// Keep the acceptance the draft recorded: the request a redirect sends back posts the
			// terms checkbox unticked, and a retry must not move the time.
			if ( ! empty( $existingPayment->termsOfUseAcceptedAt ) ) {
				unset( $data['termsOfUseAcceptedAt'] );
			}
			// if exists update
			$updateData = apply_filters( 'fullstripe_update_payment_data', $data );
			// Its flags only rise: a webhook or an admin capture can record one after the row was read, while this request
			// writes a charge it read before, as when reading Stripe again failed.
			$flags = [];
			foreach ( [ 'paid', 'captured', 'refunded' ] as $flag ) {
				$flags[ $flag ] = empty( $updateData[ $flag ] ) ? 0 : 1;
				unset( $updateData[ $flag ] );
			}
			$status = $updateData['last_charge_status'] ?? null;
			unset( $updateData['last_charge_status'] );
			$result = $wpdb->update(
				$wpdb->prefix . 'fullstripe_payments',
				$updateData,
				[ 'eventID' => $paymentIntent->id ]
			);
			self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during update!' );
			$this->writePaymentChargeState( $paymentIntent->id, $flags, $status );

		} else {
			// if not insert
			$result = $wpdb->insert( $wpdb->prefix . 'fullstripe_payments', apply_filters( 'fullstripe_insert_payment_data', $data ) );
			self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during insert!' );
		}

		if ( ! isset( $lastCharge->payment_intent ) ) {
			return $result;
		}

		$this->saveChargeReport(
			[
				'currency'              => $data['currency'],
				'amount'                => $data['amount'],
				'formId'                => $data['formId'],
				'formType'              => Utils::getFormType( $paymentFormModel->getForm() ),
				'stripePaymentIntentID' => $data['eventID'],
				'stripeCustomerID'      => $data['stripeCustomerID'],
			],
			$lastCharge
		);

		return $result;
	}

	/**
	 * @param SubscriptionFormModel $subscriptionFormModel
	 * @param SubscriptionTransactionData $transactionData
	 * @param \StripeWPFS\Stripe\Charge|\stdClass|null $lastCharge The latest charge of the first invoice's PaymentIntent,
	 *        whose refund the report shows.
	 * @return bool|int
	 *
	 * @throws \Exception
	 */
	public function insertSubscriber( $subscriptionFormModel, $transactionData, $lastCharge = null ) {
		$stripeCustomer = $subscriptionFormModel->getStripeCustomer();
		$stripeSubscription = $subscriptionFormModel->getStripeSubscription();
		$stripePaymentIntent = $subscriptionFormModel->getStripePaymentIntent();
		$stripeSetupIntent = $subscriptionFormModel->getStripeSetupIntent();

		$planId = isset( $stripeSubscription->plan ) ? $stripeSubscription->plan->id : null;
		$quantity = $stripeSubscription->quantity;

		// A subscription with a fee recovery line has no plan or quantity of its own; the plan's item holds them. It is
		// the one priced with the subscribed plan, wherever Stripe lists it.
		if ( $stripeSubscription->items->total_count > 1 && isset( $stripeSubscription->items->data[0]->plan ) ) {
			$planItem = $stripeSubscription->items->data[0];
			foreach ( $stripeSubscription->items->data as $item ) {
				if ( isset( $item->plan->id ) && (string) $item->plan->id === (string) $subscriptionFormModel->getStripePlanId() ) {
					$planItem = $item;
					break;
				}
			}
			$planId = $planItem->plan->id;
			$quantity = $planItem->quantity;
		}

		$customerName = $subscriptionFormModel->getBillingName();
		if ( empty( $customerName ) ) {
			$customerName = $subscriptionFormModel->getCardHolderName();
		}

		$data = [
			'stripeCustomerID' => $stripeCustomer->id,
			'stripeSubscriptionID' => $transactionData->getTransactionId(),
			'stripePaymentIntentID' => isset( $stripePaymentIntent ) ? $stripePaymentIntent->id : null,
			'stripeSetupIntentID' => isset( $stripeSetupIntent ) ? $stripeSetupIntent->id : null,
			'chargeMaximumCount' => $subscriptionFormModel->getCancellationCount(),
			'chargeCurrentCount' => 0,
			'invoiceCreatedCount' => 0,
			'status' => Main::SUBSCRIBER_STATUS_INCOMPLETE,
			'name' => $customerName,
			'email' => $stripeCustomer->email,
			'planID' => $planId,
			'quantity' => $quantity,
			'coupon' => empty( $transactionData->getCouponCode() ) ? null : $transactionData->getCouponCode(),
		] + self::addressColumns( $subscriptionFormModel->getBillingName(), $subscriptionFormModel->getBillingAddress( false ), $subscriptionFormModel->getShippingName(), $subscriptionFormModel->getShippingAddress( false ) ) + [
			'created' => date( self::DATE_FORMAT_DATABASE, $stripeSubscription->created ),
			'livemode' => $stripeCustomer->livemode,
			'formId' => Utils::getFormId( $subscriptionFormModel->getForm() ),
			'formName' => $subscriptionFormModel->getForm()->name,
			'ipAddressSubmit' => $subscriptionFormModel->getIpAddress(),
			'termsOfUseAcceptedAt' => self::formatTermsOfUseAcceptedAt( $subscriptionFormModel ),
			'customFields' => $subscriptionFormModel->getCustomFieldsJSON(),
			'phoneNumber' => empty( $subscriptionFormModel->getCardHolderPhone() ) ? null : $subscriptionFormModel->getCardHolderPhone()
		];

		global $wpdb;
		$insertResult = $wpdb->insert( $wpdb->prefix . 'fullstripe_subscribers', apply_filters( 'fullstripe_insert_subscriber_data', $data ) );
		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		if ( ! isset( $data['stripePaymentIntentID'] ) ) {
			return $insertResult;
		}

		$this->saveSucceededReport(
			[
				'formId'                => $data['formId'],
				'formType'              => Utils::getFormType( $subscriptionFormModel->getForm() ),
				'stripePaymentIntentID' => $data['stripePaymentIntentID'],
				'stripeCustomerID'      => $data['stripeCustomerID'],
				'stripeSubscriptionID'  => $data['stripeSubscriptionID'],
			],
			$stripeSubscription
		);
		// A first payment refunded before the subscription was recorded; one still processing stays succeeded.
		if ( isset( $lastCharge->status ) && Main::STRIPE_CHARGE_STATUS_SUCCEEDED === $lastCharge->status && ! empty( $lastCharge->amount_refunded ) ) {
			$this->updateReportRefund( $data['stripePaymentIntentID'], (int) $lastCharge->amount_refunded, $this->getPaymentStatus( $lastCharge ) );
		}

		return $insertResult;
	}

	/**
	 * Sets a subscription whose first payment went through running, unless it was cancelled or ended meanwhile.
	 *
	 * @param string $stripePaymentIntentId
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateSubscriptionByPaymentIntentToRunning( $stripePaymentIntentId ) {
		return $this->updateSubscriptionToRunningUnlessEnded( 'stripePaymentIntentID', $stripePaymentIntentId );
	}

	/**
	 * @param 'stripePaymentIntentID'|'stripeSubscriptionID' $keyColumn
	 * @param string $key
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	private function updateSubscriptionToRunningUnlessEnded( $keyColumn, $key ) {
		global $wpdb;
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE {$keyColumn}=%s AND status NOT IN (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The column is one of two literals.
				Main::SUBSCRIBER_STATUS_RUNNING,
				$key,
				Main::SUBSCRIBER_STATUS_CANCELLED,
				Main::SUBSCRIBER_STATUS_ENDED
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * Sets a subscription incomplete since its first payment failed running, unless it ended or was cancelled since.
	 *
	 * @param string $stripeSubscriptionId
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateIncompleteSubscriptionToRunning( $stripeSubscriptionId ) {
		global $wpdb;
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE stripeSubscriptionID=%s AND status=%s",
				Main::SUBSCRIBER_STATUS_RUNNING,
				$stripeSubscriptionId,
				Main::SUBSCRIBER_STATUS_INCOMPLETE
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * Marks the subscription whose first payment failed incomplete, unless it ended or was cancelled.
	 *
	 * @param string $stripePaymentIntentId The PaymentIntent of its first invoice.
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateRunningSubscriptionByPaymentIntentToIncomplete( $stripePaymentIntentId ) {
		global $wpdb;
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE stripePaymentIntentID=%s AND status=%s",
				Main::SUBSCRIBER_STATUS_INCOMPLETE,
				$stripePaymentIntentId,
				Main::SUBSCRIBER_STATUS_RUNNING
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $stripeSetupIntentId
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateSubscriptionBySetupIntentToRunning( $stripeSetupIntentId ) {
		global $wpdb;
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE stripeSetupIntentID=%s",
				Main::SUBSCRIBER_STATUS_RUNNING,
				$stripeSetupIntentId
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $id
	 * @param $subscriber
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	function updateSubscriber( $id, $subscriber ) {
		global $wpdb;
		$updateResult = $wpdb->update( $wpdb->prefix . 'fullstripe_subscribers', $subscriber, [ 'subscriberID' => $id ] );
		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * Inserts a form of the given type.
	 *
	 * @param FormTypeKey $formType
	 * @param array<string,mixed> $form
	 *
	 * @return int|false
	 * @throws \Exception
	 */
	public function insertForm( string $formType, $form ) {
		global $wpdb;
		$insertResult = $wpdb->insert( $wpdb->prefix . FormTypes::get( $formType )->table, $form );
		self::handleDbError( $insertResult, __FUNCTION__ . "({$formType}): an error occurred during insert!" );

		return $insertResult;
	}

	/**
	 * Updates a form of the given type, dropping the columns its table doesn't have.
	 *
	 * @param FormTypeKey $formType
	 * @param int|string $id
	 * @param array<string,mixed> $form
	 *
	 * @return int|false
	 * @throws \Exception
	 */
	public function updateForm( string $formType, $id, $form ) {
		global $wpdb;
		$formTypeInfo = FormTypes::get( $formType );
		foreach ( $formTypeInfo->ignoredOnUpdate as $column ) {
			unset( $form[ $column ] );
		}

		$updateResult = $wpdb->update( $wpdb->prefix . $formTypeInfo->table, $form, [ $formTypeInfo->idColumn => $id ] );
		self::handleDbError( $updateResult, __FUNCTION__ . "({$formType}): an error occurred during update!" );

		return $updateResult;
	}

	/**
	 * @param FormTypeKey $formType
	 * @param int|string $id
	 *
	 * @return int|false
	 * @throws \Exception
	 */
	public function deleteForm( string $formType, $id ) {
		global $wpdb;
		$formTypeInfo = FormTypes::get( $formType );
		$queryResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}{$formTypeInfo->table} WHERE {$formTypeInfo->idColumn}=%d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names come from FormTypes.
		self::handleDbError( $queryResult, __FUNCTION__ . "({$formType}): an error occurred during delete!" );

		return $queryResult;
	}

	/**
	 * @param FormTypeKey $formType
	 * @param int|string $id
	 *
	 * @return \stdClass|null
	 */
	public function getFormById( string $formType, $id ) {
		global $wpdb;
		$formTypeInfo = FormTypes::get( $formType );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$formTypeInfo->table} WHERE {$formTypeInfo->idColumn}={$formTypeInfo->idPlaceholder}", $id ), OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names come from FormTypes.
	}

	/**
	 * @param FormTypeKey $formType
	 * @param int|string $id
	 *
	 * @return array<string, string|null>|null
	 */
	public function getFormAsArrayById( string $formType, $id ) {
		global $wpdb;
		$formTypeInfo = FormTypes::get( $formType );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$formTypeInfo->table} WHERE {$formTypeInfo->idColumn}=%s", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names come from FormTypes.
	}

	/**
	 * @param FormTypeKey $formType
	 * @param string $name
	 *
	 * @return \stdClass|null
	 */
	public function getFormByName( string $formType, $name ) {
		global $wpdb;
		$table = FormTypes::get( $formType )->table;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table} WHERE name=%s", $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names come from FormTypes.
	}

	/*
	 * The per-type form methods stay public: getFormNameByReport() builds the get*FormById()
	 * names at runtime, and other code calls them by name. Save-card forms use the payment methods.
	 */

	/** @return int|false */
	public function insertInlineSubscriptionForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $form );
	}

	/** @return int|false */
	public function updateInlineSubscriptionForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $id, $form );
	}

	/** @return int|false */
	public function insertCheckoutSubscriptionForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $form );
	}

	/** @return int|false */
	public function updateCheckoutSubscriptionForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $id, $form );
	}

	/** @return int|false */
	function insertInlinePaymentForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_INLINE_PAYMENT, $form );
	}

	/** @return int|false */
	function insertCheckoutPaymentForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_CHECKOUT_PAYMENT, $form );
	}

	/** @return int|false */
	function insertInlineDonationForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_INLINE_DONATION, $form );
	}

	/** @return int|false */
	function insertCheckoutDonationForm( $form ) {
		return $this->insertForm( Main::FORM_TYPE_CHECKOUT_DONATION, $form );
	}

	/** @return int|false */
	function updateInlineDonationForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_INLINE_DONATION, $id, $form );
	}

	/** @return int|false */
	function updateCheckoutDonationForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_CHECKOUT_DONATION, $id, $form );
	}

	/** @return int|false */
	function updateInlinePaymentForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_INLINE_PAYMENT, $id, $form );
	}

	/** @return int|false */
	function updateCheckoutPaymentForm( $id, $form ) {
		return $this->updateForm( Main::FORM_TYPE_CHECKOUT_PAYMENT, $id, $form );
	}

	/** @return int|false */
	function deleteInlinePaymentForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_INLINE_PAYMENT, $id );
	}

	/** @return int|false */
	function deleteInlineDonationForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_INLINE_DONATION, $id );
	}

	/** @return int|false */
	function deleteCheckoutDonationForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_CHECKOUT_DONATION, $id );
	}

	/** @return int|false */
	function deleteInlineSubscriptionForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $id );
	}

	/** @return int|false */
	function deleteCheckoutPaymentForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_CHECKOUT_PAYMENT, $id );
	}

	/** @return int|false */
	function deleteCheckoutSubscriptionForm( $id ) {
		return $this->deleteForm( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $id );
	}

	/**
	 * @param $id
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	function cancelSubscription( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE subscriberID=%d", Main::SUBSCRIBER_STATUS_CANCELLED, $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $id
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	function cancelDonationByDonationId( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_donations SET subscriptionStatus=%s WHERE donationID=%d", Main::SUBSCRIBER_STATUS_CANCELLED, $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $id
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	function deleteSubscriptionById( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_subscribers WHERE subscriberID=%d", $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $queryResult;
	}

	/**
	 * @param $stripeSubscriptionID
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function cancelSubscriptionByStripeSubscriptionId( $stripeSubscriptionID ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s,cancelled=NOW() WHERE stripeSubscriptionID=%s", Main::SUBSCRIBER_STATUS_CANCELLED, $stripeSubscriptionID ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * Undoes a cancellation of a recurring donation, which Stripe resumed.
	 *
	 * @param string $stripeSubscriptionID
	 * @param string $subscriptionStatus The status Stripe reports for the subscription.
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function activateDonationByStripeSubscriptionId( $stripeSubscriptionID, $subscriptionStatus ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_donations SET subscriptionStatus=%s,cancelled=NULL WHERE stripeSubscriptionID=%s", $subscriptionStatus, $stripeSubscriptionID ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $stripeSubscriptionID
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function cancelDonationByStripeSubscriptionId( $stripeSubscriptionID ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_donations SET subscriptionStatus=%s,cancelled=NOW() WHERE stripeSubscriptionID=%s", Main::SUBSCRIBER_STATUS_CANCELLED, $stripeSubscriptionID ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $stripeSubscriptionID
	 *
	 * @param $newQuantity
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateSubscriptionPlanByStripeSubscriptionId( $stripeSubscriptionID, $newPlanId ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_subscribers SET planID=%s WHERE stripeSubscriptionID=%s", $newPlanId, $stripeSubscriptionID ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param $stripeSubscriptionID
	 *
	 * @param $newQuantity
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateSubscriptionPlanAndQuantityByStripeSubscriptionId( $stripeSubscriptionID, $newPlanId, $newQuantity ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_subscribers SET planID=%s, quantity=%d WHERE stripeSubscriptionID=%s", $newPlanId, $newQuantity, $stripeSubscriptionID ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * Ends a subscription that hasn't ended or been cancelled. Stripe can deliver an event twice at once, so the
	 * row count tells the delivery that ended it from one that found it ended.
	 *
	 * @param $stripeSubscriptionID
	 *
	 * @return false|int The number of subscriptions ended.
	 * @throws \Exception
	 */
	public function endSubscription( $stripeSubscriptionID ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s,cancelled=NOW() WHERE stripeSubscriptionID=%s AND status NOT IN (%s, %s)", Main::SUBSCRIBER_STATUS_ENDED, $stripeSubscriptionID, Main::SUBSCRIBER_STATUS_ENDED, Main::SUBSCRIBER_STATUS_CANCELLED ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param string $stripeSubscriptionID
	 * @param string|null $processedStripeEventIDs
	 * @param string|null $stripeEventID When given, the row is only updated if this event has not been recorded yet.
	 * @param string|null $readStripeEventIDs When given, the row is only updated while its event IDs are these, '' for none.
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateSubscriberWithInvoiceAndEvent( $stripeSubscriptionID, $processedStripeEventIDs, ?string $stripeEventID = null, ?string $readStripeEventIDs = null ) {
		return $this->updateSubscriberEventIDs( 'invoiceCreatedCount=invoiceCreatedCount + 1, ', $stripeSubscriptionID, $processedStripeEventIDs, $stripeEventID, $readStripeEventIDs );
	}

	/**
	 * @param string $stripeSubscriptionID
	 * @param string|null $processedStripeEventIDs
	 * @param string|null $stripeEventID When given, the row is only updated if this event has not been recorded yet.
	 * @param string|null $readStripeEventIDs When given, the row is only updated while its event IDs are these, '' for none.
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateSubscriberWithPaymentAndEvent( $stripeSubscriptionID, $processedStripeEventIDs, ?string $stripeEventID = null, ?string $readStripeEventIDs = null ) {
		return $this->updateSubscriberEventIDs( 'chargeCurrentCount=chargeCurrentCount+1, ', $stripeSubscriptionID, $processedStripeEventIDs, $stripeEventID, $readStripeEventIDs );
	}

	/**
	 * @param string $stripeSubscriptionID
	 * @param string|null $processedStripeEventIDs
	 * @param string|null $readStripeEventIDs When given, the row is only updated while its event IDs are these, '' for none.
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateSubscriberWithEvent( $stripeSubscriptionID, $processedStripeEventIDs, ?string $readStripeEventIDs = null ) {
		return $this->updateSubscriberEventIDs( '', $stripeSubscriptionID, $processedStripeEventIDs, null, $readStripeEventIDs );
	}

	/**
	 * @param 'invoiceCreatedCount=invoiceCreatedCount + 1, '|'chargeCurrentCount=chargeCurrentCount+1, '|'' $counterUpdate What the
	 *        update sets besides the event IDs.
	 * @param string $stripeSubscriptionID
	 * @param string|null $processedStripeEventIDs
	 * @param string|null $stripeEventID
	 * @param string|null $readStripeEventIDs
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	private function updateSubscriberEventIDs( $counterUpdate, $stripeSubscriptionID, $processedStripeEventIDs, $stripeEventID, $readStripeEventIDs ) {
		global $wpdb;

		$query = "UPDATE {$wpdb->prefix}fullstripe_subscribers SET {$counterUpdate}processedStripeEventIDs=%s WHERE stripeSubscriptionID=%s";
		$args = [ $processedStripeEventIDs, $stripeSubscriptionID ];
		if ( ! empty( $stripeEventID ) ) {
			// The event-ID guard keeps concurrent duplicate deliveries from both incrementing the counter.
			$query .= ' AND (processedStripeEventIDs IS NULL OR processedStripeEventIDs NOT LIKE %s)';
			$args[] = '%' . $wpdb->esc_like( $stripeEventID ) . '%';
		}
		if ( ! is_null( $readStripeEventIDs ) ) {
			// Keeps an event another delivery added since the IDs were read.
			$query .= " AND COALESCE(processedStripeEventIDs, '')=%s";
			$args[] = $readStripeEventIDs;
		}
		$queryResult = $wpdb->query( $wpdb->prepare( $query, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from literals; the values are placeholders.
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * @param string $submitHash
	 * @param string|null $relatedStripeEventIDs
	 * @param string|null $readStripeEventIDs When given, the row is only updated while its event IDs are these, '' for none.
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updatePopupFormSubmitWithEvent( $submitHash, $relatedStripeEventIDs, ?string $readStripeEventIDs = null ) {
		global $wpdb;
		$query = "UPDATE {$wpdb->prefix}fullstripe_checkout_form_submit SET relatedStripeEventIDs=%s WHERE hash=%s";
		$args = [ $relatedStripeEventIDs, $submitHash ];
		if ( ! is_null( $readStripeEventIDs ) ) {
			$query .= " AND COALESCE(relatedStripeEventIDs, '')=%s";
			$args[] = $readStripeEventIDs;
		}
		$queryResult = $wpdb->query( $wpdb->prepare( $query, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from literals; the values are placeholders.
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 *
	 * @param $id
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	function deletePayment( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_payments WHERE paymentID=%d", $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $queryResult;
	}

	/**
	 *
	 * @param $id
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	function deleteDonation( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_donations WHERE donationID=%d", $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $queryResult;
	}

	////////////////////////////////////////////////////////////////////////////////////////////

	function deleteSavedCard( $id ) {
		global $wpdb;
		$queryResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_card_captures WHERE captureID=%d", $id ) );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $queryResult;
	}

	/**
	 * Every form of the given type, as arrays.
	 *
	 * @param FormTypeKey $formType
	 *
	 * @return list<array<string, string|null>>
	 * @throws \Exception
	 */
	public function getFormsAsArray( string $formType ): array {
		$queryResult = $this->selectFormRows( FormTypes::get( $formType ), '*', ARRAY_A );
		self::handleDbError( $queryResult, __FUNCTION__ . "({$formType}): an error occurred during querying forms!" );

		return $queryResult;
	}

	/** @return list<array<string, string|null>> */
	function getInlineSaveCardFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_INLINE_SAVE_CARD );
	}

	/** @return array<string, string|null>|null */
	function getInlinePaymentFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_INLINE_PAYMENT, $id );
	}

	/** @return \stdClass|null */
	function getInlinePaymentFormById( $id ) {
		return $this->getFormById( Main::FORM_TYPE_INLINE_PAYMENT, $id );
	}

	/** @return \stdClass|null */
	function getInlinePaymentFormByName( $name ) {
		return $this->getFormByName( Main::FORM_TYPE_INLINE_PAYMENT, $name );
	}

	/** @return list<array<string, string|null>> */
	function getInlinePaymentFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_INLINE_PAYMENT );
	}

	/** @return \stdClass|null */
	function getInlineSubscriptionFormById( $id ) {
		return $this->getFormById( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $id );
	}

	/** @return array<string, string|null>|null */
	function getInlineSubscriptionFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $id );
	}

	/** @return \stdClass|null */
	function getInlineSubscriptionFormByName( $name ) {
		return $this->getFormByName( Main::FORM_TYPE_INLINE_SUBSCRIPTION, $name );
	}

	/** @return list<array<string, string|null>> */
	function getInlineSubscriptionFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_INLINE_SUBSCRIPTION );
	}

	/** @return \stdClass|null */
	public function getCheckoutSubscriptionFormById( $formId ) {
		return $this->getFormById( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $formId );
	}

	/** @return array<string, string|null>|null */
	function getCheckoutSubscriptionFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $id );
	}

	/** @return \stdClass|null */
	public function getCheckoutPaymentFormByName( $formName ) {
		return $this->getFormByName( Main::FORM_TYPE_CHECKOUT_PAYMENT, $formName );
	}

	/** @return array<string, string|null>|null */
	public function getCheckoutPaymentFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_CHECKOUT_PAYMENT, $id );
	}

	/** @return \stdClass|null */
	public function getCheckoutPaymentFormById( $id ) {
		return $this->getFormById( Main::FORM_TYPE_CHECKOUT_PAYMENT, $id );
	}

	/** @return list<array<string, string|null>> */
	function getCheckoutPaymentFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_CHECKOUT_PAYMENT );
	}

	/** @return list<array<string, string|null>> */
	function getCheckoutSaveCardFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_CHECKOUT_SAVE_CARD );
	}

	/** @return \stdClass|null */
	public function getCheckoutSubscriptionFormByName( $formName ) {
		return $this->getFormByName( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION, $formName );
	}

	/** @return list<array<string, string|null>> */
	function getCheckoutSubscriptionFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_CHECKOUT_SUBSCRIPTION );
	}

	/** @return array<string, string|null>|null */
	function getInlineDonationFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_INLINE_DONATION, $id );
	}

	/** @return list<array<string, string|null>> */
	function getInlineDonationFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_INLINE_DONATION );
	}

	/** @return list<array<string, string|null>> */
	function getCheckoutDonationFormsAsArray() {
		return $this->getFormsAsArray( Main::FORM_TYPE_CHECKOUT_DONATION );
	}

	/** @return \stdClass|null */
	function getInlineDonationFormById( $id ) {
		return $this->getFormById( Main::FORM_TYPE_INLINE_DONATION, $id );
	}

	/** @return \stdClass|null */
	public function getInlineDonationFormByName( $formName ) {
		return $this->getFormByName( Main::FORM_TYPE_INLINE_DONATION, $formName );
	}

	/** @return array<string, string|null>|null */
	function getCheckoutDonationFormAsArrayById( $id ) {
		return $this->getFormAsArrayById( Main::FORM_TYPE_CHECKOUT_DONATION, $id );
	}

	/** @return \stdClass|null */
	public function getCheckoutDonationFormByName( $formName ) {
		return $this->getFormByName( Main::FORM_TYPE_CHECKOUT_DONATION, $formName );
	}

	/** @return \stdClass|null */
	public function getCheckoutDonationFormById( $id ) {
		return $this->getFormById( Main::FORM_TYPE_CHECKOUT_DONATION, $id );
	}

	/**
	 *
	 * return customers from the payment and subscriber tables where the email address and the mode match
	 *
	 * @param $email
	 * @param $livemode
	 *
	 * @return array
	 */
	public function getExistingStripeCustomersByEmail( $email, $livemode ) {
		global $wpdb;

		$subscribers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_subscribers WHERE UPPER(email)=%s AND livemode=%s GROUP BY StripeCustomerID;", strtoupper( $email ), $livemode ? '1' : '0' ), ARRAY_A );
		$donors = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_donations WHERE UPPER(email)=%s AND livemode=%s GROUP BY StripeCustomerID;", strtoupper( $email ), $livemode ? '1' : '0' ), ARRAY_A );
		$payees = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_payments WHERE UPPER(email)=%s AND livemode=%s GROUP BY StripeCustomerID;", strtoupper( $email ), $livemode ? '1' : '0' ), ARRAY_A );
		$cards = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_card_captures WHERE UPPER(email)=%s AND livemode=%s GROUP BY StripeCustomerID;", strtoupper( $email ), $livemode ? '1' : '0' ), ARRAY_A );

		$result = array_merge( $subscribers, $donors, $payees, $cards );

		return $result;
	}

	/**
	 * @param $id
	 *
	 * @return array|null|object|void
	 */
	public function findSubscriberById( $id ) {
		global $wpdb;
		$subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_subscribers WHERE subscriberID=%d", $id ) );

		return $subscription;
	}

	/**
	 * @param $stripePaymentIntentId
	 *
	 * @return array|null|object|void
	 */
	public function findSubscriberByPaymentIntentId( $stripePaymentIntentId ) {
		global $wpdb;
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fullstripe_subscribers WHERE stripePaymentIntentId=%s",
				$stripePaymentIntentId
			)
		);

		return $subscription;
	}

	/**
	 * @param $stripeSetupIntentId
	 *
	 * @return array|null|object|void
	 */
	public function findSubscriberBySetupIntentId( $stripeSetupIntentId ) {
		global $wpdb;
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fullstripe_subscribers WHERE stripeSetupIntentId=%s",
				$stripeSetupIntentId
			)
		);

		return $subscription;
	}

	/**
	 * @param $stripeSubscriptionId
	 *
	 * @return object|null
	 */
	public function getSubscriptionByStripeSubscriptionId( $stripeSubscriptionId ) {
		global $wpdb;
		$subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_subscribers WHERE stripeSubscriptionID=%s", $stripeSubscriptionId ) );

		return $subscription;
	}

	/**
	 * @param PaymentFormModel $paymentFormModel 
	 * @param SaveCardTransactionData $transactionData 
	 * @param string|null $stripeSetupIntentId The SetupIntent Checkout saved the card with.
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function insertSavedCard( $paymentFormModel, $transactionData, $stripeSetupIntentId = null ) {
		global $wpdb;

		/** @var \StripeWPFS\Stripe\Customer $stripeCustomer */
		$stripeCustomer = $paymentFormModel->getStripeCustomer();

		$data = [
			'livemode' => $stripeCustomer->livemode,
		] + self::addressColumns( $paymentFormModel->getBillingName(), $paymentFormModel->getBillingAddress( false ), $paymentFormModel->getShippingName(), $paymentFormModel->getShippingAddress( false ) ) + [
			'created' => date( self::DATE_FORMAT_DATABASE, $stripeCustomer->created ),
			'stripeCustomerID' => $stripeCustomer->id,
			'name' => $paymentFormModel->getCardHolderName(),
			'email' => $stripeCustomer->email,
			'formId' => Utils::getFormId( $paymentFormModel->getForm() ),
			'formType' => Main::FORM_TYPE_PAYMENT,
			'formName' => $paymentFormModel->getFormName(),
			'ipAddressSubmit' => $paymentFormModel->getIpAddress(),
			'termsOfUseAcceptedAt' => self::formatTermsOfUseAcceptedAt( $paymentFormModel ),
			'customFields' => $paymentFormModel->getCustomFieldsJSON()
		];
		if ( ! is_null( $stripeSetupIntentId ) ) {
			$data['stripeSetupIntentID'] = $stripeSetupIntentId;
		}

		$insertResult = $wpdb->insert( $wpdb->prefix . 'fullstripe_card_captures', apply_filters( 'fullstripe_insert_card_data', $data ) );
		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		return $insertResult;
	}

	/**
	 * Insert card update session
	 *
	 * @param $email
	 * @param $liveMode
	 * @param $stripeCustomerId
	 * @param $cardUpdateSessionHash
	 *
	 * @return int -1 when insert failed, the inserted record id otherwise
	 * @throws \Exception
	 */
	public function insertCustomerPortalSession( $email, $liveMode, $stripeCustomerId, $cardUpdateSessionHash ) {
		global $wpdb;

		$insertResult = $wpdb->insert( "{$wpdb->prefix}fullstripe_card_update_session", [
			'hash' => $cardUpdateSessionHash,
			'email' => $email,
			'liveMode' => $liveMode,
			'stripeCustomerId' => $stripeCustomerId,
			'created' => current_time( 'mysql' ),
			'status' => CustomerPortalService::SESSION_STATUS_WAITING_FOR_CONFIRMATION
		] );

		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		if ( $insertResult === false ) {
			return -1;
		}

		return $wpdb->insert_id;
	}

	/**
	 * @param string $hash
	 * @param string $formHash
	 * @param string $formType
	 * @param string $referrer
	 * @param string $postData JSON
	 * @param boolean $liveMode
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function insertCheckoutFormSubmit( $hash, $formHash, $formType, $referrer, $postData, $liveMode ) {
		global $wpdb;

		$insertResult = $wpdb->insert( "{$wpdb->prefix}fullstripe_checkout_form_submit", [
			'hash' => $hash,
			'formHash' => $formHash,
			'formType' => $formType,
			'referrer' => $referrer,
			'postData' => $postData,
			'liveMode' => $liveMode,
			'created' => current_time( 'mysql' ),
			'status' => CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_CREATED
		]
		);

		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		if ( $insertResult === false ) {
			return -1;
		}

		return $wpdb->insert_id;
	}

	/**
	 * @param $hash
	 *
	 * @return CheckoutFormSubmit|null
	 */
	public function findPopupFormSubmitByHash( $hash ) {
		global $wpdb;

		$popupFormSubmit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_checkout_form_submit WHERE hash=%s", $hash ) );

		return $popupFormSubmit;
	}

	/**
	 * The Checkout form submissions not finished yet, oldest first, after the one with the given ID.
	 *
	 * @param boolean $liveMode
	 * @param int $afterId
	 * @param int $limit
	 * @param int|null $upToId The last ID to return, none when null.
	 *
	 * @return list<CheckoutFormSubmit>
	 */
	public function findUnfinishedCheckoutFormSubmits( $liveMode, $afterId, $limit, $upToId = null ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fullstripe_checkout_form_submit WHERE liveMode=%d AND status IN (%s, %s) AND id>%d AND id<=%d ORDER BY id LIMIT %d",
				$liveMode,
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_CREATED,
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_PENDING,
				$afterId,
				is_null( $upToId ) ? PHP_INT_MAX : $upToId,
				$limit
			)
		);
	}

	/**
	 * @return int The ID of the newest Checkout form submission, 0 when there is none.
	 */
	public function findLastCheckoutFormSubmitId(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->prefix}fullstripe_checkout_form_submit" );
	}

	/**
	 * @param string $createdBefore A local time, as the submissions record it.
	 *
	 * @return int How many were deleted.
	 */
	public function deleteFinishedCheckoutFormSubmits( $createdBefore ) {
		global $wpdb;

		$deleteResult = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}fullstripe_checkout_form_submit WHERE status IN (%s, %s, %s, %s) AND created<%s",
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_SUCCESS,
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_COMPLETE,
				CheckoutSubmissionService::POPUP_FORM_SUBMIT_STATUS_FAILED,
				CheckoutSubmissionService::CHECKOUT_SESSION_STATUS_CANCELLED,
				$createdBefore
			)
		);

		self::handleDbError( $deleteResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $deleteResult;
	}

	/**
	 * @param $status
	 * @param array $idsToUpdate
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function update_popup_form_submits_with_status_by_id( $status, $idsToUpdate ) {
		global $wpdb;

		$whereStatement = ' WHERE id IN (' . implode( ', ', array_fill( 0, sizeof( $idsToUpdate ), '%s' ) ) . ')';
		$updateResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_checkout_form_submit SET status=%s" . $whereStatement,array_merge( [ $status ], $idsToUpdate ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * Takes a Checkout form submission for this request until it releases it or ends, waiting for another request
	 * holding it.
	 *
	 * @param string $hash
	 * @param int $timeout Seconds to wait.
	 *
	 * @return bool False when another request still holds it. True when the database has no named locks, so that
	 *              the submission is completed as before they were taken.
	 */
	public function lockCheckoutFormSubmit( $hash, $timeout ) {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::getCheckoutFormSubmitLockName( $hash ), $timeout ) );
	}

	/**
	 * @param string $hash
	 */
	public function unlockCheckoutFormSubmit( $hash ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::getCheckoutFormSubmitLockName( $hash ) ) );
	}

	/**
	 * Lock names are server-wide and 64 characters at most.
	 *
	 * @param string $hash
	 *
	 * @return string
	 */
	private static function getCheckoutFormSubmitLockName( $hash ) {
		global $wpdb;

		return 'wpfs_checkout_' . md5( $wpdb->prefix . $hash );
	}

	/**
	 * Takes a payment for this request until it releases it or ends, waiting for another request holding it.
	 *
	 * @param string $paymentIntentId
	 * @param int $timeout Seconds to wait.
	 *
	 * @return bool False when another request still holds it. True when the database has no named locks.
	 */
	public function lockPaymentIntent( $paymentIntentId, $timeout ) {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::getPaymentIntentLockName( $paymentIntentId ), $timeout ) );
	}

	/**
	 * @param string $paymentIntentId
	 */
	public function unlockPaymentIntent( $paymentIntentId ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::getPaymentIntentLockName( $paymentIntentId ) ) );
	}

	/**
	 * @param string $paymentIntentId
	 *
	 * @return string
	 */
	private static function getPaymentIntentLockName( $paymentIntentId ) {
		global $wpdb;

		return 'wpfs_payment_' . md5( $wpdb->prefix . $paymentIntentId );
	}

	/**
	 * @param $popupFormSubmitHash
	 * @param $data
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function update_popup_form_submit_by_hash( $popupFormSubmitHash, $data ) {
		global $wpdb;

		$updateResult = $wpdb->update( "{$wpdb->prefix}fullstripe_checkout_form_submit", $data, [ 'hash' => $popupFormSubmitHash ] );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * @param $cardUpdateSessionId
	 * @param $data
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function updateCustomerPortalSession( $cardUpdateSessionId, $data ) {

		global $wpdb;

		$updateResult = $wpdb->update( "{$wpdb->prefix}fullstripe_card_update_session", $data, [ 'id' => $cardUpdateSessionId ] );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function findCustomerPortalSessionByHash( $cardUpdateSessionHash ) {
		global $wpdb;

		$cardUpdateSession = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_card_update_session WHERE hash=%s", $cardUpdateSessionHash ) );

		return $cardUpdateSession;
	}

	public function findCustomerPortalSessionsByEmailAndCustomer( $email, $stripeCustomerId ) {
		global $wpdb;

		$cardUpdateSession = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fullstripe_card_update_session WHERE email=%s AND stripeCustomerId=%s",
				$email,
				$stripeCustomerId
			)
		);

		return $cardUpdateSession;
	}

	public function findCustomerPortalSessionsById( $cardUpdateSessionId ) {
		global $wpdb;

		$cardUpdateSession = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_card_update_session WHERE id=%s", $cardUpdateSessionId ) );

		return $cardUpdateSession;
	}

	public function insert_security_code( $cardUpdateSessionId, $securityCode ) {
		global $wpdb;

		$insertResult = $wpdb->insert( "{$wpdb->prefix}fullstripe_security_code", [
			'sessionId' => $cardUpdateSessionId,
			'securityCode' => $securityCode,
			'created' => current_time( 'mysql' ),
			'status' => CustomerPortalService::SECURITY_CODE_STATUS_PENDING
		] );

		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		if ( $insertResult === false ) {
			return -1;
		}

		return $wpdb->insert_id;

	}

	public function find_security_code_by_session_and_code( $sessionId, $securityCode ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_security_code WHERE sessionId=%d AND securityCode=%s", $sessionId, $securityCode ) );

	}

	public function updateSecurityCode( $securityCodeId, $data ) {

		global $wpdb;

		$updateResult = $wpdb->update( "{$wpdb->prefix}fullstripe_security_code", $data, [ 'id' => $securityCodeId ] );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function incrementSecurityCodeInput( $cardUpdateSessionId ) {
		global $wpdb;

		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_card_update_session SET securityCodeInput=securityCodeInput+1 WHERE id=%d", $cardUpdateSessionId ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function increment_security_code_request( $cardUpdateSessionId ) {
		global $wpdb;

		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_card_update_session SET securityCodeRequest=securityCodeRequest+1 WHERE id=%d", $cardUpdateSessionId ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '%s: an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * Atomically takes one slot of a request counter, while the counter is below its limit.
	 *
	 * The counter is an options row, so parallel requests cannot both read the same count and pass.
	 *
	 * @param string $counterName Option name of the counter.
	 * @param int    $limit Maximum count.
	 *
	 * @return bool Whether a slot was taken.
	 */
	public function claimRequestLimitSlot( $counterName, $limit ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '0', 'no')", $counterName ) );
		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s AND option_value < %d", $counterName, $limit ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return 1 === $updateResult;
	}

	/**
	 * Deletes request counters of earlier windows.
	 *
	 * @param string $prefix Counter name prefix.
	 * @param string $currentWindow Window that ends the current counter names.
	 *
	 * @return int Number of deleted counters.
	 */
	public function deleteRequestLimitCountersOutsideWindow( $prefix, $currentWindow ) {
		global $wpdb;

		$deleteResult = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$wpdb->esc_like( $prefix ) . '%',
				'%' . $wpdb->esc_like( '_' . $currentWindow )
			)
		);

		self::handleDbError( $deleteResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $deleteResult;
	}

	public function invalidateExpiredCustomerPortalSessions( $validUntilHour ) {
		global $wpdb;

		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_card_update_session SET status=%s WHERE created < DATE_SUB(NOW(), INTERVAL %d HOUR)", CustomerPortalService::SESSION_STATUS_INVALIDATED, $validUntilHour ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function invalidateCustomerPortalSessionsBySecurityCodeRequestLimit( $securityCodeRequestLimit ) {
		global $wpdb;

		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_card_update_session SET status=%s WHERE securityCodeRequest >= %d", CustomerPortalService::SESSION_STATUS_INVALIDATED, $securityCodeRequestLimit ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function invalidateCustomerPortalSessionsBySecurityCodeInputLimit( $securityCodeInputLimit ) {
		global $wpdb;

		$updateResult = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}fullstripe_card_update_session SET status=%s WHERE securityCodeInput >= %d", CustomerPortalService::SESSION_STATUS_INVALIDATED, $securityCodeInputLimit ) );

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	public function findInvalidatedCustomerPortalSessionIds() {
		global $wpdb;

		$cardUpdateSessionIds = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fullstripe_card_update_session WHERE status=%s", CustomerPortalService::SESSION_STATUS_INVALIDATED ) );

		return $cardUpdateSessionIds;
	}

	public function deleteSecurityCodesBySessions( $invalidatedSessionIds ) {
		global $wpdb;

		$whereStatement = ' WHERE sessionId IN (' . implode( ', ', array_fill( 0, sizeof( $invalidatedSessionIds ), '%s' ) ) . ')';

		$updateResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_security_code" . $whereStatement, $invalidatedSessionIds ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $updateResult;
	}

	public function deleteInvalidatedCustomerPortalSessions( $invalidatedSessionIds ) {
		global $wpdb;

		$whereStatement = ' WHERE id IN (' . implode( ', ', array_fill( 0, sizeof( $invalidatedSessionIds ), '%s' ) ) . ')';

		$updateResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_card_update_session" . $whereStatement, $invalidatedSessionIds ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during delete!' );

		return $updateResult;
	}

	public function getPayment( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_payments WHERE paymentID=%d", $id ) );
	}

	public function getSavedCard( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_card_captures WHERE captureID=%d", $id ) );
	}

	public function getPaymentByEventId( $eventId ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_payments WHERE eventID=%s", $eventId ) );
	}

	/**
	 * True if a payment/donation/subscriber row already exists for this PaymentIntent.
	 *
	 * @param string $formType
	 * @param string $paymentIntentId
	 *
	 * @return bool
	 */
	public function isCheckoutPaymentProcessedByPaymentIntent( $formType, $paymentIntentId ) {
		if ( empty( $paymentIntentId ) ) {
			return false;
		}

		if ( Utils::isCheckoutDonationFormType( $formType ) ) {
			return ! is_null( $this->getDonationByPaymentIntentId( $paymentIntentId ) );
		}

		if ( Utils::isCheckoutSubscriptionFormType( $formType ) ) {
			return ! is_null( $this->findSubscriberByPaymentIntentId( $paymentIntentId ) );
		}

		if ( Utils::isCheckoutPaymentFormType( $formType ) ) {
			return ! is_null( $this->getPaymentByEventId( $paymentIntentId ) );
		}

		return false;
	}

	/**
	 * True if a card was already saved with this SetupIntent.
	 *
	 * @param string $stripeSetupIntentId
	 *
	 * @return bool
	 */
	public function isCheckoutSavedCardRecorded( $stripeSetupIntentId ) {
		global $wpdb;

		return ! is_null( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}fullstripe_card_captures WHERE stripeSetupIntentID=%s LIMIT 1", $stripeSetupIntentId ) ) );
	}

	/**
	 * True if a subscriber or recurring donation row already exists for this subscription.
	 *
	 * @param string $formType
	 * @param string $stripeSubscriptionId
	 *
	 * @return bool
	 */
	public function isCheckoutSubscriptionRecorded( $formType, $stripeSubscriptionId ) {
		if ( Utils::isCheckoutDonationFormType( $formType ) ) {
			return ! is_null( $this->getDonationByStripeSubscriptionId( $stripeSubscriptionId ) );
		}

		if ( Utils::isCheckoutSubscriptionFormType( $formType ) ) {
			return ! is_null( $this->getSubscriptionByStripeSubscriptionId( $stripeSubscriptionId ) );
		}

		return false;
	}

	public function updatePaymentByEventId( $event_id, $data ) {
		global $wpdb;

		$update_result = $wpdb->update( "{$wpdb->prefix}fullstripe_payments", $data, [ 'eventID' => $event_id ] );

		self::handleDbError( $update_result, __FUNCTION__ . '(): an error occurred during update!' );

		return $update_result;
	}

	public function getDonation( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_donations WHERE donationID=%d", $id ) );
	}

	public function getDonationByStripeSubscriptionId( $stripeSubscriptionId ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_donations WHERE stripeSubscriptionID=%s", $stripeSubscriptionId ) );
	}

	public function getDonationByPaymentIntentId( $paymentIntentId ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_donations WHERE stripePaymentIntentID=%s", $paymentIntentId ) );
	}

	/**
	 * Claims the after-charge action for a PaymentIntent, so it is dispatched exactly once.
	 *
	 * All callers conditionally claim the earliest donation row for this PaymentIntent. This prevents
	 * duplicate claims when concurrent check-then-insert races create multiple rows. Rows written without
	 * an explicit 0 - from before the claim existed - default to 1, so they are never claimable.
	 *
	 * @param string $paymentIntentId The PaymentIntent of the donation.
	 *
	 * @return bool true when this caller claimed the action, false when it is already claimed or no
	 *              donation row exists to claim.
	 */
	public function claimDonationAfterChargeAction( $paymentIntentId ) {
		global $wpdb;

		if ( empty( $paymentIntentId ) ) {
			return false;
		}

		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_donations SET afterChargeActionFired=1
				  WHERE donationID = ( SELECT donationID FROM (
				           SELECT MIN(donationID) AS donationID FROM {$wpdb->prefix}fullstripe_donations WHERE stripePaymentIntentID=%s
				       ) AS earliest )
				    AND afterChargeActionFired=0",
				$paymentIntentId
			)
		);

		self::handleDbError( $claimed, __FUNCTION__ . '(): an error occurred during update!' );

		return is_numeric( $claimed ) && $claimed > 0;
	}

	public function updateDonationByPaymentIntentId( $paymentIntentId, $data ) {
		global $wpdb;

		$update_result = $wpdb->update( "{$wpdb->prefix}fullstripe_donations", $data, [ 'stripePaymentIntentID' => $paymentIntentId ] );

		self::handleDbError( $update_result, __FUNCTION__ . '(): an error occurred during update!' );

		return $update_result;
	}

	/**
	 * Sets the custom fields of the donations recorded for a PaymentIntent without them.
	 *
	 * One conditional write, so it never overwrites custom fields another request stored.
	 *
	 * @param string $paymentIntentId
	 * @param string $customFields    JSON of the submitted custom fields.
	 *
	 * @return int|bool Number of rows updated, or false on error.
	 * @throws \Exception
	 */
	public function fillMissingDonationCustomFields( $paymentIntentId, $customFields ) {
		global $wpdb;

		$update_result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_donations SET customFields=%s WHERE stripePaymentIntentID=%s AND (customFields IS NULL OR customFields='')",
				$customFields,
				$paymentIntentId
			)
		);

		self::handleDbError( $update_result, __FUNCTION__ . '(): an error occurred during update!' );

		return $update_result;
	}

	/**
	 * @param string $stripeSubscriptionId
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateSubscriptionToRunning( $stripeSubscriptionId ) {
		global $wpdb;
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_subscribers SET status=%s WHERE stripeSubscriptionID=%s",
				Main::SUBSCRIBER_STATUS_RUNNING,
				$stripeSubscriptionId
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $queryResult;
	}

	/**
	 * Sets a subscription just recorded running, unless it was cancelled or ended meanwhile.
	 *
	 * @param string $stripeSubscriptionId
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function updateNewSubscriptionToRunning( $stripeSubscriptionId ) {
		return $this->updateSubscriptionToRunningUnlessEnded( 'stripeSubscriptionID', $stripeSubscriptionId );
	}

	/**
	 * Every form, one form type after the other in FormTypes order.
	 *
	 * @return list<\stdClass> Each with id, type, layout, name, displayName, and created: the
	 *                         time of the form's latest transaction, or null.
	 */
	public function getAllForms() {
		global $wpdb;

		$result = [];
		foreach ( FormTypes::all() as $formType ) {
			$forms = $this->selectFormRows(
				$formType,
				"form.{$formType->idColumn} as id, '{$formType->type}' as type, '{$formType->layout}' as layout, form.name as name, form.displayName as displayName, " .
				"( select max(created) from {$wpdb->prefix}{$formType->transactionTable} where form.{$formType->idColumn} = formId AND form.name = formName ) as created",
				OBJECT
			);
			self::handleDbError( $forms, __FUNCTION__ . "({$formType->formType}): an error occurred during select!" );

			$result = array_merge( $result, $forms );
		}

		return $result;
	}

	/**
	 * Selects the given columns from a form type's table, aliased `form`. Save-card forms share
	 * the payment tables, so customAmount tells their rows apart.
	 *
	 * @param string $columns Built from FormTypes values only, never from input.
	 * @param 'OBJECT'|'ARRAY_A' $output
	 *
	 * @return ($output is 'ARRAY_A' ? list<array<string, string|null>> : list<\stdClass>)
	 */
	private function selectFormRows( FormType $formType, string $columns, string $output ): array {
		global $wpdb;

		$where = '';
		if ( null !== $formType->cardCapture ) {
			$where = ' WHERE customAmount ' . ( $formType->cardCapture ? '=' : '!=' ) . " '" . Main::PAYMENT_TYPE_CARD_CAPTURE . "'";
		}

		return $wpdb->get_results( "SELECT {$columns} FROM {$wpdb->prefix}{$formType->table} form{$where}", $output ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table, columns and condition come from FormTypes.
	}

	/**
	 * @param $module
	 * @param $class
	 * @param $function
	 * @param $level
	 * @param $message
	 * @param $exceptionStackTrace
	 *
	 * @return false|int
	 * @throws \Exception
	 */
	public function insertLog( $module, $class, $function, $level, $message, $exceptionStackTrace ) {
		global $wpdb;
		$insertResult = $wpdb->insert(
			"{$wpdb->prefix}fullstripe_log",
			[
				'created' => current_time( 'mysql' ),
				'module' => $module,
				'class' => $class,
				'function' => $function,
				'level' => $level,
				'message' => substr( $message, 0, 512 ),
				'exception' => $exceptionStackTrace
			]
		);

		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );

		return $insertResult;
	}

	public function getFormIdsByName( $name ) {
		global $wpdb;

		$queries = [
			"SELECT paymentFormID as id FROM {$wpdb->prefix}fullstripe_payment_forms WHERE name=%s;",
			"SELECT checkoutFormID FROM {$wpdb->prefix}fullstripe_checkout_forms WHERE name=%s;",
			"SELECT subscriptionFormID FROM {$wpdb->prefix}fullstripe_subscription_forms WHERE name=%s;",
			"SELECT checkoutSubscriptionFormID FROM {$wpdb->prefix}fullstripe_checkout_subscription_forms WHERE name=%s;",
			"SELECT donationFormID FROM {$wpdb->prefix}fullstripe_donation_forms WHERE name=%s;",
			"SELECT checkoutDonationFormID FROM {$wpdb->prefix}fullstripe_checkout_donation_forms WHERE name=%s;",
		];

		$result = [];
		foreach ( $queries as $query ) {
			$forms = $wpdb->get_results( $wpdb->prepare( $query, $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- each query is a literal above, prepared here with the name.
			// Checked after each read: the next query clears the error.
			self::handleDbError( $forms, __FUNCTION__ . '(): an error occurred during select!' );
			$result = array_merge( $result, $forms );
		}

		return $result;
	}

	/**
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfOneTimePayments() {
		global $wpdb;

		$result = $wpdb->get_row( "SELECT count(*) as paymentCount FROM {$wpdb->prefix}fullstripe_payments;", OBJECT );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * Counts the inline and Checkout forms of one kind.
	 *
	 * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $type
	 *
	 * @return object{formCount: numeric-string}
	 */
	private function getNumberOfFormsOfType( string $type ): object {
		$formCount = 0;
		foreach ( FormTypes::ofType( $type ) as $formType ) {
			$formCount += (int) $this->selectFormRows( $formType, 'count(*) as formCount', OBJECT )[0]->formCount;
		}

		return (object) [ 'formCount' => (string) $formCount ];
	}

	/** @return object{formCount: numeric-string} */
	public function getNumberOfOneTimePaymentForms() {
		return $this->getNumberOfFormsOfType( Main::FORM_TYPE_PAYMENT );
	}

	/**
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfSubscriptions() {
		global $wpdb;

		$result = $wpdb->get_row( "SELECT count(*) as subscriptionCount FROM {$wpdb->prefix}fullstripe_subscribers;", OBJECT );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/** @return object{formCount: numeric-string} */
	public function getNumberOfSubscriptionForms() {
		return $this->getNumberOfFormsOfType( Main::FORM_TYPE_SUBSCRIPTION );
	}

	/**
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfDonations() {
		global $wpdb;

		$result = $wpdb->get_row( "SELECT count(*) as donationCount FROM {$wpdb->prefix}fullstripe_donations;", OBJECT );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * The donations count what they kept: a donation records only a full refund, and its report
	 * the amount of a partial one.
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function getTotalDonationsByFormId( $formId, $formType ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare( "SELECT sum(GREATEST(donations.amount - COALESCE((SELECT MAX(reports.amount_refunded) FROM {$wpdb->prefix}fullstripe_reports reports WHERE reports.stripePaymentIntentID = donations.stripePaymentIntentID), 0), 0)) FROM {$wpdb->prefix}fullstripe_donations donations WHERE formId=%d AND formType=%s AND lastChargeStatus='succeeded' AND refunded=0 AND (donationFrequency=%s OR subscriptionStatus IS NULL OR subscriptionStatus != %s);", $formId, $formType, DonationFormViewConstants::FIELD_VALUE_DONATION_FREQUENCY_ONE_TIME, Main::SUBSCRIBER_STATUS_CANCELLED ) );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result ? (int) $result : 0;
	}

	/** @return object{formCount: numeric-string} */
	public function getNumberOfDonationForms() {
		return $this->getNumberOfFormsOfType( Main::FORM_TYPE_DONATION );
	}

	/**
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfSavedCards() {
		global $wpdb;

		$result = $wpdb->get_row( "SELECT count(*) as savedCardCount FROM {$wpdb->prefix}fullstripe_card_captures;", OBJECT );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/** @return object{formCount: numeric-string} */
	public function getNumberOfSaveCardForms() {
		return $this->getNumberOfFormsOfType( Main::FORM_TYPE_SAVE_CARD );
	}

	/**
	 * Get the number of payments by method.
	 * 
	 * @param $method
	 *
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfPaymentsByMethod( $method ) {
		$cacheKey = self::TRANSIENT_KEY_PREFIX . 'getNumberOfPaymentsByMethod_' . $method;
		$cache    = get_transient( $cacheKey );

		if ( $cache !== false ) {
			return $cache;
		}

		switch ( $method ) {
			case Menu::PARAM_VALUE_TAB_PAYMENTS:
				$result = ( $this->getNumberOfOneTimePayments() )->paymentCount;
				break;
			case Menu::PARAM_VALUE_TAB_SUBSCRIPTIONS:
				$result = ( $this->getNumberOfSubscriptions() )->subscriptionCount;
				break;
			case Menu::PARAM_VALUE_TAB_DONATIONS:
				$result = ( $this->getNumberOfDonations() )->donationCount;
				break;
			case Menu::PARAM_VALUE_TAB_SAVED_CARDS:
				$result = ( $this->getNumberOfSavedCards() )->savedCardCount;
				break;
			default:
				$result = null;
		}

		$cacheTime = $result > 100 ? WEEK_IN_SECONDS : MINUTE_IN_SECONDS;
		set_transient( $cacheKey, $result, $cacheTime );

		return $result;
	}

	/**
	 * @param $query
	 *
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getResults( $query ) {
		global $wpdb;

		$result = $wpdb->get_results( $query, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * @param $query
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	public function runQuery( $query ) {
		global $wpdb;

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * The names of the inline and then the Checkout forms of one kind.
	 *
	 * @param Main::FORM_TYPE_PAYMENT|Main::FORM_TYPE_SUBSCRIPTION|Main::FORM_TYPE_DONATION|Main::FORM_TYPE_SAVE_CARD $type
	 *
	 * @return list<\stdClass> Each with name and displayName.
	 */
	private function getFormNamesOfType( string $type ): array {
		$result = [];
		foreach ( FormTypes::ofType( $type ) as $formType ) {
			$result = array_merge( $result, $this->selectFormRows( $formType, 'name, displayName', OBJECT ) );
		}

		return $result;
	}

	/** @return list<\stdClass> */
	public function getOneTimePaymentFormNames(): array {
		return $this->getFormNamesOfType( Main::FORM_TYPE_PAYMENT );
	}

	/** @return list<\stdClass> */
	public function getSaveCardFormNames(): array {
		return $this->getFormNamesOfType( Main::FORM_TYPE_SAVE_CARD );
	}

	/** @return list<\stdClass> */
	public function getSubscriptionFormNames(): array {
		return $this->getFormNamesOfType( Main::FORM_TYPE_SUBSCRIPTION );
	}

	/** @return list<\stdClass> */
	public function getDonationFormNames(): array {
		return $this->getFormNamesOfType( Main::FORM_TYPE_DONATION );
	}

	/**
	 * @return string
	 */
	public function getDatabasePrefix() {
		global $wpdb;

		return $wpdb->prefix;
	}

	/**
	 * @return array|object|null
	 * @throws \Exception
	 */
	public function getNumberOfLogEntries() {
		global $wpdb;

		$result = $wpdb->get_row( "SELECT count(*) as logCount FROM {$wpdb->prefix}fullstripe_log;", OBJECT );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	public function getLogEntries( $offset, $rowCount ) {
		global $wpdb;

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_log LIMIT %d, %d", $offset, $rowCount ), OBJECT );

		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * @throws \Exception
	 */
	function deleteLogs() {
		global $wpdb;
		$queryResult = $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'fullstripe_log;' );
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during delete!' );
	}

	/**
	 * Inserts the PaymentIntent's report, or updates it when it already exists. The insert
	 * falls back to an update on the unique stripePaymentIntentID key, so concurrent webhook
	 * deliveries for the same payment cannot both insert. A refund processed before the report
	 * existed is then stored on it.
	 *
	 * @param ReportColumns $insert Columns of a new report, including stripePaymentIntentID.
	 * @param ReportColumns $update Columns to overwrite on an existing report, except a refunded status.
	 *
	 * @return int|false Affected rows, or false on error.
	 * @throws \Exception
	 */
	public function saveReport( array $insert, array $update ) {
		$queryResult = $this->writeReport( $insert, $update );

		if ( isset( $insert['stripePaymentIntentID'] ) ) {
			$this->applyPendingReportRefund( $insert['stripePaymentIntentID'] );
		}

		return $queryResult;
	}

	/**
	 * Saves the report of a charge. A report saved earlier gets the charge's currency, amount, customer,
	 * status and mode, and keeps its other columns.
	 *
	 * @param ReportColumns $columns The report's currency, amount, form, PaymentIntent, customer and subscription.
	 * @param object $charge The charge, for its time, refund, status and mode.
	 *
	 * @throws \Exception
	 */
	private function saveChargeReport( array $columns, object $charge ): void {
		$status = $this->getPaymentStatus( $charge );
		$mode   = $charge->livemode ? 'live' : 'test';

		$this->saveReport(
			[
				'created_at'      => date( 'Y-m-d H:i:s', $charge->created ),
				'updated_at'      => date( 'Y-m-d H:i:s', $charge->created ),
				'amount_refunded' => (int) ( $charge->amount_refunded ?? 0 ),
				'status'          => $status,
				'mode'            => $mode,
			] + $columns,
			[
				'updated_at'       => date( 'Y-m-d H:i:s', time() ),
				'currency'         => $columns['currency'] ?? null,
				'amount'           => $columns['amount'] ?? null,
				'stripeCustomerID' => $columns['stripeCustomerID'] ?? null,
				'status'           => $status,
				'mode'             => $mode,
			]
		);
	}

	/**
	 * Saves the succeeded report of a subscription's payment. A report saved earlier gets every given
	 * column but the PaymentIntent and subscription, and keeps its time and refund.
	 *
	 * @param ReportColumns $columns
	 * @param object $source The subscription or paid invoice, for its time and mode.
	 *
	 * @throws \Exception
	 */
	public function saveSucceededReport( array $columns, object $source ): void {
		$mode = $source->livemode ? 'live' : 'test';

		$this->saveReport(
			[
				'created_at' => date( 'Y-m-d H:i:s', $source->created ),
				'updated_at' => date( 'Y-m-d H:i:s', $source->created ),
				'status'     => 'succeeded',
				'mode'       => $mode,
			] + $columns,
			[
				'updated_at' => date( 'Y-m-d H:i:s', time() ),
				'status'     => 'succeeded',
				'mode'       => $mode,
			] + array_diff_key( $columns, [ 'stripePaymentIntentID' => true, 'stripeSubscriptionID' => true ] )
		);
	}

	/**
	 * @param ReportColumns $insert
	 * @param ReportColumns $update
	 *
	 * @return int|false
	 * @throws \Exception
	 */
	private function writeReport( array $insert, array $update ) {
		global $wpdb;

		// The lookup keeps repeat deliveries updating on sites where the unique key could not be added.
		$paymentIntentId = $insert['stripePaymentIntentID'] ?? null;
		$report          = null === $paymentIntentId ? null : $this->getReportByPaymentIntentID( $paymentIntentId );
		if ( $report ) {
			$args        = [];
			$assignments = self::reportAssignments( $update, $args );
			$queryResult = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}fullstripe_reports SET {$assignments} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column names come from REPORT_COLUMNS, every value is a placeholder.
					array_merge( $args, [ $report->id ] )
				)
			);
			self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );

			return $queryResult;
		}

		$columns      = [];
		$placeholders = [];
		$args         = [];
		foreach ( self::REPORT_COLUMNS as $column ) {
			if ( array_key_exists( $column, $insert ) ) {
				$columns[]      = "`{$column}`";
				$placeholders[] = self::reportValuePlaceholder( $insert[ $column ], $args );
			}
		}
		$assignments = self::reportAssignments( $update, $args );

		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}fullstripe_reports (" . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ') ON DUPLICATE KEY UPDATE ' . $assignments, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- column names come from REPORT_COLUMNS, every value is a placeholder.
				$args
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during insert or update!' );

		return $queryResult;
	}

	/**
	 * The SET list of a report update. It keeps a refunded status, which only updateReportRefund()
	 * changes, and is one statement so a refund recorded meanwhile is not overwritten.
	 *
	 * @param ReportColumns    $update
	 * @param list<int|string> $args Collects the values for their placeholders.
	 */
	private static function reportAssignments( array $update, array &$args ): string {
		$refundStatuses = "'" . implode( "', '", self::REPORT_REFUND_STATUSES ) . "'";
		$assignments    = [];
		foreach ( self::REPORT_COLUMNS as $column ) {
			if ( array_key_exists( $column, $update ) ) {
				$value         = self::reportValuePlaceholder( $update[ $column ], $args );
				$assignments[] = 'status' === $column ? "`status` = IF(`status` IN ({$refundStatuses}), `status`, {$value})" : "`{$column}` = {$value}";
			}
		}

		return implode( ', ', $assignments );
	}

	/**
	 * prepare() turns null into an empty string, so NULL is written literally.
	 *
	 * @param int|string|null $value
	 * @param list<int|string> $args Collects the value for its placeholder.
	 */
	private static function reportValuePlaceholder( $value, array &$args ): string {
		if ( null === $value ) {
			return 'NULL';
		}
		$args[] = $value;

		return '%s';
	}

	/**
	 * Updates an existing report in the database.
	 *
	 * @param int $id The ID of the report to update.
	 * @param array $data An associative array of data to update in the report.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function updateReport( $id, $data ) {
		global $wpdb;
		$updateResult = $wpdb->update( $wpdb->prefix . 'fullstripe_reports', $data, [ 'id' => $id ] );
		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * Whether a payment the plugin recorded was made in live mode, from its report, payment or donation.
	 *
	 * @param string $paymentIntentId
	 *
	 * @return bool|null Null when nothing records it.
	 */
	public function isPaymentIntentLive( string $paymentIntentId ): ?bool {
		global $wpdb;

		$mode = $wpdb->get_var( $wpdb->prepare( "SELECT mode FROM {$wpdb->prefix}fullstripe_reports WHERE stripePaymentIntentID = %s", $paymentIntentId ) );
		if ( null !== $mode ) {
			return 'live' === $mode;
		}
		$liveMode = $wpdb->get_var( $wpdb->prepare( "SELECT livemode FROM {$wpdb->prefix}fullstripe_payments WHERE eventID = %s", $paymentIntentId ) )
			?? $wpdb->get_var( $wpdb->prepare( "SELECT livemode FROM {$wpdb->prefix}fullstripe_donations WHERE stripePaymentIntentID = %s", $paymentIntentId ) );

		return null === $liveMode ? null : (bool) (int) $liveMode;
	}

	/**
	 * Records the charge's capture and refund on its payment or donation and its report, without lowering what is
	 * recorded there.
	 *
	 * @param string $paymentIntentId
	 * @param object{paid: bool, captured: bool, refunded: bool, status: string, amount: int, amount_refunded: int} $charge The
	 *        payment's latest charge.
	 *
	 * @throws \Exception
	 */
	public function recordChargeState( string $paymentIntentId, object $charge ): void {
		$flags = [
			'paid' => empty( $charge->paid ) ? 0 : 1,
			'captured' => empty( $charge->captured ) ? 0 : 1,
			'refunded' => empty( $charge->refunded ) ? 0 : 1,
		];
		$this->raiseChargeFlags( 'fullstripe_payments', 'eventID', $paymentIntentId, $flags );
		$this->raiseChargeFlags( 'fullstripe_donations', 'stripePaymentIntentID', $paymentIntentId, $flags );
		if ( ! empty( $charge->amount_refunded ) ) {
			$this->saveReportRefund( $paymentIntentId, (int) $charge->amount_refunded, $this->getPaymentStatus( $charge ) );
		}
	}

	/**
	 * Writes a charge's status and flags on its payment in one statement, so that a write running meanwhile sees both or
	 * neither. The flags recorded there are kept, and so is a paid or refunded payment's status against a pending
	 * charge, as a draft saved while the payment is confirmed writes.
	 *
	 * @param string $paymentIntentId
	 * @param array{paid: int, captured: int, refunded: int} $flags
	 * @param string|null $status The charge's status, null to keep the payment's.
	 *
	 * @throws \Exception
	 */
	private function writePaymentChargeState( $paymentIntentId, array $flags, $status ): void {
		global $wpdb;

		if ( is_null( $status ) ) {
			$this->raiseChargeFlags( 'fullstripe_payments', 'eventID', $paymentIntentId, $flags );

			return;
		}
		// A pending charge has no flags set, so its status is compared with the payment's flags before or after the
		// update alike.
		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_payments SET last_charge_status=IF(%d=1 AND (paid=1 OR refunded=1), last_charge_status, %s), paid=GREATEST(paid, %d), captured=GREATEST(captured, %d), refunded=GREATEST(refunded, %d) WHERE eventID=%s",
				Main::STRIPE_CHARGE_STATUS_PENDING === $status ? 1 : 0,
				$status,
				$flags['paid'],
				$flags['captured'],
				$flags['refunded'],
				$paymentIntentId
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );
	}

	/**
	 * Sets the flags a charge has on its payment or donation, and keeps the ones recorded there.
	 *
	 * @param 'fullstripe_payments'|'fullstripe_donations' $table
	 * @param 'eventID'|'stripePaymentIntentID' $paymentIntentColumn
	 * @param string $paymentIntentId
	 * @param array{paid: int, captured: int, refunded: int} $flags
	 *
	 * @throws \Exception
	 */
	private function raiseChargeFlags( $table, $paymentIntentColumn, $paymentIntentId, array $flags ): void {
		global $wpdb;

		$queryResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}{$table} SET paid=GREATEST(paid, %d), captured=GREATEST(captured, %d), refunded=GREATEST(refunded, %d) WHERE {$paymentIntentColumn}=%s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table and column are literals.
				$flags['paid'],
				$flags['captured'],
				$flags['refunded'],
				$paymentIntentId
			)
		);
		self::handleDbError( $queryResult, __FUNCTION__ . '(): an error occurred during update!' );
	}

	/**
	 * Stores a charge's refund on its report. A refund processed before its report exists is kept
	 * for saveReport(), and the update runs again for a report saved in between.
	 *
	 * @param string $paymentIntentId
	 * @param int    $amountRefunded The charge's amount_refunded, in the currency's smallest unit.
	 * @param string $status         The status for that amount, from getPaymentStatus().
	 *
	 * @throws \Exception
	 */
	public function saveReportRefund( string $paymentIntentId, int $amountRefunded, string $status ): void {
		if ( $this->updateReportRefund( $paymentIntentId, $amountRefunded, $status ) ) {
			return;
		}

		if ( ! $this->getReportByPaymentIntentID( $paymentIntentId ) ) {
			$this->keepPendingReportRefund( $paymentIntentId, $amountRefunded, $status );
		}

		$this->updateReportRefund( $paymentIntentId, $amountRefunded, $status );
	}

	/**
	 * Keeps a refund processed before its report exists, for saveReport(). It's one statement, so of two refunds
	 * of the payment kept at the same moment, the larger amount wins.
	 *
	 * @throws \Exception
	 */
	private function keepPendingReportRefund( string $paymentIntentId, int $amountRefunded, string $status ): void {
		global $wpdb;
		$now = time();

		// Stripe retries the events that record a payment for up to three days. A refund of a payment this plugin
		// never records, such as one sold elsewhere on the same account, is kept for a week and then dropped.
		$deleteResult = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}fullstripe_report_refunds WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', $now - WEEK_IN_SECONDS ) ) );
		self::handleDbError( $deleteResult, __FUNCTION__ . '(): an error occurred during delete!' );

		// The status is set first, while amount_refunded still holds the kept amount.
		$insertResult = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}fullstripe_report_refunds (stripePaymentIntentID, amount_refunded, status, created_at) VALUES (%s, %d, %s, %s) ON DUPLICATE KEY UPDATE status = IF(VALUES(amount_refunded) > amount_refunded, VALUES(status), status), amount_refunded = GREATEST(amount_refunded, VALUES(amount_refunded))",
				$paymentIntentId,
				$amountRefunded,
				$status,
				gmdate( 'Y-m-d H:i:s', $now )
			)
		);
		self::handleDbError( $insertResult, __FUNCTION__ . '(): an error occurred during insert!' );
	}

	/**
	 * @throws \Exception
	 */
	private function applyPendingReportRefund( string $paymentIntentId ): void {
		global $wpdb;
		/** @var array{amount_refunded: string, status: string}|null $pending */
		$pending = $wpdb->get_row( $wpdb->prepare( "SELECT amount_refunded, status FROM {$wpdb->prefix}fullstripe_report_refunds WHERE stripePaymentIntentID = %s", $paymentIntentId ), ARRAY_A );
		if ( null !== $pending ) {
			$this->updateReportRefund( $paymentIntentId, (int) $pending['amount_refunded'], $pending['status'] );
			$deleteResult = $wpdb->delete( $wpdb->prefix . 'fullstripe_report_refunds', [ 'stripePaymentIntentID' => $paymentIntentId ] );
			self::handleDbError( $deleteResult, __FUNCTION__ . '(): an error occurred during delete!' );
		}
	}

	/**
	 * Stores a charge's refunded amount on its report. Stripe sends the charge's cumulative
	 * amount, but may deliver its events late or more than once, so the highest amount wins
	 * and the status changes only with it.
	 *
	 * @param string $paymentIntentId
	 * @param int    $amountRefunded The charge's amount_refunded, in the currency's smallest unit.
	 * @param string $status         The status for that amount, from getPaymentStatus().
	 *
	 * @return int|false The number of rows updated, or false on error.
	 * @throws \Exception
	 */
	public function updateReportRefund( string $paymentIntentId, int $amountRefunded, string $status ) {
		global $wpdb;
		$updateResult = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}fullstripe_reports SET amount_refunded = %d, status = %s, updated_at = %s WHERE stripePaymentIntentID = %s AND amount_refunded < %d",
				$amountRefunded,
				$status,
				date( 'Y-m-d H:i:s', time() ),
				$paymentIntentId,
				$amountRefunded
			)
		);
		self::handleDbError( $updateResult, __FUNCTION__ . '(): an error occurred during update!' );

		return $updateResult;
	}

	/**
	 * Retrieves a report by payment intent from the database.
	 *
	 * @param string $paymentIntent The paymentIntent of the report to retrieve.
	 * @return object|null The report object, or null if not found.
	 */
	public function getReportByPaymentIntentID( $paymentIntent ) {
		global $wpdb;
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}fullstripe_reports WHERE stripePaymentIntentID=%s", $paymentIntent ), OBJECT );
		self::handleDbError( $report, __FUNCTION__ . '(): an error occurred during select!' );

		return $report;
	}

	/**
	 * Get payment status by charge.
	 *
	 * @param object $charge The charge object.
	 * @return string The payment status.
	 */
	public function getPaymentStatus( $charge ) {
		if ( $charge->status !== 'succeeded' ) {
			return $charge->status;
		}

		if ( $charge->amount_refunded > 0 ) {
			return ( $charge->amount_refunded < $charge->amount ) ? 'partially_refunded' : 'refunded';
		}

		return 'succeeded';
	}

	public function getFormNameByReport( $payment ) {
		$id     = $payment->formId;
		$type   = $payment->formType;
		$method = 'get' . str_replace( '_', '', ucwords( $type, '_' ) ) . 'FormById';
	
		if ( ! method_exists( $this, $method ) ) {
			return '-';
		}
	
		// The form may have been deleted since the payment.
		$form = $this->$method( $id );

		return null === $form ? '-' : $form->displayName;
	}

	public function getTotalDonationAmount( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'sum(' . self::REPORT_NET_AMOUNT . ')', self::REPORT_DONATION_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return Currencies::format_amount_with_currency( $currency, ( $result ? (int) $result : 0 ) );
	}

	public function getTotalNumberOfDonations( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'count(*)', self::REPORT_DONATION_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return $result ? (int) $result : 0;
	}

	public function getAverageDonationAmount( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'avg(' . self::REPORT_NET_AMOUNT . ')', self::REPORT_DONATION_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return Currencies::format_amount_with_currency( $currency, ( $result ? (int) $result : 0 ) );
	}

	/**
	 * @return list<ReportRow> The latest donations in the currency and period, with net_amount after refunds.
	 * @throws \Exception
	 */
	public function getDonations( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS, $number = 10 ) {
		return $this->getReportRows( $this->prepareReportQuery( '*, ' . self::REPORT_NET_AMOUNT . ' as net_amount', self::REPORT_DONATION_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ), ' ORDER BY created_at DESC LIMIT %d', [ $number ] ), __FUNCTION__ );
	}

	public function getTopDoners( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS, $number = 10 ) {
		return $this->getReportRows( $this->prepareReportQuery( 'stripeCustomerID, currency, sum(' . self::REPORT_NET_AMOUNT . ') as total, avg(' . self::REPORT_NET_AMOUNT . ') as average', self::REPORT_DONATION_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ), ' GROUP BY stripeCustomerID ORDER BY total DESC LIMIT %d', [ $number ] ), __FUNCTION__ );
	}

	public function getTotalRevenueAmount( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'sum(' . self::REPORT_NET_AMOUNT . ')', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return Currencies::format_amount_with_currency( $currency, ( $result ? (int) $result : 0 ) );
	}

	public function getAverageTransactionAmount( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'avg(' . self::REPORT_NET_AMOUNT . ')', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return Currencies::format_amount_with_currency( $currency, ( $result ? (int) $result : 0 ) );
	}

	public function getTotalNumberOfPayments( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$result = $this->getReportValue( $this->prepareReportQuery( 'count(*)', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ) ), __FUNCTION__ );

		return $result ? (int) $result : 0;
	}

	public function getTotalActiveSubscriptions( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		global $wpdb;

		list( $periodStart, $periodEnd ) = array_values( self::getPeriod( $period ) );

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT count(*) FROM {$wpdb->prefix}fullstripe_subscribers WHERE created >= %s AND created < %s AND status='running' AND livemode=%s;",
			$periodStart,
			$periodEnd,
			Utils::isLiveMode() ? 1 : 0
		) );
		self::handleDbError( $result, __FUNCTION__ . '(): an error occurred during select!' );

		return $result ? (int) $result : 0;
	}

	/**
	 * @return list<ReportRow> The latest payments and subscriptions in the currency and period, with net_amount after refunds.
	 * @throws \Exception
	 */
	public function getPayments( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS, $number = 10 ) {
		return $this->getReportRows( $this->prepareReportQuery( '*, ' . self::REPORT_NET_AMOUNT . ' as net_amount', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ), ' ORDER BY created_at DESC LIMIT %d', [ $number ] ), __FUNCTION__ );
	}

	public function getTopCustomers( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS, $number = 10 ) {
		return $this->getReportRows( $this->prepareReportQuery( 'stripeCustomerID, currency, sum(' . self::REPORT_NET_AMOUNT . ') as total, avg(' . self::REPORT_NET_AMOUNT . ') as average', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, self::getPeriod( $period ), ' GROUP BY stripeCustomerID ORDER BY total DESC LIMIT %d', [ $number ] ), __FUNCTION__ );
	}

	public function getRevenueAndSalesData( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$range = self::getPeriod( $period );
		$dates = self::reportDays( $range, [ 'revenue' => 0, 'sales' => 0 ] );

		$revenueAndSalesData = $this->getReportRows( $this->prepareReportQuery( 'DATE(created_at) as date, formId, formType, sum(' . self::REPORT_NET_AMOUNT . ') as revenue, count(*) as sales', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REVENUE_STATUSES, $currency, $range, ' GROUP BY date, formId, formType ORDER BY date ASC' ), __FUNCTION__ );

		// Merge the results with the dates array
		foreach ( $revenueAndSalesData as $data ) {
			if ( ! isset( $dates[ $data->date ]->details ) ) {
				$dates[ $data->date ]->details = [];
			}
			$dates[ $data->date ]->revenue += $data->revenue;
			$dates[ $data->date ]->sales += $data->sales;
			$dates[ $data->date ]->details[] = $data;
		}

		return array_values($dates);
	}

	public function getRefundsData( $currency = Main::CURRENCY_USD, $period = Menu::PARAM_VALUE_RANGE_LAST_30_DAYS ) {
		$range = self::getPeriod( $period );
		$dates = self::reportDays( $range, [ 'refunds' => 0, 'refund_count' => 0 ] );

		$refundsData = $this->getReportRows( $this->prepareReportQuery( 'DATE(created_at) as date, sum(amount_refunded) as refunds, count(*) as refund_count', self::REPORT_PAYMENT_FORM_TYPES, self::REPORT_REFUND_STATUSES, $currency, $range, ' AND amount_refunded > 0 GROUP BY date ORDER BY date ASC' ), __FUNCTION__ );

		// Merge the results with the dates array
		foreach ($refundsData as $data) {
			$dates[$data->date] = $data;
		}

		return array_values($dates);
	}

	/**
	 * Prepares a query on the reports of the given form types, statuses, currency and period.
	 *
	 * @param string                          $columns   The SELECT list.
	 * @param list<string>                    $formTypes
	 * @param list<'succeeded'|'partially_refunded'|'refunded'> $statuses
	 * @param string                          $currency
	 * @param array{start: string, end: string} $range   From getPeriod().
	 * @param string                          $clauses   More conditions, GROUP BY, ORDER BY and LIMIT, with placeholders for $args.
	 * @param list<int|string>                $args
	 */
	private function prepareReportQuery( string $columns, array $formTypes, array $statuses, $currency, array $range, string $clauses = '', array $args = [] ): string {
		global $wpdb;

		$formTypePlaceholders = implode( ', ', array_fill( 0, count( $formTypes ), '%s' ) );
		$statusPlaceholders   = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return $wpdb->prepare(
			"SELECT {$columns} FROM {$wpdb->prefix}fullstripe_reports WHERE created_at >= %s AND created_at < %s AND currency=%s AND formType IN ({$formTypePlaceholders}) AND status IN ({$statusPlaceholders}) AND mode=%s{$clauses};", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the columns and clauses are literals from this class.
			array_merge( [ $range['start'], $range['end'], $currency ], $formTypes, $statuses, [ Utils::getMode() ], $args )
		);
	}

	/**
	 * @return string|null
	 * @throws \Exception
	 */
	private function getReportValue( string $query, string $function ) {
		global $wpdb;

		$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared by prepareReportQuery().
		self::handleDbError( $result, $function . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * @return list<object>
	 * @throws \Exception
	 */
	private function getReportRows( string $query, string $function ): array {
		global $wpdb;

		$result = $wpdb->get_results( $query, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared by prepareReportQuery().
		self::handleDbError( $result, $function . '(): an error occurred during select!' );

		return $result;
	}

	/**
	 * One entry per calendar day of the range, first and last day included, keyed by date.
	 *
	 * @param array{start: string, end: string} $range From getPeriod().
	 * @param array<string, int>                $zero  The values of a day without reports.
	 *
	 * @return array<string, object>
	 */
	private static function reportDays( array $range, array $zero ): array {
		$days = new \DatePeriod(
			new \DateTime( substr( $range['start'], 0, 10 ) ),
			new \DateInterval( 'P1D' ),
			( new \DateTime( substr( $range['end'], 0, 10 ) ) )->modify( '+1 day' )
		);

		$dates = [];
		foreach ( $days as $day ) {
			$dates[ $day->format( 'Y-m-d' ) ] = (object) array_merge( [ 'date' => $day->format( 'Y-m-d' ) ], $zero );
		}

		return $dates;
	}

	public function getMostUsedCurrency() {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT currency FROM ( SELECT currency FROM {$wpdb->prefix}fullstripe_reports WHERE mode=%s ORDER BY created_at DESC LIMIT 50 ) as latest GROUP BY currency ORDER BY COUNT(*) DESC LIMIT 1", Utils::getMode()
		) );

		return $result ? $result : Main::CURRENCY_USD;
	}

	public static function getPeriod( $period ) {
		$start = null;
		$end   = null;

		if ( is_array( $period ) ) {
			$start = date( 'Y-m-d 00:00:00', strtotime( $period['start'] ) );
			$end   = date( 'Y-m-d 23:59:59', strtotime( $period['end'] ) );
		
			return [
				'start' => $start,
				'end'   => $end
			];
		}
		
		switch ( $period ) {
			case Menu::PARAM_VALUE_RANGE_TODAY:
				$start = date( 'Y-m-d 00:00:00', strtotime( 'today' ) );
				$end   = date( 'Y-m-d 23:59:59', strtotime( 'today' ) );
				break;
				
			case Menu::PARAM_VALUE_RANGE_YESTERDAY:
				$start = date( 'Y-m-d 00:00:00', strtotime( 'yesterday' ) );
				$end   = date( 'Y-m-d 23:59:59', strtotime( 'yesterday' ) );
				break;
				
			case Menu::PARAM_VALUE_RANGE_LAST_7_DAYS:
				$start = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				$end   = date( 'Y-m-d H:i:s', strtotime( 'now' ) );
				break;
				
			case Menu::PARAM_VALUE_RANGE_LAST_30_DAYS:
				$start = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				$end   = date( 'Y-m-d H:i:s', strtotime( 'now' ) );
				break;
				
			case Menu::PARAM_VALUE_RANGE_THIS_MONTH:
				$start = date( 'Y-m-01 00:00:00' );
				$end   = date( 'Y-m-d 23:59:59', strtotime( 'last day of this month' ) );
				break;
				
			case Menu::PARAM_VALUE_RANGE_LAST_MONTH:
				$start = date( 'Y-m-01 00:00:00', strtotime( 'first day of last month' ) );
				$end   = date( 'Y-m-t 23:59:59', strtotime( 'last day of last month' ) );
				break;
		}
		
		return [
			'start' => $start,
			'end'   => $end
		];
	}

	/**
	 * Check if user accumulates 140 USD or 140 EUR in transactions over the last 45 days.
	 * 
	 * @return bool True if volume threshold is exceeded in USD or EUR, false otherwise
	 */
	public function requiresVolumeWarning() {
		$cached_result = get_transient( 'wpfs_transaction_volume_notice_check' );
		if ( 'yes' === $cached_result || 'no' === $cached_result ) {
			return 'yes' === $cached_result;
		}

		global $wpdb;

		$startDate = gmdate( 'Y-m-d H:i:s', strtotime( '-45 days' ) );
		$mode      = Utils::getMode();

		// Check if user has over 140 USD or EUR in transactions.
		$has_volume = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 
			FROM {$wpdb->prefix}fullstripe_reports 
			WHERE created_at >= %s 
			AND status IN (%s, %s)
			AND currency IN (%s, %s) 
			AND mode = %s 
			GROUP BY currency 
			HAVING SUM(" . self::REPORT_NET_AMOUNT . ') >= %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- REPORT_NET_AMOUNT is a literal from this class.
			array_merge(
				[ $startDate ],
				self::REPORT_REVENUE_STATUSES,
				[
					Main::CURRENCY_USD,
					Main::CURRENCY_EUR,
					$mode,
					14000, // threshold in cents
				]
			)
		) );

		$should_show = $has_volume ? 'yes' : 'no';
		set_transient( 'wpfs_transaction_volume_notice_check', $should_show, DAY_IN_SECONDS );

		return $should_show === 'yes';
	}
}
