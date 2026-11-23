/* global wpfsScrollToElement, wpfsScrollToFirstFieldError, wpfsGetFieldDescriptor, wpfsFieldErrorSelector, wpfsClearFieldErrors, wpfsGlobalErrorMessages, wpfsInitStepper, wpfsInitSelectmenu */
jQuery.noConflict();
( function ( $ ) {
	'use strict';

	$( function () {
		const FORM_TYPE_INLINE_PAYMENT = 'inline_payment';
		const FORM_TYPE_CHECKOUT_PAYMENT = 'checkout_payment';
		const FORM_TYPE_INLINE_SUBSCRIPTION = 'inline_subscription';
		const FORM_TYPE_CHECKOUT_SUBSCRIPTION = 'checkout_subscription';
		const FORM_TYPE_INLINE_SAVE_CARD = 'inline_save_card';
		const FORM_TYPE_CHECKOUT_SAVE_CARD = 'checkout_save_card';
		const FORM_TYPE_INLINE_DONATION = 'inline_donation';
		const FORM_TYPE_CHECKOUT_DONATION = 'checkout_donation';
		const FROM_TYPE_DOM = 'wpfs-form-type';
		// The wpfsFormSettings.formFields key of each form type.
		const FORM_FIELDS_KEYS = {
			[ FORM_TYPE_INLINE_PAYMENT ]: 'inlinePayment',
			[ FORM_TYPE_CHECKOUT_PAYMENT ]: 'checkoutPayment',
			[ FORM_TYPE_INLINE_SUBSCRIPTION ]: 'inlineSubscription',
			[ FORM_TYPE_CHECKOUT_SUBSCRIPTION ]: 'checkoutSubscription',
			[ FORM_TYPE_INLINE_SAVE_CARD ]: 'inlineSaveCard',
			[ FORM_TYPE_CHECKOUT_SAVE_CARD ]: 'checkoutSaveCard',
			[ FORM_TYPE_INLINE_DONATION ]: 'inlineDonation',
			[ FORM_TYPE_CHECKOUT_DONATION ]: 'checkoutDonation',
		};

		const PAYMENT_TYPE_LIST_OF_AMOUNTS = 'list_of_amounts';
		const PAYMENT_TYPE_CUSTOM_AMOUNT = 'custom_amount';
		const PAYMENT_TYPE_SPECIFIED_AMOUNT = 'specified_amount';
		const PAYMENT_TYPE_CARD_CAPTURE = 'card_capture';
		const AMOUNT_OTHER = 'other';

		const MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT = '{{amount}}';
		const ERROR_MESSAGE_FIELD_CLASS = 'wpfs-form-error-message';

		const SELECTOR_STYLE_RADIO_BUTTONS = 'radio-buttons';
		const SELECTOR_STYLE_DROPDOWN = 'dropdown';
		const SELECTOR_STYLE_BUTTON_GROUP = 'button-group';

		const COUNTRY_CODE_UNITED_STATES = 'US';

		// Query parameters Stripe appends to the return_url after a redirect.
		const STRIPE_RETURN_URL_PARAMS = [
			'payment_intent',
			'payment_intent_client_secret',
			'setup_intent',
			'setup_intent_client_secret',
			'redirect_status',
		];
		// Payment methods that take the customer off the page (bank or wallet
		// redirects, app approvals, vouchers, QR codes). Forms without an explicit
		// method list can only complete on the page, so these are hidden from their
		// Payment Element (#674). Card, Link and the bank debits stay available.
		const REDIRECT_PAYMENT_METHOD_TYPES = [
			'affirm',
			'afterpay_clearpay',
			'alipay',
			'alma',
			'amazon_pay',
			'bancontact',
			'billie',
			'blik',
			'boleto',
			'cashapp',
			'crypto',
			'eps',
			'fpx',
			'gopay',
			'grabpay',
			'ideal',
			'kakao_pay',
			'klarna',
			'konbini',
			'kr_card',
			'mb_way',
			'mobilepay',
			'multibanco',
			'naver_pay',
			'oxxo',
			'p24',
			'pay_by_bank',
			'payco',
			'paynow',
			'paypal',
			'paypay',
			'pix',
			'promptpay',
			'qris',
			'revolut_pay',
			'samsung_pay',
			'satispay',
			'scalapay',
			'sequra',
			'shopeepay',
			'sunbit',
			'swish',
			'twint',
			'upi',
			'vipps',
			'wechat_pay',
			'wero',
			'zip',
		];

		const PAYMENT_DETAIL_ROW_SETUP_FEE = 'setupFee';
		const PAYMENT_DETAIL_ROW_PRODUCT = 'product';
		const PAYMENT_DETAIL_ROW_DISCOUNT = 'discount';
		const PAYMENT_DETAIL_ROW_TAX = 'tax';
		const PAYMENT_DETAIL_ROW_TAX_0 = 'tax-0';
		const PAYMENT_DETAIL_ROW_TAX_1 = 'tax-1';
		const PAYMENT_DETAIL_ROW_TOTAL = 'total';
		const PAYMENT_DETAIL_FEE_RECOVERY = 'fee-recovery';

		const TAX_RATE_TYPE_NO_TAX = 'taxRateNoTax';
		const TAX_RATE_TYPE_FIXED = 'taxRateFixed';
		const TAX_RATE_TYPE_DYNAMIC = 'taxRateDynamic';
		const TAX_RATE_TYPE_STRIPE_TAX = 'taxRateStripeTax';

		const PRICE_LINE_ITEM_TYPE_PRODUCT = 'product';
		const PRICE_LINE_ITEM_TYPE_SETUP_FEE = 'setupFee';
		const PRICE_LINE_ITEM_TYPE_TRIAL_FIRST_CHARGE = 'trial-first-charge';

		const PRICE_LINE_ITEM_SUBTYPE_DISCOUNT = 'discount';
		const PRICE_LINE_ITEM_SUBTYPE_TAX = 'tax';
		// What a plan's first invoice charges at once (SubscriptionFormViewConstants::FIRST_CHARGE_*).
		const FIRST_CHARGE_PLAN = 'plan';
		const FIRST_CHARGE_SETUP_FEE = 'setup-fee';

		const PRICE_ID_CUSTOM_AMOUNT = 'customAmount';

		const INTERVAL_DAY = 'day';
		const INTERVAL_WEEK = 'week';
		const INTERVAL_MONTH = 'month';
		const INTERVAL_YEAR = 'year';

		const POSTAL_CODE_PLACEHOLDERS = {
			CA: 'A1B 2C3',
			US: '12345-6789',
		};

		const debugLog = false;

		const { __, _n, sprintf } = wp.i18n;

		const l10n = {
			validation_errors: {
				internal_error:
					/* translators: Banner message of internal error when no error message is returned by the application */
					__( 'An internal error occurred.', 'wp-full-stripe-free' ),
				internal_error_title:
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				mandatory_field_is_empty:
					/* translators: Error message for required fields when empty. %s: custom input field label. */
					__( "Please enter a value for '%s'", 'wp-full-stripe-free' ),
				custom_payment_amount_value_is_invalid:
					/* translators: Field validation error message when payment amount is empty or invalid */
					__( 'Payment amount is invalid', 'wp-full-stripe-free' ),
				custom_amount_format_is_invalid:
					/* translators: Form field validation error message when the custom amount is malformed. %s: example amount, e.g. 12,34 */
					__(
						'Please enter a valid amount, for example %s',
						'wp-full-stripe-free'
					),
				invalid_payment_amount:
					/* translators: Banner message when the payment amount cannot be determined (the form has been tampered with) */
					__( 'Cannot determine payment amount', 'wp-full-stripe-free' ),
				invalid_payment_amount_title:
					/* translators: Banner title when the payment amount cannot be determined (the form has been tampered with) */
					__( 'Invalid payment amount', 'wp-full-stripe-free' ),
				payment_failed_title:
					/* translators: Banner title when the customer returns from a redirect-based payment method and the payment failed */
					__( 'Payment failed', 'wp-full-stripe-free' ),
				payment_failed:
					/* translators: Banner message when the customer returns from a redirect-based payment method and the payment failed */
					__( 'Payment failed, please try again.', 'wp-full-stripe-free' ),
			},
			processing: {
				title:
					/* translators: Title shown on the processing overlay while a payment is being completed */
					__( 'Processing your payment…', 'wp-full-stripe-free' ),
				save_card_title:
					/* translators: Title shown on the processing overlay while a card is being saved (no charge is made) */
					__( 'Saving your card…', 'wp-full-stripe-free' ),
				message:
					/* translators: Message shown on the processing overlay while a payment is being completed or a card is being saved */
					__( 'Please don’t close or refresh this window.', 'wp-full-stripe-free' ),
			},
			stripe_errors: {
				internal_error:
					/* translators: Banner message of internal error when no error message is returned by the application */
					__( 'An internal error occurred.', 'wp-full-stripe-free' ),
				internal_error_title:
					/* translators: Banner title of internal error */
					__( 'Internal Error', 'wp-full-stripe-free' ),
				stripe_error_title:
					/* translators: Banner title of error returned by Stripe */
					__( 'Stripe Error', 'wp-full-stripe-free' ),
				stripe_js_not_loaded:
					/* translators: Banner message when the Stripe.js library could not be loaded in the browser, for example because an ad blocker blocked it */
					__( 'Stripe.js not loaded', 'wp-full-stripe-free' ),
			},
			products: {
				other_amount_label:
					/* translators: Product name of the custom amount entered by the customer */
					__( 'Other amount', 'wp-full-stripe-free' ),
				the_selected_product_label:
					/* translators: Product name used when no product name is available */
					__( 'The selected product', 'wp-full-stripe-free' ),
			},
			application_errors: {
				stripe_instantiation_error_title:
					/* translators: Banner title of application error when instantiating the Stripe object */
					__( 'Stripe error', 'wp-full-stripe-free' ),
				stripe_instantiation_error_message:
					/* translators: Error message when instantiating the Stripe object. %s: the message of the exception thrown. */
					__( 'Cannot initialize Stripe: %s', 'wp-full-stripe-free' ),
				not_connected_to_stripe: __(
					'You are not connected to Stripe. Please connect to Stripe in the plugin settings.',
					'wp-full-stripe-free'
				),
			},
			product_pricing: {
				default_tax_label:
					/* translators: Default tax label */
					__( 'Tax', 'wp-full-stripe-free' ),
				default_coupon_label:
					/* translators: Default label of coupon line items */
					__( 'Coupon', 'wp-full-stripe-free' ),
				fee_recovery_label:
					/* translators: Label of the fee recovery line item in the payment summary */
					__( 'Fee Recovery', 'wp-full-stripe-free' ),
				tax_label_inclusive_percentage:
					/* translators: Tax line item label with percentage, inclusive. %1$s: Tax label (VAT, GST, Sales tax, etc), %2$s: Tax percentage. */
					__( '%1$s (%2$s%%, inclusive)', 'wp-full-stripe-free' ),
				tax_label_percentage:
					/* translators: Tax line item label with percentage. %1$s: Tax label (VAT, GST, Sales tax, etc), %2$s: Tax percentage. */
					__( '%1$s (%2$s%%)', 'wp-full-stripe-free' ),
				tax_label_inclusive:
					/* translators: Tax line item label, inclusive. %s: Tax label (VAT, GST, Sales tax, etc). */
					__( '%s (inclusive)', 'wp-full-stripe-free' ),
			},
			generic_error: __(
				'An error occurred. Please contact the website administrator.',
				'wp-full-stripe-free'
			),
		};

		let stripe = null;
		try {
			//noinspection JSUnresolvedVariable
			if (
				wpfsFormSettings.stripeAccountId !== null &&
				wpfsFormSettings.stripeAccountId.trim() !== ''
			) {
				stripe = Stripe( wpfsFormSettings.stripeKey, {
					stripeAccount: wpfsFormSettings.stripeAccountId,
				} );
			} else {
				stripe = Stripe( wpfsFormSettings.stripeKey );
			}
		} catch ( err ) {
			var $form = $( 'form.wpfs-form:first' );
			const message = sprintf(
				Boolean( wpfsFormSettings.isAdmin )
					? Boolean( wpfsFormSettings.isConnected )
						? l10n.application_errors
								.stripe_instantiation_error_message
						: l10n.application_errors
								.not_connected_to_stripe
					: l10n.generic_error,
				err.message
			);
			showErrorGlobalMessage(
				$form,
				l10n.application_errors
					.stripe_instantiation_error_title,
				message
			);
			console.log( 'Cannot instantiate Stripe: ' + err.message );
		}

		const reCAPTCHAWidgetIds = [];
		let googleReCAPTCHA = null;

		/*
		 * identifier functions
		 */
		// Returns the interactive control inside a typed custom field wrapper.
		function wpfsCustomFieldControl( $field ) {
			return $field
				.find( 'input, select, textarea' )
				.not( '[type="hidden"]' )
				.first();
		}

		// Collects custom field values keyed by their actual POST name, mirroring
		// native form submission. This handles every shape uniformly: the legacy
		// indexed wpfs-custom-input[] array, keyed scalar fields
		// wpfs-custom-input[<id>], multiselect groups wpfs-custom-input[<id>][],
		// and the hidden/checkbox pair used for single checkboxes.
		function wpfsCollectCustomInputs( $form ) {
			const result = {};

			$( '[name^="wpfs-custom-input"]', $form ).each( function () {
				const $el = $( this );
				const name = $el.attr( 'name' );
				if ( ! name ) {
					return;
				}
				const type = ( $el.attr( 'type' ) || '' ).toLowerCase();
				if (
					( type === 'checkbox' || type === 'radio' ) &&
					! $el.is( ':checked' )
				) {
					return;
				}

				if ( name.slice( -2 ) === '[]' ) {
					if ( ! Array.isArray( result[ name ] ) ) {
						result[ name ] = [];
					}
					result[ name ].push( $el.val() );
				} else {
					result[ name ] = $el.val();
				}
			} );

			return result;
		}

		// Removes any serialized custom-input keys from a parsed data object so
		// that wpfsCollectCustomInputs() values can be merged in cleanly.
		function wpfsApplyCustomInputs( data, $form ) {
			Object.keys( data ).forEach( function ( key ) {
				let decoded = key;
				try {
					decoded = decodeURIComponent( key );
				} catch ( e ) {}
				if ( decoded.indexOf( 'wpfs-custom-input' ) === 0 ) {
					delete data[ key ];
				}
			} );

			const customInputs = wpfsCollectCustomInputs( $form );
			Object.keys( customInputs ).forEach( function ( key ) {
				data[ key ] = customInputs[ key ];
			} );
		}

		// jQuery before 3.3 (WordPress 5.3–5.5) silently skips async functions
		// passed as callbacks, so they are wrapped in a plain function.
		function wpfsPlainCallback( callback ) {
			return function () {
				return callback.apply( this, arguments );
			};
		}

		// Parses a serialized form into an object that jQuery posts back with the
		// same names, so PHP still builds wpfs-custom-input[...] arrays. Names
		// ending in [] collect every value; '+' is a space (jQuery 1.x).
		function wpfsParseSerializedForm( serialized ) {
			const decode = ( part ) =>
				decodeURIComponent( part.replace( /\+/g, ' ' ) );
			const result = {};

			serialized.split( '&' ).forEach( function ( pair ) {
				if ( pair === '' ) {
					return;
				}
				const separator = pair.indexOf( '=' );
				const name = decode(
					separator === -1 ? pair : pair.slice( 0, separator )
				);
				const value =
					separator === -1
						? ''
						: decode( pair.slice( separator + 1 ) );

				if ( name.slice( -2 ) === '[]' ) {
					if ( ! Array.isArray( result[ name ] ) ) {
						result[ name ] = [];
					}
					result[ name ].push( value );
				} else {
					result[ name ] = value;
				}
			} );

			return result;
		}

		function isDonationForm( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );
			return (
				formType === FORM_TYPE_INLINE_DONATION ||
				formType === FORM_TYPE_CHECKOUT_DONATION
			);
		}

		function isSubscriptionForm( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );
			return (
				FORM_TYPE_INLINE_SUBSCRIPTION === formType ||
				FORM_TYPE_CHECKOUT_SUBSCRIPTION === formType
			);
		}

		function isPaymentForm( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );
			return (
				FORM_TYPE_INLINE_PAYMENT === formType ||
				FORM_TYPE_CHECKOUT_PAYMENT === formType
			);
		}
		function isPossiblePricingRecalculationForm( $form ) {
			return isPaymentForm( $form ) || isSubscriptionForm( $form );
		}

		/**
		 * Returns the final payable amount in minor units for a given form.
		 * For inline payment forms, this reads the amount from the selected product's data attribute, or computes it for custom-amount forms.
		 * @param {object} $form The jQuery object representing the form.
		 * @returns {number|null} The final payable amount in minor units, or null if not applicable.
		 */
		function getFinalPayableAmountInMinorUnits( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );

			if ( FORM_TYPE_INLINE_PAYMENT === formType ) {
				const $selectedProduct = findSelectedOneTimeProductNode( $form );
				if ( ! $selectedProduct || $selectedProduct.length === 0 ) {
					return null;
				}
				const amountAttr = $selectedProduct.data(
					'wpfs-amount-in-smallest-common-currency'
				);
				if ( amountAttr !== undefined && amountAttr !== null && amountAttr !== '' ) {
					const amount = parseInt( amountAttr, 10 );
					if ( ! isNaN( amount ) ) {
						return amount;
					}
				}

				const amountData = findPaymentAmountData( $form );
				if ( ! amountData.valid ) {
					return null;
				}
				const coupon = WPFS.getCoupon( extractFormNameFromNode( $form ) );
				const couponResult = applyCoupon( amountData.currency, amountData.amount, coupon );
				return couponResult.total;
			}

			return null;
		}

		/**
		 * Check if the current request is a free inline submission (final payable amount is 0, e.g. a 100%-off coupon).
		 * @param {object} $form The jQuery object representing the form.
		 * @returns {boolean} True if the submission is free, false otherwise.
		 */
		function isFreeInlineSubmission( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );
			const isEligible = FORM_TYPE_INLINE_PAYMENT === formType;
			if ( ! isEligible ) {
				return false;
			}
			const total = getFinalPayableAmountInMinorUnits( $form );
			return total !== null && total <= 0;
		}

		/**
		 * Appends a hidden input to the form to mark it as a free transaction.
		 * This is used to signal the back-end to skip the confirmation step and record the transaction directly.
		 * @param {object} $form The jQuery object representing the form.
		 */
		function appendFreeTransactionInput( $form ) {
			removeFreeTransactionInput( $form );
			$( '<input>' )
				.attr( {
					type: 'hidden',
					name: 'wpfs-free-transaction',
					value: '1',
				} )
				.appendTo( $form );
		}

		/*
		 * DOM checkers
		 */
		function isBuyingAsABusiness( $form ) {
			return $( 'input[name="wpfs-buying-as-business"]', $form ).prop(
				'checked'
			);
		}
		function isNoCouponAppliedForm( $form ) {
			const coupon = WPFS.getCoupon( extractFormNameFromNode( $form ) );
			return coupon === null;
		}
		function isNoTaxForm( $form ) {
			let result = true;
			if ( isPossiblePricingRecalculationForm( $form ) ) {
				if (
					extractTaxRateTypeFromNode( $form ) !== TAX_RATE_TYPE_NO_TAX
				) {
					result = false;
				}
			}
			return result;
		}
		function isDynamicTaxesForm( $form ) {
			let result = false;
			if ( isPossiblePricingRecalculationForm( $form ) ) {
				if (
					extractTaxRateTypeFromNode( $form ) ===
					TAX_RATE_TYPE_DYNAMIC
				) {
					result = true;
				}
			}
			return result;
		}
		function isStripeTaxForm( $form ) {
			let result = false;
			if ( isPossiblePricingRecalculationForm( $form ) ) {
				if (
					extractTaxRateTypeFromNode( $form ) ===
					TAX_RATE_TYPE_STRIPE_TAX
				) {
					result = true;
				}
			}
			return result;
		}
		function isTaxIdCollectible( $form ) {
			return (
				$( 'input[name="wpfs-buying-as-business"]', $form ) &&
				$( 'input[name="wpfs-buying-as-business"]', $form ).length > 0
			);
		}
		function isSelectedProductDiscounted( $form ) {
			const priceId = findPriceIdForSelectedProduct( $form );
			const pricingData = WPFS.getPaymentDetails(
				extractFormNameFromNode( $form )
			);

			if ( pricingData ) {
				if ( pricingData.hasOwnProperty( priceId ) ) {
					const filteredItems = pricingData[ priceId ].filter(
						( item ) =>
							item.subType === PRICE_LINE_ITEM_SUBTYPE_DISCOUNT
					);
					if ( filteredItems && filteredItems.length > 0 ) {
						return true;
					}
				}
			}
			return false;
		}
		function isCouponFieldVisible( $form ) {
			let result = false;

			if ( $form.data( 'wpfs-show-coupon-field' ) === true ) {
				result = true;
			}

			return result;
		}

		/*
		 * Formatter functions
		 */

		function createCurrencyFormatter( $form ) {
			if ( $form ) {
				const decimalSeparator = $form.data( 'wpfs-decimal-separator' );
				const showCurrencySymbolInsteadOfCode = $form.data(
					'wpfs-show-currency-symbol-instead-of-code'
				);
				const showCurrencySignAtFirstPosition = $form.data(
					'wpfs-show-currency-sign-at-first-position'
				);
				const putWhitespaceBetweenCurrencyAndAmount = $form.data(
					'wpfs-put-whitespace-between-currency-and-amount'
				);

				if ( debugLog ) {
					logInfo(
						'createCurrencyFormatter',
						'form=' + $form.data( 'wpfs-form-id' )
					);
					logInfo(
						'createCurrencyFormatter',
						'decimalSeparator=' + JSON.stringify( decimalSeparator )
					);
					logInfo(
						'createCurrencyFormatter',
						'showCurrencySymbolInsteadOfCode=' +
							JSON.stringify( showCurrencySymbolInsteadOfCode )
					);
					logInfo(
						'createCurrencyFormatter',
						'showCurrencySignAtFirstPosition=' +
							JSON.stringify( showCurrencySignAtFirstPosition )
					);
					logInfo(
						'createCurrencyFormatter',
						'putWhitespaceBetweenCurrencyAndAmount=' +
							JSON.stringify(
								putWhitespaceBetweenCurrencyAndAmount
							)
					);
				}
				return WPFSCurrencyFormatter(
					decimalSeparator,
					showCurrencySymbolInsteadOfCode,
					showCurrencySignAtFirstPosition,
					putWhitespaceBetweenCurrencyAndAmount
				);
			}
			logWarn( 'createCurrencyFormatter', '$form is null' );
			return null;
		}

		/**
		 *
		 * @param  currency
		 * @param  amount
		 * @param  coupon
		 * @return {{amountCurrency: *, amount: *, couponCurrency: (OPTIONS.currency|{importance, type, default}|*|Array), discount: number, discountType: *, total: number, error: *}}
		 */
		function applyCoupon( currency, amount, coupon ) {
			// console.log('applyCoupon(): CALLED, currency=' + currency + ', amount=' + amount + ', coupon=' + JSON.stringify(coupon));
			let discount = 0;
			let discountType = null;
			let error = null;
			if ( coupon != null ) {
				//noinspection JSUnresolvedVariable
				if (
					coupon.hasOwnProperty( 'percent_off' ) &&
					coupon.percent_off != null
				) {
					discountType = 'percent_off';
					//noinspection JSUnresolvedVariable
					const percentOff = parseInt( coupon.percent_off ) / 100;
					discount = Math.round( amount * percentOff );
				} else {
					//noinspection JSUnresolvedVariable
					if (
						coupon.hasOwnProperty( 'amount_off' ) &&
						coupon.amount_off != null
					) {
						discountType = 'amount_off';
						if ( coupon.hasOwnProperty( 'currency' ) ) {
							if ( coupon.currency == currency ) {
								//noinspection JSUnresolvedVariable
								discount = parseInt( coupon.amount_off );
							} else {
								error = 'currency mismatch';
							}
						} else {
							error = 'currency mismatch';
						}
					} else {
						error = 'invalid coupon';
					}
				}
			}
			const amountAsInteger = parseInt( amount );
			const total = amountAsInteger - discount;

			const result = {
				amountCurrency: currency,
				amount: amountAsInteger,
				couponCurrency:
					coupon != null && coupon.hasOwnProperty( 'currency' )
						? coupon.currency
						: null,
				discount,
				discountType,
				discountPercentOff:
					coupon != null && coupon.hasOwnProperty( 'percent_off' )
						? coupon.percent_off
						: null,
				total,
				error,
			};

			// console.log('applyCoupon(): result=' + JSON.stringify(result));

			return result;
		}

		function resetCaptcha( $form ) {
			if ( $form ) {
				const formHash = $form.data( 'wpfs-form-hash' );
				if ( formHash ) {
					if ( googleReCAPTCHA != null ) {
						if ( reCAPTCHAWidgetIds[ formHash ] !== 'undefined' ) {
							googleReCAPTCHA.reset(
								reCAPTCHAWidgetIds[ formHash ]
							);
						}
					}
				}
			}
		}

		function getParentForm( element ) {
			return $( element ).parents( 'form:first' );
		}

		function getGlobalMessageContainerTitle( $messageContainer, title ) {
			let $messageContainerTitle = $(
				'.wpfs-form-message-title',
				$messageContainer
			);
			if ( 0 == $messageContainerTitle.length ) {
				$( '<div>', { class: 'wpfs-form-message-title' } ).prependTo(
					$messageContainer
				);
				$messageContainerTitle = $(
					'.wpfs-form-message-title',
					$messageContainer
				);
			}
			$messageContainerTitle.html( title );
			return $messageContainerTitle;
		}

		function getGlobalMessageContainer( $form, message ) {
			// Leaves the form's configuration error banner in place.
			const selector = '.wpfs-form-message:not([data-wpfs-configuration-error])';
			let $messageContainer = $( selector, $form );
			if ( 0 == $messageContainer.length ) {
				$( '<div>', { class: 'wpfs-form-message' } ).prependTo( $form );
				$messageContainer = $( selector, $form );
			}
			$messageContainer.html( message );
			return $messageContainer;
		}

		function scrollToElement( $anElement, fade ) {
			wpfsScrollToElement( $anElement, fade );
		}

		function clearGlobalMessage( $form ) {
			const $messageContainer = getGlobalMessageContainer( $form, '' );
			$messageContainer.remove();
		}

		function __showGlobalMessage( $form, messageTitle, message ) {
			const $globalMessageContainer = getGlobalMessageContainer(
				$form,
				message
			);
			getGlobalMessageContainerTitle(
				$globalMessageContainer,
				messageTitle
			);
			return $globalMessageContainer;
		}

		function showSuccessGlobalMessage( $form, messageTitle, message ) {
			const $globalMessageContainer = __showGlobalMessage(
				$form,
				messageTitle,
				message
			);
			$globalMessageContainer.addClass( 'wpfs-form-message--correct' );
			scrollToElement( $globalMessageContainer, false );
		}

		function showErrorGlobalMessage( $form, messageTitle, message ) {
			const $globalMessageContainer = __showGlobalMessage(
				$form,
				messageTitle,
				message
			);
			$globalMessageContainer.addClass( 'wpfs-form-message--incorrect' );
			scrollToElement( $globalMessageContainer, false );
		}

		function clearFieldErrors( $form ) {
			wpfsClearFieldErrors( $form );
		}

		function clearFieldError( $form, fieldName, fieldId ) {
			const formType = $form.data( 'wpfs-form-type' );
			const fieldDescriptor = getFieldDescriptor( formType, fieldName );
			if ( debugLog ) {
				logInfo(
					'clearFieldError',
					'fieldName=' + fieldName + ', fieldId=' + fieldId
				);
				logInfo( 'clearFieldError', JSON.stringify( fieldDescriptor ) );
			}
			if ( fieldDescriptor != null ) {
				// tnagy read field descriptor
				const fieldType = fieldDescriptor.type;
				const fieldClass = fieldDescriptor.class;
				const fieldSelector = fieldDescriptor.selector;
				const fieldErrorClass = fieldDescriptor.errorClass;
				const fieldErrorSelector = wpfsFieldErrorSelector(
					fieldDescriptor,
					fieldId
				);

				// tnagy initialize field
				let theFieldSelector;
				if ( fieldId != null ) {
					theFieldSelector = '#' + fieldId;
				} else {
					theFieldSelector = fieldSelector;
				}
				const $field = $( theFieldSelector, $form );

				// tnagy remove error class, remove error message
				const errorMessageFieldSelector =
					'.' + ERROR_MESSAGE_FIELD_CLASS;
				if ( 'input' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.removeClass( fieldErrorClass );
					}
					$field.closest( errorMessageFieldSelector ).remove();
				} else if ( 'input-group' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.removeClass( fieldErrorClass );
					}
					$field
						.closest( fieldErrorSelector )
						.siblings( errorMessageFieldSelector )
						.remove();
				} else if ( 'input-custom' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.removeClass( fieldErrorClass );
					}
					$field.closest( errorMessageFieldSelector ).remove();
				} else if ( 'select-menu' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( '.' + fieldClass )
							.removeClass( fieldErrorClass );
						$( fieldErrorSelector ).removeClass( fieldErrorClass );
					}
					$field.closest( errorMessageFieldSelector ).remove();
				} else if ( 'checkbox' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.removeClass( fieldErrorClass );
					}
					$field.closest( errorMessageFieldSelector ).remove();
				} else if ( 'card' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						const $cardContainer = $( '.' + fieldClass, $form );
						$cardContainer
							.closest( fieldErrorSelector )
							.removeClass( fieldErrorClass );
					}
					$field.closest( errorMessageFieldSelector ).remove();
				}
			} else {
				logInfo( 'showFieldError', 'FieldDescription not found!' );
			}
		}

		function getFieldDescriptor( formType, fieldName ) {
			return wpfsGetFieldDescriptor(
				wpfsFormSettings.formFields,
				FORM_FIELDS_KEYS[ formType ],
				fieldName
			);
		}

		function showFormError(
			$form,
			fieldName,
			fieldId,
			errorTitle,
			errorMessage,
			scrollTo
		) {
			const formType = $form.data( 'wpfs-form-type' );
			const fieldDescriptor = getFieldDescriptor( formType, fieldName );
			if ( fieldDescriptor != null ) {
				const fieldIsHidden = fieldDescriptor.hidden;
				if ( true == fieldIsHidden ) {
					showErrorGlobalMessage( $form, errorTitle, errorMessage );
				} else {
					showFieldError(
						$form,
						fieldName,
						fieldId,
						errorMessage,
						scrollTo
					);
				}
				if ( fieldName.startsWith( 'wpfs-billing' ) ) {
					$( '.wpfs-billing-address-switch', $form ).click();
				}
				if ( fieldName.startsWith( 'wpfs-shipping' ) ) {
					$( '.wpfs-shipping-address-switch', $form ).click();
				}
			}
		}

		function showFieldError(
			$form,
			fieldName,
			fieldId,
			fieldErrorMessage,
			scrollTo
		) {
			const formType = $form.data( 'wpfs-form-type' );
			const fieldDescriptor = getFieldDescriptor( formType, fieldName );
			if ( debugLog ) {
				logInfo(
					'showFieldError',
					'fieldName=' +
						fieldName +
						', fieldId=' +
						fieldId +
						', fieldErrorMessage=' +
						fieldErrorMessage
				);
				logInfo( 'showFieldError', JSON.stringify( fieldDescriptor ) );
				logInfo( 'showFieldError', fieldErrorMessage );
			}
			if ( fieldDescriptor != null ) {
				// tnagy read field descriptor
				const fieldType = fieldDescriptor.type;
				const fieldClass = fieldDescriptor.class;
				const fieldSelector = fieldDescriptor.selector;
				const fieldErrorClass = fieldDescriptor.errorClass;
				const fieldErrorSelector = wpfsFieldErrorSelector(
					fieldDescriptor,
					fieldId
				);

				// tnagy initialize field
				let theFieldSelector;
				if ( fieldId != null ) {
					theFieldSelector = '#' + fieldId;
				} else {
					theFieldSelector = fieldSelector;
				}
				const $field = $( theFieldSelector, $form );

				// tnagy create error message
				const $fieldError = $( '<div>', {
					class: ERROR_MESSAGE_FIELD_CLASS,
					'data-wpfs-field-error-for': fieldId,
				} ).html( fieldErrorMessage );

				// tnagy add error class, insert error message
				if ( 'input' === fieldType ) {
					if ( $field.is( ':checkbox, :radio' ) ) {
						// Custom-field checkbox / multiselect controls draw their
						// visible box from an `input + label` adjacent-sibling CSS
						// rule, so the error message must not be inserted between the
						// input and its label — that would break the adjacency and
						// hide the box.  Flag the input(s) for the error state and
						// append the message to the field wrapper instead.
						const $wrapper = $field.closest( '.wpfs-custom-field' );
						const $target = $wrapper.length
							? $wrapper
							: $field.closest( '.wpfs-form-check' );
						$target
							.find( 'input.wpfs-form-check-input' )
							.addClass( 'wpfs-form-check-input--error' );
						$fieldError.appendTo(
							$target.length ? $target : $field.parent()
						);
					} else {
						if ( fieldErrorSelector != null ) {
							$field
								.closest( fieldErrorSelector )
								.addClass( fieldErrorClass );
						}
						$fieldError.insertAfter( $field );
					}
				} else if ( 'input-group' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.addClass( fieldErrorClass );
					}
					$fieldError.insertAfter(
						$field.closest( fieldErrorSelector )
					);
				} else if ( 'input-custom' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.addClass( fieldErrorClass );
					}
					$fieldError.insertAfter( $field );
				} else if ( 'dropdown' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( '.' + fieldClass )
							.addClass( fieldErrorClass );
						$( fieldErrorSelector ).addClass( fieldErrorClass );
					}
					$fieldError.appendTo( $field.parent() );
				} else if ( 'checkbox' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						$field
							.closest( fieldErrorSelector )
							.addClass( fieldErrorClass );
					}
					$fieldError.appendTo( $field.parent() );
				} else if ( 'card' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						const $cardContainer = $( '.' + fieldClass, $form );
						$cardContainer
							.closest( fieldErrorSelector )
							.addClass( fieldErrorClass );
						$fieldError.insertAfter( $cardContainer );
					}
				} else if ( 'captcha' === fieldType ) {
					if ( fieldErrorSelector != null ) {
						const $captchaContainer = $( '.' + fieldClass, $form );
						$captchaContainer
							.closest( fieldErrorSelector )
							.addClass( fieldErrorClass );
						$fieldError.insertAfter( $captchaContainer );
					}
				}

				if ( typeof scrollTo !== 'undefined' ) {
					if ( scrollTo ) {
						scrollToElement( $field, false );
					}
				}
			} else {
				logInfo( 'showFieldError', 'FieldDescription not found!' );
			}
		}

		function processValidationErrors( $form, data ) {
			const formId = $form.data( 'wpfs-form-id' );
			const formHash = $form.data( 'wpfs-form-hash' );
			let hasErrors = false;
			if ( data && data.bindingResult ) {
				if (
					data.bindingResult.fieldErrors &&
					data.bindingResult.fieldErrors.errors
				) {
					const fieldErrors = data.bindingResult.fieldErrors.errors;
					for ( const index in fieldErrors ) {
						const fieldError = fieldErrors[ index ];
						const fieldId = fieldError.id;
						const fieldName = fieldError.name;
						const fieldErrorMessage = fieldError.message;
						showFormError(
							$form,
							fieldName,
							fieldId,
							data.bindingResult.fieldErrors.title,
							fieldErrorMessage
						);
						if ( ! hasErrors ) {
							hasErrors = true;
						}
					}
					wpfsScrollToFirstFieldError( $form );
				}
				if ( data.bindingResult.globalErrors ) {
					const globalErrorMessages = wpfsGlobalErrorMessages(
						data.bindingResult.globalErrors
					);
					if ( '' !== globalErrorMessages ) {
						showErrorGlobalMessage(
							$form,
							data.bindingResult.globalErrors.title,
							globalErrorMessages
						);
						if ( ! hasErrors ) {
							hasErrors = true;
						}
					}
				}
			} else {
				showErrorGlobalMessage(
					$form,
					data.messageTitle,
					data.message
				);
				logResponseException( 'WPFS form=' + formId, data );
				hasErrors = true;
			}
			if ( hasErrors ) {
				resetCaptcha( $form );
			}
		}

		/**
		 * @typedef {{fieldErrors: {title: string, errors: Array<{id: ?string, name: string, message: string}>}, globalErrors: {title: string, errors: string[]}}} BindingResult
		 *          A refused form's validation errors, as Utils::generateReturnValueFromBindings() sends them.
		 */

		/**
		 * Shows a refused form request: its title and message, if it has either, then its field
		 * and global errors.
		 *
		 * @param {JQuery<HTMLFormElement>}                                                   $form
		 * @param {?{messageTitle?: string, message?: string, bindingResult?: BindingResult}} data  The response.
		 */
		function showResponseErrors( $form, data ) {
			if ( data && ( data.messageTitle || data.message ) ) {
				showErrorGlobalMessage(
					$form,
					data.messageTitle,
					data.message
				);
			}
			processValidationErrors( $form, data );
		}

		/**
		 * Logs a form request that failed and shows the error on the form.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {string}                  handlerName The request's name in the log.
		 * @param {JQuery.jqXHR}            jqXHR
		 * @param {string}                  textStatus
		 * @param {string}                  errorThrown The HTTP status text, shown as the message.
		 */
		function showAjaxError(
			$form,
			handlerName,
			jqXHR,
			textStatus,
			errorThrown
		) {
			logError( handlerName, jqXHR, textStatus, errorThrown );
			showErrorGlobalMessage(
				$form,
				l10n.validation_errors.internal_error_title,
				errorThrown
			);
		}

		/**
		 * Whether the form shows its terms of use and the customer hasn't accepted them. A page
		 * without the checkbox passes, since its undefined state isn't loosely false, and the
		 * server refuses the form.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True when the terms stop the submit.
		 */
		function isTermsOfUseUnaccepted( $form ) {
			if ( 1 != $form.data( 'wpfs-show-terms-of-use' ) ) {
				return false;
			}
			return (
				$form
					.find( 'input[name=wpfs-terms-of-use-accepted]' )
					.prop( 'checked' ) == false
			);
		}

		function clearAndShowAndEnableAndFocusCustomAmount( $form ) {
			$( 'div[data-wpfs-amount-row=custom-amount]', $form ).show();
			$( 'input[name=wpfs-custom-amount-unique]', $form )
				.val( '' )
				.prop( 'disabled', false )
				.prop( 'readonly', false )
				.focus();
		}

		function clearAndHideAndDisableCustomAmount( $form ) {
			$( 'div[data-wpfs-amount-row="custom-amount"]', $form ).hide();
			$( 'input[name="wpfs-custom-amount-unique"]', $form )
				.val( '' )
				.prop( 'disabled', true );

			WPFS.setPaymentDetailsForPrice(
				extractFormNameFromNode( $form ),
				PRICE_ID_CUSTOM_AMOUNT,
				createCustomAmountPricingData( $form, 0 )
			);
		}

		function updateButtonCaption(
			$form,
			buttonTitle,
			currencyCode,
			currencySymbol,
			amount,
			zeroDecimalSupport
		) {
			if ( debugLog ) {
				console.log(
					'updateButtonCaption(): params=' +
						JSON.stringify( [
							currencyCode,
							currencySymbol,
							amount,
							zeroDecimalSupport,
						] )
				);
				const formId = $form.data( 'wpfs-form-id' );
				console.log( 'updateButtonCaption(): formId=' + formId );
			}

			const formatter = createCurrencyFormatter( $form );
			let captionPattern;
			let caption;
			const amountMacroRegExp = new RegExp(
				MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT,
				'g'
			);
			if ( currencySymbol == null || amount == null ) {
				caption = buttonTitle;
				if (
					caption.indexOf( MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT ) !== -1
				) {
					caption = caption.replace( amountMacroRegExp, '' ).trim();
				}
			} else {
				const amountPart = formatter.format(
					amount,
					currencyCode,
					currencySymbol,
					zeroDecimalSupport
				);
				if (
					buttonTitle.indexOf(
						MACRO_SUBMIT_BUTTON_CAPTION_AMOUNT
					) !== -1
				) {
					captionPattern = buttonTitle
						.replace( amountMacroRegExp, '%s' )
						.trim();
					const captionPatternParams = [];

					captionPatternParams.push( amountPart );
					caption = sprintf( captionPattern, ...captionPatternParams );
				} else {
					caption = buttonTitle;
				}
			}
			$form.find( 'button[type=submit]' ).html( caption );
		}

		function disableFormButtons( $form ) {
			$form.find( 'button' ).prop( 'disabled', true );
		}

		function enableFormButtons( $form ) {
			$form
				.find(
					'button:not([data-wpfs-keep-disabled]):not([data-wpfs-pricing-stale])'
				)
				.prop( 'disabled', false );
		}

		/**
		 * Keeps Pay disabled while the form shows prices the server no longer charges, because
		 * re-pricing it failed, until a re-pricing succeeds.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {boolean}                 stale
		 */
		function setPricingStale( $form, stale ) {
			const $submit = $form.find( 'button[type=submit]' );
			if ( stale ) {
				$submit
					.attr( 'data-wpfs-pricing-stale', '1' )
					.prop( 'disabled', true );
			} else {
				$submit.removeAttr( 'data-wpfs-pricing-stale' );
			}
		}

		function disableFormInputsSelectsButtons( $form ) {
			if ( debugLog ) {
				console.log( 'disableFormInputsSelectsButtons(): CALLED' );
			}
			$form
				.find(
					'input:not(.wpfs-custom-amount), select:not([data-wpfs-select]), button'
				)
				.prop( 'disabled', true );
		}

		function enableFormInputsSelectsButtons( $form ) {
			if ( debugLog ) {
				console.log( 'enableFormInputsSelectsButtons(): CALLED' );
			}
			$form
				.find(
					'input, select:not([data-wpfs-select]), button:not([data-wpfs-keep-disabled]):not([data-wpfs-pricing-stale])'
				)
				.prop( 'disabled', false );
		}

		function resetTextFields( $form ) {
			$form
				.find(
					'input[type=text], input[type=password], input[type=email]'
				)
				.val( '' );
		}

		function resetStepperFields( $form ) {
			$form.find( 'input[data-toggle="stepper"]' ).each( function () {
				let defaultValue = 1;
				if (
					typeof $( this ).data( 'default-value' ) !== 'undefined'
				) {
					defaultValue = $( this ).data( 'default-value' );
				}
				$( this ).val( defaultValue );
			} );
		}

		function resetSelectFields( $form ) {
			$form.find( 'select' ).prop( 'selectedIndex', 0 ).change();
			$form
				.find( '[data-toggle="selectmenu"]' )
				.wpfsSelectmenu( 'refresh' );
		}

		function resetCheckboxesAndRadioButtons( $form ) {
			$form
				.find( 'input[type=radio], input[type=checkbox]' )
				.prop( 'checked', false );
		}

		function resetCardField( card ) {
			if ( card != null ) {
				card.clear();
			}
		}

		function resetCouponField( $form, formId ) {
			const coupon = WPFS.getCoupon( formId );
			if ( coupon != null ) {
				WPFS.removeCoupon( formId );
				hideRedeemedCouponRow( $form );
				hideWrongRedeemedCouponRow( $form );
				showCouponToRedeemRow( $form );
			}
		}

		function resetBillingAndShippingAddressSelector( $form ) {
			$( '.wpfs-same-billing-and-shipping-address', $form )
				.prop( 'checked', true )
				.change();
		}

		function selectFirstCustomAmount( $form ) {
			$( '.wpfs-custom-amount', $form ).first().click();
		}

		function selectFirstPlanFromDropdown( $form ) {
			const $planSelectElements = $form.find(
				'select.wpfs-subscription-plan-select'
			);
			$planSelectElements.each( function () {
				if ( $( 'option:selected', $( this ) ).length ) {
					$( this ).val( $( 'option:selected', $( this ) ).val() );
				} else {
					$( this ).val( $( 'option:first', $( this ) ).val() );
				}
			} );
		}

		function selectFirstPlanFromButtonGroup( $form ) {
			if (
				$(
					'input:radio[name="wpfs-plan"]:not(:disabled):checked',
					$form
				).length === 0
			) {
				$(
					'input:radio[name="wpfs-plan"]:not(:disabled):first',
					$form
				).prop( 'checked', true );
			}
		}

		function selectFirstDonationFrequency( $form ) {
			if (
				$(
					'input:radio[name="wpfs-donation-frequency"]:not(:disabled):checked',
					$form
				).length === 0
			) {
				$(
					'input:radio[name="wpfs-donation-frequency"]:not(:disabled):first',
					$form
				).prop( 'checked', true );
			}
		}

		function selectFirstSubscriptionPlan( $form ) {
			selectFirstPlanFromDropdown( $form );
			selectFirstPlanFromButtonGroup( $form );

			handlePlanChange( $form );
		}

		function resetFormFields( $form, card ) {
			const formId = $form.data( 'wpfs-form-id' );
			const formHash = $form.data( 'wpfs-form-hash' );

			// The submit is over; refreshes the reset triggers must run.
			unlockFormFields( $form );

			resetTextFields( $form );
			resetStepperFields( $form );
			resetSelectFields( $form );
			resetCheckboxesAndRadioButtons( $form );
			resetCardField( card );
			resetCouponField( $form, formId );
			resetCaptcha( $form );
			resetBillingAndShippingAddressSelector( $form );
			removeHiddenFormFields( $form );

			if ( isCouponFieldVisible( $form ) ) {
				// Reset the payment details to the original pricing data for this form.
				WPFS.removePaymentDetails( formId );
				if (
					typeof wpfsProductPricing !== 'undefined' &&
					wpfsProductPricing.hasOwnProperty( formId )
				) {
					WPFS.setPaymentDetails( formId, wpfsProductPricing[ formId ] );
				}
				refreshProductPricing( $form );
			}

			selectFirstCustomAmount( $form );
			selectFirstSubscriptionPlan( $form );
			selectFirstDonationFrequency( $form );
		}

		function removeCustomAmountIndexInput( $form ) {
			$form.find( 'input[name="wpfs-custom-amount-index"]' ).remove();
		}

		function removeHiddenFormFields( $form ) {
			removePaymentMethodIdInput( $form );
			removePaymentIntentIdInput( $form );
			removePaymentIntentClientSecretInput( $form );
			removeSetupIntentIdInput( $form );
			removeSubscriptionIdInput( $form );
			removeCustomAmountIndexInput( $form );
			removeWPFSNonceInput( $form );
			removeFreeTransactionInput( $form );
		}

		// While a submit runs, the server may charge first and then receive the
		// form again, so a field changed in between would no longer match the
		// charge. Lock the fields without disabling them: disabled fields are
		// left out of the serialized form.
		function lockFormFields( $form ) {
			$form
				.attr( 'data-wpfs-locked', 'true' )
				.attr( 'aria-busy', 'true' );
			$form
				.find(
					'input:not([type=hidden]):not([type=radio]):not([type=checkbox]), textarea'
				)
				.not( '[readonly]' )
				.attr( { readonly: true, 'data-wpfs-lock-readonly': 'true' } );
		}

		function unlockFormFields( $form ) {
			$form.removeAttr( 'data-wpfs-locked' ).removeAttr( 'aria-busy' );
			$form
				.find( '[data-wpfs-lock-readonly]' )
				.removeAttr( 'readonly' )
				.removeAttr( 'data-wpfs-lock-readonly' );
		}

		// Radios, checkboxes, selects and their labels have no read-only state,
		// so a locked form swallows the clicks and keys that would change them.
		// Programmatic clicks (form reset, error handling) are not the donor's.
		// Links that run script (coupon Remove, href="") are blocked too; links
		// that go somewhere, like the terms of use, still open.
		function blockLockedFormInteraction( event ) {
			const link =
				event.target instanceof window.Element
					? event.target.closest( 'a' )
					: null;
			if (
				! event.isTrusted ||
				! ( event.target instanceof window.Element ) ||
				! event.target.closest( 'form[data-wpfs-locked]' ) ||
				( link &&
					link.getAttribute( 'href' ) &&
					'#' !== link.getAttribute( 'href' ) ) ||
				( 'keydown' === event.type && 'Tab' === event.key )
			) {
				return;
			}
			// The quantity stepper changes its value on wheel; let the page scroll.
			if ( 'wheel' !== event.type ) {
				event.preventDefault();
			}
			event.stopPropagation();
		}
		[ 'click', 'mousedown', 'keydown', 'wheel' ].forEach(
			function ( type ) {
				document.addEventListener(
					type,
					blockLockedFormInteraction,
					true
				);
			}
		);

		function showLoadingAnimation( $form ) {
			$form
				.find( 'button[type=submit]' )
				.addClass( 'wpfs-btn-primary--loader' );
			lockFormFields( $form );
		}

		function hideLoadingAnimation( $form ) {
			unlockFormFields( $form );
			$form
				.find( 'button[type=submit]' )
				.removeClass( 'wpfs-btn-primary--loader' );
			// Any path that re-enables the form (errors, declines) must also
			// dismiss the full-screen processing overlay.
			hideProcessingOverlay();
		}

		// Full-screen "Processing your payment…" overlay. Shown while Stripe
		// finalises a charge (incl. the redirect return) so the user keeps the
		// form context instead of seeing the page flash to the top.
		function buildProcessingOverlay() {
			let $overlay = $( '#wpfs-processing-overlay' );
			if ( $overlay.length ) {
				return $overlay;
			}
			$overlay = $(
				'<div id="wpfs-processing-overlay" class="wpfs-processing-overlay" role="dialog" aria-modal="true" aria-labelledby="wpfs-processing-overlay-title" aria-describedby="wpfs-processing-overlay-text" tabindex="-1">' +
					'<div class="wpfs-processing-overlay__card">' +
					'<div class="wpfs-processing-overlay__spinner" aria-hidden="true"></div>' +
					'<div class="wpfs-processing-overlay__title" id="wpfs-processing-overlay-title"></div>' +
					'<div class="wpfs-processing-overlay__text" id="wpfs-processing-overlay-text"></div>' +
					'</div>' +
					'</div>'
			);
			$overlay
				.find( '.wpfs-processing-overlay__title' )
				.text( l10n.processing.title );
			$overlay
				.find( '.wpfs-processing-overlay__text' )
				.text( l10n.processing.message );
			$( 'body' ).append( $overlay );
			return $overlay;
		}

		// Save-card forms don't charge anything, so "Processing your payment…"
		// is misleading there — show a save-card specific title instead.
		function processingOverlayTitleForForm( $form ) {
			const formType =
				$form && $form.length ? $form.data( FROM_TYPE_DOM ) : null;
			if (
				FORM_TYPE_INLINE_SAVE_CARD === formType ||
				FORM_TYPE_CHECKOUT_SAVE_CARD === formType
			) {
				return l10n.processing.save_card_title;
			}
			return l10n.processing.title;
		}

		// Keep keyboard focus on the modal overlay so users can't tab into the
		// underlying form while the payment is being processed.
		function trapProcessingOverlayFocus( e ) {
			const $overlay = $( '#wpfs-processing-overlay' );
			if (
				! $overlay.hasClass( 'wpfs-processing-overlay--visible' ) ||
				e.key !== 'Tab'
			) {
				return;
			}
			e.preventDefault();
			$overlay.trigger( 'focus' );
		}

		function showProcessingOverlay( $form ) {
			const $overlay = buildProcessingOverlay();
			$overlay
				.find( '.wpfs-processing-overlay__title' )
				.text( processingOverlayTitleForForm( $form ) );
			$overlay.addClass( 'wpfs-processing-overlay--visible' );
			$( 'body' ).addClass( 'wpfs-processing-overlay-open' );
			$overlay.trigger( 'focus' );
			$( document ).on( 'keydown.wpfsProcessingOverlay', trapProcessingOverlayFocus );
		}

		function hideProcessingOverlay() {
			$( '#wpfs-processing-overlay' ).removeClass(
				'wpfs-processing-overlay--visible'
			);
			$( 'body' ).removeClass( 'wpfs-processing-overlay-open' );
			$( document ).off( 'keydown.wpfsProcessingOverlay' );
		}

		function clearPaymentDetails( $form ) {
			const rowTypes = [
				PAYMENT_DETAIL_ROW_SETUP_FEE,
				PAYMENT_DETAIL_ROW_PRODUCT,
				PAYMENT_DETAIL_ROW_DISCOUNT,
				PAYMENT_DETAIL_ROW_TAX_0,
				PAYMENT_DETAIL_ROW_TAX_1,
				PAYMENT_DETAIL_ROW_TOTAL,
				PAYMENT_DETAIL_FEE_RECOVERY,
			];

			rowTypes.forEach( ( rowType ) => {
				$(
					'tr[data-wpfs-summary-row="' + rowType + '"]',
					$form
				).hide();
			} );
			$( '.wpfs-summary-description', $form ).hide();
		}

		function addPaymentMethodIdInput( $form, result ) {
			if (
				typeof result !== 'undefined' &&
				result.hasOwnProperty( 'setupIntent' ) &&
				result.setupIntent.hasOwnProperty( 'payment_method' )
			) {
				$( '<input>' )
					.attr( {
						type: 'hidden',
						name: 'wpfs-stripe-payment-method-id',
						value: result.setupIntent.payment_method,
					} )
					.appendTo( $form );
			}
		}

		function addPaymentIntentIdInput( $form, result ) {
			if (
				typeof result !== 'undefined' &&
				result.hasOwnProperty( 'paymentIntent' ) &&
				result.paymentIntent.hasOwnProperty( 'id' )
			) {
				$( '<input>' )
					.attr( {
						type: 'hidden',
						name: 'wpfs-stripe-payment-intent-id',
						value: result.paymentIntent.id,
					} )
					.appendTo( $form );
			}
		}

		// The charge endpoint verifies the PaymentIntent against the client_secret before it records
		// anything, so the second submit of an SCA flow has to send back the secret the server
		// handed out with the requires-action response.
		function addPaymentIntentClientSecretInput( $form, clientSecret ) {
			if ( typeof clientSecret === 'undefined' || ! clientSecret ) {
				return;
			}

			$( '<input>' )
				.attr( {
					type: 'hidden',
					name: 'wpfs-stripe-client-secret',
					value: clientSecret,
				} )
				.appendTo( $form );
		}

		function addSetupIntentIdInput( $form, result ) {
			if (
				typeof result !== 'undefined' &&
				result.hasOwnProperty( 'setupIntent' ) &&
				result.setupIntent.hasOwnProperty( 'id' )
			) {
				$( '<input>' )
					.attr( {
						type: 'hidden',
						name: 'wpfs-stripe-setup-intent-id',
						value: result.setupIntent.id,
					} )
					.appendTo( $form );
			}
		}

		function addWPFSNonceInput( $form, data ) {
			if (
				typeof data !== 'undefined' &&
				data.hasOwnProperty( 'nonce' )
			) {
				$( '<input>' )
					.attr( {
						type: 'hidden',
						name: 'wpfs-nonce',
						value: data.nonce,
					} )
					.appendTo( $form );
			}
		}

		function removePaymentMethodIdInput( $form ) {
			$( 'input[name="wpfs-stripe-payment-method-id"]', $form ).remove();
		}

		function removeWPFSNonceInput( $form ) {
			$( 'input[name="wpfs-nonce"]', $form ).remove();
		}

		function removePaymentIntentIdInput( $form ) {
			$( 'input[name="wpfs-stripe-payment-intent-id"]', $form ).remove();
		}

		function removePaymentIntentClientSecretInput( $form ) {
			$( 'input[name="wpfs-stripe-client-secret"]', $form ).remove();
		}

		function removeSetupIntentIdInput( $form ) {
			$( 'input[name="wpfs-stripe-setup-intent-id"]', $form ).remove();
		}

		function removeSubscriptionIdInput( $form ) {
			$( 'input[name="wpfs-stripe-subscription-id"]', $form ).remove();
		}

		function removeFreeTransactionInput( $form ) {
			$( 'input[name="wpfs-free-transaction"]', $form ).remove();
		}

		function findListOfAmountsElement( $form ) {
			let $element = null;
			const amountSelectorStyle = $form.data( 'wpfs-selector-style' );
			if ( SELECTOR_STYLE_RADIO_BUTTONS === amountSelectorStyle ) {
				$element = $form.find( 'input[name="wpfs-custom-amount"]' );
			} else if ( SELECTOR_STYLE_BUTTON_GROUP === amountSelectorStyle ) {
				$element = $form.find( 'input[name="wpfs-custom-amount"]' );
			} else if ( SELECTOR_STYLE_DROPDOWN === amountSelectorStyle ) {
				$element = $form.find( 'select[name="wpfs-custom-amount"]' );
			}
			return $element;
		}

		function findCustomAmountElement( $form ) {
			return $form.find( 'input[name="wpfs-custom-amount-unique"]' );
		}

		function findSelectedAmountFromListOfAmounts( $form ) {
			let amount = null;
			const amountSelectorStyle = $form.data( 'wpfs-selector-style' );
			if ( SELECTOR_STYLE_RADIO_BUTTONS === amountSelectorStyle ) {
				amount = $form.find(
					'input[name="wpfs-custom-amount"]:checked'
				);
			} else if ( SELECTOR_STYLE_BUTTON_GROUP === amountSelectorStyle ) {
				amount = $form.find(
					'input[name="wpfs-custom-amount"]:checked'
				);
			} else if ( SELECTOR_STYLE_DROPDOWN === amountSelectorStyle ) {
				amount = $form.find(
					'select[name="wpfs-custom-amount"] option:selected'
				);
			}

			// This is for finding the custom hidden element if there are no preset amounts
			if ( amount == null || amount.length === 0 ) {
				amount = $form.find( 'input[name="wpfs-custom-amount"]' );
			}

			return amount;
		}

		function addCustomAmountIndexInput( $form ) {
			const $selectedAmount =
				findSelectedAmountFromListOfAmounts( $form );
			if ( $selectedAmount !== null && $selectedAmount.length > 0 ) {
				const amountIndex = $selectedAmount.data( 'wpfs-amount-index' );
				if ( typeof amountIndex !== 'undefined' ) {
					$( '<input>' )
						.attr( {
							type: 'hidden',
							name: 'wpfs-custom-amount-index',
							value: encodeURIComponent( amountIndex ),
						} )
						.appendTo( $form );
				}
			}
		}

		function addCurrentURL( $form ) {
			$( '<input>' )
				.attr( {
					type: 'hidden',
					name: 'wpfs-referrer',
					value: encodeURI( window.location.href ),
				} )
				.appendTo( $form );
		}

		function setPageParametersField( $form ) {
			const params = getQueryStringIntoArray();
			const paramsJson = JSON.stringify( params );
			$( 'input[name="wpfs-form-get-parameters"]', $form ).val(
				encodeURIComponent( paramsJson )
			);
		}

		/**
		 * @typedef {{success?: function(Object): void, error?: function(JQuery.jqXHR, string, string): void, complete?: function(): void}} AjaxCallbacks
		 */

		/**
		 * Posts to admin-ajax and reads the answer as JSON, as every form request does.
		 *
		 * @param {Object<string, *>|string} data      The request body: fields, or a serialized form.
		 * @param {AjaxCallbacks}            callbacks
		 * @return {JQuery.jqXHR} The request.
		 */
		function postAjax( data, callbacks ) {
			return $.ajax( {
				type: 'POST',
				url: wpfsFormSettings.ajaxUrl,
				data,
				cache: false,
				dataType: 'json',
				...callbacks,
			} );
		}

		// A coupon typed into the field is only sent once it was redeemed.
		function serializeForm( $form ) {
			if ( WPFS.getCoupon( extractFormNameFromNode( $form ) ) !== null ) {
				return $form.serialize();
			}

			return $( ":input[name!='wpfs-coupon']", $form ).serialize();
		}

		class WpfsAbstractFormSerializer {
			constructor( $form ) {
				this.$form = $form;
			}
		}

		class WpfsDefaultFormSerializer extends WpfsAbstractFormSerializer {
			constructor( $form ) {
				super( $form );
			}

			serialize() {
				return serializeForm( this.$form );
			}
		}

		class WpfsPaymentFormSerializer extends WpfsAbstractFormSerializer {
			constructor( $form ) {
				super( $form );
			}

			serialize() {
				if ( isSelectedProductDiscounted( this.$form ) ) {
					return serializeForm( this.$form );
				}

				return $(
					":input[name!='wpfs-coupon']",
					this.$form
				).serialize();
			}
		}

		function createFormSerializer( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );

			switch ( formType ) {
				case FORM_TYPE_INLINE_PAYMENT:
				case FORM_TYPE_INLINE_SUBSCRIPTION:
					return new WpfsPaymentFormSerializer( $form );

				case FORM_TYPE_INLINE_DONATION:
				case FORM_TYPE_INLINE_SAVE_CARD:
				case FORM_TYPE_CHECKOUT_PAYMENT:
				case FORM_TYPE_CHECKOUT_SUBSCRIPTION:
				case FORM_TYPE_CHECKOUT_DONATION:
				case FORM_TYPE_CHECKOUT_SAVE_CARD:
					return new WpfsDefaultFormSerializer( $form );

				default:
					throw new Exception(
						'createFormSerializer() - Unknown form type: ' +
							formType
					);
			}
		}

		function getSetupIntentClientSecret( $form ) {
			return new Promise( ( resolve, reject ) => {
				if ( debugLog ) {
					logInfo( 'getSetupIntentClientSecret', 'CALLED' );
				}

				const data = wpfsParseSerializedForm( serializeForm( $form ) );
				data.action = 'wp_get_Setup_Intent_Client_Secret';
				data.type = $form.data( 'wpfs-form-type' );

				postAjax( data, {
					success( data ) {
						if ( debugLog ) {
							logInfo(
								'getSetupIntentClientSecret',
								'SUCCESS response=' + JSON.stringify( data )
							);
						}
						// Carry the solved-captcha nonce to save/charge (#520).
						if ( data.nonce ) {
							removeWPFSNonceInput( $form );
							addWPFSNonceInput( $form, data );
						}
						resolve( data );
					},
					error( jqXHR, textStatus, errorThrown ) {
						logError(
							'submitPaymentData',
							jqXHR,
							textStatus,
							errorThrown
						);

						if ( jqXHR?.responseJSON?.message ) {
							showErrorGlobalMessage(
								$form,
								l10n.validation_errors
									.internal_error_title,
								jqXHR.responseJSON.message
							);
						} else {
							showErrorGlobalMessage(
								$form,
								l10n.validation_errors
									.internal_error_title,
								l10n.validation_errors
									.internal_error
							);
						}

						// The submit handler disabled the buttons and started the
						// loader before this request; the failure aborts the flow,
						// so restore the form or the donor is locked out until they
						// reload (e.g. a failed/missing reCAPTCHA — #520).
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
						// This step verifies and spends the token, so a retry needs
						// a fresh one (#660).
						resetCaptcha( $form );

						reject( errorThrown );
					},
				} );
			} );
		}

		function submitPaymentData( $form, card ) {
			if ( debugLog ) {
				logInfo( 'submitPaymentData', 'CALLED' );
			}

			clearGlobalMessage( $form );
			clearFieldErrors( $form );

			// An authentication step resubmits the form once it ends, so the
			// form stays locked until that step unlocks it.
			let authenticationStarted = false;

			postAjax( createFormSerializer( $form ).serialize(), {
				success( data ) {
					if ( debugLog ) {
						logInfo(
							'submitPaymentData',
							'SUCCESS response=' + JSON.stringify( data )
						);
					}

					if ( data.nonce ) {
						removeWPFSNonceInput( $form );
						addWPFSNonceInput( $form, data );
					}

					if ( data.success ) {
						if ( data.redirect ) {
							window.location = data.redirectURL;
						} else {
							// tnagy reset form fields
							resetFormFields( $form, card );

							// tnagy show success message
							showSuccessGlobalMessage(
								$form,
								data.messageTitle,
								data.message
							);

							// tnagy enable submit button
							enableFormButtons( $form );
						}
					} else if ( data.requiresAction ) {
						authenticationStarted = handleStripeIntentAction(
							$form,
							card,
							data
						);
					} else {
						processValidationErrors( $form, data );
					}
				},
				error( jqXHR, textStatus, errorThrown ) {
					showAjaxError(
						$form,
						'submitPaymentData',
						jqXHR,
						textStatus,
						errorThrown
					);
				},
				complete() {
					if ( authenticationStarted ) {
						// Stripe shows its own authentication dialog.
						hideProcessingOverlay();
						return;
					}
					enableFormButtons( $form );
					hideLoadingAnimation( $form );
				},
			} );
		}

		function updateFailedPaymentStatus( $form, paymentIntentId, error, clientSecret ) {
			const data = {
				action: 'wpfs_update_failed_payment_status',
				nonce: wpfsFormSettings.nonce,
				paymentIntentId: paymentIntentId,
				clientSecret: clientSecret || '',
				failureCode: error.code || '',
				failureMessage: error.message || '',
			};

			postAjax( data, {
				success( response ) {
					if ( debugLog ) {
						logInfo(
							'updateFailedPaymentStatus',
							'Payment status updated in database: ' + JSON.stringify( response )
						);
					}
				},
				// Only the record failed: the banner keeps the error the customer needs to see.
				error( jqXHR, textStatus, errorThrown ) {
					if ( debugLog ) {
						logError(
							'updateFailedPaymentStatus',
							jqXHR,
							textStatus,
							errorThrown
						);
					}
				}
			} );
		}

		/**
		 * @param {Object} $form The submitted form.
		 * @param {Object} card  The card element.
		 * @param {Object} data  The charge response asking for authentication.
		 * @return {boolean} Whether an authentication step started; it unlocks the form when it fails.
		 */
		function handleStripeIntentAction( $form, card, data ) {
			let started = false;

			function follow( promise, handler ) {
				started = true;
				promise.then( handler, function ( error ) {
					handler( { error } );
				} );
			}

			function unlockAfterFailedAction() {
				enableFormButtons( $form );
				hideLoadingAnimation( $form );
			}

			function handlePaymentIntentAction( result ) {
				if ( debugLog ) {
					logInfo(
						'handlePaymentIntentAction',
						'result=' + JSON.stringify( result )
					);
				}
				if ( result.error ) {
					logWarn( 'handleStripeIntentAction', result.error.message );
					// Update payment status in database when confirmation fails
					if ( result.paymentIntent && result.paymentIntent.id ) {
						updateFailedPaymentStatus( $form, result.paymentIntent.id, result.error, data.paymentIntentClientSecret );
					}
					showErrorGlobalMessage(
						$form,
						l10n.stripe_errors.stripe_error_title,
						result.error.message
					);
					unlockAfterFailedAction();
				} else {
					removePaymentIntentIdInput( $form );
					removePaymentIntentClientSecretInput( $form );
					addPaymentIntentIdInput( $form, result );
					addPaymentIntentClientSecretInput(
						$form,
						data.paymentIntentClientSecret
					);
					disableFormButtons( $form );
					showLoadingAnimation( $form );
					submitPaymentData( $form, card );
				}
			}

			function handleSetupIntentAction( result ) {
				if ( debugLog ) {
					logInfo(
						'handleSetupIntentAction',
						'result=' + JSON.stringify( result )
					);
				}
				if ( result.error ) {
					logWarn( 'handleSetupIntentAction', result.error.message );
					showErrorGlobalMessage(
						$form,
						l10n.stripe_errors.stripe_error_title,
						result.error.message
					);
					unlockAfterFailedAction();
				} else {
					removeSetupIntentIdInput( $form );
					addSetupIntentIdInput( $form, result );
					disableFormButtons( $form );
					showLoadingAnimation( $form );
					submitPaymentData( $form, card );
				}
			}

			function handlePaymentSetupIntentAction( result ) {
				if ( debugLog ) {
					logInfo(
						'handlePaymentSetupIntentAction',
						'result=' + JSON.stringify( result )
					);
				}
				if ( result.error ) {
					logWarn(
						'handlePaymentSetupIntentAction',
						result.error.message
					);
					showErrorGlobalMessage(
						$form,
						l10n.stripe_errors.stripe_error_title,
						result.error.message
					);
					unlockAfterFailedAction();
				} else {
					removePaymentIntentIdInput( $form );
					removePaymentIntentClientSecretInput( $form );
					removeSetupIntentIdInput( $form );
					addPaymentIntentIdInput( $form, result );
					addPaymentIntentClientSecretInput(
						$form,
						data.paymentIntentClientSecret
					);
					addSetupIntentIdInput( $form, result );
					disableFormButtons( $form );
					showLoadingAnimation( $form );
					submitPaymentData( $form, card );
				}
			}

			if ( debugLog ) {
				logInfo(
					'handleStripeIntentAction',
					'CALLED, params: data=' + JSON.stringify( data )
				);
			}
			if ( stripe != null ) {
				if ( FORM_TYPE_INLINE_PAYMENT === data.formType ) {
					if ( data.isManualConfirmation ) {
						follow(
							stripe.handleCardAction(
								data.paymentIntentClientSecret
							),
							handlePaymentIntentAction
						);
					} else {
						follow(
							stripe.confirmCardPayment(
								data.paymentIntentClientSecret
							),
							handlePaymentIntentAction
						);
					}
				}
				if ( FORM_TYPE_INLINE_SUBSCRIPTION === data.formType ) {
					if (
						data.hasOwnProperty( 'paymentIntentClientSecret' ) &&
						data.paymentIntentClientSecret != null
					) {
						follow(
							stripe.handleCardPayment(
								data.paymentIntentClientSecret
							),
							handlePaymentSetupIntentAction
						);
					} else if (
						data.hasOwnProperty( 'setupIntentClientSecret' ) &&
						data.setupIntentClientSecret != null
					) {
						follow(
							stripe.handleCardSetup(
								data.setupIntentClientSecret
							),
							handlePaymentSetupIntentAction
						);
					}
				}
				if ( FORM_TYPE_INLINE_DONATION === data.formType ) {
					if ( data.isManualConfirmation ) {
						follow(
							stripe.handleCardAction(
								data.paymentIntentClientSecret
							),
							handlePaymentIntentAction
						);
					} else {
						follow(
							stripe.confirmCardPayment(
								data.paymentIntentClientSecret
							),
							handlePaymentIntentAction
						);
					}
				}
				if ( FORM_TYPE_INLINE_SAVE_CARD === data.formType ) {
					follow(
						stripe.handleCardSetup( data.setupIntentClientSecret ),
						handleSetupIntentAction
					);
				}
			} else {
				// todo tnagy show error message
			}

			return started;
		}

		function handleCustomAmountChange( $customAmountSelectElement, $form ) {
			const amountType = $form.data( 'wpfs-amount-type' );
			const amountValue = $customAmountSelectElement.val();

			if ( PAYMENT_TYPE_LIST_OF_AMOUNTS === amountType ) {
				if ( AMOUNT_OTHER === amountValue ) {
					clearAndShowAndEnableAndFocusCustomAmount( $form );
				} else {
					clearAndHideAndDisableCustomAmount( $form );
				}
			}
		}

		function parseCustomAmountUnique(
			$form,
			returnSmallestCommonCurrencyUnit = false
		) {
			let result = null;
			const formatter = createCurrencyFormatter( $form );
			const $element = $( '.wpfs-custom-amount--unique', $form );
			const customAmountValue = $element.val();

			const zeroDecimalSupport =
				$element.data( 'wpfs-zero-decimal-support' ) === true;

			if (
				customAmountValue !== '' &&
				formatter.validCustomAmount( customAmountValue, zeroDecimalSupport )
			) {
				result = parseCurrencyAmount(
					formatter.parse( customAmountValue ),
					zeroDecimalSupport,
					returnSmallestCommonCurrencyUnit
				);
			}

			return result;
		}

		/**
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {boolean}                 zeroDecimalSupport
		 * @return {string} The format error message with an example amount, e.g. "12,34".
		 */
		function getCustomAmountFormatErrorMessage( $form, zeroDecimalSupport ) {
			return sprintf(
				l10n.validation_errors.custom_amount_format_is_invalid,
				createCurrencyFormatter( $form ).formatExampleAmount(
					zeroDecimalSupport
				)
			);
		}

		/**
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {?{$element: JQuery<HTMLInputElement>, zeroDecimalSupport: boolean}}
		 * The enabled custom amount field when it holds a malformed amount, otherwise null.
		 */
		function findMalformedCustomAmount( $form ) {
			const $element = $(
				'input[name="wpfs-custom-amount-unique"]:enabled',
				$form
			);
			if ( $element.length === 0 ) {
				return null;
			}
			const zeroDecimalSupport =
				$element.data( 'wpfs-zero-decimal-support' ) === true;
			if (
				createCurrencyFormatter( $form ).validCustomAmount(
					$element.val(),
					zeroDecimalSupport
				)
			) {
				return null;
			}
			return { $element, zeroDecimalSupport };
		}

		/**
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True when the enabled custom amount field holds a malformed amount.
		 */
		function isCustomAmountMalformed( $form ) {
			return findMalformedCustomAmount( $form ) !== null;
		}

		/**
		 * Shows the expected format when the enabled custom amount field holds
		 * a malformed amount.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True when the custom amount is malformed.
		 */
		function showCustomAmountFormatError( $form ) {
			const malformed = findMalformedCustomAmount( $form );
			if ( malformed === null ) {
				return false;
			}
			const { $element, zeroDecimalSupport } = malformed;
			clearFieldError(
				$form,
				'wpfs-custom-amount-unique',
				$element.attr( 'id' )
			);
			showFieldError(
				$form,
				'wpfs-custom-amount-unique',
				$element.attr( 'id' ),
				getCustomAmountFormatErrorMessage( $form, zeroDecimalSupport ),
				true
			);
			return true;
		}

		function getTaxIdType( $form ) {
			return $( '.wpfs-tax-id-type-select option:selected', $form ).val();
		}

		/**
		 * The billing city and street the price is taxed on. The country, state and ZIP come
		 * from the tax fields or the billing address too, so the page prices one address, never
		 * a mix with the shipping one.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {{city: ?string, line1?: ?string, line2?: ?string}} The city and the lines
		 *                                                             filled in; all null
		 *                                                             without a billing city.
		 */
		function getTaxAddressFromForm( $form ) {
			const data = {};
			const billCityField = $(
				'input[name="wpfs-billing-address-city"]',
				$form
			);
			if ( billCityField.val() ) {
				data.city = billCityField.val();
				const billLin1Field = $(
					'input[name="wpfs-billing-address-line-1"]',
					$form
				);
				if ( billLin1Field.val() ) {
					data.line1 = billLin1Field.val();
				}
				const billLin2Field = $(
					'input[name="wpfs-billing-address-line-2"]',
					$form
				);
				if ( billLin2Field.val() ) {
					data.line2 = billLin2Field.val();
				}
			} else {
				data.city = null;
				data.line1 = null;
				data.line2 = null;
			}
			return data;
		}

		function gatherFormDataForTaxCalculation( $form ) {
			const data = {};

			data.currentPriceId = findPriceIdForSelectedProduct( $form );
			// The server previews the fee where the charge adds it as a line of its own.
			data.feeRecoveryAccepted = isFeeRecoveryChecked( $form ) ? 1 : 0;

			if (
				$( 'select[name="wpfs-tax-country"] option:selected', $form ) &&
				$( 'select[name="wpfs-tax-country"] option:selected', $form )
					.length > 0
			) {
				data.country = $(
					'select[name="wpfs-tax-country"] option:selected',
					$form
				).val();

				if (
					data.country === COUNTRY_CODE_UNITED_STATES &&
					$(
						'select[name="wpfs-tax-state"] option:selected',
						$form
					) &&
					$( 'select[name="wpfs-tax-state"] option:selected', $form )
						.length > 0
				) {
					data.state = $(
						'select[name="wpfs-tax-state"] option:selected',
						$form
					).val();
				} else {
					data.state = null;
				}
			} else if (
				$(
					'select[name="wpfs-billing-address-country"] option:selected',
					$form
				).length > 0
			) {
				data.country = $(
					'select[name="wpfs-billing-address-country"] option:selected',
					$form
				).val();

				if (
					data.country === COUNTRY_CODE_UNITED_STATES &&
					$(
						'select[name="wpfs-billing-address-state-select"] option:selected',
						$form
					).length > 0
				) {
					data.state = $(
						'select[name="wpfs-billing-address-state-select"] option:selected',
						$form
					).val();
				} else {
					data.state = null;
				}
			} else {
				data.country = null;
				data.state = null;
			}

			if (
				$( 'input[name="wpfs-tax-zip"]', $form ) &&
				$( 'input[name="wpfs-tax-zip"]', $form ).length > 0
			) {
				data.zip = $( 'input[name="wpfs-tax-zip"]', $form ).val();
			} else if (
				$( 'input[name="wpfs-billing-address-zip"]', $form ).val()
			) {
				data.zip = $(
					'input[name="wpfs-billing-address-zip"]',
					$form
				).val();
			} else {
				data.zip = null;
			}

			const address = getTaxAddressFromForm( $form );
			data.city = address.city;
			data.line1 = address.line1;
			data.line2 = address.line2;

			if (
				$( 'input[name="wpfs-plan-quantity"]', $form ) &&
				$( 'input[name="wpfs-plan-quantity"]', $form ).length > 0
			) {
				data.quantity = $(
					'input[name="wpfs-plan-quantity"]',
					$form
				).spinner( 'value' );
			} else {
				data.quantity = 1;
			}

			if ( isBuyingAsABusiness( $form ) ) {
				data.taxIdType = getTaxIdType( $form );
				data.taxId = $( 'input[name="wpfs-tax-id"]', $form ).val();
			} else {
				data.taxIdType = null;
				data.taxId = null;
			}

			data.formId = extractFormNameFromNode( $form );

			data.formType = $form.data( FROM_TYPE_DOM );

			const coupon = WPFS.getCoupon( data.formId );
			if ( coupon !== null ) {
				data.coupon = coupon.name;
			} else {
				data.coupon = null;
			}

			data.customAmount = null;
			if (
				$( '.wpfs-custom-amount--unique', $form ) &&
				$( '.wpfs-custom-amount--unique', $form ).length > 0
			) {
				const customAmount = parseCustomAmountUnique( $form, true );

				if (
					customAmount &&
					customAmount !== '' &&
					! isNaN( customAmount )
				) {
					data.customAmount = customAmount;
				} else {
					data.customAmount = 0;
				}
			}

			return data;
		}

		function refreshShippingStateField( $form, country ) {
			if ( country === COUNTRY_CODE_UNITED_STATES ) {
				$( 'input[name="wpfs-shipping-address-state"]', $form ).hide();
				$(
					'.wpfs-form-select.wpfs-shipping-address-state-select'
				).show();
			} else {
				$( 'input[name="wpfs-shipping-address-state"]', $form ).show();
				$(
					'.wpfs-form-select.wpfs-shipping-address-state-select'
				).hide();
			}
		}

		function handleShippingAddressCountryChange(
			$shippingAddressSelectElement,
			$form
		) {
			if ( debugLog ) {
				console.log( 'handleShippingAddressCountryChange(): CALLED' );
			}

			const selectedCountry = $(
				'option:selected',
				$shippingAddressSelectElement
			).val();
			refreshShippingStateField( $form, selectedCountry );
		}

		function refreshBillingStateField( $form, country ) {
			if ( country === COUNTRY_CODE_UNITED_STATES ) {
				$( 'input[name="wpfs-billing-address-state"]', $form ).hide();
				$(
					'.wpfs-form-select.wpfs-billing-address-state-select'
				).show();
			} else {
				$( 'input[name="wpfs-billing-address-state"]', $form ).show();
				$(
					'.wpfs-form-select.wpfs-billing-address-state-select'
				).hide();
			}
		}

		function handleBillingAddressCountryChange(
			$billingAddressSelectElement,
			$form
		) {
			if ( debugLog ) {
				console.log( 'handleBillingAddressCountryChange(): CALLED' );
			}

			const selectedCountry = $(
				'option:selected',
				$billingAddressSelectElement
			).val();
			refreshBillingStateField( $form, selectedCountry );

			if ( isDynamicTaxesForm( $form ) || isStripeTaxForm( $form ) ) {
				refreshPricingFromServer( $form );
			}

			if ( isTaxIdCollectible( $form ) ) {
				setTaxIdTypeByCountry( $form );
				if ( isTaxIdCollectible( $form ) ) {
					refreshTaxIdPlaceholder( $form );
				}
			}
		}

		function handleBillingAddressStateChange(
			$billingAddressStateSelectElement,
			$form
		) {
			if ( isDynamicTaxesForm( $form ) ) {
				refreshPricingFromServer( $form );
			}
		}

		function handleAmountChange( $form ) {
			if ( debugLog ) {
				console.log( 'handleAmountChange(): CALLED' );
			}
			if (
				PAYMENT_TYPE_LIST_OF_AMOUNTS ===
				$form.data( 'wpfs-amount-type' )
			) {
				handlePaymentListOfAmountsChange( $form );
			} else if (
				PAYMENT_TYPE_CUSTOM_AMOUNT === $form.data( 'wpfs-amount-type' )
			) {
				handlePaymentCustomAmountChange( $form );
			} else if (
				PAYMENT_TYPE_SPECIFIED_AMOUNT ===
				$form.data( 'wpfs-amount-type' )
			) {
				handlePaymentHiddenChange( $form );
			} else {
				logWarn(
					'handlePaymentChange()',
					'Payment Amount UI element not found!'
				);
			}
		}

		function handlePaymentListOfAmountsChange( $form ) {
			if ( debugLog ) {
				console.log( 'handlePaymentListOfAmountsChange(): CALLED' );
			}

			let $selectedAmountElement;
			if (
				$( '.wpfs-custom-amount-select', $form ) &&
				$( '.wpfs-custom-amount-select', $form ).length > 0 &&
				$( '.wpfs-custom-amount-select option:selected', $form ) &&
				$( '.wpfs-custom-amount-select option:selected', $form )
					.length > 0
			) {
				$selectedAmountElement = $(
					'.wpfs-custom-amount-select option:selected',
					$form
				);
			} else if (
				$( 'input.wpfs-custom-amount', $form ) &&
				$( 'input.wpfs-custom-amount', $form ).length > 0
			) {
				$selectedAmountElement = $(
					'input.wpfs-custom-amount:checked',
					$form
				);
			}
			if (
				$selectedAmountElement &&
				$selectedAmountElement.val() === AMOUNT_OTHER
			) {
				$selectedAmountElement = $(
					'input.wpfs-custom-amount--unique',
					$form
				);
			}

			if ( $selectedAmountElement ) {
				handlePaymentAmountChange( $form, $selectedAmountElement );
			}
		}

		function handlePaymentCustomAmountChange( $form ) {
			const $selectedAmountElement = $(
				'.wpfs-custom-amount--unique',
				$form
			);
			handlePaymentAmountChange( $form, $selectedAmountElement );
		}

		function handlePaymentHiddenChange( $form ) {
			const $selectedAmountElement = $( '.wpfs-custom-amount', $form );
			handlePaymentAmountChange( $form, $selectedAmountElement );
		}

		function getTotalAmountForPrice( paymentDetailsConfig ) {
			let total = 0;

			if ( paymentDetailsConfig.hasOwnProperty( 'total' ) ) {
				total = paymentDetailsConfig.total;
			} else {
				const paymentDetails = WPFS.getPaymentDetailsForPrice(
					paymentDetailsConfig.formId,
					paymentDetailsConfig.priceId
				);

				if ( paymentDetails !== null ) {
					paymentDetails.forEach( ( item ) => {
						// The caller adds the recovery fee for the current amount.
						if (
							! (
								item.subType === PRICE_LINE_ITEM_SUBTYPE_TAX &&
								item.inclusive
							) &&
							item.type !== PAYMENT_DETAIL_FEE_RECOVERY
						) {
							total += item.amount;
						}
					} );
				}
				// Note: The item.amount is in minor-units.
				const $form = $( `form[data-wpfs-form-id="${ paymentDetailsConfig?.formId }"]` );
				total = convertNonZeroDecimalAmount(
					$form,
					total,
					paymentDetailsConfig
				);
			}

			const amountLabel = formatCurrencyAmount(
				total,
				paymentDetailsConfig.zeroDecimalSupport
			);
			return parseCurrencyAmount(
				amountLabel,
				paymentDetailsConfig.zeroDecimalSupport
			);
		}

		function showPaymentButtonCaption( $form, paymentDetailsConfig ) {
			if ( ! isFeeRecoveryChecked( $form ) ) {
				clearRecoveryItem( paymentDetailsConfig );
			}

			let total = getTotalAmountForPrice( paymentDetailsConfig );

			if ( total && isFeeRecoveryChecked( $form ) ) {
				const priceAmounts = getPaymentPriceAmounts( $form );
				const feeRecoveryAmount = priceAmounts
					? getPricedRecoveryFee( $form, priceAmounts )
					: calculateRecoveryFee( total, paymentDetailsConfig );
				if ( ! paymentDetailsConfig.zeroDecimalSupport ) {
					total += feeRecoveryAmount / 100;
				} else {
					total += feeRecoveryAmount;
				}
			}
			
			if ( total === null || isNaN( total ) ) {
				updateButtonCaption(
					$form,
					paymentDetailsConfig.buttonTitle,
					null,
					null,
					null,
					null
				);
			} else {
				updateButtonCaption(
					$form,
					paymentDetailsConfig.buttonTitle,
					paymentDetailsConfig.currency,
					paymentDetailsConfig.currencySymbol,
					paymentDetailsConfig.zeroDecimalSupport
						? total
						: total.toFixed( 2 ),
					paymentDetailsConfig.zeroDecimalSupport
				);
			}
		}

		/**
		 * "every 3 months": how often a subscription is charged, for intervalCount > 1.
		 *
		 * @param {'day'|'week'|'month'|'year'} interval
		 * @param {number}                      intervalCount
		 * @return {string|null} Null for an unknown interval.
		 */
		function getChargeIntervalPhrase( interval, intervalCount ) {
			if ( 'day' === interval ) {
				return sprintf(
					/* translators: How often a subscription is charged, used in "Subscription will be charged every 3 months, for 12 occasions." %d: number of days between charges. */
					_n( 'every %d day', 'every %d days', intervalCount, 'wp-full-stripe-free' ),
					intervalCount
				);
			} else if ( 'week' === interval ) {
				return sprintf(
					/* translators: How often a subscription is charged, used in "Subscription will be charged every 3 months, for 12 occasions." %d: number of weeks between charges. */
					_n( 'every %d week', 'every %d weeks', intervalCount, 'wp-full-stripe-free' ),
					intervalCount
				);
			} else if ( 'month' === interval ) {
				return sprintf(
					/* translators: How often a subscription is charged, used in "Subscription will be charged every 3 months, for 12 occasions." %d: number of months between charges. */
					_n( 'every %d month', 'every %d months', intervalCount, 'wp-full-stripe-free' ),
					intervalCount
				);
			} else if ( 'year' === interval ) {
				return sprintf(
					/* translators: How often a subscription is charged, used in "Subscription will be charged every 3 months, for 12 occasions." %d: number of years between charges. */
					_n( 'every %d year', 'every %d years', intervalCount, 'wp-full-stripe-free' ),
					intervalCount
				);
			}

			return null;
		}

		function renderSubscriptionSummary(
			interval,
			intervalCount,
			cancellationCount
		) {
			let subscriptionDetailsLabel = null;

			if ( cancellationCount > 0 ) {
				if ( intervalCount > 1 ) {
					const chargeIntervalPhrase = getChargeIntervalPhrase(
						interval,
						intervalCount
					);
					if ( chargeIntervalPhrase !== null ) {
						subscriptionDetailsLabel = sprintf(
							/* translators: Summary of a subscription that ends after a number of charges. %1$s: how often the subscription is charged, for example "every 3 months", %2$s: number of charges, for example "12 occasions". */
							__( 'Subscription will be charged %1$s, for %2$s.', 'wp-full-stripe-free' ),
							chargeIntervalPhrase,
							sprintf(
								/* translators: Number of charges after which a subscription ends, used in "Subscription will be charged every 3 months, for 12 occasions." %d: number of charges. */
								_n( '%d occasion', '%d occasions', cancellationCount, 'wp-full-stripe-free' ),
								cancellationCount
							)
						);
					}
				} else if ( 'day' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a daily subscription that ends after a number of charges. %d: number of charges. */
						_n( 'Subscription will be charged every day, for %d occasion.', 'Subscription will be charged every day, for %d occasions.', cancellationCount, 'wp-full-stripe-free' ),
						cancellationCount
					);
				} else if ( 'week' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a weekly subscription that ends after a number of charges. %d: number of charges. */
						_n( 'Subscription will be charged every week, for %d occasion.', 'Subscription will be charged every week, for %d occasions.', cancellationCount, 'wp-full-stripe-free' ),
						cancellationCount
					);
				} else if ( 'month' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a monthly subscription that ends after a number of charges. %d: number of charges. */
						_n( 'Subscription will be charged every month, for %d occasion.', 'Subscription will be charged every month, for %d occasions.', cancellationCount, 'wp-full-stripe-free' ),
						cancellationCount
					);
				} else if ( 'year' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a yearly subscription that ends after a number of charges. %d: number of charges. */
						_n( 'Subscription will be charged every year, for %d occasion.', 'Subscription will be charged every year, for %d occasions.', cancellationCount, 'wp-full-stripe-free' ),
						cancellationCount
					);
				}
			} else if ( intervalCount > 1 ) {
				if ( 'day' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a subscription charged every few days. %d: number of days between charges. */
						_n( 'Subscription will be charged every %d day.', 'Subscription will be charged every %d days.', intervalCount, 'wp-full-stripe-free' ),
						intervalCount
					);
				} else if ( 'week' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a subscription charged every few weeks. %d: number of weeks between charges. */
						_n( 'Subscription will be charged every %d week.', 'Subscription will be charged every %d weeks.', intervalCount, 'wp-full-stripe-free' ),
						intervalCount
					);
				} else if ( 'month' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a subscription charged every few months. %d: number of months between charges. */
						_n( 'Subscription will be charged every %d month.', 'Subscription will be charged every %d months.', intervalCount, 'wp-full-stripe-free' ),
						intervalCount
					);
				} else if ( 'year' === interval ) {
					subscriptionDetailsLabel = sprintf(
						/* translators: Summary of a subscription charged every few years. %d: number of years between charges. */
						_n( 'Subscription will be charged every %d year.', 'Subscription will be charged every %d years.', intervalCount, 'wp-full-stripe-free' ),
						intervalCount
					);
				}
			} else if ( 'day' === interval ) {
				subscriptionDetailsLabel = __( 'Subscription will be charged every day.', 'wp-full-stripe-free' );
			} else if ( 'week' === interval ) {
				subscriptionDetailsLabel = __( 'Subscription will be charged every week.', 'wp-full-stripe-free' );
			} else if ( 'month' === interval ) {
				subscriptionDetailsLabel = __( 'Subscription will be charged every month.', 'wp-full-stripe-free' );
			} else if ( 'year' === interval ) {
				subscriptionDetailsLabel = __( 'Subscription will be charged every year.', 'wp-full-stripe-free' );
			}

			return subscriptionDetailsLabel;
		}

		function showPaymentDetails( $form, paymentDetailsConfig ) {
			const lineItems = WPFS.getPaymentDetailsForPrice(
				paymentDetailsConfig.formId,
				paymentDetailsConfig.priceId
			);

			renderPaymentDetails(
				$form,
				paymentDetailsConfig,
				lineItems,
				null
			);
		}

		function showSubscriptionDetails( $form, paymentDetailsConfig ) {
			const lineItems = WPFS.getPaymentDetailsForPrice(
				paymentDetailsConfig.formId,
				paymentDetailsConfig.priceId
			);
			const subscriptionDetailsLabel = renderSubscriptionSummary(
				paymentDetailsConfig.interval,
				paymentDetailsConfig.intervalCount,
				paymentDetailsConfig.cancellationCount
			);

			renderPaymentDetails(
				$form,
				paymentDetailsConfig,
				lineItems,
				subscriptionDetailsLabel
			);
		}

		function preparePaymentDetails( lineItems ) {
			const displayItems = [];

			const setupFeeItem = lineItems.find(
				( lineItem ) =>
					lineItem.type === PRICE_LINE_ITEM_TYPE_SETUP_FEE &&
					lineItem.subType === PRICE_LINE_ITEM_TYPE_SETUP_FEE
			);
			if ( setupFeeItem ) {
				displayItems.push( {
					type: PAYMENT_DETAIL_ROW_SETUP_FEE,
					amount: setupFeeItem.amount,
					displayName: setupFeeItem.displayName,
				} );
			}

			const productItem = lineItems.find(
				( lineItem ) =>
					lineItem.type === PRICE_LINE_ITEM_TYPE_PRODUCT &&
					lineItem.subType === PRICE_LINE_ITEM_TYPE_PRODUCT
			);
			if ( productItem ) {
				displayItems.push( {
					type: PAYMENT_DETAIL_ROW_PRODUCT,
					amount: productItem.amount,
					displayName: productItem.displayName,
				} );
			}

			const taxItems = lineItems.filter(
				( lineItem ) => lineItem.subType === PRICE_LINE_ITEM_SUBTYPE_TAX
			);
			if ( taxItems && taxItems.length > 0 ) {
				const taxRateIds = [
					...new Set( taxItems.map( ( taxItem ) => taxItem.id ) ),
				];
				const taxRateAmounts = {};
				taxRateIds.forEach( ( taxRateId ) => {
					taxRateAmounts[ taxRateId ] = 0;
				} );
				taxItems.forEach( ( taxItem ) => {
					taxRateAmounts[ taxItem.id ] += taxItem.amount;
				} );

				for ( const taxRateId in taxRateAmounts ) {
					const taxItem = taxItems.find(
						( taxItem ) => taxItem.id === taxRateId
					);

					displayItems.push( {
						type: PAYMENT_DETAIL_ROW_TAX,
						percentage: taxItem.percentage,
						amount: taxRateAmounts[ taxRateId ],
						inclusive: taxItem.inclusive,
						displayName: taxItem.displayName
							? taxItem.displayName
							: l10n.product_pricing
									.default_tax_label,
					} );
				}
			}

			const discountAmount = lineItems
				.filter(
					( lineItem ) =>
						lineItem.subType === PRICE_LINE_ITEM_SUBTYPE_DISCOUNT
				)
				.reduce( ( sum, lineItem ) => {
					return sum + lineItem.amount;
				}, 0 );
			if ( discountAmount < 0 ) {
				const labelItem = lineItems.find(
					( lineItem ) =>
						lineItem.subType === PRICE_LINE_ITEM_SUBTYPE_DISCOUNT
				);

				displayItems.push( {
					type: PAYMENT_DETAIL_ROW_DISCOUNT,
					amount: discountAmount,
					displayName: labelItem
						? labelItem.displayName
						: l10n.product_pricing
								.default_coupon_label,
				} );
			}

			const feeRecoveryItem = lineItems.find(
				( lineItem ) =>
					lineItem.type === PAYMENT_DETAIL_FEE_RECOVERY &&
					lineItem.subType === PAYMENT_DETAIL_FEE_RECOVERY
			);

			if ( feeRecoveryItem ) {
				displayItems.push( {
					type: PAYMENT_DETAIL_FEE_RECOVERY,
					amount: feeRecoveryItem.amount,
					displayName: feeRecoveryItem.displayName,
				} );
			}

			return displayItems;
		}

		function getTaxLineItemLabel( lineItem ) {
			let result;

			if ( lineItem.percentage ) {
				const pattern = lineItem.inclusive
					? l10n.product_pricing
							.tax_label_inclusive_percentage
					: l10n.product_pricing
							.tax_label_percentage;
				result = sprintf(
					pattern,
					lineItem.displayName,
					lineItem.percentage
				);
			} else {
				const pattern = lineItem.inclusive
					? l10n.product_pricing.tax_label_inclusive
					: '%s';
				result = sprintf( pattern, lineItem.displayName );
			}

			return result;
		}

		function renderPaymentDetails(
			$form,
			paymentDetailsConfig,
			lineItems,
			summaryLabel
		) {
			clearPaymentDetails( $form );

			if ( lineItems !== null ) {
				const displayItems = preparePaymentDetails( lineItems );

				const formatter = createCurrencyFormatter( $form );

				let total = 0;
				let taxIdx = 0;
				displayItems.forEach( ( lineItem ) => {
					if (
						lineItem.type === PAYMENT_DETAIL_FEE_RECOVERY &&
						PRICE_ID_CUSTOM_AMOUNT === paymentDetailsConfig.priceId &&
						! paymentDetailsConfig.zeroDecimalSupport
					) {
						lineItem.amount = lineItem.amount / 100;
					} else {
						lineItem.amount = convertNonZeroDecimalAmount(
							$form,
							lineItem.amount,
							paymentDetailsConfig
						);
					}
					const amount = formatter.format(
						formatCurrencyAmount(
							lineItem.amount,
							paymentDetailsConfig.zeroDecimalSupport
						),
						paymentDetailsConfig.currency.toUpperCase(),
						paymentDetailsConfig.currencySymbol,
						paymentDetailsConfig.zeroDecimalSupport
					);

					if (
						lineItem.type === PAYMENT_DETAIL_ROW_PRODUCT ||
						lineItem.type === PAYMENT_DETAIL_ROW_SETUP_FEE ||
						lineItem.type === PAYMENT_DETAIL_ROW_DISCOUNT ||
						lineItem.type === PAYMENT_DETAIL_FEE_RECOVERY
					) {
						$(
							'td[data-wpfs-summary-row-label="' +
								lineItem.type +
								'"]',
							$form
						).text( lineItem.displayName );
						$(
							'td[data-wpfs-summary-row-value="' +
								lineItem.type +
								'"]',
							$form
						).text( amount );
						$(
							'tr[data-wpfs-summary-row="' + lineItem.type + '"]',
							$form
						).show();

						total += lineItem.amount;
					} else if ( lineItem.type === PAYMENT_DETAIL_ROW_TAX ) {
						$(
							'td[data-wpfs-summary-row-label="tax-' +
								taxIdx +
								'"]',
							$form
						).text( getTaxLineItemLabel( lineItem ) );
						$(
							'td[data-wpfs-summary-row-value="tax-' +
								taxIdx +
								'"]',
							$form
						).text( amount );
						$(
							'tr[data-wpfs-summary-row="tax-' + taxIdx + '"]',
							$form
						).show();

						if ( ! lineItem.inclusive ) {
							total += lineItem.amount;
						}

						taxIdx++;
					}
				} );

				const totalLabel = formatter.format(
					formatCurrencyAmount(
						total,
						paymentDetailsConfig.zeroDecimalSupport
					),
					paymentDetailsConfig.currency.toUpperCase(),
					paymentDetailsConfig.currencySymbol,
					paymentDetailsConfig.zeroDecimalSupport
				);
				$(
					'td[data-wpfs-summary-row-value="' +
						PAYMENT_DETAIL_ROW_TOTAL +
						'"]',
					$form
				).text( totalLabel );
				$(
					'tr[data-wpfs-summary-row="' +
						PAYMENT_DETAIL_ROW_TOTAL +
						'"]',
					$form
				).show();
			}

			if ( summaryLabel !== null ) {
				$( '.wpfs-summary-description', $form ).text( summaryLabel );
				$( '.wpfs-summary-description', $form ).show();
			}
		}

		/**
		 * @typedef {{formId: string|number|boolean|null, currency: string, currencySymbol: string, zeroDecimalSupport: boolean}} AmountConfig
		 */

		/**
		 * Reads the currency settings that pricing needs from the selected amount, price or plan.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {JQuery<HTMLElement>}     $element The selected amount, price or plan.
		 * @return {AmountConfig} The settings.
		 */
		function readAmountConfig( $form, $element ) {
			return {
				formId: extractFormNameFromNode( $form ),
				currency: $element.data( 'wpfs-currency' ),
				currencySymbol: $element.data( 'wpfs-currency-symbol' ),
				zeroDecimalSupport:
					$element.data( 'wpfs-zero-decimal-support' ) === true,
			};
		}

		/**
		 * Adds the fee percentage and fixed amount of an element that recovers the fee.
		 *
		 * @param {AmountConfig & {feePercentage?: number|string, feeAmount?: number|string}} config
		 * @param {JQuery<HTMLElement>}                                                       $element
		 */
		function addFeeRecoveryConfig( config, $element ) {
			if ( $element.data( 'wpfs-fee-recovery-enabled' ) ) {
				config.feePercentage = $element.data( 'wpfs-fee-percentage' );
				config.feeAmount = $element.data( 'wpfs-fee-amount' );
			}
		}

		function handleDonationPaymentAmountChange(
			$form,
			$selectedProductElement
		) {
			const buttonCaptionConfig = {
				...readAmountConfig( $form, $selectedProductElement ),
				buttonTitle:
					$selectedProductElement.data( 'wpfs-button-title' ),
			};
			if (
				$selectedProductElement.hasClass( 'wpfs-custom-amount--unique' )
			) {
				buttonCaptionConfig.total = $selectedProductElement.val()
					? parseCustomAmountUnique( $form )
					: 0;
			} else {
				buttonCaptionConfig.total = $selectedProductElement.data(
					'wpfs-amount-in-smallest-common-currency'
				);
				if ( ! buttonCaptionConfig.zeroDecimalSupport ) {
					buttonCaptionConfig.total = parseInt( buttonCaptionConfig.total ) / 100;
				}
			}
			addFeeRecoveryConfig(
				buttonCaptionConfig,
				$selectedProductElement
			);

			showPaymentButtonCaption( $form, buttonCaptionConfig );
			syncStripeElementsAmount( $form );
		}

		function handlePriceAwarePaymentAmountChange(
			$form,
			$selectedProductElement
		) {
			if ( isCouponFieldVisible( $form ) ) {
				refreshCouponField( $form );
			}

			// A malformed custom amount has no price. The stored lines are kept, because
			// the payment serializer reads them to decide whether to send the coupon.
			if ( isCustomAmountMalformed( $form ) ) {
				updateButtonCaption(
					$form,
					$selectedProductElement.data( 'wpfs-button-title' ),
					null,
					null,
					null,
					null
				);
				clearPaymentDetails( $form );
				return;
			}

			// update pricing to update payment intent
			if ( $form.data( 'wpfs-stripe-payment-intent-id' ) ) {
				const data = gatherFormDataForTaxCalculation( $form );

				data.action = 'wpfs-update-payment-intent';
				data.nonce = wpfsFormSettings.nonce;
				data.stripePaymentIntentId = $form.data(
					'wpfs-stripe-payment-intent-id'
				);
				data.stripeClientSecret = $form.data(
					'wpfs-stripe-client-secret'
				);
				data.stripePriceId = $selectedProductElement.data(
					'wpfs-amount-price-id'
				);
				postAjax( data, {
					success( data ) {
						if ( debugLog ) {
							logInfo(
								'handlePriceAwarePaymentAmountChange',
								'SUCCESS response=' + JSON.stringify( data )
							);
						}
					},
					error( jqXHR, textStatus, errorThrown ) {
						showAjaxError(
							$form,
							'handlePriceAwarePaymentAmountChange',
							jqXHR,
							textStatus,
							errorThrown
						);
					},
				} );
			}

			const buttonCaptionConfig = {
				priceId: $selectedProductElement.data( 'wpfs-amount-price-id' ),
				...readAmountConfig( $form, $selectedProductElement ),
				buttonTitle:
					$selectedProductElement.data( 'wpfs-button-title' ),
			};
			addFeeRecoveryConfig(
				buttonCaptionConfig,
				$selectedProductElement
			);
			showPaymentButtonCaption( $form, buttonCaptionConfig );

			const paymentDetailsConfig = {
				priceId: $selectedProductElement.data( 'wpfs-amount-price-id' ),
				...readAmountConfig( $form, $selectedProductElement ),
			};
			addFeeRecoveryConfig(
				paymentDetailsConfig,
				$selectedProductElement
			);
			if ( isFeeRecoveryChecked( $form ) ) {
				setFeeRecoveryItem( $form, $selectedProductElement, paymentDetailsConfig );
			} else {
				clearRecoveryItem( paymentDetailsConfig );
			}
			showPaymentDetails( $form, paymentDetailsConfig );
			syncStripeElementsAmount( $form );
		}

		/**
		 * Elements was created in deferred mode with a placeholder amount
		 * (the custom amount is unknown at init). Sync the selected/typed
		 * amount so wallet payment sheets (Revolut Pay, Apple Pay, Google
		 * Pay) show the real amount instead of the placeholder (issue #410).
		 *
		 * @param {object} $form
		 */
		function syncStripeElementsAmount( $form ) {
			const stripeElements = WPFS.getStripeElements(
				extractFormNameFromNode( $form )
			);
			if (
				stripeElements &&
				'payment' === $form.data( 'wpfs-intent-type' )
			) {
				const amountData = findPaymentAmountData( $form );
				const chargeAmount =
					amountData.valid && amountData.amount > 0
						? getChargeAmount( $form, amountData.amount )
						: 0;
				// Stripe.js rejects an amount of 0. A fully discounted payment is submitted
				// without Stripe, so Elements keeps its last amount.
				if ( chargeAmount > 0 ) {
					stripeElements.update( {
						amount: chargeAmount,
						currency: amountData.currency.toLowerCase(),
					} );
				}
			} else if ( stripeElements && isFirstChargeForm( $form ) ) {
				const isPriced = isFirstChargePriced( $form );
				const firstCharge = isPriced
					? getFirstChargeData( $form )
					: null;
				if ( isPriced && null === firstCharge ) {
					// The page hasn't read the plan's prices yet.
					return;
				}
				// A plan whose first charge the page can't price switches to setup mode, without an amount.
				const update = firstCharge
					? { mode: 'subscription', ...firstCharge }
					: { mode: 'setup' };
				try {
					stripeElements.update( update );
					$form.data( 'wpfs-elements-mode', update.mode );
				} catch ( error ) {
					// A wallet sheet without the amount is better than a form that can't be paid.
					logError( 'syncStripeElementsAmount', null, null, error );
				}
			}
		}

		/**
		 * Whether the form confirms a SetupIntent and the server then charges the first invoice of a
		 * subscription: inline subscriptions and recurring donations.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True for those forms.
		 */
		function isFirstChargeForm( $form ) {
			const formTypes = [
				FORM_TYPE_INLINE_SUBSCRIPTION,
				FORM_TYPE_INLINE_DONATION,
			];

			return (
				'setup' === $form.data( 'wpfs-intent-type' ) &&
				formTypes.includes( $form.data( FROM_TYPE_DOM ) )
			);
		}

		/**
		 * Whether the page can price the first charge of the selected plan or donation. It can't for a
		 * plan with a billing anchor day, or on a page cached without data-wpfs-first-charge.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True when it can.
		 */
		function isFirstChargePriced( $form ) {
			if ( FORM_TYPE_INLINE_DONATION === $form.data( FROM_TYPE_DOM ) ) {
				return true;
			}

			const $plan = findSelectedPlanNode( $form );
			const firstCharge = $plan
				? $plan.attr( 'data-wpfs-first-charge' )
				: null;

			return (
				FIRST_CHARGE_PLAN === firstCharge ||
				FIRST_CHARGE_SETUP_FEE === firstCharge
			);
		}

		/**
		 * What a subscription or a recurring donation charges at once, which wallet payment sheets
		 * (Apple Pay, Google Pay) show for Elements in subscription mode.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {?{amount: number, currency: string}} In the smallest currency unit, or null when
		 * the form can't price it.
		 */
		function getFirstChargeData( $form ) {
			if ( FORM_TYPE_INLINE_DONATION === $form.data( FROM_TYPE_DOM ) ) {
				// The donation, with the recovery fee Pay shows.
				const amountData = findPaymentAmountData( $form );
				if ( ! amountData.valid ) {
					return null;
				}

				return {
					amount:
						amountData.amount > 0
							? getChargeAmount( $form, amountData.amount )
							: 0,
					currency: amountData.currency.toLowerCase(),
				};
			}

			if ( ! isFirstChargePriced( $form ) ) {
				return null;
			}
			const $plan = findSelectedPlanNode( $form );
			const lines = WPFS.getPaymentDetailsForPrice(
				extractFormNameFromNode( $form ),
				$plan.data( 'wpfs-value' )
			);
			if ( ! lines ) {
				return null;
			}
			const firstCharge = $plan.attr( 'data-wpfs-first-charge' );
			const currency = String(
				$plan.data( 'wpfs-currency' )
			).toLowerCase();

			// The server previews a trial's first invoice on its own when the preview of the plan
			// spreads the coupon differently.
			const trialFirstCharge = lines.find(
				( line ) =>
					PRICE_LINE_ITEM_TYPE_TRIAL_FIRST_CHARGE === line.type
			);
			if ( FIRST_CHARGE_SETUP_FEE === firstCharge && trialFirstCharge ) {
				return { amount: trialFirstCharge.amount, currency };
			}

			// The plan with its setup fee and recovery fee, or only the setup fee during a trial.
			const amount = lines.reduce( ( sum, line ) => {
				const charged =
					( FIRST_CHARGE_PLAN === firstCharge &&
						line !== trialFirstCharge ) ||
					PRICE_LINE_ITEM_TYPE_SETUP_FEE === line.type;
				const inclusiveTax =
					PRICE_LINE_ITEM_SUBTYPE_TAX === line.subType &&
					line.inclusive;

				return charged && ! inclusiveTax ? sum + line.amount : sum;
			}, 0 );

			return { amount, currency };
		}

		function handlePaymentAmountChange( $form, $selectedProductElement ) {
			if ( isDonationForm( $form ) ) {
				handleDonationPaymentAmountChange(
					$form,
					$selectedProductElement
				);
			} else {
				handlePriceAwarePaymentAmountChange(
					$form,
					$selectedProductElement
				);
			}
		}

		function getSelectedProductName( $form ) {
			const priceId = findPriceIdForSelectedProduct( $form );
			const pricingData = WPFS.getPaymentDetails(
				extractFormNameFromNode( $form )
			);

			if ( pricingData ) {
				if ( pricingData.hasOwnProperty( priceId ) ) {
					const filteredItems = pricingData[ priceId ].filter(
						( item ) => item.type === PRICE_LINE_ITEM_TYPE_PRODUCT
					);
					if ( filteredItems.length > 0 ) {
						return filteredItems[ 0 ].displayName;
					}
				}
			}

			return l10n.products.the_selected_product_label;
		}

		function handlePlanChange( $form ) {
			if ( debugLog ) {
				console.log( 'handlePlanChange(): CALLED' );
			}
			if (
				$( '.wpfs-subscription-plan-select', $form ) &&
				$( '.wpfs-subscription-plan-select', $form ).length > 0
			) {
				handlePlanSelectChange( $form );
			} else if (
				$( '.wpfs-subscription-plan-radio', $form ) &&
				$( '.wpfs-subscription-plan-radio', $form ).length > 0
			) {
				handlePlanRadioButtonChange( $form );
			} else if (
				$( '.wpfs-subscription-plan-hidden', $form ) &&
				$( '.wpfs-subscription-plan-hidden', $form ).length > 0
			) {
				handlePlanHiddenChange( $form );
			} else {
				logWarn(
					'handlePlanChange()',
					'Subscription Plan Selector UI element not found!'
				);
			}
		}

		function handlePlanSelectChange( $form ) {
			if ( debugLog ) {
				console.log( 'handlePlanSelectChange(): CALLED' );
			}
			const $subscriptionPlanSelectElement = $(
				'.wpfs-subscription-plan-select',
				$form
			);
			const selectedPlanId = $subscriptionPlanSelectElement.val();
			const $selectedPlanElement = $(
				'option[value="' + selectedPlanId + '"]',
				$form
			);
			handleSubscriptionPlanChange( $form, $selectedPlanElement );
		}

		function handlePlanRadioButtonChange( $form ) {
			if ( debugLog ) {
				console.log( 'handlePlanRadioButtonChange(): CALLED' );
			}
			const $selectedPlanElement = $(
				'.wpfs-subscription-plan-radio:checked',
				$form
			);
			if ( debugLog ) {
				console.log(
					'handlePlanRadioButtonChange(): selectedPlanElement=' +
						JSON.stringify( $selectedPlanElement )
				);
			}
			handleSubscriptionPlanChange( $form, $selectedPlanElement );
		}

		function handlePlanHiddenChange( $form ) {
			if ( debugLog ) {
				console.log( 'handlePlanHiddenChange(): CALLED' );
			}
			const $selectedPlanElement = $(
				'.wpfs-subscription-plan-hidden',
				$form
			);
			if ( debugLog ) {
				console.log(
					'handlePlanHiddenChange(): selectedPlanElement=' +
						JSON.stringify( $selectedPlanElement )
				);
			}
			handleSubscriptionPlanChange( $form, $selectedPlanElement );
		}

		function refreshCouponField( $form ) {
			const coupon = WPFS.getCoupon( extractFormNameFromNode( $form ) );

			if ( coupon ) {
				hideCouponToRedeemRow( $form );
				if ( isSelectedProductDiscounted( $form ) ) {
					showRedeemedCouponRow( $form, coupon.name );
					hideWrongRedeemedCouponRow( $form );
				} else {
					hideRedeemedCouponRow( $form );
					showWrongRedeemedCouponRow(
						$form,
						coupon.name,
						getSelectedProductName( $form )
					);
				}
			} else {
				showCouponToRedeemRow( $form );
			}
		}

		/**
		 * The fee is charged in the selected plan's currency, and plans of one form may have different
		 * currencies, so the opt-in shows the fee the plan carries.
		 *
		 * @param {Object} $planForm
		 * @param {Object} $selectedPlanElement
		 */
		function showFeeRecoveryLabel( $planForm, $selectedPlanElement ) {
			const feeRecoveryLabel = $selectedPlanElement.attr(
				'data-wpfs-fee-recovery-label'
			);
			if ( undefined === feeRecoveryLabel ) {
				return;
			}

			const feeRecoveryId = $(
				'input[name="wpfs-fee-recovery-accepted"]',
				$planForm
			).attr( 'id' );
			// The attribute decodes to the HTML the server renders the opt-in message with.
			$( 'label[for="' + feeRecoveryId + '"]', $planForm ).html(
				feeRecoveryLabel
			);
		}

		function handleSubscriptionPlanChange( $form, $selectedPlanElement ) {
			if ( debugLog ) {
				console.log( 'handleSubscriptionPlanChange(): CALLED' );
			}

			if ( isCouponFieldVisible( $form ) ) {
				refreshCouponField( $form );
			}

			const paymentDetailsConfig = {
				priceId: $selectedPlanElement.data( 'wpfs-value' ),
				...readAmountConfig( $form, $selectedPlanElement ),
				interval: $selectedPlanElement.attr( 'data-wpfs-interval' ),
				intervalCount: parseInt(
					$selectedPlanElement.attr( 'data-wpfs-interval-count' )
				),
				cancellationCount: parseInt(
					$selectedPlanElement.attr( 'data-wpfs-cancellation-count' )
				),
			};
			addFeeRecoveryConfig( paymentDetailsConfig, $selectedPlanElement );

			showFeeRecoveryLabel( $form, $selectedPlanElement );

			if ( isFeeRecoveryChecked( $form ) ) {
				setFeeRecoveryItem( $form, $selectedPlanElement, paymentDetailsConfig );
			} else {
				clearRecoveryItem( paymentDetailsConfig );
			}

			showSubscriptionDetails( $form, paymentDetailsConfig );
			syncStripeElementsAmount( $form );
		}

		function showCouponToRedeemRow( $form ) {
			$( '.wpfs-coupon-to-redeem-row', $form ).show();
		}

		function hideCouponToRedeemRow( $form ) {
			$( '.wpfs-coupon-to-redeem-row', $form ).hide();
		}

		function showWrongRedeemedCouponRow(
			$form,
			couponName,
			productOrServiceName
		) {
			const wrongRedeemedCouponLabelPattern = $(
				'.wpfs-coupon-wrong-redeemed-label',
				$form
			).data( 'wpfs-coupon-wrong-redeemed-label' );
			const wrongRedeemedCouponLabel = sprintf(
				wrongRedeemedCouponLabelPattern,
				couponName,
				productOrServiceName
			);
			if ( debugLog ) {
				console.log(
					'showWrongRedeemedCouponRow(): redeemCouponLabelPattern=' +
						wrongRedeemedCouponLabelPattern +
						', couponName=' +
						couponName +
						', productOrServiceName=' +
						productOrServiceName +
						', redeemCouponLabel=' +
						wrongRedeemedCouponLabel
				);
			}
			$( '.wpfs-coupon-wrong-redeemed-label', $form ).html(
				wrongRedeemedCouponLabel
			);
			$( '.wpfs-coupon-wrong-redeemed-row', $form ).show();
		}

		function hideWrongRedeemedCouponRow( $form ) {
			$( '.wpfs-coupon-wrong-redeemed-label', $form ).html( '' );
			$( '.wpfs-coupon-wrong-redeemed-row', $form ).hide();
		}

		function showRedeemedCouponRow( $form, couponName ) {
			const redeemedCouponLabelPattern = $(
				'.wpfs-coupon-redeemed-label',
				$form
			).data( 'wpfs-coupon-redeemed-label' );
			const redeemedCouponLabel = sprintf(
				redeemedCouponLabelPattern,
				couponName
			);
			if ( debugLog ) {
				console.log(
					'showRedeemedCouponRow(): redeemCouponLabelPattern=' +
						redeemedCouponLabelPattern +
						', couponName=' +
						couponName +
						', redeemCouponLabel=' +
						redeemedCouponLabel
				);
			}
			$( '.wpfs-coupon-redeemed-label', $form ).html(
				redeemedCouponLabel
			);
			$( '.wpfs-coupon-redeemed-row', $form ).show();
		}

		function hideRedeemedCouponRow( $form ) {
			$( '.wpfs-coupon-redeemed-label', $form ).html( '' );
			$( '.wpfs-coupon-redeemed-row', $form ).hide();
		}

		function showRedeemLoadingAnimation( $form ) {
			const $redeemLink = $( '.wpfs-coupon-redeem-link', $form );
			$redeemLink.blur();
			if ( $redeemLink.hasClass( 'wpfs-input-group-link--loader' ) ) {
				return;
			}
			$redeemLink.addClass( 'wpfs-input-group-link--loader' );
		}

		function hideRedeemLoadingAnimation( $form ) {
			const $redeemLink = $( '.wpfs-coupon-redeem-link', $form );
			$redeemLink.blur();
			$redeemLink.removeClass( 'wpfs-input-group-link--loader' );
		}

		/**
		 * Parses the customer's custom amount, and shows its format error when it is malformed.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {string|undefined}        value              The custom amount field's value.
		 * @param {boolean}                 zeroDecimalSupport
		 * @return {?number} The amount in the smallest currency unit, NaN when it is empty, or
		 * null when it is malformed.
		 */
		function readCustomAmount( $form, value, zeroDecimalSupport ) {
			const formatter = createCurrencyFormatter( $form );
			const isWellFormed = formatter.validCustomAmount(
				value,
				zeroDecimalSupport
			);
			const fieldId = $(
				'input[name="wpfs-custom-amount-unique"]',
				$form
			).attr( 'id' );
			clearFieldError( $form, 'wpfs-custom-amount-unique', fieldId );
			if ( ! isWellFormed ) {
				showFieldError(
					$form,
					'wpfs-custom-amount-unique',
					fieldId,
					getCustomAmountFormatErrorMessage(
						$form,
						zeroDecimalSupport
					),
					true
				);
				return null;
			}
			return parseCurrencyAmount(
				formatter.parse( value ),
				zeroDecimalSupport,
				true
			);
		}

		function findPaymentAmountData( $form ) {
			const result = {};
			let currency;
			let amount;
			const amountType = $form.data( 'wpfs-amount-type' );
			const amountSelectorStyle = $form.data( 'wpfs-selector-style' );
			let zeroDecimalSupport;
			const returnSmallestCommonCurrencyUnit = true;
			if ( debugLog ) {
				console.log(
					'findPaymentAmountData(): ' +
						'amountType=' +
						amountType +
						', ' +
						'amountSelectorStyle=' +
						amountSelectorStyle
				);
			}
			if ( PAYMENT_TYPE_SPECIFIED_AMOUNT === amountType ) {
				result.paymentType = PAYMENT_TYPE_SPECIFIED_AMOUNT;
				result.customAmount = false;
				currency = $form.data( 'wpfs-currency' );
				amount = parseInt( $form.data( 'wpfs-amount' ) );
			} else if ( PAYMENT_TYPE_LIST_OF_AMOUNTS === amountType ) {
				result.paymentType = PAYMENT_TYPE_LIST_OF_AMOUNTS;
				const $listOfAmounts = findListOfAmountsElement( $form );
				currency = $listOfAmounts.data( 'wpfs-currency' );
				zeroDecimalSupport =
					true === $listOfAmounts.data( 'wpfs-zero-decimal-support' );
				const allowCustomAmountValue =
					1 == $form.data( 'wpfs-allow-list-of-amounts-custom' );
				const $selectedAmount =
					findSelectedAmountFromListOfAmounts( $form );
				if ( $selectedAmount && $selectedAmount.length > 0 ) {
					amount = $selectedAmount.val();
				}
				if ( debugLog ) {
					console.log(
						'findPaymentAmountData(): ' +
							'zeroDecimalSupport=' +
							zeroDecimalSupport +
							', ' +
							'allowCustomAmountValue=' +
							allowCustomAmountValue +
							', ' +
							'amount=' +
							amount
					);
				}
				if ( allowCustomAmountValue && AMOUNT_OTHER == amount ) {
					result.customAmount = true;
					amount = readCustomAmount(
						$form,
						$(
							'input[name="wpfs-custom-amount-unique"]',
							$form
						).val(),
						zeroDecimalSupport
					);
					if ( amount === null ) {
						result.invalidFormat = true;
					}
					if ( debugLog ) {
						console.log(
							'findPaymentAmountData(): ' +
								'custom amount=' +
								amount
						);
					}
				} else {
					result.customAmount = false;
					amount = parseCurrencyAmount(
						$selectedAmount.val(),
						zeroDecimalSupport,
						returnSmallestCommonCurrencyUnit
					);
				}
			} else if ( PAYMENT_TYPE_CUSTOM_AMOUNT == amountType ) {
				result.customAmount = true;
				const customAmountElement = findCustomAmountElement( $form );
				currency = customAmountElement.data( 'wpfs-currency' );
				zeroDecimalSupport =
					true ===
					customAmountElement.data( 'wpfs-zero-decimal-support' );
				amount = readCustomAmount(
					$form,
					customAmountElement.val(),
					zeroDecimalSupport
				);
				if ( amount === null ) {
					result.invalidFormat = true;
				}
			}
			// The currency comes from the form/amount element markup, so expose
			// it even while the amount is empty or invalid — the Payment Element
			// is initialized before the customer types a valid amount, and
			// falling back to 'usd' on non-USD forms makes confirmPayment fail
			// with a currency mismatch (issue #410).
			if ( currency ) {
				result.currency = currency;
			}
			if ( amount === null || isNaN( amount ) || amount < 0 ) {
				result.valid = false;
			} else {
				result.valid = true;
				result.amount = amount;
			}

			if ( debugLog ) {
				console.log(
					'findPaymentAmountData(): ' +
						'result=' +
						JSON.stringify( result )
				);
			}

			return result;
		}

		function findSelectedOneTimeProductNode( $form ) {
			let $selectedProduct;

			const amountSelectorStyle = $form.data( 'wpfs-selector-style' );
			if ( SELECTOR_STYLE_RADIO_BUTTONS === amountSelectorStyle ) {
				$selectedProduct = $form.find(
					'input[name="wpfs-custom-amount"]:checked'
				);
			} else if ( SELECTOR_STYLE_BUTTON_GROUP === amountSelectorStyle ) {
				$selectedProduct = $form.find(
					'input[name="wpfs-custom-amount"]:checked'
				);
			} else if ( SELECTOR_STYLE_DROPDOWN === amountSelectorStyle ) {
				$selectedProduct = $form.find(
					'select[name="wpfs-custom-amount"] option:selected'
				);
			} else {
				$selectedProduct = $form.find(
					'input[name="wpfs-custom-amount-unique"]'
				);
				if ( $selectedProduct.length === 0 ) {
					$selectedProduct = $form.find(
						'input[name="wpfs-custom-amount"]'
					);
				}
				if ( $selectedProduct.length === 0 ) {
					logWarn(
						'findSelectedOneTimeProductNode(): Cannot find product node'
					);
				}
			}

			return $selectedProduct;
		}

		function findSelectedPlanNode( $form ) {
			let $selectedPlan;

			if ( 1 == $form.data( 'wpfs-simple-button-layout' ) ) {
				$selectedPlan = $( 'input[name="wpfs-plan"]', $form );
			} else if (
				$( '.wpfs-subscription-plan-select', $form ) &&
				$( '.wpfs-subscription-plan-select', $form ).length > 0
			) {
				$selectedPlan = $(
					'select[name="wpfs-plan"] option:selected',
					$form
				);
			} else if (
				$( '.wpfs-subscription-plan-radio', $form ) &&
				$( '.wpfs-subscription-plan-radio', $form ).length > 0
			) {
				$selectedPlan = $(
					'.wpfs-subscription-plan-radio:checked',
					$form
				);
			} else if (
				$( '.wpfs-subscription-plan-hidden', $form ) &&
				$( '.wpfs-subscription-plan-hidden', $form ).length > 0
			) {
				$selectedPlan = $( '.wpfs-subscription-plan-hidden', $form );
			} else {
				logError(
					'findSelectedPlanNode',
					null,
					'WARNING: Subscription Plan Selector UI element not found!'
				);
			}

			return $selectedPlan;
		}

		function findPriceIdForSelectedOneTimeProduct( $form ) {
			let result = null;

			const $selectedProduct = findSelectedOneTimeProductNode( $form );
			if ( $selectedProduct && $selectedProduct.length === 1 ) {
				result = $selectedProduct.data( 'wpfs-amount-price-id' );
			}

			return result;
		}

		function findPriceIdForSelectedPlan( $form ) {
			let result = null;

			const $selectedPlan = findSelectedPlanNode( $form );
			if ( $selectedPlan && $selectedPlan.length === 1 ) {
				result = $selectedPlan.data( 'wpfs-value' );
			}

			return result;
		}

		function findPriceIdForSelectedProduct( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );

			if (
				formType === FORM_TYPE_INLINE_PAYMENT ||
				formType === FORM_TYPE_CHECKOUT_PAYMENT
			) {
				return findPriceIdForSelectedOneTimeProduct( $form );
			} else if (
				formType === FORM_TYPE_INLINE_SUBSCRIPTION ||
				formType === FORM_TYPE_CHECKOUT_SUBSCRIPTION
			) {
				return findPriceIdForSelectedPlan( $form );
			}

			throw new Error(
				'findPriceIdForSelectedProduct() - Unknown form type: ' +
					formType
			);
		}

		function findPlanAmountData( $form ) {
			const result = {};
			let planAmount,
				setupFee,
				currency,
				currencySymbol,
				vatPercent,
				vatAmount,
				discountAmount;

			const $selectedPlan = findSelectedPlanNode( $form );
			if ( $selectedPlan && $selectedPlan.length == 1 ) {
				planAmount = $selectedPlan.data(
					'wpfs-plan-amount-in-smallest-common-currency'
				);
				setupFee = $selectedPlan.data(
					'wpfs-plan-setup-fee-in-smallest-common-currency'
				);
				currency = $selectedPlan.data( 'wpfs-currency' );
				currencySymbol = $selectedPlan.data( 'wpfs-currency-symbol' );
				vatPercent = 0;
			}
			if (
				planAmount === null ||
				isNaN( planAmount ) ||
				planAmount < 0
			) {
				result.valid = false;
			} else {
				const formId = $form.data( 'wpfs-form-id' );
				const coupon = WPFS.getCoupon( formId );
				const couponResult = applyCoupon(
					currency,
					planAmount + setupFee,
					coupon
				);
				discountAmount = couponResult.discount;
				const subTotal = planAmount + setupFee - discountAmount;
				vatAmount = calculateVATAmount( subTotal, vatPercent );
				result.valid = true;
				result.amount = subTotal + vatAmount;
				result.currency = currency;
				result.currencySymbol = currencySymbol;
			}
			return result;
		}

		function convertNonZeroDecimalAmount( $form, amount, paymentDetailsConfig ) {
			if ( ! paymentDetailsConfig.zeroDecimalSupport ) {
				if (
					PRICE_ID_CUSTOM_AMOUNT !== paymentDetailsConfig.priceId ||
					( ! isNoTaxForm( $form ) || ! isNoCouponAppliedForm( $form ) )
				) {
					amount = amount / 100;
				}
			}

			return amount;
		}

		function calculateRecoveryFee(amount, paymentDetailsConfig) {
			
			// Convert main amount to minor units (cents)
			const amountInCents = convertAmountToMinorUnits(amount, paymentDetailsConfig);
			let percentageFee = paymentDetailsConfig.feePercentage;
			let fixedFeeDecimal = paymentDetailsConfig.feeAmount;

			// Normalize fixed fee
			if (typeof fixedFeeDecimal === 'string') {
				fixedFeeDecimal = parseFloat(fixedFeeDecimal);
				fixedFeeDecimal = Number.isNaN(fixedFeeDecimal) ? 0.0 : fixedFeeDecimal;
			}

			const fixedFeeInCents = convertAmountToMinorUnits(fixedFeeDecimal, paymentDetailsConfig);

			// Normalize percentage fee
			if (typeof percentageFee === 'string') {
				percentageFee = parseFloat(percentageFee);
				percentageFee = Number.isNaN(percentageFee) ? 0.0 : percentageFee;
			}

			// Prevent division by zero
			percentageFee = Math.min(99.99, percentageFee);

			// Calculate original amount required to cover fees
			const originalAmountInCents =
				(amountInCents + fixedFeeInCents) / (1 - percentageFee / 100);

			return Math.round(originalAmountInCents - amountInCents);
		}


		function convertAmountToMinorUnits(amount, paymentDetailsConfig) {
			// Zero-decimal currencies (e.g., JPY)
			if (paymentDetailsConfig.zeroDecimalSupport) {
				return Math.round(parseFloat(amount));
			}

			// Two-decimal currencies (e.g., USD, EUR)
			return Math.round(parseFloat(amount) * 100);
		}

		/**
		 * The amount Stripe charges: the selected price, discounted and taxed, plus the recovery fee
		 * when it applies. Wallet payment sheets show the amount Elements holds, while Stripe charges
		 * the PaymentIntent amount, which the server sets.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {number}                  amount The selected amount, in the smallest currency unit.
		 * @return {number} In the smallest currency unit.
		 */
		function getChargeAmount( $form, amount ) {
			const amounts = getPaymentPriceAmounts( $form ) || {
				gross: amount,
				net: amount,
			};

			return amounts.gross + getRecoveryFee( $form, amounts.net );
		}

		/**
		 * The amounts the server charges for the selected price of a payment form, from the lines
		 * its preview priced, as Customer::computeInlinePaymentChargeAmount() works them out: the
		 * gross amount, discounted and taxed, the net amount, discounted and without tax, and the
		 * price, before its discount and any tax added to it.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {?{gross: number, net: number, price: number}} In the smallest currency unit, or
		 * null when the server hasn't priced the selected amount.
		 */
		function getPaymentPriceAmounts( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );
			if (
				FORM_TYPE_INLINE_PAYMENT !== formType &&
				FORM_TYPE_CHECKOUT_PAYMENT !== formType
			) {
				return null;
			}

			const priceId = findPriceIdForSelectedProduct( $form );
			const lines = WPFS.getPaymentDetailsForPrice(
				extractFormNameFromNode( $form ),
				priceId
			);
			const productLine = ( lines || [] ).find(
				( line ) => PRICE_LINE_ITEM_TYPE_PRODUCT === line.subType
			);
			if ( ! productLine ) {
				return null;
			}
			// A custom amount is priced when the field is left, and only by the server on forms
			// with tax or a coupon. Other lines hold another amount, or the amount in major units.
			if (
				PRICE_ID_CUSTOM_AMOUNT === priceId &&
				productLine.amount !== parseCustomAmountUnique( $form, true )
			) {
				return null;
			}

			const amounts = {
				gross: productLine.amount,
				net: productLine.amount,
				price: productLine.amount,
			};
			lines.forEach( ( line ) => {
				if ( PRICE_LINE_ITEM_SUBTYPE_DISCOUNT === line.subType ) {
					amounts.gross += line.amount;
					amounts.net += line.amount;
				} else if ( PRICE_LINE_ITEM_SUBTYPE_TAX === line.subType ) {
					if ( line.inclusive ) {
						amounts.net -= line.amount;
					} else {
						amounts.gross += line.amount;
					}
				}
			} );

			return amounts;
		}

		/**
		 * The recovery fee on an amount, when it applies. A fully discounted payment is free, with no fee.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {number}                  amount In the smallest currency unit.
		 * @return {number} In the smallest currency unit.
		 */
		function getRecoveryFee( $form, amount ) {
			const $feeRecoveryElement = $( '[data-wpfs-fee-recovery-enabled="1"]', $form ).first();
			if (
				amount <= 0 ||
				0 === $feeRecoveryElement.length ||
				! isFeeRecoveryChecked( $form )
			) {
				return 0;
			}

			const feeRecoveryConfig = {
				feePercentage: $feeRecoveryElement.data( 'wpfs-fee-percentage' ),
				feeAmount: $feeRecoveryElement.data( 'wpfs-fee-amount' ),
				zeroDecimalSupport: true === $feeRecoveryElement.data( 'wpfs-zero-decimal-support' ),
			};
			const majorAmount = feeRecoveryConfig.zeroDecimalSupport ? amount : amount / 100;

			return calculateRecoveryFee( majorAmount, feeRecoveryConfig );
		}

		/**
		 * The recovery fee the server adds to the priced amounts of a payment form. An inline
		 * payment takes it on the net amount, and Stripe Checkout on the price.
		 *
		 * @param {JQuery<HTMLFormElement>}                     $form
		 * @param {{gross: number, net: number, price: number}} amounts From getPaymentPriceAmounts().
		 * @return {number} In the smallest currency unit.
		 */
		function getPricedRecoveryFee( $form, amounts ) {
			if ( FORM_TYPE_CHECKOUT_PAYMENT !== $form.data( FROM_TYPE_DOM ) ) {
				return getRecoveryFee( $form, amounts.net );
			}

			const previewedFee = findPreviewedRecoveryFee(
				$form,
				findPriceIdForSelectedProduct( $form )
			);
			if ( previewedFee ) {
				return isFeeRecoveryChecked( $form ) ? previewedFee.amount : 0;
			}

			return getRecoveryFee( $form, amounts.price );
		}

		/**
		 * Whether the server previews the recovery fee as a line of its own, as the charge adds it on
		 * subscriptions and Stripe Checkout payments, so a coupon discounts it and Stripe Tax taxes it.
		 *
		 * @param {JQuery<HTMLFormElement>} $form
		 * @return {boolean} True on forms whose price preview lists the fee.
		 */
		function isRecoveryFeePreviewed( $form ) {
			return [
				FORM_TYPE_CHECKOUT_PAYMENT,
				FORM_TYPE_INLINE_SUBSCRIPTION,
				FORM_TYPE_CHECKOUT_SUBSCRIPTION,
			].includes( $form.data( FROM_TYPE_DOM ) );
		}

		/**
		 * @param {JQuery<HTMLFormElement>} $form
		 * @param {string}                  priceId
		 * @return {?{amount: number, displayName: string}} The fee line the server previewed for the
		 * price, after its discount and with its tax, or null when the preview has none.
		 */
		function findPreviewedRecoveryFee( $form, priceId ) {
			const lines = WPFS.getPaymentDetailsForPrice(
				extractFormNameFromNode( $form ),
				priceId
			);

			return (
				( lines || [] ).find(
					( line ) =>
						PAYMENT_DETAIL_FEE_RECOVERY === line.type &&
						line.previewed
				) || null
			);
		}

		function isFeeRecoveryChecked( $form ) {
			const $feeRecoveryAccepted = $( 'input[name="wpfs-fee-recovery-accepted"]', $form );
			// Forms that always apply the fee render a hidden input instead of the opt-in checkbox.
			return $feeRecoveryAccepted.attr( 'type' ) === 'hidden' || $feeRecoveryAccepted.prop( 'checked' );
		}

		function setFeeRecoveryItem( $form, $selectedElement, paymentDetailsConfig ) {
			let amount = 0;
			const formType = $form.data( FROM_TYPE_DOM );
			const allowCustomAmountValue = 1 === $form.data( 'wpfs-allow-list-of-amounts-custom' );
			const $selectedAmount = findSelectedAmountFromListOfAmounts( $form );
			const amountType = $form.data( 'wpfs-amount-type' );
			let selected_amount = '';
			let is_custom_amount = false;
			if ( $selectedAmount && $selectedAmount.length > 0 ) {
				selected_amount = $selectedAmount.val();
			}

			if ( PAYMENT_TYPE_CUSTOM_AMOUNT == amountType ) {
				amount = parseCustomAmountUnique( $form );
				is_custom_amount = true;
			} else if ( allowCustomAmountValue && AMOUNT_OTHER == selected_amount ) {
				amount = parseCustomAmountUnique( $form );
				is_custom_amount = true;
			} else if (
				FORM_TYPE_INLINE_SUBSCRIPTION === formType ||
				FORM_TYPE_CHECKOUT_SUBSCRIPTION === formType
			) {
				// The subscription bills the fee on the plan's price for the quantity, before tax and discount.
				const productLine = (
					WPFS.getPaymentDetailsForPrice(
						paymentDetailsConfig.formId,
						paymentDetailsConfig.priceId
					) || []
				).find(
					( line ) =>
						PRICE_LINE_ITEM_TYPE_PRODUCT === line.type &&
						PRICE_LINE_ITEM_TYPE_PRODUCT === line.subType
				);
				amount = productLine
					? productLine.amount
					: $selectedElement.data(
							'wpfs-plan-amount-in-smallest-common-currency'
					  );
			} else if (
				FORM_TYPE_INLINE_PAYMENT === formType ||
				FORM_TYPE_CHECKOUT_PAYMENT === formType ||
				FORM_TYPE_INLINE_DONATION === formType ||
				FORM_TYPE_CHECKOUT_DONATION === formType
			) {
				amount = $selectedElement.data('wpfs-amount-in-smallest-common-currency');
			}

			const priceAmounts = getPaymentPriceAmounts( $form );
			// An empty or malformed custom amount, or a fully discounted payment, has no fee to recover.
			if (
				( is_custom_amount && amount === null ) ||
				( priceAmounts && priceAmounts.net <= 0 )
			) {
				clearRecoveryItem( paymentDetailsConfig );
				return;
			}

			const previewedFee = findPreviewedRecoveryFee(
				$form,
				paymentDetailsConfig.priceId
			);
			if ( previewedFee ) {
				previewedFee.displayName =
					l10n.product_pricing.fee_recovery_label;
				return;
			}
			// Each refresh adds the fee again, so drop the one it added before.
			clearRecoveryItem( paymentDetailsConfig );

			if ( ! is_custom_amount && ! paymentDetailsConfig.zeroDecimalSupport ) {
				amount = amount / 100;
			}
			const feeRecoveryAmount = priceAmounts
				? getPricedRecoveryFee( $form, priceAmounts )
				: calculateRecoveryFee( amount, paymentDetailsConfig );
			const paymentDetail = {
				'type': PAYMENT_DETAIL_FEE_RECOVERY,
				'subType': PAYMENT_DETAIL_FEE_RECOVERY,
				'id': paymentDetailsConfig.priceId,
				'displayName': l10n.product_pricing.fee_recovery_label,
				'amount': feeRecoveryAmount,
			}
			const paymentDetails = WPFS.getPaymentDetails( paymentDetailsConfig.formId );

			let priceIdPaymentDetails = paymentDetails?.[paymentDetailsConfig.priceId];
			priceIdPaymentDetails.push( paymentDetail );

			WPFS.setPaymentDetailsForPrice(
				paymentDetailsConfig.formId,
				paymentDetailsConfig.priceId,
				priceIdPaymentDetails
			);
		}

		function clearRecoveryItem( paymentDetailsConfig ) {
			const paymentDetails = WPFS.getPaymentDetails( paymentDetailsConfig.formId );
			if ( paymentDetails === null ) {
				return;
			}

			let priceIdPaymentDetails = paymentDetails?.[ paymentDetailsConfig.priceId ];
			if ( ! priceIdPaymentDetails ) {
				return;
			}
			priceIdPaymentDetails = priceIdPaymentDetails.filter(
				(item) =>
					item.type !== PAYMENT_DETAIL_FEE_RECOVERY &&
					item.subType !== PAYMENT_DETAIL_FEE_RECOVERY
			);

			WPFS.setPaymentDetailsForPrice(
				paymentDetailsConfig.formId,
				paymentDetailsConfig.priceId,
				priceIdPaymentDetails
			);
		}

		var WPFS = {};
		WPFS.couponMap = {};
		WPFS.paymentDetailsMap = {};
		WPFS.stripeElementsMap = {};
		WPFS.setStripeElements = function ( formId, elements ) {
			WPFS.stripeElementsMap[ formId ] = elements;
		};
		WPFS.getStripeElements = function ( formId ) {
			if ( WPFS.stripeElementsMap.hasOwnProperty( formId ) ) {
				return WPFS.stripeElementsMap[ formId ];
			}
			return null;
		};
		/**
		 * @param formId
		 * @param coupon
		 */
		WPFS.setCoupon = function ( formId, coupon ) {
			WPFS.couponMap[ formId ] = coupon;
		};
		/**
		 * @param  formId
		 * @return {*}
		 */
		WPFS.getCoupon = function ( formId ) {
			if ( WPFS.couponMap.hasOwnProperty( formId ) ) {
				return WPFS.couponMap[ formId ];
			}
			return null;
		};
		/**
		 * @param formId
		 */
		WPFS.removeCoupon = function ( formId ) {
			if ( WPFS.couponMap.hasOwnProperty( formId ) ) {
				delete WPFS.couponMap[ formId ];
			}
		};

		/**
		 * @param formId
		 * @param paymentDetails
		 */
		WPFS.setPaymentDetails = function ( formId, paymentDetails ) {
			WPFS.paymentDetailsMap[ formId ] = paymentDetails;
		};
		/**
		 * @param  formId
		 * @return {*}
		 */
		WPFS.getPaymentDetails = function ( formId ) {
			if ( WPFS.paymentDetailsMap.hasOwnProperty( formId ) ) {
				return WPFS.paymentDetailsMap[ formId ];
			}
			return null;
		};
		/**
		 * @param  formId
		 * @param  priceId
		 * @return {*}
		 */
		WPFS.getPaymentDetailsForPrice = function ( formId, priceId ) {
			const priceMap = WPFS.getPaymentDetails( formId );

			if ( priceMap !== null ) {
				if ( priceMap.hasOwnProperty( priceId ) ) {
					return priceMap[ priceId ];
				}
				return null;
			}
			return null;
		};
		/**
		 * @param formId
		 * @param priceId
		 * @param priceData
		 */
		WPFS.setPaymentDetailsForPrice = function (
			formId,
			priceId,
			priceData
		) {
			let priceMap = WPFS.getPaymentDetails( formId );
			if ( priceMap === null ) {
				priceMap = {};
			}

			priceMap[ priceId ] = priceData;
			WPFS.setPaymentDetails( formId, priceMap );
		};
		/**
		 * @param formId
		 */
		WPFS.removePaymentDetails = function ( formId ) {
			if ( WPFS.paymentDetailsMap.hasOwnProperty( formId ) ) {
				delete WPFS.paymentDetailsMap[ formId ];
			}
		};

		WPFS.initCustomAmount = function () {
			$( 'input.wpfs-custom-amount' ).change( function ( e ) {
				const $form = getParentForm( this );
				handleCustomAmountChange( $( this ), $form );
				handleAmountChange( $form );
			} );
			$( '.wpfs-custom-amount--unique' ).blur( function () {
				const $form = getParentForm( this );

				if ( isPossiblePricingRecalculationForm( $form ) ) {
					if (
						isNoTaxForm( $form ) &&
						isNoCouponAppliedForm( $form )
					) {
						refreshOtherPricingLocally( $form );
					} else {
						refreshPricingFromServer( $form );
					}
				} else {
					refreshPaymentDetails( $form );
				}
			} );
		};

		WPFS.initStripeJSCard = function () {
			const $cards = $(
				'form[data-wpfs-form-type] [data-toggle="card"]'
			);

			WPFS.initStripeJSCardElement( $cards );
		};
		
		WPFS.initStripeJSCardElement = function ( $cards ) {
			if ( $cards.length === 0 ) {
				return;
			}

			$cards.each( async function ( index ) {
				if ( stripe != null ) {
					var $form = getParentForm( this );
					const formId = $form.data( 'wpfs-form-id' );
					var { elements, cardElement } = await WPFS.initStripeCardElement( $form );
					WPFS.setStripeElements( extractFormNameFromNode( $form ), elements );
					// Elements was created before WPFS.ready() read the prices, with their tax and discount.
					syncStripeElementsAmount( $form );
					cardElement.mount(
						'div[data-wpfs-form-id="' + formId + '"]'
					);
				} else {
					showErrorGlobalMessage(
						$form,
						l10n.stripe_errors
							.internal_error_title,
						l10n.stripe_errors.stripe_js_not_loaded
					);
				}

				$($form).on('change', 'input[name="wpfs-donation-frequency"]', async function() {
					if ( $(this).is(':checked') ) {
						cardElement.unmount();
						cardElement.destroy();
						const formId = $form.data( 'wpfs-form-id' );
						
						const stripeCardElements = await WPFS.initStripeCardElement( $form );
						elements = stripeCardElements.elements;
						cardElement = stripeCardElements.cardElement;
						WPFS.setStripeElements( extractFormNameFromNode( $form ), elements );

						cardElement.mount(
							'div[data-wpfs-form-id="' + formId + '"]'
						);
					}
				});
	
				// handle form submission
				$form.submit( async function ( event ) {
					event.preventDefault();
					/*
						disable submit button and show loading animation,
						clear message panel, reset token and amount index
					*/
					disableFormButtons( $form );
					showLoadingAnimation( $form );
					clearFieldErrors( $form );
					clearGlobalMessage( $form );
					removeHiddenFormFields( $form );
	
					// Add get parameters to the form
					setPageParametersField( $form );
	
					// add amount index
					addCustomAmountIndexInput( $form );
	
					if ( isTermsOfUseUnaccepted( $form ) ) {
						const errorMessage = $form.data(
							'wpfs-terms-of-use-not-checked-error-message'
						);
						showFieldError(
							$form,
							'wpfs-terms-of-use-accepted',
							null,
							errorMessage
						);
						scrollToElement(
							$(
								'input[name=wpfs-terms-of-use-accepted]',
								$form
							),
							false
						);
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
						return false;
					}

					if ( showCustomAmountFormatError( $form ) ) {
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
						return false;
					}

					// handle free inline submission.
					if ( isFreeInlineSubmission( $form ) ) {
						showProcessingOverlay( $form );
						appendFreeTransactionInput( $form );
						submitPaymentData( $form, cardElement );
						return false;
					}

					const paymentMethodData = {};
	
					// capture cardholder email
					const cardHolderEmail = $(
						'input[name="wpfs-card-holder-email"]',
						$form
					);
					if ( cardHolderEmail.val() ) {
						paymentMethodData.billing_details = {
							email: cardHolderEmail.val(),
						};
					}
	
					// capture cardholder name
					const cardHolderNameField = $(
						'input[name="wpfs-card-holder-name"]',
						$form
					);
					if ( cardHolderNameField.val() ) {
						if ( ! paymentMethodData.billing_details ) {
							paymentMethodData.billing_details = {};
						}
						paymentMethodData.billing_details.name =
							cardHolderNameField.val();
					}
	
					// capture billing address if country is set
					const billingAddressCountryField = $(
						'select[name="wpfs-billing-address-country"] option:selected',
						$form
					);
					if ( billingAddressCountryField.val() ) {
						if ( ! paymentMethodData.billing_details ) {
							paymentMethodData.billing_details = {};
						}
						paymentMethodData.billing_details.address = {
							country: billingAddressCountryField.val(),
						};
						// get city if available
						const billingAddressCityField = $(
							'input[name="wpfs-billing-address-city"]',
							$form
						);
						if ( billingAddressCityField.val() ) {
							paymentMethodData.billing_details.address.city =
								billingAddressCityField.val();
						}
						// get line 1
						const billingAddressLine1Field = $(
							'input[name="wpfs-billing-address-line-1"]',
							$form
						);
						if ( billingAddressLine1Field.val() ) {
							paymentMethodData.billing_details.address.line1 =
								billingAddressLine1Field.val();
						}
						// get line 2
						const billingAddressLine2Field = $(
							'input[name="wpfs-billing-address-line-2"]',
							$form
						);
						if ( billingAddressLine2Field.val() ) {
							paymentMethodData.billing_details.address.line2 =
								billingAddressLine2Field.val();
						}
						// get postal code
						const billingAddressPostalCodeField = $(
							'input[name="wpfs-billing-address-zip"]',
							$form
						);
						if ( billingAddressPostalCodeField.val() ) {
							paymentMethodData.billing_details.address.postal_code =
								billingAddressPostalCodeField.val();
						}
						// get state
						const billingAddressStateField = $(
							'select[name="wpfs-billing-address-state-select"] option:selected',
							$form
						);
						if ( billingAddressStateField.val() ) {
							paymentMethodData.billing_details.address.state =
								billingAddressStateField.val();
						}
					}
	
					if ( stripe != null ) {
						const intentType = $form.data( 'wpfs-intent-type' );
						const returnUrl = new URL( window.location.href );
						// A retry after an earlier redirect must not carry its
						// Stripe return parameters into the new return URL (#674).
						STRIPE_RETURN_URL_PARAMS.forEach( ( param ) =>
							returnUrl.searchParams.delete( param )
						);
						if ( $form.data( 'wpfs-form-id' ) ) {
							returnUrl.searchParams.set(
								'wpfs-form-id',
								$form.data( 'wpfs-form-id' )
							);
						}
						const return_url = returnUrl.toString();

						if ( intentType === 'payment' ) {
							// need to save a draft transaction locally before confirming the payment
							const data = wpfsParseSerializedForm(
								createFormSerializer( $form ).serialize()
							);
							const isOneTimeDonation =
								data.hasOwnProperty(
									'wpfs-donation-frequency'
								) &&
								data[ 'wpfs-donation-frequency' ] ===
									'one-time';
							data.action = isOneTimeDonation
								? 'wpfs-save-one-time-donation'
								: 'wpfs-save-draft-transaction';
							// custom fields are not serialized correctly by default
							wpfsApplyCustomInputs( data, $form );
							data[ 'wpfs-intent-type' ] = intentType;

							const freshClientSecret =
								await WPFS.submitStripeElement(
									elements,
									$form
								);
							if ( ! freshClientSecret ) return;

							// Preserve the solved nonce to avoid re-verifying token. The server
							// doesn't check the captcha on a one-time donation's save.
							if ( ! isOneTimeDonation ) {
								const wpfsNonce = $form
									.find( 'input[name="wpfs-nonce"]' )
									.val();
								if ( wpfsNonce ) {
									data[ 'wpfs-nonce' ] = wpfsNonce;
								}
							}

							data[ 'wpfs-stripe-client-secret' ] =
								freshClientSecret;
							data[ 'wpfs-stripe-payment-intent-id' ] =
								freshClientSecret.substr(
									0,
									freshClientSecret.indexOf( '_secret_' )
								);
							postAjax( data, {
								success: wpfsPlainCallback(
									async function ( responseData ) {
										if ( responseData.success !== true ) {
											showResponseErrors(
												$form,
												responseData
											);
											enableFormButtons( $form );
											hideLoadingAnimation( $form );
											return;
										}
										showProcessingOverlay( $form );
										if ( ! isOneTimeDonation ) {
											// confirmPayment will redirect to return_url on success
											await WPFS.confirmStripeIntent(
												$form,
												'payment',
												{
													elements,
													clientSecret:
														freshClientSecret,
													confirmParams: {
														return_url: return_url,
														payment_method_data:
															paymentMethodData,
													},
												}
											);
											return;
										}

										const confirmResult =
											await WPFS.confirmStripeIntent(
												$form,
												'payment',
												{
													elements,
													clientSecret:
														freshClientSecret,
													confirmParams: {
														// return_url is only used by Stripe if a redirect is actually required; with allow_redirects:'never' and redirect:'if_required' we should stay on-page and continue here.
														return_url: return_url,
														payment_method_data:
															paymentMethodData,
													},
													redirect: 'if_required',
												}
											);
										if ( ! confirmResult ) return;

										clearFieldErrors( $form );
										if ( debugLog ) {
											console.log(
												'form.submit(): PaymentMethod creation result=' +
													JSON.stringify(
														confirmResult
													)
											);
										}
										const inputAction = $(
											'input[name="action"]',
											$form
										);
										if (
											'wp_full_stripe_inline_donation_charge' ===
											inputAction.val()
										) {
											inputAction.val(
												'wp_full_stripe_onetime_donation_charge'
											);
										}
										$( '<input>' )
											.attr( {
												type: 'hidden',
												name: 'wpfs-stripe-payment-intent-id',
												value: confirmResult
													.paymentIntent.id,
											} )
											.appendTo( $form );
										$( '<input>' )
											.attr( {
												type: 'hidden',
												name: 'wpfs-stripe-payment-method-id',
												value: confirmResult
													.paymentIntent
													.payment_method,
											} )
											.appendTo( $form );
										removePaymentIntentClientSecretInput(
											$form
										);
										addPaymentIntentClientSecretInput(
											$form,
											freshClientSecret
										);
										submitPaymentData( $form, cardElement );
									}
								),
								error( jqXHR, textStatus, errorThrown ) {
									if ( isOneTimeDonation ) {
										logError(
											'submitSaveOneTimeDonation',
											jqXHR,
											textStatus,
											errorThrown
										);
										showErrorGlobalMessage(
											$form,
											l10n.stripe_errors.internal_error
										);
									} else {
										showAjaxError(
											$form,
											'submitDraftTransaction',
											jqXHR,
											textStatus,
											errorThrown
										);
									}
									enableFormButtons( $form );
									hideLoadingAnimation( $form );
								},
							} );
						} else if ( intentType === 'setup' ) {
							const freshClientSecret = await WPFS.submitStripeElement( elements, $form );
							if ( ! freshClientSecret ) return;

							showProcessingOverlay( $form );
							const confirmResult = await WPFS.confirmStripeIntent(
									$form,
									'setup',
									{
										elements,
										clientSecret: freshClientSecret,
										confirmParams: {
											return_url: return_url,
											payment_method_data: paymentMethodData
										},
										redirect: 'if_required'
									}
								);
							if ( ! confirmResult ) return;

							if ( debugLog ) {
								console.log( 'form.submit(): PaymentMethod creation result=' + JSON.stringify( confirmResult ) );
							}
							clearFieldErrors( $form );
							addPaymentMethodIdInput( $form, confirmResult );
							submitPaymentData( $form, cardElement );
						}
					} else {
						showErrorGlobalMessage(
							$form,
							l10n.stripe_errors
								.internal_error_title,
							l10n.stripe_errors.stripe_js_not_loaded
						);
					}
	
					return false;
				} );
			} );
		};

		WPFS.initStripeCardElement = async function ( $form ) {
			const elementsLocale = $form.data(
				'wpfs-preferred-language'
			);
			// Add get parameters to the form
			setPageParametersField( $form );

			// add amount index
			addCustomAmountIndexInput( $form );

			let appearance = {
				theme: $form.data( 'wpfs-elements-theme' ),
			};

			if ( $form.data( 'wpfs-elements-font' ) ) {
				appearance = {
					...appearance,
					variables: {
						fontFamily: $form.data( 'wpfs-elements-font' ),
					},
				};
			}

			const formType = $form.data( 'wpfs-form-type' );
			let intentType = ( FORM_TYPE_INLINE_DONATION === formType || FORM_TYPE_INLINE_SUBSCRIPTION === formType || FORM_TYPE_INLINE_SAVE_CARD === formType ) ? 'setup' : 'payment';

			const paymentMethodTypesRaw = $form.data( 'wpfs-payment-method-types' );
			const paymentMethodTypes = paymentMethodTypesRaw
				? ( Array.isArray( paymentMethodTypesRaw ) ? paymentMethodTypesRaw : JSON.parse( paymentMethodTypesRaw ) )
				: null;

			let options = {
				locale: elementsLocale,
				loader: 'always',
				appearance,
			};

			/**
			 * Determines whether the current form configuration supports zero-decimal currencies and sets the amount accordingly to prevent Stripe errors for unsupported amounts.
			 * @param {object} $form
			 * @returns {boolean}
			 */
			function resolveZeroDecimalSupport( $form ) {
				const amountType = $form.data( 'wpfs-amount-type' );
				if ( PAYMENT_TYPE_CUSTOM_AMOUNT === amountType ) {
					return true === findCustomAmountElement( $form ).data( 'wpfs-zero-decimal-support' );
				}
				if ( PAYMENT_TYPE_LIST_OF_AMOUNTS === amountType ) {
					const $listEl = findListOfAmountsElement( $form );
					return $listEl && $listEl.length > 0 ? true === $listEl.first().data( 'wpfs-zero-decimal-support' ) : false;
				}
				return true === $form.data( 'wpfs-zero-decimal-support' );
			}

			if (
				FORM_TYPE_INLINE_PAYMENT === formType ||
				$form.find( 'input[name="wpfs-donation-frequency"]:checked' ).val() === 'one-time' ||
				$form.find( 'input[type="hidden"][name="wpfs-donation-frequency"]' ).val() === 'one-time'
			) {
				const amountData = findPaymentAmountData( $form );
				const currency = amountData.currency || $form.data( 'wpfs-currency' ) || 'usd';
				intentType = 'payment';
				const zeroDecimalSupport = resolveZeroDecimalSupport( $form );
				const minimumAmount = zeroDecimalSupport ? 1 : 50;
				const chargeAmount =
					amountData.valid && amountData.amount > 0
						? getChargeAmount( $form, amountData.amount )
						: 0;
				options = {
					...options,
					mode: intentType,
					currency: currency.toLowerCase(),
					// Stripe.js rejects an amount of 0, e.g. of a fully discounted payment.
					amount: chargeAmount > 0 ? chargeAmount : minimumAmount,
				};
			} else if ( FORM_TYPE_INLINE_DONATION === formType ) {
				const amountData = findPaymentAmountData( $form );
				const currency = amountData.valid ? amountData.currency : ( $form.data( 'wpfs-currency' ) || 'usd' );
				const firstCharge = getFirstChargeData( $form );
				// Subscription mode gives wallet sheets the recurring donation's first charge, which
				// setup mode leaves at 0. The SetupIntent is still confirmed with confirmSetup().
				options = {
					...options,
					mode: 'subscription',
					currency: currency.toLowerCase(),
					amount: firstCharge ? firstCharge.amount : 0,
				};
			} else if ( FORM_TYPE_INLINE_SUBSCRIPTION === formType ) {
				const amountData = findPlanAmountData( $form );
				const currency = amountData.valid ? amountData.currency : ( $form.data( 'wpfs-currency' ) || 'usd' );
				options = {
					...options,
					mode: intentType,
					currency: currency.toLowerCase(),
				};
				// Subscription mode gives wallet sheets the first charge. A plan whose first charge the page
				// can't price keeps setup mode; syncStripeElementsAmount() switches the mode with the plan.
				if ( isFirstChargePriced( $form ) ) {
					const firstCharge = getFirstChargeData( $form );
					options.mode = 'subscription';
					options.amount = firstCharge ? firstCharge.amount : 0;
				}
			} else if ( FORM_TYPE_INLINE_SAVE_CARD === formType ) {
				options = {
					...options,
					mode: intentType,
					currency: ( $form.data( 'wpfs-currency' ) || 'usd' ).toLowerCase(),
				};
			}
			if ( intentType === 'payment' && FORM_TYPE_INLINE_PAYMENT === formType  ) {
				// If the form is a payment form, we need to specify the payment method types to use.
				// If the form has a data attribute for payment method types, we use that.
				// Otherwise, we default to card and link.
				options.paymentMethodTypes = paymentMethodTypes || [
					'card',
					'link',
				];
			} else {
				// The other forms follow the Stripe Dashboard methods but can't finish a
				// redirect, so hide the methods that need one (#674).
				options.excludedPaymentMethodTypes =
					REDIRECT_PAYMENT_METHOD_TYPES;
			}

			// Use the jQuery .data() setter (not .attr) so it matches the .data()
			// reads elsewhere — jQuery caches data-* on first read and ignores later
			// .attr() changes, which made re-inits (e.g. donation frequency toggle)
			// read a stale intent type.
			$form.data( 'wpfs-intent-type', intentType );
			// create Stripe Payments Element.
			let elements;
			try {
				elements = stripe.elements( options );
			} catch ( error ) {
				if ( 'subscription' !== options.mode ) {
					throw error;
				}
				// Stripe.js rejected the first charge, e.g. one below its minimum: set the card up
				// without it, as before.
				logError( 'initStripeJSCardElement', null, null, error );
				delete options.amount;
				options.mode = intentType;
				elements = stripe.elements( options );
			}
			$form.data( 'wpfs-elements-mode', options.mode );
			const paymentElementOptions = {
				fields: {
					billingDetails: {
						name: 'never', // never show the name field as it's handled by the FP form
						email: 'never', // never show the email field as it's handled by the FP form
					},
				},
			};
			// Stripe offers Link inside card payments too, so leaving it out of the
			// payment method list isn't enough to hide it.
			if ( 'never' === $form.data( 'wpfs-stripe-link' ) ) {
				paymentElementOptions.wallets = { link: 'never' };
			}
			const cardElement = elements.create(
				'payment',
				paymentElementOptions
			);
			return { elements, cardElement };
		}

		/**
		 * Handles errors from Stripe and surfaces them to the user, while also re-enabling the form and hiding any loading animations.
		 *
		 * @param {object} $form
		 * @param {object} error
		 */
		function abortWithStripeError( $form, error ) {
			enableFormButtons( $form );
			hideLoadingAnimation( $form );
			// The captcha token was spent getting the client secret, so the retry
			// needs a new one or it is rejected until the page reloads (#660).
			resetCaptcha( $form );
			// Stripe's errors carry a type, and a message for the customer such as a decline.
			const fromStripe = !! ( error && error.type && error.message );
			showErrorGlobalMessage(
				$form,
				fromStripe
					? l10n.stripe_errors.stripe_error_title
					: l10n.stripe_errors.internal_error_title,
				( error && error.message )
					? error.message
					: l10n.stripe_errors.internal_error
			);
			scrollToElement( $( '.wpfs-form-card', $form ), false );
		}

		/**
		 * Validates the Stripe payment element, then fetches a fresh client secret.
		 *
		 * @param {object} elements
		 * @param {object} $form
		 * @returns {string|null} The fresh client secret, or null on any failure.
		 */
		WPFS.submitStripeElement = async function ( elements, $form ) {
			const submitResult = await elements.submit();
			if ( submitResult.error ) {
				abortWithStripeError( $form, submitResult.error );
				return null;
			}

			let freshIntent;
			try {
				freshIntent = await getSetupIntentClientSecret( $form );
			} catch ( _error ) {
				// Error already displayed by getSetupIntentClientSecret
				return null;
			}

			if ( ! freshIntent || ! freshIntent.clientSecret ) {
				return null;
			}

			return freshIntent.clientSecret;
		};

		/**
		 * Validates the Stripe payment element, then confirms the intent with Stripe using the provided payment method data and client secret.
		 *
		 * @param {object} $form
		 * @param {string} intentType
		 * @param {object} options
		 * @returns {object|null} The Stripe result object, or null on any error.
		 */
		WPFS.confirmStripeIntent = async function (
			$form,
			intentType,
			options
		) {
			try {
				const confirmResult = intentType === 'setup'
					? await stripe.confirmSetup( options )
					: await stripe.confirmPayment( options );

				if ( confirmResult && confirmResult.error ) {
					abortWithStripeError( $form, confirmResult.error );
					return null;
				}

				return confirmResult;
			} catch ( error ) {
				if ( ! error || error.type !== 'validation_error' ) {
					abortWithStripeError( $form, error );
				} else {
					// A validation_error is surfaced inline by the Stripe Element, so we
					// skip the global error banner — but we must still re-enable the form
					// and dismiss the processing overlay, otherwise the user is locked
					// behind it with no escape but a page reload.
					enableFormButtons( $form );
					hideLoadingAnimation( $form );
				}
				return null;
			}
		};

		WPFS.initCoupon = function () {
			const COUPON_FIELD_NAME = 'wpfs-coupon';

			function getCouponNode( $form ) {
				return $( 'input[name="' + COUPON_FIELD_NAME + '"]', $form );
			}

			function emptyCouponField( $form ) {
				const formId = extractFormNameFromNode( $form );
				WPFS.removeCoupon( formId );

				const $coupon = getCouponNode( $form );
				if ( $coupon && $coupon.length > 0 ) {
					$coupon.val( '' );
				}
			}

			$( '.wpfs-coupon-remove-link' ).click( function ( event ) {
				event.preventDefault();
				const $form = getParentForm( this );

				emptyCouponField( $form );

				hideRedeemedCouponRow( $form );
				hideWrongRedeemedCouponRow( $form );
				showCouponToRedeemRow( $form );

				refreshPricingFromServer( $form );
			} );
			$( '.wpfs-coupon-add-another-link' ).click( function ( event ) {
				event.preventDefault();
				const $form = getParentForm( this );
				const $coupon = getCouponNode( $form );
				const couponFieldId = $coupon.attr( 'id' );

				emptyCouponField( $form );
				clearFieldError( $form, COUPON_FIELD_NAME, couponFieldId );

				hideRedeemedCouponRow( $form );
				hideWrongRedeemedCouponRow( $form );
				showCouponToRedeemRow( $form );

				refreshPricingFromServer( $form );
			} );
			$( '.wpfs-coupon-redeem-link' ).click( function ( event ) {
				event.preventDefault();
				const $form = getParentForm( this );
				const $coupon = getCouponNode( $form );

				if ( showCustomAmountFormatError( $form ) ) {
					return;
				}

				const taxData = gatherFormDataForTaxCalculation( $form );
				taxData.coupon = $coupon.val();
				// include payment intent id and its client secret if available
				const intentType = $form.data( 'wpfs-intent-type' );
				if ( intentType && intentType === 'payment' ) {
					taxData.stripePaymentIntentId = $form.data(
						'wpfs-stripe-payment-intent-id'
					);
					taxData.stripeClientSecret = $form.data(
						'wpfs-stripe-client-secret'
					);
				}

				disableFormButtons( $form );
				if ( $coupon && $coupon.length > 0 ) {
					clearGlobalMessage( $form );
					clearFieldErrors( $form );
					$coupon.prop( 'disabled', true );
					showRedeemLoadingAnimation( $form );

					const data = {
						action: 'wpfs-check-coupon',
						nonce: wpfsFormSettings.nonce,
						code: $coupon.val(),
						taxData,
					};
					postAjax( data, {
						success( couponRedeemData ) {
							if ( couponRedeemData.success ) {
								// The coupon check priced the form's current fields.
								setPricingStale( $form, false );
								WPFS.setCoupon(
									extractFormNameFromNode( $form ),
									couponRedeemData.coupon
								);

								setPaymentDetailsData(
									$form,
									couponRedeemData.productPricing
								);
								refreshProductPricing( $form );
								// Also gives Stripe Elements the discounted amount, for wallet payment sheets.
								refreshPaymentDetails( $form );
							} else if ( couponRedeemData.bindingResult ) {
								processValidationErrors(
									$form,
									couponRedeemData
								);
							} else {
								console.log(
									'Calling the coupon error field directly...'
								);
								const couponFieldId = $coupon.attr( 'id' );
								showFieldError(
									$form,
									COUPON_FIELD_NAME,
									couponFieldId,
									couponRedeemData.msg
								);
								scrollToElement( $coupon, false );
							}
						},
						error( jqXHR, textStatus, errorThrown ) {
							showAjaxError(
								$form,
								'coupon',
								jqXHR,
								textStatus,
								errorThrown
							);
						},
						complete() {
							$coupon.prop( 'disabled', false );
							hideRedeemLoadingAnimation( $form );
							// A submit that started meanwhile keeps its buttons disabled.
							if ( ! $form.is( '[data-wpfs-locked]' ) ) {
								enableFormButtons( $form );
							}
						},
					} );
				}
			} );
		};

		WPFS.initInputGroup = function () {
			const inputGroupPrependClass = '.wpfs-input-group-prepend';
			$( document ).on( 'click', inputGroupPrependClass, function ( e ) {
				const $target = $( e.target );
				if ( $target.hasClass( 'wpfs-input-group-link' ) ) {
					return;
				}

				if (
					$target.parents( '.wpfs-input-group-link' ) &&
					$target.parents( '.wpfs-input-group-link' ).length > 0
				) {
					return;
				}

				$( this ).next().focus();
			} );

			$( document ).on(
				'mouseenter',
				inputGroupPrependClass,
				function () {
					$( this ).next().mouseenter();
				}
			);

			$( document ).on(
				'mouseleave',
				inputGroupPrependClass,
				function () {
					$( this ).next().mouseleave();
				}
			);

			const inputGroupAppendClass = '.wpfs-input-group-append';
			$( document ).on( 'click', inputGroupAppendClass, function ( e ) {
				const $target = $( e.target );
				if ( $target.hasClass( 'wpfs-input-group-link' ) ) {
					return;
				}

				if (
					$target.parents( '.wpfs-input-group-link' ) &&
					$target.parents( '.wpfs-input-group-link' ).length > 0
				) {
					return;
				}

				$( this ).prev().focus();
			} );

			$( document ).on( 'mouseenter', inputGroupAppendClass, function () {
				$( this ).prev().mouseenter();
			} );

			$( document ).on( 'mouseleave', inputGroupAppendClass, function () {
				$( this ).prev().mouseleave();
			} );

		};

		WPFS.initSelectmenu = function () {
			wpfsInitSelectmenu( {
				onChange( event ) {
					// todo: Remove harcoded event handling, put it in event handlers
					const $selectElement = $( event.target );
					const $form = getParentForm( event.target );
					if ( $selectElement && $selectElement.length > 0 ) {
						const selectElementType =
							$selectElement.data( 'wpfs-select' );
						if (
							'wpfs-billing-address-country-select' ===
							selectElementType
						) {
							const $billingAddressCountrySelectElement = $(
								'.wpfs-billing-address-country-select',
								$form
							);
							handleBillingAddressCountryChange(
								$billingAddressCountrySelectElement,
								$form
							);
						} else if (
							'wpfs-billing-address-state-select' ===
							selectElementType
						) {
							const $billingAddressStateSelectElement = $(
								'.wpfs-billing-address-state-select',
								$form
							);
							handleBillingAddressStateChange(
								$billingAddressStateSelectElement,
								$form
							);
						} else if (
							'wpfs-shipping-address-country-select' ===
							selectElementType
						) {
							const $shippingAddressCountrySelectElement = $(
								'.wpfs-shipping-address-country-select',
								$form
							);
							handleShippingAddressCountryChange(
								$shippingAddressCountrySelectElement,
								$form
							);
						} else if (
							'wpfs-custom-amount-select' === selectElementType
						) {
							// Update the custom field first, as the radio handler
							// does, so the amount change sees whether it's enabled.
							const amountType = $form.data( 'wpfs-amount-type' );
							if ( 'list_of_amounts' === amountType ) {
								const $customAmountSelectElement = $(
									'.wpfs-custom-amount-select',
									$form
								);
								handleCustomAmountChange(
									$customAmountSelectElement,
									$form
								);
							}
							handleAmountChange( $form );
						} else if (
							'wpfs-subscription-plan-select' ===
							selectElementType
						) {
							handlePlanChange( $form );
						} else if (
							'wpfs-tax-country-select' === selectElementType
						) {
							//handleTaxCountryChange($form);
						} else if (
							'wpfs-tax-state-select' === selectElementType
						) {
							handleTaxStateChange( $form );
						}
					}
				},
			} );
		};

		WPFS.initAddressSwitcher = function () {
			$( '.wpfs-same-billing-and-shipping-address' ).change( function () {
				if ( debugLog ) {
					logInfo(
						'.wpfs-same-billing-and-shipping-address CHANGE',
						'CALLED'
					);
				}
				const addressSwitcherId = $( this ).data(
					'wpfs-address-switcher-id'
				);
				const billingAddressSwitchId = $( this ).data(
					'wpfs-billing-address-switch-id'
				);
				if ( $( this ).prop( 'checked' ) ) {
					$( '#' + addressSwitcherId ).hide();
					$( '#' + billingAddressSwitchId ).click();
				} else {
					$( '#' + addressSwitcherId ).show();
					$( '#' + billingAddressSwitchId ).click();
				}
				$( '.wpfs-billing-address-switch' ).change();
				$( '.wpfs-shipping-address-switch' ).change();
			} );
			$( '.wpfs-billing-address-switch' ).change( function () {
				if ( debugLog ) {
					logInfo( '.wpfs-billing-address-switch CHANGE', 'CALLED' );
				}
				const billingAddressPanelId = $( this ).data(
					'wpfs-billing-address-panel-id'
				);
				const shippingAddressPanelId = $( this ).data(
					'wpfs-shipping-address-panel-id'
				);
				if ( $( this ).prop( 'checked' ) ) {
					$( '#' + billingAddressPanelId ).show();
					$( '#' + shippingAddressPanelId ).hide();
				}
			} );
			$( '.wpfs-shipping-address-switch' ).change( function () {
				const billingAddressPanelId = $( this ).data(
					'wpfs-billing-address-panel-id'
				);
				const shippingAddressPanelId = $( this ).data(
					'wpfs-shipping-address-panel-id'
				);
				if ( $( this ).prop( 'checked' ) ) {
					$( '#' + billingAddressPanelId ).hide();
					$( '#' + shippingAddressPanelId ).show();
				}
			} );
		};

		WPFS.initTooltip = function () {
			$.widget.bridge( 'wpfstooltip', $.ui.tooltip );

			$( '[data-toggle="tooltip"]' )
				.wpfstooltip( {
					items: '[data-toggle="tooltip"]',
					content() {
						const contentId = $( this ).data( 'tooltip-content' );
						return $(
							'[data-tooltip-id="' + contentId + '"]'
						).html();
					},
					position: {
						my: 'left top+5',
						at: 'left bottom+5',
						using( position, feedback ) {
							const $this = $( this );
							$this.css( position );
							$this
								.addClass( feedback.vertical )
								.addClass( feedback.horizontal );
						},
					},
					classes: {
						'ui-tooltip': 'wpfs-ui wpfs-tooltip',
					},
					tooltipClass: 'wpfs-ui wpfs-tooltip',
				} )
				.on( {
					click( event ) {
						event.preventDefault();
						$( this ).tooltip( 'open' );
					},
				} );
		};

		WPFS.initStepper = wpfsInitStepper;

		WPFS.initDatepicker = function () {
			$( '[data-toggle="datepicker"]' ).each( function () {
				const $this = $( this );
				const dateFormat = $this.data( 'dateFormat' ) || 'dd/mm/yyyy';
				let defaultValue = $this.data( 'defaultValue' ) || '';

				if ( $this.val() === '' ) {
					$this.val( defaultValue );
				}

				$this
					.attr( 'placeholder', dateFormat )
					.inputmask( {
						alias: dateFormat,
						showMaskOnHover: false,
						showMaskOnFocus: false,
					} )
					.datepicker( {
						prevText: '',
						nextText: '',
						hideIfNoPrevNext: true,
						firstDay: 1,
						dateFormat: dateFormat.replace( /yy/g, 'y' ),
						showOtherMonths: true,
						selectOtherMonths: true,
						onChangeMonthYear( year, month, inst ) {
							if ( inst.dpDiv.hasClass( 'bottom' ) ) {
								setTimeout( function () {
									inst.dpDiv.css(
										'top',
										inst.input.offset().top -
											inst.dpDiv.outerHeight()
									);
								} );
							}
						},
						beforeShow( el, inst ) {
							const $el = $( el );
							inst.dpDiv.addClass(
								'wpfs-ui wpfs-datepicker-div'
							);
							setTimeout( function () {
								if (
									$el.offset().top > inst.dpDiv.offset().top
								) {
									inst.dpDiv.removeClass( 'top' );
									inst.dpDiv.addClass( 'bottom' );
								} else {
									inst.dpDiv.removeClass( 'bottom' );
									inst.dpDiv.addClass( 'top' );
								}
							} );
						},
					} )
					.on( 'blur', function () {
						const $this = $( this );
						setTimeout( function () {
							const date = $this.val();
							const isValid = Inputmask.isValid( date, {
								alias: dateFormat,
							} );
							if ( isValid ) {
								defaultValue = date;
							} else {
								$this.val( defaultValue );
							}
						}, 50 );
					} );
			} );
		};

		WPFS.initCombobox = function () {
			$.widget( 'custom.combobox', {
				_selectOptions: [],
				_lastValidValue: null,
				_create() {
					this.wrapper = $( '<div>' )
						.addClass( 'wpfs-input-group wpfs-combobox' )
						.addClass( this.element.attr( 'class' ) )
						.insertAfter( this.element );

					this._selectOptions = this.element
						.children( 'option' )
						.map( function () {
							const $this = $( this );
							const text = $this.text();
							const value = $this.val();
							if ( value && value !== '' ) {
								return {
									label: text,
									value: text,
									option: this,
								};
							}
						} );

					this.element.hide();
					this._createAutocomplete();
					this._createShowAllButton();
				},
				_createAutocomplete() {
					const selected = this.element.children( ':selected' );
					const value = selected.val() ? selected.text() : '';

					this.input = $( '<input>' )
						.attr(
							'placeholder',
							this.element.data( 'placeholder' )
						)
						.appendTo( this.wrapper )
						.val( value )
						.addClass( 'wpfs-input-group-form-control' )
						.autocomplete( {
							delay: 0,
							minLength: 0,
							source: $.proxy( this, '_source' ),
							position: {
								my: 'left-1px top+2px',
								at: 'left-1px bottom+2px',
								using( position, feedback ) {
									const $this = $( this );
									$this.css( position );
									$this.width( feedback.target.width + 46 );
								},
							},
							classes: {
								'ui-autocomplete': 'wpfs-ui wpfs-combobox-menu',
							},
							open() {
								$( this )
									.parent()
									.addClass( 'wpfs-combobox--open' );
							},
							close() {
								const $this = $( this );
								$this
									.parent()
									.removeClass( 'wpfs-combobox--open' );
								$this.blur();
							},
							search( e, ui ) {
								// Fix autocomplete combobox memory leak
								$( this ).data(
									'uiAutocomplete'
								).menu.bindings = $();
							},
						} )
						.on( 'focus', function () {
							$( this ).data( 'uiAutocomplete' ).search( '' );
						} )
						.on( 'keydown', function ( e ) {
							if ( e.keyCode === 13 ) {
								$( this ).blur();
							}
						} );

					this.input.data( 'uiAutocomplete' )._renderItem =
						this._renderItem;

					this._on( this.input, {
						autocompleteselect( e, ui ) {
							if ( ! ui.item.noResultsItem ) {
								ui.item.option.selected = true;
								this._trigger( 'select', e, {
									item: ui.item.option,
								} );
							}
							this._trigger( 'blur' );
						},
						autocompletechange: '_validateValue',
					} );
				},
				_createShowAllButton() {
					const input = this.input;
					let wasOpen = false;
					const html =
						'<div class="wpfs-input-group-append"><span class="wpfs-input-group-icon"><span class="wpfs-icon-arrow"></span></span></div>';

					$( html )
						.appendTo( this.wrapper )
						.on( 'mousedown', function () {
							wasOpen = input
								.autocomplete( 'widget' )
								.is( ':visible' );
						} )
						.on( 'click', function ( e ) {
							e.stopPropagation();
							if ( wasOpen ) {
								input.autocomplete( 'close' );
							} else {
								input.trigger( 'focus' );
							}
						} );
				},
				_source( request, response ) {
					const matcher = new RegExp(
						$.ui.autocomplete.escapeRegex( request.term ),
						'i'
					);
					const results = this._selectOptions.map(
						function ( i, option ) {
							if (
								option.value &&
								( ! request.term ||
									matcher.test( option.label ) )
							) {
								return option;
							}
						}
					);

					if ( results && results.length > 0 ) {
						response( results );
					} else {
						response( [
							{
								label: this.element.data( 'noResultsMessage' ),
								value: request.term,
								noResultsItem: true,
							},
						] );
					}
				},
				_validateValue( e, ui ) {
					// Selected an item, nothing to do
					if ( ui.item ) {
						return;
					}

					// Search for a match (case-insensitive)
					const value = this.input.val();
					const valueLowerCase = value.toLowerCase();
					let valid = false;
					let selectedText = null;
					this.element.children( 'option' ).each( function () {
						const text = $( this ).text();
						if ( text.toLowerCase() === valueLowerCase ) {
							selectedText = text;
							this.selected = valid = true;
							return false;
						}
					} );

					if ( valid ) {
						// Fix valid value
						this.input.val( selectedText );
						this._lastValidValue = selectedText;
					} else if ( ! valid && this._lastValidValue !== null ) {
						// Set last valid value
						this.input.val( this._lastValidValue );
					} else {
						// Remove invalid value
						this.input.val( '' );
						this.element.val( '' );
						this.input.autocomplete( 'instance' ).term = '';
					}
				},
				_renderItem( ul, item ) {
					let t = '';
					const idx = item.label
						.toLowerCase()
						.indexOf( this.term.toLowerCase() );
					const sameLabelAndTerm =
						item.label.toLowerCase() === this.term.toLowerCase();

					if (
						idx !== -1 &&
						! sameLabelAndTerm &&
						this.term !== ''
					) {
						const termLength = this.term.length;
						t += item.label.substring( 0, idx );
						t +=
							'<strong>' +
							item.label.substr( idx, termLength ) +
							'</strong>';
						t += item.label.substr( idx + termLength );
					} else {
						t = item.label;
					}

					const className = '';
					const $li = $( '<li></li>' );
					const $div = $(
						'<div class="ui-menu-item-wrapper">' + t + '</div>'
					);
					if ( ! item.noResultsItem ) {
						$li.data( 'item.autocomplete', item );
						if ( sameLabelAndTerm ) {
							$div.addClass( 'ui-state-selected' );
						}
					} else {
						$li.addClass( 'ui-state-disabled' );
					}

					ul.addClass( 'wpfs-ui' ).addClass( 'wpfs-combobox-menu' );

					return $li.append( $div ).appendTo( ul );
				},
				_destroy() {
					this.wrapper.remove();
					this.element.show();
				},
			} );

			$( '[data-toggle="combobox"]' ).combobox();
		};

		WPFS.initInlineForms = function () {
			const inlineFormSelector =
				'form[data-wpfs-form-type="' +
				FORM_TYPE_INLINE_PAYMENT +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_INLINE_SUBSCRIPTION +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_INLINE_SAVE_CARD +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_INLINE_DONATION +
				'"]';
			const $inlineForms = $( inlineFormSelector );
			$inlineForms.each( function ( index, inlineForm ) {
				const $form = $( inlineForm );

				if ( isDynamicTaxesForm( $form ) ) {
					handleTaxStateVisibility( $form );
				} else if ( isStripeTaxForm( $form ) ) {
					refreshTaxPostalCodeVisibility( $form );
				}

				if ( isTaxIdCollectible( $form ) ) {
					refreshTaxIdPlaceholder( $form );
				}
			} );
		};

		WPFS.initCheckoutForms = function () {
			if ( debugLog ) {
				logInfo( 'initCheckoutForms', 'CALLED' );
			}

			const popupFormsSelector =
				'form[data-wpfs-form-type="' +
				FORM_TYPE_CHECKOUT_PAYMENT +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_CHECKOUT_SUBSCRIPTION +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_CHECKOUT_SAVE_CARD +
				'"], form[data-wpfs-form-type="' +
				FORM_TYPE_CHECKOUT_DONATION +
				'"]';
			const $popupForms = $( popupFormsSelector );
			$popupForms.each( function ( index, popupForm ) {
				const $form = $( popupForm );

				if ( debugLog ) {
					logInfo(
						'initCheckoutForms',
						'form=' + $form.data( 'wpfs-form-id' )
					);
				}

				$form.submit( function ( event ) {
					if ( debugLog ) {
						logInfo(
							'checkoutFormSubmit',
							'CALLED, formId=' + $form.data( 'wpfs-form-id' )
						);
					}

					event.preventDefault();

					/*
                         tnagy disable submit button and show loading animation,
                         clear message panel, reset token and amount index
                         */
					disableFormButtons( $form );
					showLoadingAnimation( $form );
					clearFieldErrors( $form );
					clearGlobalMessage( $form );
					removeCustomAmountIndexInput( $form );

					// Add get parameters to the form
					setPageParametersField( $form );

					// tnagy add amount index
					addCustomAmountIndexInput( $form );

					// tnagy validate form
					let hasErrors = false;
					let $firstInvalidField = null;
					let fieldErrorMessage = null;

					// tnagy validate custom fields
					const customInputRequired = $form.data(
						'wpfs-custom-input-required'
					);
					if ( 1 == customInputRequired ) {
						const customInputFieldsWithMissingValue = [];
						let customInputValues = $(
							'input[name="wpfs-custom-input"]',
							$form
						);
						if ( customInputValues.length == 0 ) {
							customInputValues = $(
								'input[name="wpfs-custom-input[]"]',
								$form
							);
						}
						if ( customInputValues ) {
							customInputValues.each( function () {
								if ( $( this ).val().length == 0 ) {
									customInputFieldsWithMissingValue.push(
										this
									);
								}
							} );
						}
						if (
							customInputFieldsWithMissingValue &&
							customInputFieldsWithMissingValue.length > 0
						) {
							for ( const index in customInputFieldsWithMissingValue ) {
								const $customInputField = $(
									customInputFieldsWithMissingValue[ index ]
								);
								if (
									$customInputField &&
									$customInputField.length > 0
								) {
									if ( $firstInvalidField == null ) {
										$firstInvalidField = $customInputField;
									}
									const id = $customInputField.attr( 'id' );
									let name = $customInputField.attr( 'name' );
									if ( name ) {
										name = name.replace( /\[]/g, '' );
									}
									const label = $customInputField.data(
										'wpfs-custom-input-label'
									);
									fieldErrorMessage = sprintf(
										l10n.validation_errors
											.mandatory_field_is_empty,
										label
									);
									showFieldError(
										$form,
										name,
										id,
										fieldErrorMessage,
										false
									);
								}
							}
							hasErrors = true;
						}
					}

					// tnagy validate typed custom fields with a per-field required flag.
					// Client validation improves UX only; server validation is authoritative.
					$(
						'.wpfs-custom-field[data-wpfs-cf-id][data-wpfs-cf-required="1"]',
						$form
					).each( function () {
						const $field = $( this );
						const type = $field.data( 'wpfs-cf-type' );
						let empty = false;
						if ( type === 'checkbox' ) {
							empty = ! $field
								.find( 'input[type="checkbox"]' )
								.is( ':checked' );
						} else if ( type === 'multiselect' ) {
							empty =
								$field.find( 'input[type="checkbox"]:checked' )
									.length === 0;
						} else {
							const val = wpfsCustomFieldControl( $field ).val();
							empty = ! val || ( '' + val ).trim().length === 0;
						}
						if ( ! empty ) {
							return;
						}
						const $control = wpfsCustomFieldControl( $field );
						if ( $firstInvalidField == null ) {
							$firstInvalidField = $control.length
								? $control
								: $field;
						}
						const id = $control.attr( 'id' );
						const label = $field
							.find( '.wpfs-form-label, .wpfs-form-check-label' )
							.first()
							.text()
							.trim();
						fieldErrorMessage = sprintf(
							l10n.validation_errors
								.mandatory_field_is_empty,
							label
						);
						showFieldError(
							$form,
							'wpfs-custom-input',
							id,
							fieldErrorMessage,
							false
						);
						hasErrors = true;
					} );

					if ( isTermsOfUseUnaccepted( $form ) ) {
						if ( $firstInvalidField == null ) {
							$firstInvalidField = $(
								'input[name=wpfs-terms-of-use-accepted]',
								$form
							);
						}
						fieldErrorMessage = $form.data(
							'wpfs-terms-of-use-not-checked-error-message'
						);
						showFieldError(
							$form,
							'wpfs-terms-of-use-accepted',
							null,
							fieldErrorMessage,
							false
						);
						hasErrors = true;
					}

					// tnagy prevent submit on validation errors
					if ( hasErrors ) {
						if (
							$firstInvalidField &&
							$firstInvalidField.length > 0
						) {
							scrollToElement( $firstInvalidField, false );
						}
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
						return false;
					}

					// tnagy continue with valid data
					const formType = $form.data( 'wpfs-form-type' );
					let amountData;
					if (
						FORM_TYPE_CHECKOUT_PAYMENT === formType ||
						FORM_TYPE_CHECKOUT_DONATION === formType
					) {
						amountData = findPaymentAmountData( $form );
					} else if ( FORM_TYPE_CHECKOUT_SUBSCRIPTION === formType ) {
						amountData = findPlanAmountData( $form );
					} else if ( FORM_TYPE_CHECKOUT_SAVE_CARD === formType ) {
						amountData = {
							paymentType: PAYMENT_TYPE_CARD_CAPTURE,
							customAmount: false,
							currency: 'usd',
							amount: 0,
							valid: true,
						};
					}

					if ( debugLog ) {
						logInfo(
							'initCheckoutForms',
							'amountData=' + JSON.stringify( amountData )
						);
					}

					if ( false == amountData.valid ) {
						// A malformed custom amount already shows the expected format.
						if ( amountData.customAmount && ! amountData.invalidFormat ) {
							showFieldError(
								$form,
								'wpfs-custom-amount-unique',
								$(
									'input[name="wpfs-custom-amount-unique"]',
									$form
								).attr( 'id' ),
								l10n.validation_errors
									.custom_payment_amount_value_is_invalid,
								true
							);
						} else if ( ! amountData.customAmount ) {
							showErrorGlobalMessage(
								$form,
								l10n.validation_errors
									.invalid_payment_amount_title,
								l10n.validation_errors
									.invalid_payment_amount
							);
						}
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
						return false;
					}

					addCurrentURL( $form );

					if ( stripe != null ) {
						postAjax( serializeForm( $form ), {
							success( data ) {
								if ( debugLog ) {
									logInfo(
										'initCheckoutForms',
										'SUCCESS response=' +
											JSON.stringify( data )
									);
								}

								if ( data.success ) {
									if ( data.redirectUrl ) {
										location.href = data.redirectUrl;
									}
								} else {
									showResponseErrors( $form, data );
								}
							},
							error( jqXHR, textStatus, errorThrown ) {
								showAjaxError(
									$form,
									'initCheckoutForms',
									jqXHR,
									textStatus,
									errorThrown
								);
							},
							complete() {
								enableFormButtons( $form );
								hideLoadingAnimation( $form );
							},
						} );
					} else {
						// todo tnagy show error message
					}

					return false;
				} );
			} );
		};

		WPFS.initReCaptcha = function () {
			window.addEventListener(
				'load',
				function () {
					let reCAPTCHAWidgetId;
					let formHash;
					const formCAPTCHAs =
						document.getElementsByClassName( 'wpfs-form-captcha' );
					//noinspection JSUnresolvedVariable
					if (
						window.grecaptcha !== 'undefined' &&
						formCAPTCHAs !== null &&
						formCAPTCHAs.length > 0
					) {
						//noinspection JSUnresolvedVariable
						googleReCAPTCHA = window.grecaptcha;
						Array.prototype.forEach.call(
							formCAPTCHAs,
							function ( element ) {
								formHash = element.getAttribute(
									'data-wpfs-form-hash'
								);
								//noinspection JSUnresolvedVariable
								reCAPTCHAWidgetId = googleReCAPTCHA.render(
									element,
									{
										sitekey:
											wpfsFormSettings.googleReCaptchaSiteKey,
									}
								);
								reCAPTCHAWidgetIds[ formHash ] =
									reCAPTCHAWidgetId;
							}
						);
					}
				},
				true
			);
		};

		WPFS.initPlanRadioButton = function () {
			$( 'input.wpfs-subscription-plan-radio' ).change( function ( e ) {
				const $form = getParentForm( this );
				handlePlanChange( $form );
			} );
		};

		WPFS.initTaxFieldChanges = function () {
			$( 'select.wpfs-tax-country-select' ).on(
				'selectmenuchange',
				function ( e ) {
					const $form = getParentForm( this );
					handleTaxCountryChange( $form );
				}
			);

			$( 'select.wpfs-tax-id-type-select' ).on(
				'selectmenuchange',
				function ( e ) {
					const $form = getParentForm( this );
					handleTaxIdTypeChange( $form );
				}
			);

			$( 'input[name="wpfs-tax-zip"]' ).change( function ( e ) {
				const $form = getParentForm( this );

				refreshPricingFromServer( $form );
			} );

			$( 'input[name="wpfs-billing-address-zip"]' ).change(
				function ( e ) {
					const $form = getParentForm( this );

					if ( isStripeTaxForm( $form ) ) {
						refreshPricingFromServer( $form );
					}
				}
			);
		};

		WPFS.initBuyingAsBusiness = function () {
			$( 'input[name="wpfs-buying-as-business"]' ).change(
				function ( e ) {
					const $form = getParentForm( this );
					handleBuyingAsBusinessChange( $form );
				}
			);

			$( 'input[name="wpfs-tax-id"]' ).change( function ( e ) {
				const $form = getParentForm( this );

				if ( isDynamicTaxesForm( $form ) || isStripeTaxForm( $form ) ) {
					refreshPricingFromServer( $form );
				}
			} );
		};

		function handleBuyingAsBusinessChange( $form ) {
			if (
				$( 'input[name="wpfs-buying-as-business"]', $form ).prop(
					'checked'
				)
			) {
				$( '#wpfs-business-name-row', $form ).show();
				$( '#wpfs-tax-id-row', $form ).show();
			} else {
				$( '#wpfs-business-name-row', $form ).hide();
				$( '#wpfs-tax-id-row', $form ).hide();
				$( 'input[name="wpfs-tax-id"]', $form ).val( '' );

				if ( isDynamicTaxesForm( $form ) || isStripeTaxForm( $form ) ) {
					refreshPricingFromServer( $form );
				}
			}
		}

		function setTaxIdTypeByCountry( $form ) {
			const countryCode = getTaxCountryCode( $form );
			let taxIdType = null;

			if ( wpfsTaxIdData[ countryCode ] ) {
				const countryItem = wpfsTaxIdData[ countryCode ];
				taxIdType = Object.values( countryItem )[ 0 ].id;
			}

			$( 'select[name="wpfs-tax-id-type"]', $form ).val( taxIdType );
			$( 'select[name="wpfs-tax-id-type"]', $form ).wpfsSelectmenu(
				'refresh'
			);
		}

		function handleTaxCountryChange( $form ) {
			if ( isDynamicTaxesForm( $form ) ) {
				handleTaxStateVisibility( $form );
			} else if ( isStripeTaxForm( $form ) ) {
				refreshTaxPostalCodeVisibility( $form );
			}

			if ( isDynamicTaxesForm( $form ) || isStripeTaxForm( $form ) ) {
				refreshPricingFromServer( $form );
			}

			setTaxIdTypeByCountry( $form );
			if ( isTaxIdCollectible( $form ) ) {
				refreshTaxIdPlaceholder( $form );
			}
		}

		function handleTaxStateChange( $form ) {
			if ( isDynamicTaxesForm( $form ) ) {
				refreshPricingFromServer( $form );
			}
		}

		function getTaxCountryCode( $form ) {
			let result;

			if (
				$( 'select[name="wpfs-tax-country"] option:selected', $form ) &&
				$( 'select[name="wpfs-tax-country"] option:selected', $form )
					.length > 0
			) {
				result = $(
					'select[name="wpfs-tax-country"] option:selected',
					$form
				).val();
			} else if (
				$(
					'select[name="wpfs-billing-address-country"] option:selected',
					$form
				) &&
				$(
					'select[name="wpfs-billing-address-country"] option:selected',
					$form
				).length > 0
			) {
				result = $(
					'select[name="wpfs-billing-address-country"] option:selected',
					$form
				).val();
			} else {
				result = null;
			}

			return result;
		}

		function getPlaceholderForTaxIdTypeAndCountry(
			taxIdType,
			countryCode
		) {
			let result = '';

			if ( wpfsTaxIdData[ countryCode ] ) {
				const countryItem = wpfsTaxIdData[ countryCode ];

				if ( countryItem[ taxIdType ] ) {
					result = countryItem[ taxIdType ].example;
				}
			}

			return result;
		}

		function refreshTaxIdPlaceholder( $form ) {
			const countryCode = getTaxCountryCode( $form );
			const taxIdType = getTaxIdType( $form );

			$( 'input[name="wpfs-tax-id"]', $form ).attr(
				'placeholder',
				getPlaceholderForTaxIdTypeAndCountry( taxIdType, countryCode )
			);
		}

		function getCountryCodesRequiringPostalCodeForTax() {
			return Object.getOwnPropertyNames( POSTAL_CODE_PLACEHOLDERS );
		}

		function handleTaxStateVisibility( $form ) {
			const selectedCountry = $(
				'select[name="wpfs-tax-country"] option:selected',
				$form
			).val();

			if ( selectedCountry === COUNTRY_CODE_UNITED_STATES ) {
				$( '#wpfs-tax-state-row', $form ).show();
			} else {
				$( '#wpfs-tax-state-row', $form ).hide();
			}
		}

		function getPostalCodePlaceholderForCountry( countryCode ) {
			return POSTAL_CODE_PLACEHOLDERS[ countryCode ]
				? POSTAL_CODE_PLACEHOLDERS[ countryCode ]
				: '';
		}

		function refreshTaxPostalCodeVisibility( $form ) {
			const countryCode = getTaxCountryCode( $form );

			if (
				getCountryCodesRequiringPostalCodeForTax().includes(
					countryCode
				)
			) {
				$( '#wpfs-tax-postal-code-row', $form ).show();
				$( 'input[name="wpfs-tax-zip"]', $form ).attr(
					'placeholder',
					getPostalCodePlaceholderForCountry( countryCode )
				);
			} else {
				$( '#wpfs-tax-postal-code-row', $form ).hide();
				$( 'input[name="wpfs-tax-zip"]', $form ).val( '' );
			}
		}

		function handleTaxIdTypeChange( $form ) {
			refreshTaxIdPlaceholder( $form );
		}

		function handleQuantityChange( $form ) {
			refreshPricingFromServer( $form );
		}

		function extractFormNameFromNode( $form ) {
			return $form.data( 'wpfs-form-id' );
		}

		function extractAmountSelectorStyleFromNode( $form ) {
			return $form.data( 'wpfs-selector-style' );
		}

		function extractTaxRateTypeFromNode( $form ) {
			return $form.data( 'wpfs-tax-rate-type' );
		}

		function setPaymentDetailsData( $form, data ) {
			const formName = extractFormNameFromNode( $form );

			WPFS.setPaymentDetails( formName, data );
		}

		class WPFP_ProductPriceSetter {
			constructor( $form ) {
				this.$form = $form;
				this.formName = extractFormNameFromNode( $form );
			}

			calculateGrossPricing( priceId ) {
				return WPFS.getPaymentDetails( this.formName )
					[ priceId ].filter(
						( lineItem ) =>
							! (
								lineItem.subType ===
									PRICE_LINE_ITEM_SUBTYPE_TAX &&
								lineItem.inclusive
							) && lineItem.type !== PAYMENT_DETAIL_FEE_RECOVERY
					)
					.reduce( ( sum, lineItem ) => {
						return sum + lineItem.amount;
					}, 0 );
			}
			getProductId( $product ) {
				return $product.attr( 'id' );
			}

			getProductZeroDecimalSupport( $product ) {
				return $product.data( 'wpfs-zero-decimal-support' );
			}

			getProductCurrencyCode( $product ) {
				return $product.data( 'wpfs-currency' );
			}

			getProductCurrencySymbol( $product ) {
				return $product.data( 'wpfs-currency-symbol' );
			}

			formatPriceForInputValue( $product, priceInCents ) {
				const zeroDecimalSupport =
					this.getProductZeroDecimalSupport( $product );

				let amount = priceInCents;
				if ( ! zeroDecimalSupport ) {
					amount /= 100;
				}

				return number_format(
					amount,
					zeroDecimalSupport ? 0 : 2,
					'.',
					''
				);
			}

			setProductLabel( formatter, $product, priceInCents ) {
				const zeroDecimalSupport =
					this.getProductZeroDecimalSupport( $product );

				let amount = priceInCents;
				if ( ! zeroDecimalSupport ) {
					amount /= 100;
				}

				const formattedAmount = formatter.format(
					amount,
					this.getProductCurrencyCode( $product ),
					this.getProductCurrencySymbol( $product ),
					zeroDecimalSupport
				);

				this.formatProductLabel( $product, formattedAmount );
			}

			findProductInput( priceId ) {
				// Dummy implementation
				throw new Error(
					'WPFP_ProductPriceSetter.findProductInput() - Dont call directly, implement a subclass'
				);
			}

			getProductName( $product ) {
				return $product.data( 'wpfs-product-name' );
			}

			formatProductLabel( $product, formattedAmount ) {
				// Dummy implementation
				throw new Error(
					'WPFP_ProductPriceSetter.formatProductLabel() - Dont call directly, implement a subclass'
				);
			}

			refresh() {}
		}

		class WPFP_OneTimePriceSetter extends WPFP_ProductPriceSetter {
			constructor( $form ) {
				super( $form );
			}

			setProductPrice( $product, priceInCents ) {
				$product.data(
					'wpfs-amount-in-smallest-common-currency',
					priceInCents
				);
				$product.val(
					this.formatPriceForInputValue( $product, priceInCents )
				);
			}

			setPricesAndLabels( priceId ) {
				const $product = this.findProductInput( priceId );
				const grossPriceInCents = this.calculateGrossPricing( priceId );

				this.setProductPrice( $product, grossPriceInCents );
				this.setProductLabel(
					createCurrencyFormatter( this.$form ),
					$product,
					grossPriceInCents
				);
			}
		}

		class WPFP_PriceSetter_PaymentRadioList extends WPFP_OneTimePriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'input[data-wpfs-amount-price-id="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				const formattedLabel =
					this.getProductName( $product ) + ' -  ' + formattedAmount;
				$product.data( 'wpfs-amount-description', formattedLabel );
				$(
					'label[for="' + this.getProductId( $product ) + '"]',
					this.$form
				).text( formattedLabel );
			}
		}

		class WPFP_PriceSetter_PaymentDropdown extends WPFP_OneTimePriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'option[data-wpfs-amount-price-id="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				const formattedLabel =
					this.getProductName( $product ) + ' -  ' + formattedAmount;
				$product.data( 'wpfs-amount-description', formattedLabel );
				$product.text( formattedLabel );
			}

			refresh() {
				this.$form
					.find( '[data-toggle="selectmenu"]' )
					.wpfsSelectmenu( 'refresh' );
			}
		}

		class WPFP_PriceSetter_PaymentButtonGroup extends WPFP_OneTimePriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'input[data-wpfs-amount-price-id="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				const formattedLabel = formattedAmount;
				$product.data( 'wpfs-amount-description', formattedLabel );
				$(
					'label[for="' + this.getProductId( $product ) + '"]',
					this.$form
				).text( formattedLabel );
			}
		}

		class WPFP_PriceSetter_PaymentHidden extends WPFP_OneTimePriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'input[data-wpfs-amount-price-id="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				$product.data( 'wpfs-amount-description', formattedAmount );
			}
		}

		class WPFP_SubscriptionPriceSetter extends WPFP_ProductPriceSetter {
			constructor( $form ) {
				super( $form );
			}

			calculateGrossComponentPricing( priceId, component ) {
				return WPFS.getPaymentDetails( this.formName )
					[ priceId ].filter(
						( lineItem ) => lineItem.type === component
					)
					.filter(
						( lineItem ) =>
							! (
								lineItem.subType ===
									PRICE_LINE_ITEM_SUBTYPE_TAX &&
								lineItem.inclusive
							)
					)
					.reduce( ( sum, lineItem ) => {
						return sum + lineItem.amount;
					}, 0 );
			}

			setProductPrice(
				$product,
				setupFeePriceInCents,
				productPriceInCents
			) {
				$product.data(
					'wpfs-plan-setup-fee-in-smallest-common-currency',
					setupFeePriceInCents
				);
				$product.data(
					'wpfs-plan-amount-in-smallest-common-currency',
					productPriceInCents
				);
			}

			getPriceAndIntervalLabel(
				interval,
				intervalCount,
				formattedAmount
			) {
				let priceLabel;

				switch ( interval ) {
					case INTERVAL_DAY:
						priceLabel =
							intervalCount === 1
								? sprintf(
										/* translators: Daily recurring price label. %1$s: formatted price amount. */
										__( '%1$s / day', 'wp-full-stripe-free' ),
										formattedAmount
								  )
								: sprintf(
										/* translators: Recurring price label for prices charged every x days. %1$s: formatted price amount, %2$d: number of days between charges. */
										_n( '%1$s / %2$d day', '%1$s / %2$d days', intervalCount, 'wp-full-stripe-free' ),
										formattedAmount,
										intervalCount
								  );
						break;

					case INTERVAL_WEEK:
						priceLabel =
							intervalCount === 1
								? sprintf(
										/* translators: Weekly recurring price label. %1$s: formatted price amount. */
										__( '%1$s / week', 'wp-full-stripe-free' ),
										formattedAmount
								  )
								: sprintf(
										/* translators: Recurring price label for prices charged every x weeks. %1$s: formatted price amount, %2$d: number of weeks between charges. */
										_n( '%1$s / %2$d week', '%1$s / %2$d weeks', intervalCount, 'wp-full-stripe-free' ),
										formattedAmount,
										intervalCount
								  );
						break;

					case INTERVAL_MONTH:
						priceLabel =
							intervalCount === 1
								? sprintf(
										/* translators: Monthly recurring price label. %1$s: formatted price amount. */
										__( '%1$s / month', 'wp-full-stripe-free' ),
										formattedAmount
								  )
								: sprintf(
										/* translators: Recurring price label for prices charged every x months. %1$s: formatted price amount, %2$d: number of months between charges. */
										_n( '%1$s / %2$d month', '%1$s / %2$d months', intervalCount, 'wp-full-stripe-free' ),
										formattedAmount,
										intervalCount
								  );
						break;

					case INTERVAL_YEAR:
						priceLabel =
							intervalCount === 1
								? sprintf(
										/* translators: Annual recurring price label. %1$s: formatted price amount. */
										__( '%1$s / year', 'wp-full-stripe-free' ),
										formattedAmount
								  )
								: sprintf(
										/* translators: Recurring price label for prices charged every x years. %1$s: formatted price amount, %2$d: number of years between charges. */
										_n( '%1$s / %2$d year', '%1$s / %2$d years', intervalCount, 'wp-full-stripe-free' ),
										formattedAmount,
										intervalCount
								  );
						break;
				}

				return priceLabel;
			}

			setProductLabel( formatter, $product, priceInCents ) {
				const zeroDecimalSupport =
					this.getProductZeroDecimalSupport( $product );

				let amount = priceInCents;
				if ( ! zeroDecimalSupport ) {
					amount /= 100;
				}

				const formattedAmount = formatter.format(
					amount,
					this.getProductCurrencyCode( $product ),
					this.getProductCurrencySymbol( $product ),
					zeroDecimalSupport
				);
				const interval = $product.data( 'wpfs-interval' );
				const intervalCount = $product.data( 'wpfs-interval-count' );

				this.formatProductLabel(
					$product,
					this.getPriceAndIntervalLabel(
						interval,
						intervalCount,
						formattedAmount
					)
				);
			}

			setPricesAndLabels( priceId ) {
				const $product = this.findProductInput( priceId );

				const grossSetupFeeInCents =
					this.calculateGrossComponentPricing(
						priceId,
						PRICE_LINE_ITEM_TYPE_SETUP_FEE
					);
				const grossProductPriceInCents =
					this.calculateGrossComponentPricing(
						priceId,
						PRICE_LINE_ITEM_TYPE_PRODUCT
					);

				this.setProductPrice(
					$product,
					grossSetupFeeInCents,
					grossProductPriceInCents
				);
				this.setProductLabel(
					createCurrencyFormatter( this.$form ),
					$product,
					grossProductPriceInCents
				);
			}
		}

		class WPFP_PriceSetter_SubscriptionRadioList extends WPFP_SubscriptionPriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'input[data-wpfs-value="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				const formattedLabel =
					this.getProductName( $product ) + ' -  ' + formattedAmount;
				$product.data( 'wpfs-amount-description', formattedLabel );
				$(
					'label[for="' + this.getProductId( $product ) + '"]',
					this.$form
				).text( formattedLabel );
			}
		}

		class WPFP_PriceSetter_SubscriptionDropdown extends WPFP_SubscriptionPriceSetter {
			constructor( $form ) {
				super( $form );
			}

			findProductInput( priceId ) {
				return $(
					'option[data-wpfs-value="' + priceId + '"]',
					this.$form
				);
			}

			formatProductLabel( $product, formattedAmount ) {
				const formattedLabel =
					this.getProductName( $product ) + ' -  ' + formattedAmount;
				$product.data( 'wpfs-amount-description', formattedLabel );
				$product.text( formattedLabel );
			}

			refresh() {
				this.$form
					.find( '[data-toggle="selectmenu"]' )
					.wpfsSelectmenu( 'refresh' );
			}
		}

		function createProductPriceSetter( $form, priceId, grossPriceInCents ) {
			const formType = $form.data( FROM_TYPE_DOM );

			if (
				formType === FORM_TYPE_INLINE_PAYMENT ||
				formType === FORM_TYPE_CHECKOUT_PAYMENT
			) {
				switch ( extractAmountSelectorStyleFromNode( $form ) ) {
					case SELECTOR_STYLE_RADIO_BUTTONS:
						return new WPFP_PriceSetter_PaymentRadioList( $form );
					case SELECTOR_STYLE_DROPDOWN:
						return new WPFP_PriceSetter_PaymentDropdown( $form );
					case SELECTOR_STYLE_BUTTON_GROUP:
						return new WPFP_PriceSetter_PaymentButtonGroup( $form );
					case undefined:
					default:
						return new WPFP_PriceSetter_PaymentHidden( $form );
				}
			} else if (
				formType === FORM_TYPE_INLINE_SUBSCRIPTION ||
				formType === FORM_TYPE_CHECKOUT_SUBSCRIPTION
			) {
				switch ( extractAmountSelectorStyleFromNode( $form ) ) {
					case SELECTOR_STYLE_RADIO_BUTTONS:
						return new WPFP_PriceSetter_SubscriptionRadioList(
							$form
						);
					case SELECTOR_STYLE_DROPDOWN:
						return new WPFP_PriceSetter_SubscriptionDropdown(
							$form
						);
				}
			}
		}

		function refreshProductPricing( $form ) {
			const productPriceSetter = createProductPriceSetter( $form );

			// Donation forms have no price setter; a TypeError here would
			// strand the processing overlay (#609).
			if ( ! productPriceSetter ) {
				return;
			}

			const pricingData = WPFS.getPaymentDetails(
				extractFormNameFromNode( $form )
			);

			for ( const priceId in pricingData ) {
				if ( priceId === PRICE_ID_CUSTOM_AMOUNT ) {
					continue;
				}

				productPriceSetter.setPricesAndLabels( priceId );
			}

			productPriceSetter.refresh();
		}

		function refreshPaymentDetails( $form ) {
			const formType = $form.data( FROM_TYPE_DOM );

			if (
				formType === FORM_TYPE_INLINE_PAYMENT ||
				formType === FORM_TYPE_CHECKOUT_PAYMENT ||
				formType === FORM_TYPE_INLINE_DONATION ||
				formType === FORM_TYPE_CHECKOUT_DONATION
			) {
				handleAmountChange( $form );
			} else if (
				formType === FORM_TYPE_INLINE_SUBSCRIPTION ||
				formType === FORM_TYPE_CHECKOUT_SUBSCRIPTION
			) {
				handlePlanChange( $form );
			}
		}

		function createCustomAmountPricingData( $form, customAmount ) {
			return [
				{
					type: 'product',
					subType: 'product',
					id: PRICE_ID_CUSTOM_AMOUNT,
					displayName:
						l10n.products.other_amount_label,
					currency: $( '.wpfs-custom-amount--unique', $form ).data(
						'wpfs-currency'
					),
					amount: customAmount,
				},
			];
		}

		function refreshOtherPricingLocally( $form ) {
			if ( showCustomAmountFormatError( $form ) ) {
				refreshPaymentDetails( $form );
				return;
			}
			const customAmount = parseCustomAmountUnique( $form );

			if ( ! isNaN( customAmount ) ) {
				WPFS.setPaymentDetailsForPrice(
					extractFormNameFromNode( $form ),
					PRICE_ID_CUSTOM_AMOUNT,
					createCustomAmountPricingData( $form, customAmount )
				);

				refreshPaymentDetails( $form );
			}
		}

		function refreshPricingFromServer( $form ) {
			// A submit holds the lock and its fields can't change, so there is
			// nothing to reprice; the refresh would also release the lock.
			if ( $form.is( '[data-wpfs-locked]' ) ) {
				return;
			}
			if ( showCustomAmountFormatError( $form ) ) {
				refreshPaymentDetails( $form );
				return;
			}
			const data = gatherFormDataForTaxCalculation( $form );
			data.action = 'wpfs-calculate-pricing';
			data.nonce = wpfsFormSettings.nonce;
			clearGlobalMessage( $form );
			clearFieldErrors( $form );
			showLoadingAnimation( $form );
			disableFormInputsSelectsButtons( $form );

			postAjax( data, {
				success( data ) {
					setPricingStale( $form, ! data.success );
					if ( data.success ) {
						setPaymentDetailsData( $form, data.productPricing );
						refreshProductPricing( $form );
						refreshPaymentDetails( $form );
					} else if ( data.bindingResult ) {
						processValidationErrors( $form, data );
					} else {
						showErrorGlobalMessage(
							$form,
							l10n.validation_errors
								.internal_error_title,
							data.msg
						);
					}
				},
				error( jqXHR, textStatus, errorThrown ) {
					setPricingStale( $form, true );
					showAjaxError(
						$form,
						'refreshPricingFromServer',
						jqXHR,
						textStatus,
						errorThrown
					);
				},
				complete() {
					hideLoadingAnimation( $form );
					enableFormInputsSelectsButtons( $form );
				},
			} );
		}

		function readCouponData() {
			if ( typeof wpfsCouponData !== 'undefined' ) {
				for ( const key in wpfsCouponData ) {
					if (
						wpfsCouponData.hasOwnProperty( key ) &&
						wpfsCouponData[ key ]
					) {
						WPFS.setCoupon( key, wpfsCouponData[ key ] );

						$form = getParentForm(
							$(
								"input[name='wpfs-form-name'][value='" +
									key +
									"']"
							)
						);
						$( "input[name='wpfs-coupon']", $form ).val(
							wpfsCouponData[ key ].name
						);
					}
				}
			}
		}

		function readProductPricing() {
			if ( typeof wpfsProductPricing !== 'undefined' ) {
				for ( const key in wpfsProductPricing ) {
					if ( wpfsProductPricing.hasOwnProperty( key ) ) {
						WPFS.setPaymentDetails(
							key,
							wpfsProductPricing[ key ]
						);
					}
				}
			}
		}

		WPFS.ready = function () {
			if ( debugLog ) {
				logInfo( 'ready', 'CALLED' );
			}

			readProductPricing();
			readCouponData();

			// trigger plan change to initialize payment summary
			const $planSelectElements = $(
				'select.wpfs-subscription-plan-select'
			);
			$planSelectElements.each( function () {
				const $form = getParentForm( this );
				if ( $( 'option:selected', $( this ) ).length ) {
					$( this ).val( $( 'option:selected', $( this ) ).val() );
				} else {
					$( this ).val( $( 'option:first', $( this ) ).val() );
				}
				handlePlanChange( $form );
			} );
			const $planRadioElements = $(
				'input.wpfs-subscription-plan-radio'
			);
			$planRadioElements.each( function () {
				const $form = getParentForm( this );
				if (
					$(
						'input:radio[name="wpfs-plan"]:not(:disabled):checked',
						$form
					).length === 0
				) {
					$(
						'input:radio[name="wpfs-plan"]:not(:disabled):first',
						$form
					).attr( 'checked', true );
				}
				handlePlanChange( $form );
			} );
			const $planHiddenElements = $(
				'input.wpfs-subscription-plan-hidden'
			);
			$planHiddenElements.each( function () {
				const $form = getParentForm( this );
				handlePlanChange( $form );
			} );

			const $customSelectElements = $(
				'select.wpfs-custom-amount-select'
			);
			$customSelectElements.each( function () {
				const $form = getParentForm( this );
				if ( $( 'option:selected', $( this ) ).length ) {
					$( this ).val( $( 'option:selected', $( this ) ).val() );
				} else {
					$( this ).val( $( 'option:first', $( this ) ).val() );
				}
				handleAmountChange( $form );
				handleCustomAmountChange( $( this ), $form );
			} );
			$customSelectElements.on( 'change', function () {
				if ( ! $( this ).data( 'custom-wpfsSelectmenu' ) ) {
					const $form = getParentForm( this );
					handleCustomAmountChange( $( this ), $form );
					handleAmountChange( $form );
				}
			} );
			const $customRadioElements = $( 'input.wpfs-custom-amount-radio' );
			$customRadioElements.each( function () {
				const $form = getParentForm( this );
				if (
					$(
						'input:radio[name="wpfs-custom-amount"]:not(:disabled):checked',
						$form
					).length === 0
				) {
					$(
						'input:radio[name="wpfs-custom-amount"]:not(:disabled):first',
						$form
					).attr( 'checked', true );
				}
				handleAmountChange( $form );
			} );
			const $customHiddenElements = $(
				'input.wpfs-custom-amount-hidden'
			);
			$customHiddenElements.each( function () {
				const $form = getParentForm( this );
				handleAmountChange( $form );
			} );
			const $paymentFormsWithCustomAmount = $(
				'form[data-wpfs-amount-type="custom_amount"]'
			);
			$paymentFormsWithCustomAmount.each( function () {
				const $form = $( this );
				handleAmountChange( $form );
			} );

			const $spinnerElements = $( 'input[name="wpfs-plan-quantity"]' );
			$spinnerElements.each( function () {
				const $form = getParentForm( this );
				$( 'input[name="wpfs-plan-quantity"]', $form ).on(
					'spinchange',
					function ( e, ui ) {
						handleQuantityChange( $form );
					}
				);
			} );

			$( 'input[name="wpfs-fee-recovery-accepted"]' ).on(
				'change',
				function () {
					const $form = getParentForm( this );
					// Donation forms have no price id, so only forms that preview the fee look for
					// a custom amount, which a form without tax or a coupon prices on the page.
					const isPricedOnServer =
						isRecoveryFeePreviewed( $form ) &&
						! (
							PRICE_ID_CUSTOM_AMOUNT ===
								findPriceIdForSelectedProduct( $form ) &&
							isNoTaxForm( $form ) &&
							isNoCouponAppliedForm( $form )
						);
					if ( isPricedOnServer ) {
						refreshPricingFromServer( $form );
					} else {
						refreshPaymentDetails( $form );
					}
				}
			);
		};

		// tnagy initialize components
		WPFS.initInputGroup();
		WPFS.initSelectmenu();
		WPFS.initAddressSwitcher();
		WPFS.initTooltip();
		WPFS.initStepper();
		WPFS.initDatepicker();
		WPFS.initCombobox();
		WPFS.initCustomAmount();
		WPFS.initStripeJSCard();
		WPFS.initCoupon();
		WPFS.initCheckoutForms();
		WPFS.initInlineForms();
		WPFS.initReCaptcha();
		WPFS.initPlanRadioButton();
		WPFS.initBuyingAsBusiness();
		WPFS.initTaxFieldChanges();

		WPFS.ready();

		// to catch redirect from stripe
		// url has payment_intent and redirect_status parameters
		const urlParams = new URLSearchParams( window.location.search );
		const paymentIntent = urlParams.get( 'payment_intent' );
		const redirectStatus = urlParams.get( 'redirect_status' );
		const paymentIntentClientSecret = urlParams.get(
			'payment_intent_client_secret'
		);
		const formId = urlParams.get( 'wpfs-form-id' );

		$form = $( `form[data-wpfs-form-id="${ formId }"]` );

		// handle payment intent redirects
		if (
			paymentIntent &&
			redirectStatus &&
			redirectStatus === 'succeeded' &&
			$form.length
		) {
			// lock the screen to prevent user interaction
			disableFormButtons( $form );
			showLoadingAnimation( $form );
			// On return from Stripe the page reloads at the top; show the
			// processing overlay immediately so the user keeps context instead
			// of seeing the page flash while the charge finalises.
			showProcessingOverlay( $form );
			clearFieldErrors( $form );
			clearGlobalMessage( $form );

			const data = wpfsParseSerializedForm(
				createFormSerializer( $form ).serialize()
			);
			data.action = 'wp_full_stripe_confirm_redirect';
			data[ 'wpfs-stripe-payment-intent-id' ] = paymentIntent;
			data.redirect_status = redirectStatus;
			data.payment_intent_client_secret = paymentIntentClientSecret;

			postAjax( data, {
				success( data ) {
					if ( debugLog ) {
						logInfo(
							'stripeConfirmRedirect',
							'SUCCESS response=' + JSON.stringify( data )
						);
					}
					if ( data.success ) {
						if ( data.redirectURL ) {
							location.href = data.redirectURL;
						} else {
							// reset form fields
							resetFormFields( $form, null );

							// show success message
							showSuccessGlobalMessage(
								$form,
								data.messageTitle,
								data.message
							);

							// enable submit button
							enableFormButtons( $form );

							// stop the animations
							hideLoadingAnimation( $form );

							// clear the parameters
							window.history.replaceState(
								{},
								document.title,
								window.location.pathname
							);
						}
					} else {
						showResponseErrors( $form, data );
						// finalisation failed; let the customer retry and clear the overlay
						enableFormButtons( $form );
						hideLoadingAnimation( $form );
					}
				},
				error( jqXHR, textStatus, errorThrown ) {
					showAjaxError(
						$form,
						'stripeConfirmRedirect',
						jqXHR,
						textStatus,
						errorThrown
					);
					enableFormButtons( $form );
					hideLoadingAnimation( $form );
				},
				complete() {},
			} );
		} else if (
			paymentIntent &&
			redirectStatus &&
			redirectStatus === 'failed'
		) {
			// show error message
			showErrorGlobalMessage(
				$form,
				l10n.validation_errors.payment_failed_title,
				l10n.validation_errors.payment_failed
			);

			// clear the parameters
			window.history.replaceState(
				{},
				document.title,
				window.location.pathname
			);
		} else if ( paymentIntent ) {
			// customer was redirected back to us, but without a status indicator
			// we'll need to get the payment intent to see the result of the transaction
		}
	} );
} )( jQuery );
