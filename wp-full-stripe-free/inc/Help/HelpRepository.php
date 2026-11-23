<?php

namespace ThemeIsle\WPFullPay\Help;

use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;

/**
 * Class HelpRepository
 *
 * In-memory data structure for context-sensitive help.
 */
class HelpRepository {
    use LoggerAddOn;

	private $data = [];

    public function __construct( $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
    }

    /**
	 * @param ContextHelp $contextHelp
	 * @param $page
	 * @param $section
	 */
	public function addHelp( ContextHelp $contextHelp, $page, $section ) {
        $this->logger->debug(__FUNCTION__, 'CALLED, page=' . $page . ', section=' . $section);

		$this->data[ $page ][ $section ] = $contextHelp;
	}

	/**
	 * @param $page
	 * @param $section
	 *
	 * @return ContextHelp
	 */
	public function getHelp( $page, $section ) {
        $this->logger->debug(__FUNCTION__, 'CALLED, page=' . $page . ', section=' . $section);

		$contextHelp = null;
		if ( array_key_exists( $page, $this->data ) ) {
            $this->logger->debug(__FUNCTION__, 'Page found');

			if ( array_key_exists( $section, $this->data[ $page ] ) ) {
				$contextHelp = $this->data[ $page ][ $section ];

                $this->logger->debug(__FUNCTION__, 'Section found');
			} else {
				$contextHelp = $this->data[ $page ][''];

                $this->logger->debug(__FUNCTION__, 'Section not found');
			}
		} else {
            $this->logger->debug(__FUNCTION__, 'Page not found');
		}

		// tnagy retrieve default help entry
		if ( is_null( $contextHelp ) ) {
			$contextHelp = $this->data[''][''];

            $this->logger->debug(__FUNCTION__, 'Default help entry retrieved');
		}

		return $contextHelp;
	}

}
