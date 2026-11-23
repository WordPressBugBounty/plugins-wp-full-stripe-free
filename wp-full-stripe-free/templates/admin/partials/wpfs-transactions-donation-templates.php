<?php
// The confirmation dialog of each action, and the dialog shown while it runs.
foreach ( [
	[
		'refund-donation',
		'primary',
		__( 'Refund donation', 'wp-full-stripe-free' ),
		__( 'Cancel', 'wp-full-stripe-free' ),
		__( "It takes 7 days for the refund to appear on the customer's card.", 'wp-full-stripe-free' ),
	],
	[
		'cancel-donation',
		'primary',
		__( 'Cancel donation', 'wp-full-stripe-free' ),
		__( 'Keep donation', 'wp-full-stripe-free' ),
		__( "The recurring donation will be cancelled immediately.", 'wp-full-stripe-free' ),
	],
	[
		'delete-donation',
		'danger',
		__( 'Delete donation', 'wp-full-stripe-free' ),
		__( 'Keep donation', 'wp-full-stripe-free' ),
		__( 'After deleting this donation from the WordPress database, you will still be able to find it in Stripe.', 'wp-full-stripe-free' ),
	],
] as list( $dialogAction, $dialogButtonStyle, $dialogConfirmLabel, $dialogCancelLabel, $dialogInProgressMessage ) ) {
	include 'wpfs-transactions-action-dialog-templates.php';
}
