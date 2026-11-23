/**
 * Created by tnagy on 2018.06.26..
 */

/* global jQuery */

const WPFS_DECIMAL_SEPARATOR_DOT = 'dot';
const WPFS_DECIMAL_SEPARATOR_COMMA = 'comma';
const WPFS_DECIMAL_SEPARATOR_SYMBOL_DOT = '.';
const WPFS_DECIMAL_SEPARATOR_SYMBOL_COMMA = ',';
const WPFS_GROUP_SEPARATOR_EMPTY = '';

const wpfsDebugLog = false;

/**
 * Formats a currency amount in smallest common currency unit to display as a String.
 *
 * @param amount             in smallest common currency unit
 * @param zeroDecimalSupport
 */
function formatCurrencyAmount( amount, zeroDecimalSupport ) {
	const theAmount = parseFloat( amount );
	if ( ! isNaN( theAmount ) ) {
		if ( zeroDecimalSupport == true ) {
			return theAmount.toFixed( 0 );
		}
		return theAmount.toFixed( 2 );
	}
	return amount;
}

function parseCurrencyAmount(
	amount,
	zeroDecimalSupport,
	returnSmallestCommonCurrencyUnit
) {
	let theAmount;
	if ( zeroDecimalSupport == true ) {
		theAmount = parseFloat( amount );
		theAmount = parseInt( theAmount.toFixed( 0 ) );
	} else {
		theAmount = parseFloat( amount );
		theAmount = parseFloat( theAmount.toFixed( 2 ) );
	}
	if ( ! isNaN( theAmount ) ) {
		if ( returnSmallestCommonCurrencyUnit ) {
			if ( zeroDecimalSupport == false ) {
				theAmount = Math.round( theAmount * 100 );
			}
		}
	}
	return theAmount;
}

/**
 * Apply coupon to the given amount.
 *
 * @param  currency
 * @param  amount   in smallest common currency unit
 * @param  coupon
 * @return {result}
 */

function calculateVATAmount( amount, vatPercent ) {
	return Math.round( ( amount * vatPercent ) / 100 );
}

function logError( handlerName, jqXHR, textStatus, errorThrown ) {
	if ( window.console ) {
		console.log( handlerName + '.error(): textStatus=' + textStatus );
		console.log( handlerName + '.error(): errorThrown=' + errorThrown );
		if ( jqXHR ) {
			console.log(
				handlerName + '.error(): jqXHR.status=' + jqXHR.status
			);
			console.log(
				handlerName +
					'.error(): jqXHR.responseText=' +
					jqXHR.responseText
			);
		}
	}
}

function logInfo( handlerName, message ) {
	if ( window.console ) {
		console.log( handlerName + '  INFO: ' + message );
	}
}

function logWarn( handlerName, message ) {
	if ( window.console ) {
		console.log( handlerName + '  WARN: ' + message );
	}
}

function logException( formId, exception ) {
	if ( window.console && exception ) {
		if ( exception.message ) {
			console.log(
				'ERROR: formId=' + formId + ', message=' + exception.message
			);
		}
	}
}

function logResponseException( source, response ) {
	if ( window.console && response ) {
		if ( response.ex_msg ) {
			console.log(
				'ERROR: source=' + source + ', message=' + response.ex_msg
			);
		}
	}
}

/**
 * Whether the whole element is inside the window.
 *
 * @param {jQuery} $anElement
 * @return {boolean} True when neither edge is scrolled out of view.
 */
function wpfsIsInViewport( $anElement ) {
	const $window = jQuery( window );

	//noinspection JSValidateTypes
	const viewPortTop = $window.scrollTop();
	const viewPortBottom = viewPortTop + $window.height();

	const elementTop = $anElement.offset().top;
	const elementBottom = elementTop + $anElement.outerHeight();

	if ( wpfsDebugLog ) {
		console.log(
			'isInViewport(): elementBottom=' +
				elementBottom +
				', viewPortBottom=' +
				viewPortBottom +
				', elementTop=' +
				elementTop +
				', viewPortTop=' +
				viewPortTop
		);
	}

	return elementBottom <= viewPortBottom && elementTop >= viewPortTop;
}

/**
 * Scrolls an element that is out of view to 100px below the top of the window.
 *
 * @param {jQuery}  $anElement
 * @param {boolean} fade       Whether to blink the element afterwards.
 */
function wpfsScrollToElement( $anElement, fade ) {
	if ( $anElement && $anElement.offset() && $anElement.offset().top ) {
		if ( ! wpfsIsInViewport( $anElement ) ) {
			jQuery( 'html, body' ).animate(
				{
					scrollTop: $anElement.offset().top - 100,
				},
				1000
			);
		}
	}
	if ( $anElement && fade ) {
		$anElement.fadeIn( 500 ).fadeOut( 500 ).fadeIn( 500 );
	}
}

/**
 * Scrolls to the field of the form's first field error message.
 *
 * @param {jQuery} $form
 */
function wpfsScrollToFirstFieldError( $form ) {
	const firstErrorFieldId = jQuery( '.wpfs-form-error-message', $form )
		.first()
		.data( 'wpfs-field-error-for' );
	if ( firstErrorFieldId ) {
		wpfsScrollToElement( jQuery( '#' + firstErrorFieldId, $form ), false );
	}
}

/**
 * @typedef {Object} WpfsFieldDescriptor How to find a form field and flag its errors.
 * @property {string}  type          Field type, such as input, input-group or dropdown.
 * @property {string}  name          Field name.
 * @property {?string} class         Class of the field or its container.
 * @property {?string} selector      Finds the field when its id is not known.
 * @property {?string} errorClass    Class added to the flagged element.
 * @property {?string} errorSelector Element to flag; may contain {fieldId}.
 * @property {boolean} hidden        Whether errors go to the form's banner.
 */

/**
 * The descriptor of a field of a form type.
 *
 * @param {Object<string, Object<string, WpfsFieldDescriptor>>} fieldsByFormType Descriptors by form type, then field name.
 * @param {string|undefined}                                    formType
 * @param {string}                                              fieldName
 * @return {?WpfsFieldDescriptor} Null when the form type or field is unknown.
 */
function wpfsGetFieldDescriptor( fieldsByFormType, formType, fieldName ) {
	const has = ( object, key ) =>
		Object.prototype.hasOwnProperty.call( object, key );
	if (
		has( fieldsByFormType, formType ) &&
		has( fieldsByFormType[ formType ], fieldName )
	) {
		return fieldsByFormType[ formType ][ fieldName ];
	}
	return null;
}

/**
 * The descriptor's error selector for one field.
 *
 * @param {WpfsFieldDescriptor} fieldDescriptor
 * @param {?string}             fieldId
 * @return {?string} The selector with {fieldId} replaced, or null without one.
 */
function wpfsFieldErrorSelector( fieldDescriptor, fieldId ) {
	const errorSelector = fieldDescriptor.errorSelector;
	if ( errorSelector === null || errorSelector === undefined ) {
		return errorSelector;
	}
	return errorSelector.replace( /\{fieldId}/g, fieldId );
}

/**
 * Removes the form's field error messages and error classes.
 *
 * @param {jQuery} $form
 */
function wpfsClearFieldErrors( $form ) {
	jQuery( '.wpfs-form-error-message', $form ).remove();
	jQuery( '.wpfs-form-control', $form ).removeClass(
		'wpfs-form-control--error'
	);
	jQuery( '.wpfs-input-group', $form ).removeClass(
		'wpfs-input-group--error'
	);
	jQuery( '.wpfs-form-control--error', $form ).removeClass(
		'wpfs-form-control--error'
	);
	jQuery( '.wpfs-form-check-input--error', $form ).removeClass(
		'wpfs-form-check-input--error'
	);
	// jQuery UI appends selectmenu menus to the body, outside the form.
	jQuery( 'select[data-toggle="selectmenu"]', $form ).each( function () {
		jQuery( document.getElementById( this.id + '-menu' ) ).removeClass(
			'wpfs-form-control--error'
		);
	} );
}

/**
 * The form's global error messages, each followed by a line break.
 *
 * @param {{errors: string[]}} globalErrors
 * @return {string} HTML for the error banner.
 */
function wpfsGlobalErrorMessages( globalErrors ) {
	let globalErrorMessages = '';
	for ( let i = 0; i < globalErrors.errors.length; i++ ) {
		globalErrorMessages += globalErrors.errors[ i ] + '<br/>';
	}
	return globalErrorMessages;
}

/**
 * Turns every [data-toggle="selectmenu"] select into a jQuery UI selectmenu
 * styled as a form control.
 *
 * @param {Object}                                                      [options]          Settings.
 * @param {(this: HTMLSelectElement, event: Event, ui: Object) => void} [options.onChange] Runs first when the selection changes.
 */
function wpfsInitSelectmenu( options = {} ) {
	jQuery.widget( 'custom.wpfsSelectmenu', jQuery.ui.selectmenu, {
		_renderItem( ul, item ) {
			const $li = jQuery( '<li>' );
			const wrapper = jQuery( '<div>', {
				class: 'menu-item-wrapper ui-menu-item-wrapper',
				text: item.label,
			} );

			if ( item.disabled ) {
				$li.addClass( 'ui-state-disabled' );
			}

			return $li.append( wrapper ).appendTo( ul );
		},
	} );

	jQuery( '[data-toggle="selectmenu"]' ).each( function () {
		if ( typeof jQuery( this ).select2 === 'function' ) {
			try {
				jQuery( this ).select2( 'destroy' );
			} catch ( err ) {}
		}

		const $selectmenu = jQuery( this ).wpfsSelectmenu( {
			classes: {
				'ui-selectmenu-button':
					'wpfs-form-control wpfs-selectmenu-button',
				'ui-selectmenu-menu': 'wpfs-ui wpfs-selectmenu-menu',
			},
			icons: {
				button: 'wpfs-icon-arrow',
			},
			create() {
				if ( wpfsDebugLog ) {
					console.log( 'selectmenu.create(): CALLED' );
				}
				const $this = jQuery( this );
				const $selectMenuButton = $this.next();
				$selectMenuButton.addClass( $this.attr( 'class' ) );
				if (
					$this.find( 'option:selected:disabled' ) &&
					$this.find( 'option:selected:disabled' ).length > 0
				) {
					$selectMenuButton.addClass( 'ui-state-placeholder' );
				}
			},
			open() {
				if ( wpfsDebugLog ) {
					console.log( 'selectmenu.open(): CALLED' );
				}
				const $this = jQuery( this );
				const $button = $this.data( 'custom-wpfsSelectmenu' ).button;
				$button.removeClass( 'ui-selectmenu-button-closed' );
				$button.addClass( 'ui-selectmenu-button-open' );
				const selectedClass = 'ui-state-selected';
				const selectedIndex = $this
					.find( 'option' )
					.index( $this.find( 'option:selected' ) );
				jQuery(
					'.ui-selectmenu-open .ui-menu-item-wrapper'
				).removeClass( selectedClass );
				const $menuItem = jQuery(
					'.ui-selectmenu-open .ui-menu-item'
				).eq( selectedIndex );
				if ( ! $menuItem.hasClass( 'ui-state-disabled' ) ) {
					$menuItem
						.find( '.ui-menu-item-wrapper' )
						.addClass( selectedClass );
				}
			},
			close() {
				if ( wpfsDebugLog ) {
					console.log( 'selectmenu.close(): CALLED' );
				}
				const $this = jQuery( this );
				const $button = $this.data( 'custom-wpfsSelectmenu' ).button;
				$button.removeClass( 'ui-selectmenu-button-open' );
				$button.addClass( 'ui-selectmenu-button-closed' );
			},
			change( event, ui ) {
				if ( wpfsDebugLog ) {
					console.log( 'selectmenu.change(): CALLED' );
				}
				if ( options.onChange ) {
					options.onChange.call( this, event, ui );
				}
				jQuery( this ).next().removeClass( 'ui-state-placeholder' );
				jQuery( this ).trigger( 'selectmenuchange' );
			},
		} );

		const $selectmenuParent = $selectmenu.parent();
		$selectmenuParent
			.find( '.ui-selectmenu-button' )
			.addClass( 'wpfs-form-control' )
			.addClass( 'wpfs-selectmenu-button' )
			.addClass( 'ui-button' );

		$selectmenu
			.data( 'custom-wpfsSelectmenu' )
			.menuWrap.addClass( 'wpfs-ui' )
			.addClass( 'wpfs-selectmenu-menu' );
	} );
}

/**
 * Turns every [data-toggle="stepper"] input into a jQuery UI spinner whose
 * + and − buttons grey out at the input's data-max and data-min.
 */
function wpfsInitStepper() {
	const updateButtons = ( $input, value ) => {
		const $container = $input.parent();
		const disabledClassName = 'ui-state-disabled';
		const $up = $container.find( '.ui-spinner-up' );
		const $down = $container.find( '.ui-spinner-down' );

		$up.removeClass( disabledClassName );
		$down.removeClass( disabledClassName );

		if ( value === $input.spinner( 'option', 'max' ) ) {
			$up.addClass( disabledClassName );
		}

		if ( value === $input.spinner( 'option', 'min' ) ) {
			$down.addClass( disabledClassName );
		}
	};

	jQuery( '[data-toggle="stepper"]' ).each( function () {
		const $this = jQuery( this );
		let defaultValue = $this.data( 'defaultValue' ) || 1;

		if ( $this.val() === '' ) {
			$this.val( defaultValue );
		}

		$this
			.spinner( {
				min: $this.data( 'min' ) || 1,
				max: $this.data( 'max' ) || 9999,
				icons: {
					down: 'wpfs-icon-decrease',
					up: 'wpfs-icon-increase',
				},
				change() {
					const $input = jQuery( this );
					if ( $input.spinner( 'isValid' ) ) {
						defaultValue = $input.val();
					} else {
						$input.val( defaultValue );
					}
					// A typed value is text; the limits are numbers.
					updateButtons( $input, Number( $input.val() ) );
				},
				spin( e, ui ) {
					updateButtons( jQuery( this ), ui.value );
				},
			} )
			.parent()
			.find( '.ui-icon' )
			.text( '' );
	} );
}

function splitQueryStringIntoArray( query ) {
	const res = {};

	idx = query.indexOf( '#' );
	if ( idx >= 0 ) {
		query = query.slice( 0, idx );
	}

	// Build the associative array. '+' is a space in a query string, and
	// names ending in [] collect every value.
	const decode = ( part ) => decodeURIComponent( part.replace( /\+/g, ' ' ) );
	const hashes = query.split( '&' );
	for ( let i = 0; i < hashes.length; i++ ) {
		const sep = hashes[ i ].indexOf( '=' );
		if ( sep <= 0 ) {
			continue;
		}
		const key = decode( hashes[ i ].slice( 0, sep ) );
		const val = decode( hashes[ i ].slice( sep + 1 ) );
		if ( key.slice( -2 ) === '[]' ) {
			if ( ! Array.isArray( res[ key ] ) ) {
				res[ key ] = [];
			}
			res[ key ].push( val );
		} else {
			res[ key ] = val;
		}
	}

	return res;
}

function getQueryStringIntoArray() {
	let res = {};

	// Get the start index of the query string
	const idx = window.location.href.indexOf( '?' );
	if ( idx !== -1 ) {
		const query = window.location.href.slice( idx + 1 );
		res = splitQueryStringIntoArray( query );
	}

	return res;
}

/**
 * Source: https://stackoverflow.com/a/34817120
 *
 * @param  number
 * @param  decimals
 * @param  dec_point
 * @param  thousands_sep
 * @return {string}
 */
function number_format( number, decimals, dec_point, thousands_sep ) {
	// makes sure `number` is numeric value
	number = number * 1;
	const str = number
		.toFixed( decimals ? decimals : 0 )
		.toString()
		.split( '.' );
	const parts = [];
	for ( let i = str[ 0 ].length; i > 0; i -= 3 ) {
		parts.unshift( str[ 0 ].substring( Math.max( 0, i - 3 ), i ) );
	}
	str[ 0 ] = parts.join(
		thousands_sep || thousands_sep === '' ? thousands_sep : ','
	);
	return str.join( dec_point ? dec_point : '.' );
}

const WPFSCurrencyFormatter = function (
	decimalSeparator,
	showCurrencySymbolInsteadOfCode,
	showCurrencySignAtFirstPosition,
	putWhitespaceBetweenCurrencyAndAmount
) {
	const instance = {};
	if ( WPFS_DECIMAL_SEPARATOR_COMMA === decimalSeparator ) {
		instance.decimalSeparator = WPFS_DECIMAL_SEPARATOR_SYMBOL_COMMA;
	} else if ( WPFS_DECIMAL_SEPARATOR_DOT === decimalSeparator ) {
		instance.decimalSeparator = WPFS_DECIMAL_SEPARATOR_SYMBOL_DOT;
	} else {
		instance.decimalSeparator = WPFS_DECIMAL_SEPARATOR_SYMBOL_DOT;
	}
	instance.showCurrencySymbolInsteadOfCode =
		1 == showCurrencySymbolInsteadOfCode;
	instance.showCurrencySignAtFirstPosition =
		1 == showCurrencySignAtFirstPosition;
	instance.putWhitespaceBetweenCurrencyAndAmount =
		1 == putWhitespaceBetweenCurrencyAndAmount;
	instance.groupSeparator = WPFS_GROUP_SEPARATOR_EMPTY;
	instance.format = function (
		amount,
		currencyCode,
		currencySymbol,
		zeroDecimalSupport
	) {
		// tnagy format amount with the current properties
		if ( wpfsDebugLog ) {
			logInfo( 'format', 'object=' + JSON.stringify( this ) );
			logInfo(
				'format',
				'amount=' +
					amount +
					', currencyCode=' +
					currencyCode +
					', currencySymbol=' +
					currencySymbol +
					', zeroDecimalSupport=' +
					zeroDecimalSupport
			);
		}
		let currencySign;
		if ( instance.showCurrencySymbolInsteadOfCode ) {
			currencySign = currencySymbol;
		} else {
			currencySign = currencyCode;
		}
		let pattern =
			'%s' +
			( instance.putWhitespaceBetweenCurrencyAndAmount ? ' ' : '' ) +
			'%s';
		let amountString;
		let result;
		if ( zeroDecimalSupport ) {
			amountString = number_format(
				amount,
				0,
				instance.decimalSeparator,
				instance.groupSeparator
			);
		} else if ( Math.round( amount ) == amount ) {
			amountString = number_format(
				amount,
				0,
				instance.decimalSeparator,
				instance.groupSeparator
			);
		} else {
			amountString = number_format(
				amount,
				2,
				instance.decimalSeparator,
				instance.groupSeparator
			);
		}
		if ( wpfsDebugLog ) {
			logInfo( 'format', 'pattern=' + pattern );
			logInfo( 'format', 'amountString=' + amountString );
		}
		let argv;
		if ( instance.showCurrencySignAtFirstPosition ) {
			argv = [ currencySign, amountString ];
			if ( wpfsDebugLog ) {
				logInfo( 'format', 'argv=' + JSON.stringify( argv ) );
			}
			result = wp.i18n.sprintf( pattern, ...argv );
		} else {
			pattern =
				'%s' +
				( instance.putWhitespaceBetweenCurrencyAndAmount ? ' ' : '' ) +
				'%s';
			argv = [ amountString, currencySign ];
			if ( wpfsDebugLog ) {
				logInfo( 'format', 'argv=' + JSON.stringify( argv ) );
			}
			result = wp.i18n.sprintf( pattern, ...argv );
		}
		if ( wpfsDebugLog ) {
			logInfo( 'format', 'result=' + result );
		}
		return result;
	};
	instance.parse = function ( value ) {
		// tnagy parse amount with the current properties
		if ( wpfsDebugLog ) {
			logInfo( 'parse', 'object=' + JSON.stringify( this ) );
			logInfo( 'parse', 'value=' + value );
		}
		if ( value == null ) {
			return null;
		}
		let normalizedValue;
		if (
			WPFS_DECIMAL_SEPARATOR_SYMBOL_COMMA === instance.decimalSeparator
		) {
			normalizedValue = value
				.replace( /[^0-9,]+/g, '' )
				.replace( /[,]+/g, '.' );
		} else {
			normalizedValue = value.replace( /[^0-9.]+/g, '' );
		}
		if ( wpfsDebugLog ) {
			logInfo( 'parse', 'normalizedValue=' + normalizedValue );
		}
		return normalizedValue;
	};
	instance.validForParse = function ( value ) {
		let normalizedValue;
		if (
			WPFS_DECIMAL_SEPARATOR_SYMBOL_COMMA === instance.decimalSeparator
		) {
			normalizedValue = value.replace( /[^0-9,]+/g, '' );
		} else {
			normalizedValue = value.replace( /[^0-9.]+/g, '' );
		}
		if ( wpfsDebugLog ) {
			logInfo( 'validForParse', 'normalizedValue=' + normalizedValue );
		}
		return normalizedValue == value;
	};
	/**
	 * Whether a customer-typed amount is digits, optionally followed by the
	 * decimal separator and at most as many digits as the currency has
	 * decimals. Mirrors CurrencyFormat::parseToSmallestCurrencyUnit() on the
	 * server. An empty value passes, it is reported as a missing amount instead.
	 *
	 * @param {string}  value
	 * @param {boolean} zeroDecimalSupport
	 * @return {boolean} True when the value is well-formed.
	 */
	instance.validCustomAmount = function ( value, zeroDecimalSupport ) {
		const trimmedValue = String( value ?? '' ).trim();
		if ( '' === trimmedValue ) {
			return true;
		}
		const decimalPart =
			zeroDecimalSupport == true
				? ''
				: '(?:\\' + instance.decimalSeparator + '[0-9]{1,2})?';
		const pattern = new RegExp( '^[0-9]{1,12}' + decimalPart + '$' );
		if ( wpfsDebugLog ) {
			logInfo( 'validCustomAmount', 'pattern=' + pattern );
		}
		return pattern.test( trimmedValue );
	};
	/**
	 * @param {boolean} zeroDecimalSupport
	 * @return {string} An example amount in the accepted format, e.g. "12,34".
	 */
	instance.formatExampleAmount = function ( zeroDecimalSupport ) {
		return zeroDecimalSupport == true
			? '12'
			: '12' + instance.decimalSeparator + '34';
	};

	return instance;
};
