<?php

/**
 * The server-side transaction a client-supplied PaymentIntent has to be bound to.
 */
class MM_WPFS_PaymentIntentBindingContext {
	/** @var string|null Form type*/
	public $formType = null;

	/** @var string|null Name of the form. */
	public $formName = null;

	/** @var int|string|null Numeric id of the form record, when available. */
	public $formId = null;

	/** @var string|null Currency of the form record. */
	public $currency = null;

	/** @var string[]|null Price ids configured on the form, or null when they cannot be determined. */
	public $allowedPriceIds = null;

	/** @var bool Allows custom amount. */
	public $allowsCustomAmount = false;

	/** @var string|null Price id the client claims is selected. */
	public $selectedPriceId = null;
}

/**
 * Outcome of a PaymentIntent binding check: valid, or invalid.
 * Reasons are never returned to the client, so a caller cannot
 * probe foreign PaymentIntents through the error message.
 */
class MM_WPFS_PaymentIntentBindingResult {
	/** @var string|null */
	private $reasonCode;

	/**
	 * @param string|null $reasonCode
	 */
	private function __construct( $reasonCode ) {
		$this->reasonCode = $reasonCode;
	}

	/**
	 * @return MM_WPFS_PaymentIntentBindingResult
	 */
	public static function valid() {
		return new self( null );
	}

	/**
	 * @param string $reasonCode One of the MM_WPFS_PaymentIntentBinding::REASON_* constants.
	 * @return MM_WPFS_PaymentIntentBindingResult
	 */
	public static function invalid( $reasonCode ) {
		return new self( $reasonCode );
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return is_null( $this->reasonCode );
	}

	/**
	 * @return string|null
	 */
	public function getReasonCode() {
		return $this->reasonCode;
	}
}

/**
 * Binds PaymentIntents to the form transaction they were created for.
 *
 * A valid form nonce only proves that the request comes from a rendered form, not that the
 * PaymentIntent id sent along with it belongs to that checkout. The binding metadata written
 * here at PaymentIntent creation time is what makes ownership verifiable later: the public
 * pricing endpoints retrieve the PaymentIntent from Stripe and compare its metadata, currency
 * and status with the current form transaction before any amount is recalculated or updated.
 */
class MM_WPFS_PaymentIntentBinding {

	const METADATA_KEY_FORM_TYPE = 'wpfs_form_type';
	const METADATA_KEY_FORM_NAME = 'wpfs_form_name';
	const METADATA_KEY_FORM_ID = 'wpfs_form_id';
	const METADATA_KEY_TRANSACTION_ID = 'wpfs_transaction_id';

	const REASON_MISSING_PAYMENT_INTENT = 'missing_payment_intent';
	const REASON_MISSING_CLIENT_SECRET = 'missing_client_secret';
	const REASON_MISSING_CONTEXT = 'missing_transaction_context';
	const REASON_CLIENT_SECRET_MISMATCH = 'client_secret_mismatch';
	const REASON_UNEXPECTED_STATUS = 'unexpected_status';
	const REASON_CURRENCY_MISMATCH = 'currency_mismatch';
	const REASON_NOT_BOUND = 'not_bound_to_form_transaction';
	const REASON_FORM_MISMATCH = 'form_mismatch';
	const REASON_PRICE_NOT_ON_FORM = 'price_not_on_form';

	/**
	 * Statuses in which a PaymentIntent's amount may still be changed.
	 *
	 * @return string[]
	 */
	public static function getRepriceableStatuses() {
		return [
			\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
			\StripeWPFS\Stripe\PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
		];
	}

	/**
	 * @return string[] The metadata keys that make up the binding.
	 */
	public static function getBindingMetadataKeys() {
		return [
			self::METADATA_KEY_FORM_TYPE,
			self::METADATA_KEY_FORM_NAME,
			self::METADATA_KEY_FORM_ID,
			self::METADATA_KEY_TRANSACTION_ID,
		];
	}

	/**
	 * Build the metadata that binds a PaymentIntent to a form transaction.
	 *
	 * @param string $formType Form type the PaymentIntent belongs to.
	 * @param object|null $form Form record from the database.
	 * @param mixed $metadata Metadata to extend.
	 * @return array<string,mixed>
	 */
	public static function createBindingMetadata( $formType, $form, $metadata = [] ) {
		if ( is_object( $metadata ) ) {
			$metadata = (array) $metadata;
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = [];
		}

		if ( empty( $formType ) || empty( $form ) || empty( $form->name ) ) {
			return $metadata;
		}

		$metadata[ self::METADATA_KEY_FORM_TYPE ] = $formType;
		$metadata[ self::METADATA_KEY_FORM_NAME ] = $form->name;

		$formId = MM_WPFS_Utils::getFormId( $form );
		if ( ! empty( $formId ) ) {
			$metadata[ self::METADATA_KEY_FORM_ID ] = (string) $formId;
		}

		if ( empty( $metadata[ self::METADATA_KEY_TRANSACTION_ID ] ) ) {
			$metadata[ self::METADATA_KEY_TRANSACTION_ID ] = self::generateTransactionId();
		}

		return $metadata;
	}

	/**
	 * Carry the binding of a PaymentIntent over to a metadata set that replaces its metadata.
	 *
	 * @param object $paymentIntent PaymentIntent whose current metadata still holds the binding.
	 * @param mixed $metadata The metadata set that is about to replace it.
	 * @return array<string,mixed>
	 */
	public static function preserveBindingMetadata( $paymentIntent, $metadata ) {
		if ( is_object( $metadata ) ) {
			$metadata = (array) $metadata;
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = [];
		}

		foreach ( self::getBindingMetadataKeys() as $key ) {
			$value = self::readMetadataValue( $paymentIntent, $key );
			if ( ! is_null( $value ) && empty( $metadata[ $key ] ) ) {
				$metadata[ $key ] = $value;
			}
		}

		return $metadata;
	}

	/**
	 * Whether a form offers an amount the visitor types in, i.e. whether the custom-amount price
	 * sentinel may legitimately be sent for it. Mirrors the condition the form view uses to render
	 * the custom amount field.
	 *
	 * @param object|null $form Form record from the database.
	 * @return bool
	 */
	public static function allowsCustomAmount( $form ) {
		if ( empty( $form ) || ! isset( $form->customAmount ) ) {
			return false;
		}

		if ( MM_WPFS::PAYMENT_TYPE_CUSTOM_AMOUNT === $form->customAmount ) {
			return true;
		}

		return MM_WPFS::PAYMENT_TYPE_LIST_OF_AMOUNTS === $form->customAmount &&
			! empty( $form->allowListOfAmountsCustom );
	}

	/**
	 * The currency of a form's configured products.
	 *
	 * @param mixed $products Products of the form (decoratedProducts/decoratedPlans entries).
	 * @return string|null
	 */
	public static function extractCurrencyFromProducts( $products ) {
		if ( empty( $products ) || ! is_array( $products ) ) {
			return null;
		}

		foreach ( $products as $product ) {
			if ( ! empty( $product->currency ) ) {
				return (string) $product->currency;
			}
		}

		return null;
	}

	/**
	 * Generate a random transaction id for a PaymentIntent.
	 *
	 * @return string An opaque per-transaction identifier, used to correlate server-side logs.
	 */
	public static function generateTransactionId() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 24, false, false );
		}

		return bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Verify that a client-supplied PaymentIntent belongs to the current form transaction.
	 *
	 * @param object|null $paymentIntent PaymentIntent as retrieved from Stripe, never a client-supplied structure.
	 * @param mixed $clientSecret client_secret the browser sent for that PaymentIntent.
	 * @param MM_WPFS_PaymentIntentBindingContext|null $context Trusted server-side transaction data.
	 * @return MM_WPFS_PaymentIntentBindingResult
	 */
	public static function validate( $paymentIntent, $clientSecret, $context ) {
		if ( empty( $paymentIntent ) || empty( $paymentIntent->id ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_MISSING_PAYMENT_INTENT );
		}

		if ( empty( $clientSecret ) || ! is_string( $clientSecret ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_MISSING_CLIENT_SECRET );
		}

		if (
			! $context instanceof MM_WPFS_PaymentIntentBindingContext ||
			empty( $context->formType ) ||
			empty( $context->formName )
		) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_MISSING_CONTEXT );
		}

		// The client_secret is only known to the browser session the PaymentIntent was created
		// for, so it is the first ownership proof. It is compared in constant time and never logged.
		if (
			empty( $paymentIntent->client_secret ) ||
			! hash_equals( (string) $paymentIntent->client_secret, $clientSecret )
		) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_CLIENT_SECRET_MISMATCH );
		}

		$status = isset( $paymentIntent->status ) ? (string) $paymentIntent->status : '';
		if ( ! in_array( $status, self::getRepriceableStatuses(), true ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_UNEXPECTED_STATUS );
		}

		if ( ! empty( $context->currency ) ) {
			$paymentIntentCurrency = isset( $paymentIntent->currency ) ? strtolower( (string) $paymentIntent->currency ) : '';
			if ( $paymentIntentCurrency !== strtolower( (string) $context->currency ) ) {
				return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_CURRENCY_MISMATCH );
			}
		}

		$formResult = self::matchesForm( $paymentIntent, $context );
		if ( ! $formResult->isValid() ) {
			return $formResult;
		}

		if ( ! self::isSelectedPriceOnForm( $context ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_PRICE_NOT_ON_FORM );
		}

		return MM_WPFS_PaymentIntentBindingResult::valid();
	}

	/**
	 * Verify that a PaymentIntent carries the binding of the given form transaction.
	 *
	 * @param object|null $paymentIntent PaymentIntent as retrieved from Stripe, never a client-supplied structure.
	 * @param MM_WPFS_PaymentIntentBindingContext|null $context Trusted server-side transaction data.
	 * @return MM_WPFS_PaymentIntentBindingResult
	 */
	public static function matchesForm( $paymentIntent, $context ) {
		if ( empty( $paymentIntent ) || empty( $paymentIntent->id ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_MISSING_PAYMENT_INTENT );
		}

		if (
			! $context instanceof MM_WPFS_PaymentIntentBindingContext ||
			empty( $context->formType ) ||
			empty( $context->formName )
		) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_MISSING_CONTEXT );
		}

		$boundFormType = self::readMetadataValue( $paymentIntent, self::METADATA_KEY_FORM_TYPE );
		$boundFormName = self::readMetadataValue( $paymentIntent, self::METADATA_KEY_FORM_NAME );
		if ( is_null( $boundFormType ) || is_null( $boundFormName ) ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_NOT_BOUND );
		}

		if (
			! hash_equals( $boundFormType, (string) $context->formType ) ||
			! hash_equals( $boundFormName, (string) $context->formName )
		) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_FORM_MISMATCH );
		}

		$boundFormId = self::readMetadataValue( $paymentIntent, self::METADATA_KEY_FORM_ID );
		if ( ! is_null( $boundFormId ) && ! empty( $context->formId ) && $boundFormId !== (string) $context->formId ) {
			return MM_WPFS_PaymentIntentBindingResult::invalid( self::REASON_FORM_MISMATCH );
		}

		return MM_WPFS_PaymentIntentBindingResult::valid();
	}

	/**
	 * The payable amount of a pricing result, in the smallest currency unit.
	 *
	 * @param mixed $productPricing Server-calculated pricing data, keyed by price id.
	 * @param string|null $selectedPriceId Price id selected by the client.
	 * @return int
	 */
	public static function calculatePayableAmount( $productPricing, $selectedPriceId ) {
		$amount = 0;

		if ( empty( $productPricing ) || ! is_array( $productPricing ) ) {
			return $amount;
		}

		// When a specific price is selected and exists in pricing data, sum only that price's line items.
		// Otherwise (custom amount / fixed-price forms without a Stripe Price key) sum all line items.
		$priceMatched = ! is_null( $selectedPriceId ) && array_key_exists( $selectedPriceId, $productPricing );
		foreach ( $productPricing as $key => $productPrice ) {
			if ( $priceMatched && $key !== $selectedPriceId ) {
				continue;
			}
			foreach ( $productPrice as $price ) {
				if ( isset( $price->amount ) ) {
					$amount += (int) $price->amount;
				}
			}
		}

		return $amount;
	}

	/**
	 * Whether the price the client claims is selected is configured on the form.
	 *
	 * @param MM_WPFS_PaymentIntentBindingContext $context
	 * @return bool
	 */
	private static function isSelectedPriceOnForm( $context ) {
		if ( empty( $context->selectedPriceId ) ) {
			return true;
		}

		if ( MM_WPFS::PRICE_ID_CUSTOM_AMOUNT === $context->selectedPriceId ) {
			return (bool) $context->allowsCustomAmount;
		}

		// Nothing to compare against: custom-amount-only forms have no configured Stripe prices.
		if ( empty( $context->allowedPriceIds ) ) {
			return true;
		}

		return in_array( (string) $context->selectedPriceId, array_map( 'strval', $context->allowedPriceIds ), true );
	}

	/**
	 * Read a metadata value from a PaymentIntent, tolerating both object and array metadata.
	 *
	 * @param object $paymentIntent
	 * @param string $key
	 * @return string|null The value as string, or null when missing or empty.
	 */
	private static function readMetadataValue( $paymentIntent, $key ) {
		if ( ! isset( $paymentIntent->metadata ) ) {
			return null;
		}

		$metadata = $paymentIntent->metadata;
		$value = null;

		if ( is_array( $metadata ) ) {
			$value = isset( $metadata[ $key ] ) ? $metadata[ $key ] : null;
		} elseif ( is_object( $metadata ) ) {
			$value = isset( $metadata->{$key} ) ? $metadata->{$key} : null;
		}

		if ( is_null( $value ) || ! is_scalar( $value ) || '' === (string) $value ) {
			return null;
		}

		return (string) $value;
	}
}
