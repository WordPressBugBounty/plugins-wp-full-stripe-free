<?php

/**
 * Normalized definition of a single custom field.
 *
 * Produced by {@see MM_WPFS_CustomFields::parse()} from either the legacy
 * `{{`-delimited label format or the JSON v1 configuration format. This is the
 * authoritative, type-aware description used for rendering, validation and
 * snapshot generation.
 */
class MM_WPFS_CustomFieldDef {

	/** @var string Stable identifier (e.g. cf_ab12cd34, or cf_legacy_0). */
	public $id = '';
	/** @var string One of the MM_WPFS_CustomFields::TYPE_* constants. */
	public $type = 'text';
	/** @var string Public, localizable label. */
	public $label = '';
	/** @var bool Whether a value is required on submission. */
	public $required = false;
	/** @var string Optional placeholder. */
	public $placeholder = '';
	/** @var string Optional description rendered below the control. */
	public $description = '';
	/** @var array<int, array{label:string, value:string}> Options for select/multiselect. */
	public $options = [];
	/** @var array<string, mixed> Type specific settings (rows, maxLength, min, max, step, minDate, maxDate). */
	public $settings = [];
	/** @var string Admin-only title for html blocks (sortable list management). */
	public $title = '';
	/** @var string Sanitized html content for html blocks. */
	public $content = '';

	public function isInteractive(): bool {
		return MM_WPFS_CustomFields::isInteractive( $this->type );
	}

	public function isOption(): bool {
		return MM_WPFS_CustomFields::isOption( $this->type );
	}

	public function isMultiValue(): bool {
		return MM_WPFS_CustomFields::isMultiValue( $this->type );
	}

	/**
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function getSetting( $key, $default = null ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}
}

/**
 * Parsing, normalization, validation and value-projection for typed custom fields.
 *
 * The plugin stores custom field configuration in the existing `customInputs`
 * column. Two formats are supported:
 *
 *  - Legacy: labels joined by `{{`. Each label becomes a `text` field whose
 *    required flag inherits the form level `customInputRequired` value.
 *  - JSON v1: `{"version":1,"fields":[ ... ]}` describing typed fields.
 *
 * Saved configuration is always authoritative: client submitted type, label,
 * option and required information is never trusted.
 */
class MM_WPFS_CustomFields {

	const CONFIG_VERSION = 1;

	const TYPE_TEXT        = 'text';
	const TYPE_TEXTAREA    = 'textarea';
	const TYPE_SELECT      = 'select';
	const TYPE_MULTISELECT = 'multiselect';
	const TYPE_CHECKBOX    = 'checkbox';
	const TYPE_DATE        = 'date';
	const TYPE_NUMBER      = 'number';
	const TYPE_PHONE       = 'phone';
	const TYPE_HTML        = 'html';

	const MAX_OPTIONS             = 20;
	const MAX_OPTION_VALUE_LENGTH = 100;
	const PHONE_MAX_LENGTH        = 40;
	const DEFAULT_TEXTAREA_ROWS   = 3;

	const CHECKBOX_VALUE_YES = 'yes';
	const CHECKBOX_VALUE_NO  = 'no';

	const LEGACY_ID_PREFIX = 'cf_legacy_';

	/**
	 * @return string[]
	 */
	public static function getSupportedTypes() {
		return [
			self::TYPE_TEXT,
			self::TYPE_TEXTAREA,
			self::TYPE_SELECT,
			self::TYPE_MULTISELECT,
			self::TYPE_CHECKBOX,
			self::TYPE_DATE,
			self::TYPE_NUMBER,
			self::TYPE_PHONE,
			self::TYPE_HTML,
		];
	}

	/**
	 * Maximum number of UTF-8 characters allowed in a public label.
	 *
	 * Matches the Stripe metadata key limit because labels become metadata keys.
	 *
	 * @return int
	 */
	public static function getMaxLabelLength() {
		return MM_WPFS_Utils::STRIPE_METADATA_KEY_MAX_LENGTH;
	}

	/**
	 * Normalize an externally supplied type string to a supported type.
	 *
	 * `dropdown` is accepted as an alias of `select`. Unknown types fall back to text.
	 *
	 * @param mixed $type
	 *
	 * @return string
	 */
	public static function normalizeType( $type ) {
		$type = is_string( $type ) ? strtolower( trim( $type ) ) : '';
		if ( 'dropdown' === $type ) {
			return self::TYPE_SELECT;
		}
		if ( in_array( $type, self::getSupportedTypes(), true ) ) {
			return $type;
		}

		return self::TYPE_TEXT;
	}

	/**
	 * @param string $type
	 *
	 * @return bool True for every type that accepts user input (everything but html).
	 */
	public static function isInteractive( $type ) {
		return self::TYPE_HTML !== $type;
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	public static function isOption( $type ) {
		return self::TYPE_SELECT === $type || self::TYPE_MULTISELECT === $type;
	}

	/**
	 * @param string $type
	 *
	 * @return bool True when the field stores an array of values.
	 */
	public static function isMultiValue( $type ) {
		return self::TYPE_MULTISELECT === $type;
	}

	/**
	 * Whether a stored customInputs value is JSON v1 configuration.
	 *
	 * @param mixed $customInputs
	 *
	 * @return bool
	 */
	public static function isJsonConfig( $customInputs ) {
		if ( ! is_string( $customInputs ) ) {
			return false;
		}
		$trimmed = ltrim( $customInputs );
		if ( '' === $trimmed || '{' !== $trimmed[0] ) {
			return false;
		}
		$decoded = json_decode( $trimmed, true );

		return is_array( $decoded ) && isset( $decoded['fields'] ) && is_array( $decoded['fields'] );
	}

	/**
	 * Parse a stored customInputs value into normalized field definitions.
	 *
	 * @param string|null $customInputs   value of the form's customInputs column
	 * @param int         $globalRequired legacy form level customInputRequired flag
	 *
	 * @return MM_WPFS_CustomFieldDef[]
	 */
	public static function parse( $customInputs, $globalRequired = 0 ) {
		if ( self::isJsonConfig( $customInputs ) ) {
			return self::parseJson( $customInputs );
		}

		return self::parseLegacy( $customInputs, $globalRequired );
	}

	/**
	 * @param string|null $customInputs
	 * @param int         $globalRequired
	 *
	 * @return MM_WPFS_CustomFieldDef[]
	 */
	public static function parseLegacy( $customInputs, $globalRequired = 0 ) {
		$defs = [];
		if ( is_null( $customInputs ) || '' === $customInputs ) {
			return $defs;
		}
		$labels = explode( '{{', $customInputs );
		foreach ( $labels as $index => $label ) {
			$def           = new MM_WPFS_CustomFieldDef();
			$def->id       = self::LEGACY_ID_PREFIX . $index;
			$def->type     = self::TYPE_TEXT;
			$def->label    = $label;
			$def->required = ( 1 == $globalRequired );
			$defs[]        = $def;
		}

		return $defs;
	}

	/**
	 * @param string $customInputs
	 *
	 * @return MM_WPFS_CustomFieldDef[]
	 */
	public static function parseJson( $customInputs ) {
		$defs    = [];
		$decoded = json_decode( $customInputs, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['fields'] ) || ! is_array( $decoded['fields'] ) ) {
			return $defs;
		}
		foreach ( $decoded['fields'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$defs[] = self::fieldDefFromArray( $raw );
		}

		return $defs;
	}

	/**
	 * Build a definition from an already canonicalized (stored) field array.
	 *
	 * @param array<string, mixed> $raw
	 *
	 * @return MM_WPFS_CustomFieldDef
	 */
	protected static function fieldDefFromArray( $raw ) {
		$type        = self::normalizeType( isset( $raw['type'] ) ? $raw['type'] : self::TYPE_TEXT );
		$def         = new MM_WPFS_CustomFieldDef();
		$def->type   = $type;
		$def->id     = ( isset( $raw['id'] ) && is_string( $raw['id'] ) && '' !== $raw['id'] ) ? $raw['id'] : self::generateId();
		$def->label  = isset( $raw['label'] ) ? (string) $raw['label'] : '';

		if ( self::TYPE_HTML === $type ) {
			$def->required = false;
			$def->title    = isset( $raw['title'] ) ? (string) $raw['title'] : '';
			$def->content  = isset( $raw['content'] ) ? (string) $raw['content'] : '';

			return $def;
		}

		$def->required    = ! empty( $raw['required'] );
		$def->description = isset( $raw['description'] ) ? (string) $raw['description'] : '';
		$def->placeholder = isset( $raw['placeholder'] ) ? (string) $raw['placeholder'] : '';

		if ( self::isOption( $type ) ) {
			$def->options = self::readOptions( isset( $raw['options'] ) ? $raw['options'] : [] );
		}
		$def->settings = self::readSettings( $type, $raw );

		return $def;
	}

	/**
	 * @param mixed $rawOptions
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	protected static function readOptions( $rawOptions ) {
		$options = [];
		if ( ! is_array( $rawOptions ) ) {
			return $options;
		}
		foreach ( $rawOptions as $rawOption ) {
			if ( ! is_array( $rawOption ) ) {
				continue;
			}
			$label = isset( $rawOption['label'] ) ? (string) $rawOption['label'] : '';
			$value = isset( $rawOption['value'] ) ? (string) $rawOption['value'] : '';
			if ( '' === $value && '' === $label ) {
				continue;
			}
			$options[] = [ 'label' => $label, 'value' => $value ];
		}

		return $options;
	}

	/**
	 * @param string               $type
	 * @param array<string, mixed> $raw
	 *
	 * @return array<string, mixed>
	 */
	protected static function readSettings( $type, $raw ) {
		$settings = [];
		switch ( $type ) {
			case self::TYPE_TEXTAREA:
				$settings['rows'] = ( isset( $raw['rows'] ) && is_numeric( $raw['rows'] ) && (int) $raw['rows'] > 0 )
					? (int) $raw['rows'] : self::DEFAULT_TEXTAREA_ROWS;
				if ( isset( $raw['maxLength'] ) && is_numeric( $raw['maxLength'] ) && (int) $raw['maxLength'] > 0 ) {
					$settings['maxLength'] = (int) $raw['maxLength'];
				}
				break;
			case self::TYPE_NUMBER:
				foreach ( [ 'min', 'max', 'step' ] as $key ) {
					if ( isset( $raw[ $key ] ) && is_numeric( $raw[ $key ] ) ) {
						$settings[ $key ] = $raw[ $key ] + 0;
					}
				}
				break;
			case self::TYPE_DATE:
				foreach ( [ 'minDate', 'maxDate' ] as $key ) {
					if ( isset( $raw[ $key ] ) && self::isValidDate( $raw[ $key ] ) ) {
						$settings[ $key ] = $raw[ $key ];
					}
				}
				break;
		}

		return $settings;
	}

	/* -------------------------------------------------------------------------
	 * Admin save: sanitize + canonicalize
	 * ---------------------------------------------------------------------- */

	/**
	 * Sanitize and re-encode an admin submitted configuration to canonical JSON v1.
	 *
	 * This is the only entry point that should touch admin supplied configuration.
	 * It performs per-field sanitization (text labels through sanitize_text_field,
	 * html content through wp_kses_post), generates stable ids and option values,
	 * de-duplicates ids and option values, and enforces the field/option/label limits.
	 *
	 * @param mixed $raw       JSON string or decoded array from the request
	 * @param int   $maxFields maximum number of fields allowed (0 = unlimited)
	 *
	 * @return string canonical JSON, or '' when there are no usable fields
	 */
	public static function normalizeFromAdminJson( $raw, $maxFields = 0 ) {
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		if ( isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ) {
			$fields = $decoded['fields'];
		} elseif ( self::isList( $decoded ) ) {
			$fields = $decoded;
		} else {
			$fields = [];
		}
		if ( empty( $fields ) ) {
			return '';
		}

		$normalized = [];
		$usedIds    = [];
		foreach ( $fields as $rawField ) {
			if ( ! is_array( $rawField ) ) {
				continue;
			}
			$normalized[] = self::normalizeAdminField( $rawField, $usedIds );
			if ( $maxFields > 0 && count( $normalized ) >= $maxFields ) {
				break;
			}
		}
		if ( empty( $normalized ) ) {
			return '';
		}

		return wp_json_encode( [ 'version' => self::CONFIG_VERSION, 'fields' => $normalized ] );
	}

	/**
	 * @param array<string, mixed> $rawField
	 * @param array<string, bool>  $usedIds  reference of ids already used in this configuration
	 *
	 * @return array<string, mixed> canonical field array
	 */
	protected static function normalizeAdminField( $rawField, &$usedIds ) {
		$type = self::normalizeType( isset( $rawField['type'] ) ? $rawField['type'] : '' );

		$id = isset( $rawField['id'] ) && is_string( $rawField['id'] ) ? preg_replace( '/[^A-Za-z0-9_]/', '', $rawField['id'] ) : '';
		if ( '' === $id || isset( $usedIds[ $id ] ) ) {
			$id = self::generateId( $usedIds );
		}
		$usedIds[ $id ] = true;

		$field = [ 'id' => $id, 'type' => $type ];

		if ( self::TYPE_HTML === $type ) {
			$field['title']   = self::truncate( sanitize_text_field( isset( $rawField['title'] ) ? $rawField['title'] : '' ), self::getMaxLabelLength() );
			$field['content'] = self::sanitizeHtmlContent( isset( $rawField['content'] ) ? $rawField['content'] : '' );

			return $field;
		}

		$field['label']    = self::truncate( sanitize_text_field( isset( $rawField['label'] ) ? $rawField['label'] : '' ), self::getMaxLabelLength() );
		$field['required'] = ! empty( $rawField['required'] );

		$description = sanitize_text_field( isset( $rawField['description'] ) ? $rawField['description'] : '' );
		if ( '' !== $description ) {
			$field['description'] = $description;
		}

		if ( self::typeSupportsPlaceholder( $type ) ) {
			$placeholder = sanitize_text_field( isset( $rawField['placeholder'] ) ? $rawField['placeholder'] : '' );
			if ( '' !== $placeholder ) {
				$field['placeholder'] = $placeholder;
			}
		}

		if ( self::isOption( $type ) ) {
			$field['options'] = self::normalizeAdminOptions( isset( $rawField['options'] ) ? $rawField['options'] : [] );
		}

		switch ( $type ) {
			case self::TYPE_TEXTAREA:
				if ( isset( $rawField['rows'] ) && is_numeric( $rawField['rows'] ) && (int) $rawField['rows'] > 0 ) {
					$field['rows'] = (int) $rawField['rows'];
				}
				if ( isset( $rawField['maxLength'] ) && is_numeric( $rawField['maxLength'] ) && (int) $rawField['maxLength'] > 0 ) {
					$field['maxLength'] = min( (int) $rawField['maxLength'], MM_WPFS_Utils::STRIPE_METADATA_VALUE_MAX_LENGTH );
				}
				break;
			case self::TYPE_NUMBER:
				foreach ( [ 'min', 'max', 'step' ] as $key ) {
					if ( isset( $rawField[ $key ] ) && is_numeric( $rawField[ $key ] ) ) {
						$field[ $key ] = $rawField[ $key ] + 0;
					}
				}
				break;
			case self::TYPE_DATE:
				foreach ( [ 'minDate', 'maxDate' ] as $key ) {
					if ( isset( $rawField[ $key ] ) && self::isValidDate( $rawField[ $key ] ) ) {
						$field[ $key ] = $rawField[ $key ];
					}
				}
				break;
		}

		return $field;
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	protected static function typeSupportsPlaceholder( $type ) {
		return in_array(
			$type,
			[ self::TYPE_TEXT, self::TYPE_PHONE, self::TYPE_NUMBER, self::TYPE_DATE, self::TYPE_TEXTAREA, self::TYPE_SELECT ],
			true
		);
	}

	/**
	 * @param mixed $rawOptions
	 *
	 * @return array<int, array{label:string, value:string}>
	 */
	protected static function normalizeAdminOptions( $rawOptions ) {
		$options = [];
		if ( ! is_array( $rawOptions ) ) {
			return $options;
		}
		$usedValues = [];
		foreach ( $rawOptions as $rawOption ) {
			if ( count( $options ) >= self::MAX_OPTIONS ) {
				break;
			}
			if ( is_string( $rawOption ) ) {
				$rawOption = [ 'label' => $rawOption, 'value' => '' ];
			}
			if ( ! is_array( $rawOption ) ) {
				continue;
			}
			$label = self::truncate( sanitize_text_field( isset( $rawOption['label'] ) ? $rawOption['label'] : '' ), self::getMaxLabelLength() );
			$value = self::sanitizeOptionValue( isset( $rawOption['value'] ) ? $rawOption['value'] : '' );
			if ( '' === $label && '' === $value ) {
				continue;
			}
			if ( '' === $value ) {
				$value = self::generateOptionValue( $label );
			}
			$base    = '' === $value ? 'option' : $value;
			$counter = 2;
			while ( '' === $value || isset( $usedValues[ $value ] ) ) {
				$suffix = '_' . $counter;
				// Keep the de-duplicated value within the configured length limit.
				$value = self::truncate( $base, self::MAX_OPTION_VALUE_LENGTH - strlen( $suffix ) ) . $suffix;
				$counter++;
			}
			$usedValues[ $value ] = true;
			if ( '' === $label ) {
				$label = $value;
			}
			$options[] = [ 'label' => $label, 'value' => $value ];
		}

		return $options;
	}

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	public static function sanitizeOptionValue( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9_\-]+/', '_', $value );
		$value = trim( $value, '_' );

		return self::truncate( $value, self::MAX_OPTION_VALUE_LENGTH );
	}

	/**
	 * @param string $label
	 *
	 * @return string
	 */
	public static function generateOptionValue( $label ) {
		$value = self::sanitizeOptionValue( $label );

		return '' === $value ? 'option' : $value;
	}

	/**
	 * Sanitize html block content for output.
	 *
	 * wp_kses_post() removes disallowed <script>/<style> tags but keeps the text
	 * inside them, which would otherwise render as visible CSS/JS to visitors.
	 * We strip those blocks entirely first. Inline style attributes that
	 * wp_kses_post() permits are preserved.
	 *
	 * @param mixed $content
	 *
	 * @return string
	 */
	public static function sanitizeHtmlContent( $content ) {
		$content = (string) $content;
		$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $content );
		$content = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $content );
		$content = preg_replace( '#</?(?:script|style)\b[^>]*>#i', '', $content );

		return wp_kses_post( $content );
	}

	/* -------------------------------------------------------------------------
	 * Submission: server side validation
	 * ---------------------------------------------------------------------- */

	/**
	 * Validate a submitted value against its definition.
	 *
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param mixed                  $value raw submitted value (string or array)
	 *
	 * @return string|null translated error message, or null when valid
	 */
	public static function validateSubmittedValue( $def, $value ) {
		if ( self::TYPE_HTML === $def->type ) {
			return null;
		}

		// Single-value field types must not receive an array (e.g. a crafted
		// `wpfs-custom-input[id][]=...` request). Reduce to a scalar so the
		// string casts below stay correct and never validate the literal "Array".
		if ( self::TYPE_MULTISELECT !== $def->type && is_array( $value ) ) {
			$value = self::firstScalarValue( $value );
		}

		$isEmpty = self::isEmptyValue( $def, $value );
		if ( $def->required && $isEmpty ) {
			return self::requiredError( $def );
		}
		if ( $isEmpty ) {
			return null;
		}

		switch ( $def->type ) {
			case self::TYPE_CHECKBOX:
				return null;

			case self::TYPE_SELECT:
				if ( ! self::isValidOption( $def, $value ) ) {
					return self::invalidError( $def );
				}

				return self::checkMetadataLength( $def, (string) $value );

			case self::TYPE_MULTISELECT:
				$values     = is_array( $value ) ? $value : [ $value ];
				$normalized = [];
				foreach ( $values as $single ) {
					$single = self::firstScalarValue( $single );
					if ( ! self::isValidOption( $def, $single ) ) {
						return self::invalidError( $def );
					}
					$normalized[] = $single;
				}

				return self::checkMetadataLength( $def, implode( ', ', $normalized ) );

			case self::TYPE_DATE:
				if ( ! self::isValidDate( $value ) ) {
					return self::invalidError( $def );
				}
				$minDate = $def->getSetting( 'minDate' );
				$maxDate = $def->getSetting( 'maxDate' );
				if ( ( $minDate && $value < $minDate ) || ( $maxDate && $value > $maxDate ) ) {
					return self::rangeError( $def );
				}

				return self::checkMetadataLength( $def, (string) $value );

			case self::TYPE_NUMBER:
				if ( ! is_numeric( $value ) ) {
					return self::invalidError( $def );
				}
				$number = $value + 0;
				$min    = $def->getSetting( 'min' );
				$max    = $def->getSetting( 'max' );
				if ( ( null !== $min && $number < $min ) || ( null !== $max && $number > $max ) ) {
					return self::rangeError( $def );
				}

				return self::checkMetadataLength( $def, (string) $value );

			case self::TYPE_PHONE:
				if ( ! self::isValidPhone( $value ) ) {
					return self::invalidError( $def );
				}

				return self::checkMetadataLength( $def, trim( (string) $value ) );

			case self::TYPE_TEXTAREA:
				$maxLength = $def->getSetting( 'maxLength' );
				if ( $maxLength && self::length( (string) $value ) > $maxLength ) {
					return self::tooLongError( $def );
				}

				return self::checkMetadataLength( $def, (string) $value );

			case self::TYPE_TEXT:
			default:
				return self::checkMetadataLength( $def, (string) $value );
		}
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param mixed                  $value
	 *
	 * @return bool
	 */
	public static function isEmptyValue( $def, $value ) {
		if ( self::TYPE_CHECKBOX === $def->type ) {
			return ! self::isCheckboxChecked( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $single ) {
				if ( '' !== trim( self::firstScalarValue( $single ) ) ) {
					return false;
				}
			}

			return true;
		}

		return '' === trim( (string) $value );
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isCheckboxChecked( $value ) {
		if ( is_array( $value ) ) {
			$value = end( $value );
		}
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, [ '1', self::CHECKBOX_VALUE_YES, 'on', 'true' ], true );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param mixed                  $value
	 *
	 * @return bool
	 */
	public static function isValidOption( $def, $value ) {
		foreach ( $def->options as $option ) {
			if ( (string) $option['value'] === (string) $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool true for a strict YYYY-MM-DD calendar date
	 */
	public static function isValidDate( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$parts = explode( '-', $value );

		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Light phone validation: trim, max length and an allowed character set.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function isValidPhone( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || self::length( $value ) > self::PHONE_MAX_LENGTH ) {
			return false;
		}

		return (bool) preg_match( '/^[0-9+\-\s().\/]+$/', $value );
	}

	/* -------------------------------------------------------------------------
	 * Submission: value projection (stored value, display value, metadata value)
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalize a raw submitted value into the value stored in the snapshot.
	 *
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param mixed                  $value
	 *
	 * @return string|string[] scalar value, or array for multiselect
	 */
	public static function storedValueFor( $def, $value ) {
		switch ( $def->type ) {
			case self::TYPE_CHECKBOX:
				return self::isCheckboxChecked( $value ) ? self::CHECKBOX_VALUE_YES : self::CHECKBOX_VALUE_NO;

			case self::TYPE_MULTISELECT:
				$values = is_array( $value ) ? $value : [ $value ];
				$result = [];
				foreach ( $values as $single ) {
					$single = self::firstScalarValue( $single );
					if ( '' !== $single && self::isValidOption( $def, $single ) ) {
						$result[] = $single;
					}
				}

				return $result;

			default:
				return trim( self::firstScalarValue( $value ) );
		}
	}

	/**
	 * Reduce a possibly-nested array to its first scalar value as a string.
	 *
	 * Guards single-value field handling against crafted array submissions so
	 * casts never trigger "Array to string conversion".
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public static function firstScalarValue( $value ) {
		while ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Human facing display value (option labels, Yes/No, etc.).
	 *
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param string|string[]        $storedValue
	 *
	 * @return string|string[]
	 */
	public static function displayValueFor( $def, $storedValue ) {
		switch ( $def->type ) {
			case self::TYPE_CHECKBOX:
				return self::CHECKBOX_VALUE_YES === $storedValue ? self::CHECKBOX_VALUE_YES : self::CHECKBOX_VALUE_NO;

			case self::TYPE_SELECT:
				return self::optionLabelForValue( $def, (string) $storedValue );

			case self::TYPE_MULTISELECT:
				$labels = [];
				foreach ( (array) $storedValue as $single ) {
					$labels[] = self::optionLabelForValue( $def, (string) $single );
				}

				return $labels;

			default:
				return is_array( $storedValue ) ? implode( ', ', $storedValue ) : (string) $storedValue;
		}
	}

	/**
	 * Metadata value for a stored value, or null when nothing should be sent.
	 *
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param string|string[]        $storedValue
	 *
	 * @return string|null
	 */
	public static function metadataValueFor( $def, $storedValue ) {
		if ( self::TYPE_HTML === $def->type ) {
			return null;
		}
		if ( self::TYPE_CHECKBOX === $def->type ) {
			return self::CHECKBOX_VALUE_YES === $storedValue ? self::CHECKBOX_VALUE_YES : null;
		}
		if ( self::TYPE_MULTISELECT === $def->type ) {
			$values = array_filter(
				(array) $storedValue,
				static function ( $single ) {
					return '' !== (string) $single;
				}
			);
			if ( empty( $values ) ) {
				return null;
			}

			return implode( ', ', $values );
		}
		$value = is_array( $storedValue ) ? implode( ', ', $storedValue ) : (string) $storedValue;

		return '' === trim( $value ) ? null : $value;
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param string                 $value
	 *
	 * @return string
	 */
	public static function optionLabelForValue( $def, $value ) {
		foreach ( $def->options as $option ) {
			if ( (string) $option['value'] === (string) $value ) {
				return (string) $option['label'];
			}
		}

		return (string) $value;
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Find a definition by its stable id.
	 *
	 * @param MM_WPFS_CustomFieldDef[] $defs
	 * @param string                   $id
	 *
	 * @return MM_WPFS_CustomFieldDef|null
	 */
	public static function findById( $defs, $id ) {
		foreach ( $defs as $def ) {
			if ( $def->id === $id ) {
				return $def;
			}
		}

		return null;
	}

	/**
	 * @param MM_WPFS_CustomFieldDef[] $defs
	 *
	 * @return array{version:int, fields:array<int, array<string, mixed>>}
	 */
	public static function toConfigArray( $defs ) {
		$fields = [];
		foreach ( $defs as $def ) {
			$fields[] = self::defToArray( $def );
		}

		return [ 'version' => self::CONFIG_VERSION, 'fields' => $fields ];
	}

	/**
	 * @param MM_WPFS_CustomFieldDef[] $defs
	 *
	 * @return string
	 */
	public static function toConfigJson( $defs ) {
		return wp_json_encode( self::toConfigArray( $defs ) );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return array<string, mixed>
	 */
	public static function defToArray( $def ) {
		$arr = [ 'id' => $def->id, 'type' => $def->type ];
		if ( self::TYPE_HTML === $def->type ) {
			$arr['title']   = $def->title;
			$arr['content'] = $def->content;

			return $arr;
		}
		$arr['label']    = $def->label;
		$arr['required'] = (bool) $def->required;
		if ( '' !== (string) $def->description ) {
			$arr['description'] = $def->description;
		}
		if ( '' !== (string) $def->placeholder ) {
			$arr['placeholder'] = $def->placeholder;
		}
		if ( self::isOption( $def->type ) ) {
			$arr['options'] = $def->options;
		}
		foreach ( $def->settings as $key => $value ) {
			$arr[ $key ] = $value;
		}

		return $arr;
	}

	/**
	 * Generate a stable unique field id.
	 *
	 * @param array<string, bool> $usedIds optional map of ids already in use
	 *
	 * @return string
	 */
	public static function generateId( $usedIds = [] ) {
		do {
			$id = 'cf_' . self::randomToken( 8 );
		} while ( isset( $usedIds[ $id ] ) );

		return $id;
	}

	/**
	 * @param int $length
	 *
	 * @return string
	 */
	protected static function randomToken( $length ) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$max   = strlen( $chars ) - 1;
		$token = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$token .= $chars[ wp_rand( 0, $max ) ];
		}

		return $token;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool true when $value is a sequential (list) array
	 */
	protected static function isList( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}
		$i = 0;
		foreach ( $value as $key => $unused ) {
			if ( $key !== $i ) {
				return false;
			}
			$i++;
		}

		return true;
	}

	/**
	 * UTF-8 aware truncation.
	 *
	 * @param string $value
	 * @param int    $length
	 *
	 * @return string
	 */
	protected static function truncate( $value, $length ) {
		$value = (string) $value;
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}

		return substr( $value, 0, $length );
	}

	/**
	 * UTF-8 aware length.
	 *
	 * @param string $value
	 *
	 * @return int
	 */
	protected static function length( $value ) {
		$value = (string) $value;
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}

		return strlen( $value );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 * @param string                 $metadataValue
	 *
	 * @return string|null
	 */
	protected static function checkMetadataLength( $def, $metadataValue ) {
		// Stripe's 500 limit is a character limit, so measure characters (mb_strlen via length())
		// to match the rest of the class and the character-based admin maxLength cap, not bytes.
		if ( self::length( (string) $metadataValue ) > MM_WPFS_Utils::STRIPE_METADATA_VALUE_MAX_LENGTH ) {
			return self::tooLongError( $def );
		}

		return null;
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return string
	 */
	protected static function localizedLabel( $def ): string {
		return MM_WPFS_Localization::translateLabel( $def->label );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return string
	 */
	protected static function requiredError( $def ): string {
		/* translators: %s: custom field label */
		return sprintf( __( "Please enter a value for '%s'", 'wp-full-stripe-free' ), self::localizedLabel( $def ) );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return string
	 */
	protected static function invalidError( $def ): string {
		/* translators: %s: custom field label */
		return sprintf( __( "The value for '%s' is invalid", 'wp-full-stripe-free' ), self::localizedLabel( $def ) );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return string
	 */
	protected static function tooLongError( $def ): string {
		/* translators: %s: custom field label */
		return sprintf( __( "The value for '%s' is too long", 'wp-full-stripe-free' ), self::localizedLabel( $def ) );
	}

	/**
	 * @param MM_WPFS_CustomFieldDef $def
	 *
	 * @return string
	 */
	protected static function rangeError( $def ): string {
		/* translators: %s: custom field label */
		return sprintf( __( "The value for '%s' is out of the allowed range", 'wp-full-stripe-free' ), self::localizedLabel( $def ) );
	}
}
