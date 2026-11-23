<?php

namespace ThemeIsle\WPFullPay\Help;

class HelpArticle {

	private $caption;
	private $href;
	private $visualType;

	/**
	 * HelpArticle constructor.
	 *
	 * @param $caption
	 * @param $href
	 * @param $visualType
	 */
	public function __construct( $caption, $href, $visualType ) {
		$this->caption    = $caption;
		$this->href       = $href;
		$this->visualType = $visualType;
	}

	/**
	 * @return mixed
	 */
	public function getCaption() {
		return $this->caption;
	}

	/**
	 * @param mixed $caption
	 */
	public function setCaption( $caption ) {
		$this->caption = $caption;
	}

	/**
	 * @return mixed
	 */
	public function getHref() {
		return $this->href;
	}

	/**
	 * @return HelpArticleVisualType
	 */
	public function getVisualType() {
		return $this->visualType;
	}

}
