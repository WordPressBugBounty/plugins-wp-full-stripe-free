<?php
// The confirmation dialog of each action, and the dialog shown while it runs.
foreach ( [
	[
		'cancel-subscription',
		'primary',
		__( 'Cancel subscription', 'wp-full-stripe-free' ),
		__( 'Keep subscription', 'wp-full-stripe-free' ),
		__( "The subscription will be cancelled immediately.", 'wp-full-stripe-free' ),
	],
	[
		'delete-subscription',
		'danger',
		__( 'Delete subscription', 'wp-full-stripe-free' ),
		__( 'Keep subscription', 'wp-full-stripe-free' ),
		__( 'After deleting this subscription from the WordPress database, you will still be able to find it in Stripe.', 'wp-full-stripe-free' ),
	],
] as list( $dialogAction, $dialogButtonStyle, $dialogConfirmLabel, $dialogCancelLabel, $dialogInProgressMessage ) ) {
	include 'wpfs-transactions-action-dialog-templates.php';
}
