<?php

namespace ThemeIsle\WPFullPay\Admin\FormTabCreators;

use ThemeIsle\WPFullPay\Admin\Menu;

abstract class FormTabCreator {
	/**
	 * @return array<array<string, string>>
	 */
	abstract public function getTabs();

	protected function generalTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "General" tab on "Edit form" pages */
				__( 'General', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_GENERAL
		]
		);
	}

	protected function paymentTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "Payment" tab on "Edit form" pages */
				__( 'Payment', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_PAYMENT
		]
		);
	}

	protected function appearanceTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "Appearance" tab on "Edit form" pages */
				__( 'Appearance', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_APPEARANCE
		]
		);
	}

	protected function formFieldsTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "Form fields" tab on "Edit form" pages */
				__( 'Form fields', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_FORM_LAYOUT
		]
		);
	}

	/**
	 * Add the "Notifications & Integrations" tab on the form edit page.
	 *
	 * @param array<array<string, string>> $tabs The existing tabs array.
	 * @return void
	 */
	protected function notificationsAndIntegrationTab( &$tabs ) {
		array_push( $tabs, [
			'title' =>
				/* translators: Label of the "Notifications & Integrations" tab on "Edit form" pages */
				__( 'Notifications & Integrations', 'wp-full-stripe-free' ),
			'tab' => Menu::PARAM_VALUE_TAB_NOTIFICATIONS_AND_INTEGRATION
		]
		);
	}
}
