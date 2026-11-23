<?php

namespace ThemeIsle\WPFullPay\Exceptions;

/**
 * Class UserFriendlyException
 *
 * This exception can be thrown in action and event hooks, and it's content (title, message)
 * will be displayed as a global message above the form which has invoked it.
 */
class UserFriendlyException extends \Exception {
	protected $_title;

	/**
	 * UserFriendlyException constructor.
	 * @param string $message
	 * @param int $code
	 * @param \Throwable|null $previous
	 */
	public function __construct( $message = "", $code = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * @return mixed
	 */
	public function getTitle() {
		return $this->_title;
	}

	/**
	 * @param mixed $title
	 */
	public function setTitle( $title ) {
		$this->_title = $title;
	}
}
