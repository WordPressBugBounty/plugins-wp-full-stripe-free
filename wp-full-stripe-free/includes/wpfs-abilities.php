<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * Registers WP Full Pay abilities with the WordPress Abilities API (WordPress 6.9+).
 *
 * Every ability is a thin wrapper over the services the admin screens already use.
 */
class MM_WPFS_Abilities {

	const CATEGORY = 'fullpay';
	const MAX_PER_PAGE = 100;
	const DEFAULT_PER_PAGE = 20;

	const CANCEL_AT_NOW = 'now';
	const CANCEL_AT_PERIOD_END = 'period_end';

	/**
	 * Hooks the registration callbacks. No-op when the Abilities API is not available.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'registerCategory' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'registerAbilities' ] );
	}

	/**
	 * @return void
	 */
	public static function registerCategory() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label' => __( 'WP Full Pay', 'wp-full-stripe-free' ),
				'description' => __( 'Payment forms, payments and subscriptions managed by WP Full Pay.', 'wp-full-stripe-free' ),
			]
		);
	}

	/**
	 * @return void
	 */
	public static function registerAbilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'fullpay/list-forms',
			[
				'label' => __( 'List payment forms', 'wp-full-stripe-free' ),
				'description' => __( 'Lists WP Full Pay forms (payment, subscription, donation and save card) with their type, layout and shortcode.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'type' => [
							'type' => 'string',
							'description' => __( 'Only return forms of this type.', 'wp-full-stripe-free' ),
							'enum' => self::getFormTypes(),
						],
						'layout' => [
							'type' => 'string',
							'description' => __( 'Only return forms with this layout.', 'wp-full-stripe-free' ),
							'enum' => self::getFormLayouts(),
						],
						'search' => [
							'type' => 'string',
							'description' => __( 'Only return forms whose identifier or display name contains this text.', 'wp-full-stripe-free' ),
						],
					],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'mode' => self::getModeSchema(),
						'total' => [ 'type' => 'integer' ],
						'forms' => [
							'type' => 'array',
							'items' => self::getFormSummarySchema(),
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'listForms' ],
				'permission_callback' => [ __CLASS__, 'canManageForms' ],
				'meta' => [
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'fullpay/get-form',
			[
				'label' => __( 'Get payment form', 'wp-full-stripe-free' ),
				'description' => __( 'Returns the full configuration of one WP Full Pay form. Form ids are unique only per type and layout, so all three are required.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'form_id' => [
							'type' => 'integer',
							'minimum' => 1,
							'description' => __( 'Form id as returned by fullpay/list-forms.', 'wp-full-stripe-free' ),
						],
						'type' => [
							'type' => 'string',
							'enum' => self::getFormTypes(),
						],
						'layout' => [
							'type' => 'string',
							'enum' => self::getFormLayouts(),
						],
					],
					'required' => [ 'form_id', 'type', 'layout' ],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'mode' => self::getModeSchema(),
						'form' => self::getFormSummarySchema(),
						'settings' => [
							'type' => 'object',
							'description' => __( 'Stored form settings. Webhook header values are redacted.', 'wp-full-stripe-free' ),
							'additionalProperties' => true,
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'getForm' ],
				'permission_callback' => [ __CLASS__, 'canManageForms' ],
				'meta' => [
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'fullpay/upsert-form',
			[
				'label' => __( 'Create form', 'wp-full-stripe-free' ),
				'description' => __( 'Creates a WP Full Pay form with the default settings of its type, exactly like the "Add form" admin screen. If a form with the same identifier, type and layout already exists it is returned unchanged. Changing the settings of an existing form is not supported; use the form editor.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => wp_strip_all_tags( __( "<strong>Identifier</strong> is used to insert the form into pages via a shortcode. Use alphanumerical characters, underscores and dashes, without spaces.", 'wp-full-stripe-free' ) ),
						],
						'display_name' => [
							'type' => 'string',
							'description' => __( 'Display name', 'wp-full-stripe-free' ),
						],
						'type' => [
							'type' => 'string',
							'enum' => self::getFormTypes(),
						],
						'layout' => [
							'type' => 'string',
							'enum' => self::getFormLayouts(),
						],
						'dry_run' => [
							'type' => 'boolean',
							'default' => false,
							'description' => __( 'Validate the input and report what would happen without saving anything.', 'wp-full-stripe-free' ),
						],
					],
					'required' => [ 'name', 'display_name', 'type', 'layout' ],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'mode' => self::getModeSchema(),
						'dry_run' => [ 'type' => 'boolean' ],
						'created' => [ 'type' => 'boolean' ],
						'form' => self::getFormSummarySchema(),
					],
				],
				'execute_callback' => [ __CLASS__, 'upsertForm' ],
				'permission_callback' => [ __CLASS__, 'canManageForms' ],
				'meta' => [
					'annotations' => [
						'readonly' => false,
						'destructive' => false,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'fullpay/list-payments',
			[
				'label' => __( 'List payments', 'wp-full-stripe-free' ),
				'description' => __( 'Lists one-time payment records with filters. Pass id to get a single payment in full. Returns personal data.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'minimum' => 1,
							'description' => __( 'Payment id. When set, the other filters are ignored and the payment is returned in full.', 'wp-full-stripe-free' ),
						],
						'mode' => self::getModeFilterSchema(),
						'status' => [
							'type' => 'string',
							'enum' => MM_WPFS_Utils::getPaymentStatuses(),
						],
						'customer' => [
							'type' => 'string',
							'description' => __( 'Matches customer name, email, Stripe customer id or payment intent id.', 'wp-full-stripe-free' ),
						],
						'form_id' => [
							'type' => 'integer',
							'minimum' => 1,
						],
						'form_type' => [
							'type' => 'string',
							'description' => __( 'Form type of the payment, for example inline_payment or checkout_payment. Use together with form_id.', 'wp-full-stripe-free' ),
						],
						'form_name' => [
							'type' => 'string',
							'description' => __( 'Identifier', 'wp-full-stripe-free' ),
						],
						'date_from' => [
							'type' => 'string',
							'description' => __( 'Only payments created on or after this date (YYYY-MM-DD).', 'wp-full-stripe-free' ),
						],
						'date_to' => [
							'type' => 'string',
							'description' => __( 'Only payments created on or before this date (YYYY-MM-DD).', 'wp-full-stripe-free' ),
						],
						'page' => [
							'type' => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
						'per_page' => [
							'type' => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => self::DEFAULT_PER_PAGE,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'mode' => self::getModeSchema(),
						'total' => [ 'type' => 'integer' ],
						'page' => [ 'type' => 'integer' ],
						'per_page' => [ 'type' => 'integer' ],
						'payments' => [
							'type' => 'array',
							'items' => [
								'type' => 'object',
								'additionalProperties' => true,
							],
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'listPayments' ],
				'permission_callback' => [ __CLASS__, 'canManageTransactions' ],
				'meta' => [
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'fullpay/list-subscriptions',
			[
				'label' => __( 'List subscriptions', 'wp-full-stripe-free' ),
				'description' => __( 'Lists subscription records with filters. Pass id to get a single subscription in full. Returns personal data.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'minimum' => 1,
							'description' => __( 'Subscription record id. When set, the other filters are ignored and the subscription is returned in full.', 'wp-full-stripe-free' ),
						],
						'mode' => self::getModeFilterSchema(),
						'status' => [
							'type' => 'string',
							'enum' => MM_WPFS_Utils::getSubscriptionStatuses(),
						],
						'plan' => [
							'type' => 'string',
							'description' => __( 'Stripe price or plan id.', 'wp-full-stripe-free' ),
						],
						'customer' => [
							'type' => 'string',
							'description' => __( 'Matches customer name, email, Stripe customer id or Stripe subscription id.', 'wp-full-stripe-free' ),
						],
						'page' => [
							'type' => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
						'per_page' => [
							'type' => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PER_PAGE,
							'default' => self::DEFAULT_PER_PAGE,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'mode' => self::getModeSchema(),
						'total' => [ 'type' => 'integer' ],
						'page' => [ 'type' => 'integer' ],
						'per_page' => [ 'type' => 'integer' ],
						'subscriptions' => [
							'type' => 'array',
							'items' => [
								'type' => 'object',
								'additionalProperties' => true,
							],
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'listSubscriptions' ],
				'permission_callback' => [ __CLASS__, 'canManageTransactions' ],
				'meta' => [
					'annotations' => [
						'readonly' => true,
						'destructive' => false,
						'idempotent' => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'fullpay/cancel-subscription',
			[
				'label' => __( 'Cancel subscription', 'wp-full-stripe-free' ),
				'description' => __( 'Cancels one running subscription in Stripe and marks it as cancelled locally. The mode must match both the subscription and the active Stripe API mode of the plugin. This cannot be undone.', 'wp-full-stripe-free' ),
				'category' => self::CATEGORY,
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'subscription_id' => [
							'type' => 'integer',
							'minimum' => 1,
							'description' => __( 'Subscription record id as returned by fullpay/list-subscriptions.', 'wp-full-stripe-free' ),
						],
						'mode' => [
							'type' => 'string',
							'enum' => [ MM_WPFS::STRIPE_API_MODE_TEST, MM_WPFS::STRIPE_API_MODE_LIVE ],
							'description' => __( 'Mode the subscription is expected to be in.', 'wp-full-stripe-free' ),
						],
						'at' => [
							'type' => 'string',
							'enum' => [ self::CANCEL_AT_NOW, self::CANCEL_AT_PERIOD_END ],
							'default' => self::CANCEL_AT_NOW,
							'description' => __( 'Cancel immediately or at the end of the current billing period.', 'wp-full-stripe-free' ),
						],
					],
					'required' => [ 'subscription_id', 'mode' ],
					'additionalProperties' => false,
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'subscription_id' => [ 'type' => 'integer' ],
						'stripe_subscription_id' => [ 'type' => 'string' ],
						'status' => [ 'type' => 'string' ],
						'at' => [ 'type' => 'string' ],
						'effective_at' => [
							'type' => 'string',
							'description' => __( 'UTC time of the cancellation when cancelled immediately; empty when cancelled at period end.', 'wp-full-stripe-free' ),
						],
						'stripe_confirmed' => [
							'type' => 'boolean',
							'description' => __( 'Whether the Stripe service reported a cancellation result.', 'wp-full-stripe-free' ),
						],
						'mode' => self::getModeSchema(),
						'customer_ref' => [
							'type' => 'object',
							'properties' => [
								'stripe_customer_id' => [ 'type' => 'string' ],
								'name' => [ 'type' => 'string' ],
								'email' => [ 'type' => 'string' ],
							],
						],
					],
				],
				'execute_callback' => [ __CLASS__, 'cancelSubscription' ],
				'permission_callback' => [ __CLASS__, 'canManageTransactions' ],
				'meta' => [
					'ai_connect' => false,
					'annotations' => [
						'readonly' => false,
						'destructive' => true,
						'idempotent' => false,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Mirrors the capability of the WP Full Pay admin screens and AJAX handlers
	 * (MM_WPFS_Admin_Menu::$capability, MM_WPFS_Admin::validate_request()).
	 *
	 * @return bool
	 */
	public static function canManageForms() {
		$capability = 'manage_options';
		if ( MM_WPFS_Utils::isDemoMode() ) {
			$capability = 'read';
		}

		return current_user_can( $capability );
	}

	/**
	 * Personal data and financial operations are limited to administrators.
	 *
	 * @return bool
	 */
	public static function canManageTransactions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function listForms( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$type = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : '';
		$layout = isset( $input['layout'] ) ? sanitize_text_field( $input['layout'] ) : '';
		$search = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

		if ( '' !== $type && ! in_array( $type, self::getFormTypes(), true ) ) {
			return new WP_Error( 'fullpay_invalid_form_type', __( 'Unknown form type.', 'wp-full-stripe-free' ) );
		}
		if ( '' !== $layout && ! in_array( $layout, self::getFormLayouts(), true ) ) {
			return new WP_Error( 'fullpay_invalid_form_layout', __( 'Unknown form layout.', 'wp-full-stripe-free' ) );
		}

		try {
			$forms = ( new MM_WPFS_Database() )->getAllForms();
		} catch ( Exception $ex ) {
			return new WP_Error( 'fullpay_database_error', $ex->getMessage() );
		}

		$result = [];
		foreach ( $forms as $form ) {
			if ( '' !== $type && $type !== $form->type ) {
				continue;
			}
			if ( '' !== $layout && $layout !== $form->layout ) {
				continue;
			}
			if ( '' !== $search && false === stripos( (string) $form->name, $search ) && false === stripos( (string) $form->displayName, $search ) ) {
				continue;
			}

			$summary = self::formatFormSummary( $form );
			$summary['last_transaction'] = isset( $form->created ) ? (string) $form->created : '';
			$result[] = $summary;
		}

		return [
			'mode' => self::getCurrentMode(),
			'total' => count( $result ),
			'forms' => $result,
		];
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function getForm( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$formId = isset( $input['form_id'] ) ? absint( $input['form_id'] ) : 0;
		$type = isset( $input['type'] ) ? sanitize_text_field( $input['type'] ) : '';
		$layout = isset( $input['layout'] ) ? sanitize_text_field( $input['layout'] ) : '';

		if ( 0 === $formId ) {
			return new WP_Error( 'fullpay_missing_form_id', __( 'A form id is required.', 'wp-full-stripe-free' ) );
		}
		if ( ! in_array( $type, self::getFormTypes(), true ) ) {
			return new WP_Error( 'fullpay_invalid_form_type', __( 'Unknown form type.', 'wp-full-stripe-free' ) );
		}
		if ( ! in_array( $layout, self::getFormLayouts(), true ) ) {
			return new WP_Error( 'fullpay_invalid_form_layout', __( 'Unknown form layout.', 'wp-full-stripe-free' ) );
		}

		$row = self::findFormRow( $formId, $type, $layout );
		if ( is_null( $row ) ) {
			return new WP_Error( 'fullpay_form_not_found', __( 'Form not found.', 'wp-full-stripe-free' ) );
		}

		$form = new stdClass();
		$form->id = $formId;
		$form->type = $type;
		$form->layout = $layout;
		$form->name = isset( $row['name'] ) ? $row['name'] : '';
		$form->displayName = isset( $row['displayName'] ) ? $row['displayName'] : '';

		return [
			'mode' => self::getCurrentMode(),
			'form' => self::formatFormSummary( $form ),
			'settings' => self::formatFormSettings( $row ),
		];
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function upsertForm( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$dryRun = ! empty( $input['dry_run'] );

		$postData = [
			MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_NAME => isset( $input['name'] ) ? (string) $input['name'] : '',
			MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_DISPLAY_NAME => isset( $input['display_name'] ) ? (string) $input['display_name'] : '',
			MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_TYPE => isset( $input['type'] ) ? (string) $input['type'] : '',
			MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_LAYOUT => isset( $input['layout'] ) ? (string) $input['layout'] : '',
		];

		$type = sanitize_text_field( $postData[ MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_TYPE ] );
		$layout = sanitize_text_field( $postData[ MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_LAYOUT ] );
		$name = sanitize_text_field( $postData[ MM_WPFS_Admin_CreateFormViewConstants::FIELD_FORM_NAME ] );

		try {
			// A form with the same identifier, type and layout makes the call a no-op.
			if ( '' !== $name && in_array( $type, self::getFormTypes(), true ) && in_array( $layout, self::getFormLayouts(), true ) ) {
				$existing = self::findFormByName( $name, $type, $layout );
				if ( ! is_null( $existing ) ) {
					return [
						'mode' => self::getCurrentMode(),
						'dry_run' => $dryRun,
						'created' => false,
						'form' => self::formatFormSummary( $existing ),
					];
				}
			}

			$createFormModel = new MM_WPFS_Admin_CreateFormModel( self::createLoggerService() );
			$bindingResult = $createFormModel->bindByArray( $postData );

			if ( $bindingResult->hasErrors() ) {
				return self::bindingResultToError( $bindingResult );
			}

			$form = new stdClass();
			$form->id = 0;
			$form->type = $createFormModel->getType();
			$form->layout = $createFormModel->getLayout();
			$form->name = $createFormModel->getName();
			$form->displayName = $createFormModel->getDisplayName();

			if ( ! $dryRun ) {
				$form->id = (int) ( new MM_WPFS_Admin_CreateFormFactory() )->createForm( $createFormModel );
			}
		} catch ( Exception $ex ) {
			return new WP_Error( 'fullpay_form_create_failed', $ex->getMessage() );
		}

		return [
			'mode' => self::getCurrentMode(),
			'dry_run' => $dryRun,
			'created' => ! $dryRun,
			'form' => self::formatFormSummary( $form ),
		];
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function listPayments( $input = [] ) {
		global $wpdb;

		$input = is_array( $input ) ? $input : [];
		$db = new MM_WPFS_Database();

		if ( ! empty( $input['id'] ) ) {
			$payment = $db->getPayment( absint( $input['id'] ) );
			if ( ! is_object( $payment ) ) {
				return new WP_Error( 'fullpay_payment_not_found', __( 'No payment found.', 'wp-full-stripe-free' ) );
			}

			return [
				'mode' => self::getCurrentMode(),
				'total' => 1,
				'page' => 1,
				'per_page' => 1,
				'payments' => [ self::formatPayment( $payment, true ) ],
			];
		}

		$where = [];
		$args = [];

		$modeError = self::addModeCondition( $input, $where, $args );
		if ( is_wp_error( $modeError ) ) {
			return $modeError;
		}

		if ( isset( $input['status'] ) && '' !== $input['status'] ) {
			$status = sanitize_text_field( $input['status'] );
			// Same conditions as the Transactions > Payments admin table.
			switch ( $status ) {
				case MM_WPFS::PAYMENT_STATUS_FAILED:
					$where[] = 'last_charge_status = %s';
					$args[] = MM_WPFS::STRIPE_CHARGE_STATUS_FAILED;
					break;
				case MM_WPFS::PAYMENT_STATUS_PENDING:
					$where[] = 'last_charge_status = %s';
					$args[] = MM_WPFS::STRIPE_CHARGE_STATUS_PENDING;
					break;
				case MM_WPFS::PAYMENT_STATUS_EXPIRED:
					$where[] = 'expired = 1';
					break;
				case MM_WPFS::PAYMENT_STATUS_REFUNDED:
					$where[] = 'refunded = 1';
					break;
				case MM_WPFS::PAYMENT_STATUS_RELEASED:
					$where[] = '(refunded = 1 AND captured = 0)';
					break;
				case MM_WPFS::PAYMENT_STATUS_PAID:
					$where[] = '(last_charge_status = %s AND paid = 1 AND captured = 1 AND expired = 0 AND refunded = 0)';
					$args[] = MM_WPFS::STRIPE_CHARGE_STATUS_SUCCEEDED;
					break;
				case MM_WPFS::PAYMENT_STATUS_AUTHORIZED:
					$where[] = '(last_charge_status = %s AND paid = 1 AND captured = 0 AND expired = 0 AND refunded = 0)';
					$args[] = MM_WPFS::STRIPE_CHARGE_STATUS_SUCCEEDED;
					break;
				default:
					return new WP_Error( 'fullpay_invalid_status', __( 'Unknown payment status.', 'wp-full-stripe-free' ) );
			}
		}

		if ( isset( $input['customer'] ) && '' !== trim( (string) $input['customer'] ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $input['customer'] ) ) . '%';
			$where[] = '(name LIKE %s OR email LIKE %s OR eventID LIKE %s OR stripeCustomerID LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}

		if ( ! empty( $input['form_id'] ) ) {
			$where[] = 'formId = %d';
			$args[] = absint( $input['form_id'] );
		}
		if ( isset( $input['form_type'] ) && '' !== $input['form_type'] ) {
			$where[] = 'formType = %s';
			$args[] = sanitize_text_field( $input['form_type'] );
		}
		if ( isset( $input['form_name'] ) && '' !== $input['form_name'] ) {
			$where[] = 'formName = %s';
			$args[] = sanitize_text_field( $input['form_name'] );
		}

		foreach ( [ 'date_from' => [ '>=', ' 00:00:00' ], 'date_to' => [ '<=', ' 23:59:59' ] ] as $key => $rule ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				continue;
			}
			$date = self::parseDate( $input[ $key ] );
			if ( is_null( $date ) ) {
				return new WP_Error( 'fullpay_invalid_date', __( 'Dates must use the YYYY-MM-DD format.', 'wp-full-stripe-free' ) );
			}
			$where[] = 'created ' . $rule[0] . ' %s';
			$args[] = $date . $rule[1];
		}

		list( $page, $perPage ) = self::getPagination( $input );
		$rows = self::queryRows( $wpdb->prefix . 'fullstripe_payments', 'paymentID', $where, $args, $page, $perPage );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$payments = [];
		foreach ( $rows['rows'] as $row ) {
			$payments[] = self::formatPayment( $row, false );
		}

		return [
			'mode' => self::getCurrentMode(),
			'total' => $rows['total'],
			'page' => $page,
			'per_page' => $perPage,
			'payments' => $payments,
		];
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function listSubscriptions( $input = [] ) {
		global $wpdb;

		$input = is_array( $input ) ? $input : [];
		$db = new MM_WPFS_Database();

		if ( ! empty( $input['id'] ) ) {
			$id = absint( $input['id'] );
			$subscription = $db->findSubscriberById( $id );
			if ( ! is_object( $subscription ) ) {
				return new WP_Error(
					'fullpay_subscription_not_found',
					/* translators: Error message displayed when a subscription is not found.
					 * p1: Subscription identifier
					 */
					sprintf( __( "Subscription '%s' not found!", 'wp-full-stripe-free' ), $id )
				);
			}

			return [
				'mode' => self::getCurrentMode(),
				'total' => 1,
				'page' => 1,
				'per_page' => 1,
				'subscriptions' => [ self::formatSubscription( $subscription, true ) ],
			];
		}

		$where = [];
		$args = [];

		$modeError = self::addModeCondition( $input, $where, $args );
		if ( is_wp_error( $modeError ) ) {
			return $modeError;
		}

		if ( isset( $input['status'] ) && '' !== $input['status'] ) {
			$status = sanitize_text_field( $input['status'] );
			if ( ! in_array( $status, MM_WPFS_Utils::getSubscriptionStatuses(), true ) ) {
				return new WP_Error( 'fullpay_invalid_status', __( 'Unknown subscription status.', 'wp-full-stripe-free' ) );
			}
			$where[] = 'status = %s';
			$args[] = $status;
		}

		if ( isset( $input['plan'] ) && '' !== $input['plan'] ) {
			$where[] = 'planID = %s';
			$args[] = sanitize_text_field( $input['plan'] );
		}

		if ( isset( $input['customer'] ) && '' !== trim( (string) $input['customer'] ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $input['customer'] ) ) . '%';
			$where[] = '(name LIKE %s OR email LIKE %s OR stripeSubscriptionID LIKE %s OR stripeCustomerID LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}

		list( $page, $perPage ) = self::getPagination( $input );
		$rows = self::queryRows( $wpdb->prefix . 'fullstripe_subscribers', 'subscriberID', $where, $args, $page, $perPage );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$subscriptions = [];
		foreach ( $rows['rows'] as $row ) {
			$subscriptions[] = self::formatSubscription( $row, false );
		}

		return [
			'mode' => self::getCurrentMode(),
			'total' => $rows['total'],
			'page' => $page,
			'per_page' => $perPage,
			'subscriptions' => $subscriptions,
		];
	}

	/**
	 * Same service calls as the admin "Cancel" action (MM_WPFS_Admin::cancelSubscription()).
	 *
	 * @param mixed $input
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function cancelSubscription( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$id = isset( $input['subscription_id'] ) ? absint( $input['subscription_id'] ) : 0;
		$mode = isset( $input['mode'] ) ? sanitize_text_field( $input['mode'] ) : '';
		$at = isset( $input['at'] ) && '' !== $input['at'] ? sanitize_text_field( $input['at'] ) : self::CANCEL_AT_NOW;

		if ( 0 === $id ) {
			return new WP_Error( 'fullpay_missing_subscription_id', __( 'A subscription id is required.', 'wp-full-stripe-free' ) );
		}
		if ( ! in_array( $mode, [ MM_WPFS::STRIPE_API_MODE_TEST, MM_WPFS::STRIPE_API_MODE_LIVE ], true ) ) {
			return new WP_Error( 'fullpay_invalid_mode', __( 'The mode must be "test" or "live".', 'wp-full-stripe-free' ) );
		}
		if ( ! in_array( $at, [ self::CANCEL_AT_NOW, self::CANCEL_AT_PERIOD_END ], true ) ) {
			return new WP_Error( 'fullpay_invalid_cancel_time', __( 'The cancellation time must be "now" or "period_end".', 'wp-full-stripe-free' ) );
		}

		$db = new MM_WPFS_Database();
		$subscriber = $db->findSubscriberById( $id );
		if ( ! is_object( $subscriber ) ) {
			return new WP_Error(
				'fullpay_subscription_not_found',
				/* translators: Error message displayed when a subscription is not found.
				 * p1: Subscription identifier
				 */
				sprintf( __( "Subscription '%s' not found!", 'wp-full-stripe-free' ), $id )
			);
		}

		$subscriberMode = self::getModeLabel( $subscriber->livemode );
		if ( $subscriberMode !== $mode ) {
			return new WP_Error(
				'fullpay_mode_mismatch',
				/* translators: %s is the Stripe mode of the subscription, "test" or "live". */
				sprintf( __( 'This subscription was created in %s mode.', 'wp-full-stripe-free' ), $subscriberMode )
			);
		}
		if ( self::getCurrentMode() !== $subscriberMode ) {
			return new WP_Error(
				'fullpay_api_mode_mismatch',
				/* translators: %s is the active Stripe mode of the plugin, "test" or "live". */
				sprintf( __( 'The plugin is in %s mode, so this subscription cannot be cancelled now.', 'wp-full-stripe-free' ), self::getCurrentMode() )
			);
		}
		if ( MM_WPFS::SUBSCRIBER_STATUS_RUNNING !== $subscriber->status ) {
			return new WP_Error( 'fullpay_subscription_not_running', __( 'Only running subscriptions can be cancelled.', 'wp-full-stripe-free' ) );
		}
		if ( empty( $subscriber->stripeSubscriptionID ) ) {
			return new WP_Error( 'fullpay_missing_stripe_subscription', __( 'The subscription has no Stripe subscription id.', 'wp-full-stripe-free' ) );
		}

		try {
			do_action( 'fullstripe_admin_cancel_subscriber_action', $id );

			$loggerService = self::createLoggerService();
			$staticContext = new MM_WPFS_StaticContext( $loggerService, new MM_WPFS_Options() );
			$stripe = new MM_WPFS_Stripe( MM_WPFS_Stripe::getStripeAuthenticationToken( $staticContext ), $loggerService );

			// Stripe first, so a failed request leaves the local record untouched.
			$confirmed = $stripe->cancelSubscription(
				$subscriber->stripeCustomerID,
				$subscriber->stripeSubscriptionID,
				self::CANCEL_AT_PERIOD_END === $at
			);
			$db->cancelSubscription( $id );
		} catch ( Exception $ex ) {
			return new WP_Error( 'fullpay_cancel_failed', $ex->getMessage() );
		}

		$updated = $db->findSubscriberById( $id );

		return [
			'subscription_id' => $id,
			'stripe_subscription_id' => (string) $subscriber->stripeSubscriptionID,
			'status' => is_object( $updated ) ? (string) $updated->status : MM_WPFS::SUBSCRIBER_STATUS_CANCELLED,
			'at' => $at,
			'effective_at' => self::CANCEL_AT_NOW === $at ? gmdate( 'Y-m-d\TH:i:s\Z' ) : '',
			'stripe_confirmed' => (bool) $confirmed,
			'mode' => $subscriberMode,
			'customer_ref' => [
				'stripe_customer_id' => (string) $subscriber->stripeCustomerID,
				'name' => (string) $subscriber->name,
				'email' => (string) $subscriber->email,
			],
		];
	}

	/**
	 * @return string[]
	 */
	private static function getFormTypes() {
		return [
			MM_WPFS::FORM_TYPE_PAYMENT,
			MM_WPFS::FORM_TYPE_SUBSCRIPTION,
			MM_WPFS::FORM_TYPE_DONATION,
			MM_WPFS::FORM_TYPE_SAVE_CARD,
		];
	}

	/**
	 * @return string[]
	 */
	private static function getFormLayouts() {
		return [
			MM_WPFS::FORM_LAYOUT_INLINE,
			MM_WPFS::FORM_LAYOUT_CHECKOUT,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getModeSchema() {
		return [
			'type' => 'string',
			'enum' => [ MM_WPFS::STRIPE_API_MODE_TEST, MM_WPFS::STRIPE_API_MODE_LIVE ],
			'description' => __( 'Stripe mode. On list results this is the active API mode of the plugin; each record carries its own mode.', 'wp-full-stripe-free' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getModeFilterSchema() {
		return [
			'type' => 'string',
			'enum' => [ MM_WPFS::STRIPE_API_MODE_TEST, MM_WPFS::STRIPE_API_MODE_LIVE, 'all' ],
			'description' => __( 'Record mode to return. Defaults to the active API mode of the plugin.', 'wp-full-stripe-free' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getFormSummarySchema() {
		return [
			'type' => 'object',
			'properties' => [
				'id' => [ 'type' => 'integer' ],
				'type' => [ 'type' => 'string' ],
				'layout' => [ 'type' => 'string' ],
				'name' => [ 'type' => 'string' ],
				'display_name' => [ 'type' => 'string' ],
				'shortcode' => [ 'type' => 'string' ],
				'edit_url' => [ 'type' => 'string' ],
				'last_transaction' => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * @return string
	 */
	private static function getCurrentMode() {
		return MM_WPFS_Utils::isLiveMode() ? MM_WPFS::STRIPE_API_MODE_LIVE : MM_WPFS::STRIPE_API_MODE_TEST;
	}

	/**
	 * @param mixed $liveMode
	 *
	 * @return string
	 */
	private static function getModeLabel( $liveMode ) {
		return 1 === (int) $liveMode ? MM_WPFS::STRIPE_API_MODE_LIVE : MM_WPFS::STRIPE_API_MODE_TEST;
	}

	/**
	 * @return MM_WPFS_LoggerService
	 */
	private static function createLoggerService() {
		$options = new MM_WPFS_Options();

		return new MM_WPFS_LoggerService( $options->get( MM_WPFS_Options::OPTION_LOG_LEVEL ), 1 == $options->get( MM_WPFS_Options::OPTION_LOG_TO_WEB_SERVER ) );
	}

	/**
	 * @param object $form
	 *
	 * @return array<string, mixed>
	 */
	private static function formatFormSummary( $form ) {
		$shortcode = MM_WPFS_Shortcode::createShortCodeByForm( $form );
		$id = (int) $form->id;

		return [
			'id' => $id,
			'type' => (string) $form->type,
			'layout' => (string) $form->layout,
			'name' => (string) $form->name,
			'display_name' => (string) $form->displayName,
			'shortcode' => false === $shortcode ? '' : $shortcode,
			'edit_url' => $id > 0 ? (string) MM_WPFS_Utils::getFormEditUrl( $id, $form->type, $form->layout ) : '',
		];
	}

	/**
	 * @param int $formId
	 * @param string $type
	 * @param string $layout
	 *
	 * @return array<string, mixed>|null
	 */
	private static function findFormRow( $formId, $type, $layout ) {
		$db = new MM_WPFS_Database();
		$inline = MM_WPFS::FORM_LAYOUT_INLINE === $layout;
		$row = null;

		switch ( $type ) {
			case MM_WPFS::FORM_TYPE_PAYMENT:
			case MM_WPFS::FORM_TYPE_SAVE_CARD:
				$row = $inline ? $db->getInlinePaymentFormAsArrayById( $formId ) : $db->getCheckoutPaymentFormAsArrayById( $formId );
				break;
			case MM_WPFS::FORM_TYPE_SUBSCRIPTION:
				$row = $inline ? $db->getInlineSubscriptionFormAsArrayById( $formId ) : $db->getCheckoutSubscriptionFormAsArrayById( $formId );
				break;
			case MM_WPFS::FORM_TYPE_DONATION:
				$row = $inline ? $db->getInlineDonationFormAsArrayById( $formId ) : $db->getCheckoutDonationFormAsArrayById( $formId );
				break;
		}

		if ( ! is_array( $row ) ) {
			return null;
		}

		// Save card forms share their tables with payment forms.
		if ( MM_WPFS::FORM_TYPE_PAYMENT === $type || MM_WPFS::FORM_TYPE_SAVE_CARD === $type ) {
			$isSaveCard = isset( $row['customAmount'] ) && MM_WPFS_Admin_CreateFormFactory::CUSTOM_AMOUNT_SAVE_CARD === $row['customAmount'];
			if ( ( MM_WPFS::FORM_TYPE_SAVE_CARD === $type ) !== $isSaveCard ) {
				return null;
			}
		}

		return $row;
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @param string $layout
	 *
	 * @return object|null
	 */
	private static function findFormByName( $name, $type, $layout ) {
		$forms = ( new MM_WPFS_Database() )->getAllForms();

		foreach ( $forms as $form ) {
			if ( $name === $form->name && $type === $form->type && $layout === $form->layout ) {
				return $form;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $row
	 *
	 * @return array<string, mixed>
	 */
	private static function formatFormSettings( $row ) {
		$settings = [];

		foreach ( $row as $key => $value ) {
			if ( is_string( $value ) && '' !== $value && ( '[' === $value[0] || '{' === $value[0] ) ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$value = $decoded;
				}
			}

			if ( 'webhook' === $key && is_array( $value ) && isset( $value['headers'] ) && is_array( $value['headers'] ) ) {
				foreach ( array_keys( $value['headers'] ) as $header ) {
					$value['headers'][ $header ] = '[redacted]';
				}
			} elseif ( 'webhook' === $key && is_string( $value ) && '' !== $value ) {
				$value = '[redacted]';
			}

			$settings[ $key ] = $value;
		}

		return $settings;
	}

	/**
	 * @param MM_WPFS_BindingResult $bindingResult
	 *
	 * @return WP_Error
	 */
	private static function bindingResultToError( $bindingResult ) {
		$messages = [];
		foreach ( $bindingResult->getFieldErrors() as $fieldError ) {
			$messages[] = $fieldError['message'];
		}
		foreach ( $bindingResult->getGlobalErrors() as $globalError ) {
			$messages[] = $globalError;
		}

		return new WP_Error(
			'fullpay_validation_failed',
			implode( ' ', $messages ),
			[
				'status' => 400,
				'errors' => $messages,
			]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @param string[] $where
	 * @param array<int, mixed> $args
	 *
	 * @return true|WP_Error
	 */
	private static function addModeCondition( $input, &$where, &$args ) {
		$mode = isset( $input['mode'] ) && '' !== $input['mode'] ? sanitize_text_field( $input['mode'] ) : self::getCurrentMode();

		if ( 'all' === $mode ) {
			return true;
		}
		if ( ! in_array( $mode, [ MM_WPFS::STRIPE_API_MODE_TEST, MM_WPFS::STRIPE_API_MODE_LIVE ], true ) ) {
			return new WP_Error( 'fullpay_invalid_mode', __( 'The mode must be "test", "live" or "all".', 'wp-full-stripe-free' ) );
		}

		$where[] = 'livemode = %d';
		$args[] = MM_WPFS::STRIPE_API_MODE_LIVE === $mode ? 1 : 0;

		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return int[]
	 */
	private static function getPagination( $input ) {
		$page = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
		$perPage = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : self::DEFAULT_PER_PAGE;
		$perPage = min( self::MAX_PER_PAGE, max( 1, $perPage ) );

		return [ $page, $perPage ];
	}

	/**
	 * @param mixed $value
	 *
	 * @return string|null
	 */
	private static function parseDate( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return null;
		}
		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * @param string $table Table name built from the WordPress prefix and a fixed suffix.
	 * @param string $idColumn Fixed primary key column name.
	 * @param string[] $where Conditions using placeholders only.
	 * @param array<int, mixed> $args
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return array{total: int, rows: array<int, object>}|WP_Error
	 */
	private static function queryRows( $table, $idColumn, $where, $args, $page, $perPage ) {
		global $wpdb;

		$whereSql = empty( $where ) ? '' : ' WHERE ' . implode( ' AND ', $where );
		$countSql = "SELECT COUNT(*) FROM {$table}{$whereSql}";
		$rowsSql = "SELECT * FROM {$table}{$whereSql} ORDER BY {$idColumn} DESC LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table and column names are fixed, every value goes through prepare().
		$total = empty( $args ) ? $wpdb->get_var( $countSql ) : $wpdb->get_var( $wpdb->prepare( $countSql, $args ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $rowsSql, array_merge( $args, [ $perPage, ( $page - 1 ) * $perPage ] ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'fullpay_database_error', __( 'The records could not be read.', 'wp-full-stripe-free' ) );
		}

		return [
			'total' => (int) $total,
			'rows' => $rows,
		];
	}

	/**
	 * @param object $payment
	 * @param bool $full
	 *
	 * @return array<string, mixed>
	 */
	private static function formatPayment( $payment, $full ) {
		$data = [
			'id' => (int) $payment->paymentID,
			'stripe_payment_intent_id' => (string) $payment->eventID,
			'status' => MM_WPFS_Utils::getPaymentStatus( $payment ),
			'amount' => (int) $payment->amount,
			'currency' => (string) $payment->currency,
			'mode' => self::getModeLabel( $payment->livemode ),
			'payment_method' => (string) $payment->payment_method,
			'created' => (string) $payment->created,
			'customer' => [
				'stripe_customer_id' => (string) $payment->stripeCustomerID,
				'name' => (string) $payment->name,
				'email' => (string) $payment->email,
			],
			'form' => [
				'id' => (int) $payment->formId,
				'type' => (string) $payment->formType,
				'name' => (string) $payment->formName,
			],
		];

		if ( ! $full ) {
			return $data;
		}

		$data['description'] = (string) $payment->description;
		$data['fee'] = (int) $payment->fee;
		$data['price_id'] = (string) $payment->priceId;
		$data['coupon'] = (string) $payment->coupon;
		$data['state'] = [
			'paid' => 1 === (int) $payment->paid,
			'captured' => 1 === (int) $payment->captured,
			'refunded' => 1 === (int) $payment->refunded,
			'expired' => 1 === (int) $payment->expired,
			'last_charge_status' => (string) $payment->last_charge_status,
			'failure_code' => (string) $payment->failure_code,
			'failure_message' => (string) $payment->failure_message,
		];
		$data['customer']['phone'] = (string) $payment->phoneNumber;
		$data['billing_address'] = self::formatAddress( $payment, 'billingName', 'address' );
		$data['shipping_address'] = self::formatAddress( $payment, 'shippingName', 'shippingAddress' );
		$data['custom_fields'] = self::decodeCustomFields( $payment->customFields );

		return $data;
	}

	/**
	 * @param object $subscription
	 * @param bool $full
	 *
	 * @return array<string, mixed>
	 */
	private static function formatSubscription( $subscription, $full ) {
		$data = [
			'id' => (int) $subscription->subscriberID,
			'stripe_subscription_id' => (string) $subscription->stripeSubscriptionID,
			'status' => (string) $subscription->status,
			'plan' => (string) $subscription->planID,
			'quantity' => (int) $subscription->quantity,
			'mode' => self::getModeLabel( $subscription->livemode ),
			'created' => (string) $subscription->created,
			'cancelled' => (string) $subscription->cancelled,
			'customer' => [
				'stripe_customer_id' => (string) $subscription->stripeCustomerID,
				'name' => (string) $subscription->name,
				'email' => (string) $subscription->email,
			],
			'form' => [
				'id' => (int) $subscription->formId,
				'name' => (string) $subscription->formName,
			],
		];

		if ( ! $full ) {
			return $data;
		}

		$data['coupon'] = (string) $subscription->coupon;
		$data['charge_maximum_count'] = (int) $subscription->chargeMaximumCount;
		$data['charge_current_count'] = (int) $subscription->chargeCurrentCount;
		$data['invoice_created_count'] = (int) $subscription->invoiceCreatedCount;
		$data['vat_percent'] = (string) $subscription->vatPercent;
		$data['customer']['phone'] = (string) $subscription->phoneNumber;
		$data['billing_address'] = self::formatAddress( $subscription, 'billingName', 'address' );
		$data['shipping_address'] = self::formatAddress( $subscription, 'shippingName', 'shippingAddress' );
		$data['custom_fields'] = self::decodeCustomFields( $subscription->customFields );

		return $data;
	}

	/**
	 * @param object $record
	 * @param string $nameColumn
	 * @param string $prefix
	 *
	 * @return array<string, string>
	 */
	private static function formatAddress( $record, $nameColumn, $prefix ) {
		$address = [ 'name' => isset( $record->$nameColumn ) ? (string) $record->$nameColumn : '' ];

		foreach ( [ 'Line1' => 'line1', 'Line2' => 'line2', 'City' => 'city', 'State' => 'state', 'Zip' => 'zip', 'Country' => 'country', 'CountryCode' => 'country_code' ] as $suffix => $key ) {
			$column = $prefix . $suffix;
			$address[ $key ] = isset( $record->$column ) ? (string) $record->$column : '';
		}

		return $address;
	}

	/**
	 * @param mixed $customFields
	 *
	 * @return mixed
	 */
	private static function decodeCustomFields( $customFields ) {
		if ( ! is_string( $customFields ) || '' === $customFields ) {
			return [];
		}

		$decoded = json_decode( $customFields, true );

		return is_array( $decoded ) ? $decoded : $customFields;
	}
}

MM_WPFS_Abilities::init();
