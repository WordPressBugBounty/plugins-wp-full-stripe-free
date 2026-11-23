<?php

namespace ThemeIsle\WPFullPay\Forms\Fields;

/**
 * Normalized definition of a single custom field.
 *
 * Produced by {@see CustomFields::parse()} from either the legacy
 * `{{`-delimited label format or the JSON v1 configuration format. This is the
 * authoritative, type-aware description used for rendering, validation and
 * snapshot generation.
 */
class CustomFieldDef {

	/** @var string Stable identifier (e.g. cf_ab12cd34, or cf_legacy_0). */
	public $id = '';
	/** @var string One of the CustomFields::TYPE_* constants. */
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
		return CustomFields::isInteractive( $this->type );
	}

	public function isOption(): bool {
		return CustomFields::isOption( $this->type );
	}

	public function isMultiValue(): bool {
		return CustomFields::isMultiValue( $this->type );
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
