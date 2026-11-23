<?php

namespace ThemeIsle\WPFullPay\Help;

class HelpArticleVisualType {

	const TYPE_BOOKMARD = 'bookmark';
	const TYPE_GETTING_STARTED = 'getting-started';
	const TYPE_KNOWLEDGE_BASE = 'knowledge-base';
	const TYPE_FEEDBACK = 'feedback';
	const ICON_BOOKMARK = 'wpfs-icon-book-open-bookmark';
	const ICON_GETTING_STARTED = 'wpfs-icon-space-rocket-flying';
	const ICON_KNOWLEDGE_BASE = 'wpfs-icon-e-learning-monitor';
	const ICON_FEEDBACK = 'wpfs-icon-messages-bubble-square-text';

	private $name;
	private $cssClass;

	/**
	 * HelpArticleVisualType constructor.
	 *
	 * @param $name
	 * @param $cssClass
	 */
	public function __construct( $name, $cssClass ) {
		$this->name     = $name;
		$this->cssClass = $cssClass;
	}

	/**
	 * @return HelpArticleVisualType
	 */
	public static function bookmark() {
		return new HelpArticleVisualType( self::TYPE_BOOKMARD, self::ICON_BOOKMARK );
	}

	/**
	 * @return HelpArticleVisualType
	 */
	public static function gettingStarted() {
		return new HelpArticleVisualType( self::TYPE_GETTING_STARTED, self::ICON_GETTING_STARTED );
	}

	/**
	 * @return HelpArticleVisualType
	 */
	public static function knowledgeBase() {
		return new HelpArticleVisualType( self::TYPE_KNOWLEDGE_BASE, self::ICON_KNOWLEDGE_BASE );
	}

	/**
	 * @return HelpArticleVisualType
	 */
	public static function feedback() {
		return new HelpArticleVisualType( self::TYPE_FEEDBACK, self::ICON_FEEDBACK );
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed
	 */
	public function getCssClass() {
		return $this->cssClass;
	}

}
