<?php

namespace ThemeIsle\WPFullPay;

use ThemeIsle\WPFullPay\CustomerPortal\CustomerPortalModel;

class ViewTemplateUtils {

	/**
	 * @param CustomerPortalModel $model
	 */
	public static function fullstripe_customer_portal_header( $model ) {
		$headerContent = '';
		try {
			$headerContent = apply_filters(
				Main::FILTER_NAME_CUSTOMER_PORTAL_HEADER,
				$headerContent,
				[
					'email'            => $model->getCustomerEmail(),
					'stripeCustomerId' => $model->getStripeCustomer()->id,
					'stripeClient'     => $model->getStripeClient()->getStripeClient()
				]
			);
		} catch ( \Exception $e ) {
			Utils::logException( $e );
		}
		echo $headerContent;
	}

	/**
	 * @param CustomerPortalModel $model
	 */
	public static function fullstripe_customer_portal_footer( $model ) {
		$footerContent = '';
		try {
			$footerContent = apply_filters(
				Main::FILTER_NAME_CUSTOMER_PORTAL_FOOTER,
				$footerContent,
				[
					'email'            => $model->getCustomerEmail(),
					'stripeCustomerId' => $model->getStripeCustomer()->id,
					'stripeClient'     => $model->getStripeClient()->getStripeClient()
				]
			);
		} catch ( \Exception $e ) {
			Utils::logException( $e );
		}
		echo $footerContent;
	}


}
