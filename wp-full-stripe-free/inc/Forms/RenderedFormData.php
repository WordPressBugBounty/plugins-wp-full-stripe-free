<?php

namespace ThemeIsle\WPFullPay\Forms;

class RenderedFormData {

	/** @var int */
	private $total = 0;

	public function renderLater( $type ) {
		if ( FormTypes::exists( $type ) ) {
			$this->total += 1;
		}

		return $this;
	}

	/**
	 * @return int
	 */
	public function getTotal() {
		return $this->total;
	}

}
