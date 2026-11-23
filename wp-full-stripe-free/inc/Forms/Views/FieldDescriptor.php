<?php

namespace ThemeIsle\WPFullPay\Forms\Views;

class FieldDescriptor {

	/**
	 * @var string
	 */
	protected $type;
	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var string
	 */
	protected $class;
	/**
	 * @var string
	 */
	protected $selector;
	/**
	 * @var string
	 */
	protected $errorClass;
	/**
	 * @var string
	 */
	protected $errorSelector;

	/**
	 * FieldDescriptor constructor.
	 *
	 * @param string $type
	 * @param string $name
	 * @param string $class
	 * @param string $selector
	 * @param string $errorClass
	 * @param string $errorSelector
	 */
	public function __construct( $type, $name, $class, $selector, $errorClass, $errorSelector ) {
		$this->type = $type;
		$this->name = $name;
		$this->class = $class;
		$this->selector = $selector;
		$this->errorClass = $errorClass;
		$this->errorSelector = $errorSelector;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->class;
	}

}
