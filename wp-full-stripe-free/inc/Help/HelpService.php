<?php

namespace ThemeIsle\WPFullPay\Help;

use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

class HelpService {
    use LoggerAddOn;

	/**
	 * @var HelpRepository
	 */
	private $repository;

	/**
	 * HelpService constructor.
	 */
	public function __construct( $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
	}

	private function initRepository() {
		if ( is_null( $this->repository ) ) {
			$this->repository = HelpRepositoryFactory::create( $this->loggerService );
		}
	}

	/**
	 * @param array $requestParameters
	 *
	 * @return ContextHelp
	 */
	public function getContextSensitiveHelp( $requestParameters = [] ) {

		$this->initRepository();
		
		$page = array_key_exists( 'page', $requestParameters ) ? $requestParameters['page'] : null;
		$tab  = array_key_exists( 'tab', $requestParameters ) ? $requestParameters['tab'] : null;
		$type = array_key_exists( 'type', $requestParameters ) ? $requestParameters['type'] : null;

		if ( ! is_null( $type ) ) {
			return $this->repository->getHelp( $page, $type );
		} else {
			return $this->repository->getHelp( $page, $tab );
		}
	}

}
