<?php

namespace ThemeIsle\WPFullPay\Admin\Localizers;

use ThemeIsle\WPFullPay\Assets;
use ThemeIsle\WPFullPay\Main;
use ThemeIsle\WPFullPay\Options;

abstract class AdminScriptLocalizer {
	/** @var Options */
	protected $options;
	protected $tab;
	protected $getParameters;

	/** @var array<string, array<string, mixed>>|false|null Null when this page has no CSS editor. */
	protected $codeEditorSettings = null;

	public function __construct( $options, $tab = null, $getParameters = null ) {
		$this->options = $options;
		$this->tab = $tab;
		$this->getParameters = $getParameters;
	}

	public function getSettingsOptions() {
		return [];
	}

	/** @return list<string> */
	public function getScriptDependencies(): array {
		if ( $this->codeEditorSettings === null ) {
			return [];
		}

		return $this->codeEditorSettings ? [ 'beautify-css-js', 'code-editor' ] : [ 'beautify-css-js' ];
	}

	protected function enqueueCssEditor(): void {
		wp_enqueue_script( 'beautify-css-js', Assets::scripts( 'beautify/beautify-css.js' ), [], Main::VERSION );
		$this->codeEditorSettings = wp_enqueue_code_editor( [
			'type' => 'text/css',
			'codemirror' => [
				'indentUnit' => 2,
				'tabSize' => 2,
				'indentWithTabs' => false,
				'lineNumbers' => true,
				'lineWrapping' => true,
				'styleActiveLine' => false,
				'highlightSelectionMatches' => true,
				'lint' => false
			]
		] );
	}

	public function enqueueScripts() {
	}

	abstract public function localizeScripts();
}
