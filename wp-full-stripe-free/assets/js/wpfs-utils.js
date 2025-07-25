/**
 * Created by tnagy on 2018.06.26..
 */

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

function splitQueryStringIntoArray( query ) {
	const res = {};

	idx = query.indexOf( '#' );
	if ( idx >= 0 ) {
		query = query.slice( 0, idx );
	}

	// Build the associative array
	const hashes = query.split( '&' );
	for ( let i = 0; i < hashes.length; i++ ) {
		const sep = hashes[ i ].indexOf( '=' );
		if ( sep <= 0 ) {
			continue;
		}
		const key = decodeURIComponent( hashes[ i ].slice( 0, sep ) );
		const val = decodeURIComponent( hashes[ i ].slice( sep + 1 ) );
		res[ key ] = val;
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
			result = vsprintf( pattern, argv );
		} else {
			pattern =
				'%s' +
				( instance.putWhitespaceBetweenCurrencyAndAmount ? ' ' : '' ) +
				'%s';
			argv = [ amountString, currencySign ];
			if ( wpfsDebugLog ) {
				logInfo( 'format', 'argv=' + JSON.stringify( argv ) );
			}
			result = vsprintf( pattern, argv );
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

	return instance;
};
