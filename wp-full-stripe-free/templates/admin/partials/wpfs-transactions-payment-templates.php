<?php
// The confirmation dialog of each action, and the dialog shown while it runs.
foreach ( [
	[
		'refund-payment',
		'primary',
		__( 'Refund payment', 'wp-full-stripe-free' ),
		__( 'Cancel', 'wp-full-stripe-free' ),
		__( "It takes 7 days for the refund to appear on the customer's card.", 'wp-full-stripe-free' ),
	],
	[
		'capture-payment',
		'primary',
		__( 'Capture payment', 'wp-full-stripe-free' ),
		__( 'Cancel', 'wp-full-stripe-free' ),
		__( "A captured payment is charged immediately.", 'wp-full-stripe-free' ),
	],
	[
		'delete-payment',
		'danger',
		__( 'Delete payment', 'wp-full-stripe-free' ),
		__( 'Keep payment', 'wp-full-stripe-free' ),
		__( 'After deleting this payment from the WordPress database, you will still be able to find it in Stripe.', 'wp-full-stripe-free' ),
	],
] as list( $dialogAction, $dialogButtonStyle, $dialogConfirmLabel, $dialogCancelLabel, $dialogInProgressMessage ) ) {
	include 'wpfs-transactions-action-dialog-templates.php';
}
