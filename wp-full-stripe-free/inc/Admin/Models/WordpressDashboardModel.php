<?php

namespace ThemeIsle\WPFullPay\Admin\Models;

use ThemeIsle\WPFullPay\Admin\Validators\WordpressDashboardValidator;
use ThemeIsle\WPFullPay\Admin\Views\WordpressDashboardViewConstants;
use ThemeIsle\WPFullPay\Forms\Models\Binder;
use ThemeIsle\WPFullPay\Forms\Models\BindingResult;
use ThemeIsle\WPFullPay\Forms\Models\Model;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Main;

class WordpressDashboardModel implements Binder {
	use Model;
	use LoggerAddOn;

	protected $decimalSeparator;
	protected $useSymbolNotCode;
	protected $currencySymbolAtFirstPosition;
	protected $putSpaceBetweenSymbolAndAmount;

	public function __construct( $loggerService ) {
		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );

		$this->__validator = new WordpressDashboardValidator( $loggerService );
	}

	/**
	 * @return mixed
	 */
	public function getDecimalSeparator() {
		return $this->decimalSeparator;
	}

	/**
	 * @return mixed
	 */
	public function getUseSymbolNotCode() {
		return $this->useSymbolNotCode;
	}

	/**
	 * @return mixed
	 */
	public function getCurrencySymbolAtFirstPosition() {
		return $this->currencySymbolAtFirstPosition;
	}

	/**
	 * @return mixed
	 */
	public function getPutSpaceBetweenSymbolAndAmount() {
		return $this->putSpaceBetweenSymbolAndAmount;
	}

	public function bind() {
		return $this->bindByArray( $_POST );
	}

	public function bindByArray( $postData ) {
		$bindingResult = new BindingResult();

		$this->decimalSeparator = $this->getSanitizedArrayParam( $postData, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_DECIMAL_SEPARATOR, Main::DECIMAL_SEPARATOR_SYMBOL_DOT );
		$this->useSymbolNotCode = $this->getSanitizedArrayParam( $postData, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_NOT_CODE, 1 );
		$this->currencySymbolAtFirstPosition = $this->getSanitizedArrayParam( $postData, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SYMBOL_AT_FIRST_POSITION, 1 );
		$this->putSpaceBetweenSymbolAndAmount = $this->getSanitizedArrayParam( $postData, WordpressDashboardViewConstants::FIELD_WP_DASHBOARD_SPACE_BETWEEN_SYMBOL_AND_AMOUNT, 0 );

		if ( isset( $this->__validator ) ) {
			$this->__validator->validate( $bindingResult, $this );
		}

		return $bindingResult;
	}

	public function getData() {
		$data = [
			'decimalSeparator' => $this->decimalSeparator,
			'useSymbolNotCode' => $this->useSymbolNotCode,
			'currencySymbolAtFirstPosition' => $this->currencySymbolAtFirstPosition,
			'putSpaceBetweenSymbolAndAmount' => $this->putSpaceBetweenSymbolAndAmount,
		];

		return $data;
	}

	public function getPostData() {
		return [];
	}

	public function afterBind() {
		// noop
	}
}
