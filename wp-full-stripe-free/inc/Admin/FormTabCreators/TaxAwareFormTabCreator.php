<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

use ThemeIsle\WPFullPay\Admin\Menu;

trait TaxAwareFormTabCreator {
	/**
	 * Add the "Payment & Tax" tab on the form edit page.
	 *
	 * @param array<array<string, string>> $tabs The existing tabs array.
	 * @return void
	 */
	protected function paymentAndTaxTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "Payment & Tax" tab on "Edit form" pages */
				__( 'Payment & Tax', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_PAYMENT_AND_TAX
		]
		);
	}
}
