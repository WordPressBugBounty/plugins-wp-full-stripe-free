<?php

namespace ThemeIsle\WPFullPay\Help;

class ContextHelp {

	protected $page;
	protected $section;

	protected $relatedArticles = [];
	protected $globalArticles = [];

	/**
	 * ContextHelp constructor.
	 *
	 * @param $page
	 * @param $section
	 */
	public function __construct( $page, $section ) {
		$this->page    = $page;
		$this->section = $section;
	}

	/**
	 * @return mixed
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * @return mixed
	 */
	public function getSection() {
		return $this->section;
	}

	/**
	 * @param HelpArticle[] $relatedArticles
	 */
	public function setRelatedArticles( $relatedArticles ) {
		$this->relatedArticles = $relatedArticles;
	}

	/**
	 * @return HelpArticle[]
	 */
	public function getGlobalArticles() {
		return $this->globalArticles;
	}

	/**
	 * @param HelpArticle[] $globalArticles
	 */
	public function setGlobalArticles( array $globalArticles ) {
		$this->globalArticles = $globalArticles;
	}

}
