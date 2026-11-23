<?php
// The confirmation dialog of each action, and the dialog shown while it runs.
foreach ( [
	[
		'delete-saved-card',
		'danger',
		__( 'Delete saved card', 'wp-full-stripe-free' ),
		__( 'Keep saved card', 'wp-full-stripe-free' ),
		__( 'After deleting this saved card from the WordPress database, you will still be able to find it in Stripe.', 'wp-full-stripe-free' ),
	],
] as list( $dialogAction, $dialogButtonStyle, $dialogConfirmLabel, $dialogCancelLabel, $dialogInProgressMessage ) ) {
	include 'wpfs-transactions-action-dialog-templates.php';
}
