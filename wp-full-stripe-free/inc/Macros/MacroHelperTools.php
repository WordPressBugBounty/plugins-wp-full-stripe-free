<?php

namespace ThemeIsle\WPFullPay\Macros;

class MacroHelperTools {
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getMacroDescriptions(): array {
		$macros = [
			'%CUSTOMERNAME%' => __( "Customer's cardholder name", 'wp-full-stripe-free' ),
			'%CUSTOMER_EMAIL%' => __( "Customer's email address", 'wp-full-stripe-free' ),
			'%CUSTOMER_PHONE%' => __( "Customer's phone number", 'wp-full-stripe-free' ),
			'%CUSTOMER_TAX_ID%' => __( "Customer's tax id", 'wp-full-stripe-free' ),
			'%STRIPE_CUSTOMER_ID%' => __( "Identifier of the Stripe customer", 'wp-full-stripe-free' ),
			'%NAME%' => __( "Name of your WordPress site", 'wp-full-stripe-free' ),
			'%FORM_NAME%' => __( "Name of the form used to make the transaction", 'wp-full-stripe-free' ),
			'%DATE%' => __( "Current date", 'wp-full-stripe-free' ),
			'%TRANSACTION_ID%' => __( "Identifier of the Stripe object created in the transaction", 'wp-full-stripe-free' ),
			'%AMOUNT%' => __( "Gross payment amount", 'wp-full-stripe-free' ),
			'%RECOVERY_FEE%' => __( "Transaction fee the customer paid to cover processing costs", 'wp-full-stripe-free' ),
			'%CHARGED_TOTAL%' => __( "Total charged, transaction fee included", 'wp-full-stripe-free' ),
			'%PRODUCT_NAME%' => __( "Name of product purchased", 'wp-full-stripe-free' ),
			'%PRODUCT_AMOUNT_GROSS%' => __( "Gross amount of product purchased", 'wp-full-stripe-free' ),
			'%PRODUCT_AMOUNT_TAX%' => __( "Tax of product purchased", 'wp-full-stripe-free' ),
			'%PRODUCT_AMOUNT_NET%' => __( "Net amount of product purchased", 'wp-full-stripe-free' ),
			'%PLAN_NAME%' => __( "Name of the subscription plan", 'wp-full-stripe-free' ),
			'%PLAN_QUANTITY%' => __( "The number of subscription plans purchased", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT%' => __( "Gross amount of the subscription plan", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_TOTAL%' => __( "Gross subscription amount (= PLAN_AMOUNT × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_GROSS%' => __( "Gross amount of the subscription plan", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_GROSS_TOTAL%' => __( "Gross subscription amount (= PLAN_AMOUNT_GROSS × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_NET%' => __( "Net amount of the subscription plan", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_NET_TOTAL%' => __( "Net subscription amount (= PLAN_AMOUNT_NET × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_VAT%' => __( "VAT amount of the subscription plan", 'wp-full-stripe-free' ),
			'%PLAN_AMOUNT_VAT_TOTAL%' => __( "VAT amount of the subscription (= PLAN_AMOUNT_VAT × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%PLAN_FUTURE_AMOUNT_NET%' => __( "Net amount of the subscription plan after trial", 'wp-full-stripe-free' ),
			'%PLAN_FUTURE_AMOUNT_VAT%' => __( "VAT amount of the subscription plan after trial", 'wp-full-stripe-free' ),
			'%PLAN_FUTURE_AMOUNT_GROSS%' => __( "Gross amount of the subscription plan after trial", 'wp-full-stripe-free' ),
			'%SETUP_FEE%' => __( "Gross setup fee of the subscription plan", 'wp-full-stripe-free' ),
			'%SETUP_FEE_TOTAL%' => __( "Gross setup fee (= SETUP_FEE × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%SETUP_FEE_GROSS%' => __( "Gross setup fee of the subscription plan", 'wp-full-stripe-free' ),
			'%SETUP_FEE_GROSS_TOTAL%' => __( "Gross setup fee (= SETUP_FEE × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%SETUP_FEE_NET%' => __( "Net setup fee of the subscription plan", 'wp-full-stripe-free' ),
			'%SETUP_FEE_NET_TOTAL%' => __( "Net setup fee (= SETUP_FEE_NET × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%SETUP_FEE_VAT%' => __( "VAT amount of the subscription plan’s setup fee", 'wp-full-stripe-free' ),
			'%SETUP_FEE_VAT_TOTAL%' => __( "VAT amount of the subscription’s setup fee (= SETUP_FEE_VAT × PLAN_QUANTITY)", 'wp-full-stripe-free' ),
			'%INVOICE_URL%' => __( "Link to the downloadable PDF invoice of the payment", 'wp-full-stripe-free' ),
			'%INVOICE_NUMBER%' => __( "Invoice number of the payment", 'wp-full-stripe-free' ),
			'%RECEIPT_URL%' => __( "Link to the downloadable PDF receipt of the payment", 'wp-full-stripe-free' ),
			'%BILLING_NAME%' => __( "Billing name", 'wp-full-stripe-free' ),
			'%ADDRESS1%' => __( "Billing address line 1", 'wp-full-stripe-free' ),
			'%ADDRESS2%' => __( "Billing address line 2", 'wp-full-stripe-free' ),
			'%CITY%' => __( "Billing address city", 'wp-full-stripe-free' ),
			'%STATE%' => __( "Billing address state", 'wp-full-stripe-free' ),
			'%ZIP%' => __( "Billing address zip (or postal) code", 'wp-full-stripe-free' ),
			'%COUNTRY%' => __( "Billing address country", 'wp-full-stripe-free' ),
			'%COUNTRY_CODE%' => __( "ISO code of the billing address country", 'wp-full-stripe-free' ),
			'%SHIPPING_NAME%' => __( "Shipping name", 'wp-full-stripe-free' ),
			'%SHIPPING_ADDRESS1%' => __( "Shipping address line 1", 'wp-full-stripe-free' ),
			'%SHIPPING_ADDRESS2%' => __( "Shipping address line 2", 'wp-full-stripe-free' ),
			'%SHIPPING_CITY%' => __( "Shipping address city", 'wp-full-stripe-free' ),
			'%SHIPPING_STATE%' => __( "Shipping address state", 'wp-full-stripe-free' ),
			'%SHIPPING_ZIP%' => __( "Shipping address zip (or postal) code", 'wp-full-stripe-free' ),
			'%SHIPPING_COUNTRY%' => __( "Shipping address country", 'wp-full-stripe-free' ),
			'%SHIPPING_COUNTRY_CODE%' => __( "ISO code of the shipping address country", 'wp-full-stripe-free' ),
			'%COUPON_CODE%' => __( "Coupon code redeemed on the form", 'wp-full-stripe-free' ),
			'%DONATION_FREQUENCY%' => __( "Donation frequency (one-time, daily, weekly, monthly, or annual)", 'wp-full-stripe-free' ),
			'%CUSTOMFIELD1%' => __( "Custom field 1 value", 'wp-full-stripe-free' ),
			'%CARD_UPDATE_SECURITY_CODE%' => __( "Login code generated by the Customer Portal page", 'wp-full-stripe-free' ),
			'%IP_ADDRESS%' => __( "Customer's IP address", 'wp-full-stripe-free' )
		];

		return $macros;
	}
}
