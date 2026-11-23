<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

/**
 * Used by WPFSM, WPFP_Mailchimp
 */
class Control {

	const ESCAPE_TYPE_NONE = 'none';
	const ESCAPE_TYPE_ATTRIBUTE = 'attribute';
	const ESCAPE_TYPE_HTML = 'html';
	const PHP_MULTI_VALUE_POST_PARAMETER_NAME_POSTFIX = '[]';

	protected $id;
	protected $name;
	protected $placeholder;
	protected $index;
	protected $caption;
	protected $value;
	protected $tooltip;
	protected $label;
	protected $labelEscape = self::ESCAPE_TYPE_HTML;
	protected $labelAttributes = [];
	protected $options = [];
	protected $attributes = [];
	protected $metadata = [];
	/** @var  boolean $multiValue */
	protected $multiValue = false;

	/**
	 * Control constructor.
	 *
	 * @param $id
	 * @param $name
	 * @param $placeholder
	 */
	public function __construct( $id, $name, $placeholder ) {
		$this->id = $id;
		$this->name = $name;
		$this->placeholder = $placeholder;
	}

	/**
	 * @param mixed $name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * @param mixed $caption
	 */
	public function setCaption( $caption ) {
		$this->caption = $caption;
	}

	/**
	 * @param mixed $value
	 */
	public function setValue( $value ) {
		$this->value = $value;
	}

	/**
	 * @param mixed $tooltip
	 */
	public function setTooltip( $tooltip ) {
		$this->tooltip = $tooltip;
	}

	/**
	 * @param mixed $index
	 */
	public function setIndex( $index ) {
		$this->index = $index;
	}

	/**
	 * @param mixed $label
	 * @param string $labelEscape
	 */
	public function setLabel( $label, $labelEscape = self::ESCAPE_TYPE_HTML ) {
		$this->label = $label;
		if ( self::ESCAPE_TYPE_NONE === $labelEscape ) {
			$this->labelEscape = self::ESCAPE_TYPE_NONE;
		} else {
			$this->labelEscape = self::ESCAPE_TYPE_HTML;
		}
	}

	/**
	 * @param string $labelEscape
	 */
	public function setLabelEscape( $labelEscape ) {
		$this->labelEscape = $labelEscape;
	}

	/**
	 * @param array $labelAttributes
	 */
	public function setLabelAttributes( $labelAttributes ) {
		$this->labelAttributes = $labelAttributes;
	}

	/**
	 * @param array $options
	 */
	public function setOptions( $options ) {
		$this->options = $options;
	}

	/**
	 * @param array $attributes
	 */
	public function setAttributes( $attributes ) {
		$this->attributes = $attributes;
	}

	public function getMetadata( $key ) {
		return $this->metadata[ $key ];
	}
	/**
	 * @param array $metadata
	 */
	public function setMetadata( $metadata ) {
		$this->metadata = $metadata;
	}

	/**
	 * @param boolean $multiValue
	 */
	public function setMultiValue( $multiValue ) {
		$this->multiValue = $multiValue;
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function name( $echo = true ) {
		$name = $this->name;
		if ( $this->multiValue ) {
			$name .= self::PHP_MULTI_VALUE_POST_PARAMETER_NAME_POSTFIX;
		}

		return $this->echoOrReturnValue( $echo, $name );
	}

	/**
	 * @param $echo
	 * @param $fieldValue
	 * @param string $escapeType
	 *
	 * @return bool|null|string|void
	 */
	protected function echoOrReturnValue( $echo, $fieldValue, $escapeType = self::ESCAPE_TYPE_ATTRIBUTE ) {
		$escapedValue = null;
		if ( self::ESCAPE_TYPE_ATTRIBUTE === $escapeType ) {
			$escapedValue = esc_attr( $fieldValue );
		} elseif ( self::ESCAPE_TYPE_HTML === $escapeType ) {
			$escapedValue = esc_html( $fieldValue );
		} else {
			$escapedValue = $fieldValue;
		}
		if ( $echo ) {
			echo ( $escapedValue );

			return true;
		} else {
			return $escapedValue;
		}
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function index( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->index );
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function id( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->id );
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function placeholder( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->placeholder );
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function caption( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->caption, self::ESCAPE_TYPE_HTML );
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function value( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->value );
	}

	/**
	 * @param bool $echo
	 *
	 * @return mixed
	 */
	public function tooltip( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->tooltip );
	}

	/**
	 * @param bool $echo
	 *
	 * @return bool|null|string|void
	 */
	public function label( $echo = true ) {
		return $this->echoOrReturnValue( $echo, $this->label, $this->labelEscape );
	}

	/**
	 * @param bool $echo
	 *
	 * @return array
	 */
	public function labelAttributes( $echo = true ) {
		if ( $echo ) {

			$labelAttributesAsString = $this->attributesAsString( $this->labelAttributes );

			echo ( $labelAttributesAsString );
		}

		return $this->labelAttributes;
	}

	/**
	 * @return array
	 */
	public function metadata() {
		return $this->metadata;
	}

	/**
	 * @param array $attributes
	 *
	 * @return string
	 *
	 * Used by WPFSM, WPFP_Mailchimp
	 */
	public static function attributesAsString( array $attributes ) {
		$attributesAsString = implode(
			' ',
			array_map(
				function ($value, $key) {
					return $key . '=' . '"' . esc_attr( $value ) . '"';
				},
				$attributes,
				array_keys( $attributes )
			)
		);

		return $attributesAsString;
	}

	/**
	 * @return array
	 */
	public function options() {
		return $this->options;
	}

	/**
	 * @param bool $echo
	 *
	 * @return array
	 */
	public function attributes( $echo = true ) {
		if ( $echo ) {

			$attributesAsString = $this->attributesAsString( $this->attributes );

			echo ( $attributesAsString );
		}

		return $this->attributes;
	}
}
