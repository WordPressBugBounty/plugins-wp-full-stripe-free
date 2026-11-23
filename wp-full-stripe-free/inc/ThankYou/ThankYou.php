<?php

namespace ThemeIsle\WPFullPay\ThankYou;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;
use ThemeIsle\WPFullPay\TransactionData\FormTransactionData;
use ThemeIsle\WPFullPay\TransactionData\TransactionDataService;
use ThemeIsle\WPFullPay\Utils;

class ThankYou {
    use LoggerAddOn;
    use StaticContextAddOn;

    const SHORTCODE_FULLSTRIPE_THANKYOU = 'fullstripe_thankyou';
    const SHORTCODE_FULLSTRIPE_THANKYOU_SUCCESS = 'fullstripe_thankyou_success';
    const SHORTCODE_FULLSTRIPE_THANKYOU_DEFAULT = 'fullstripe_thankyou_default';

    const FILTER_NAME_THANK_YOU_URL_PARAMETERS = 'fullstripe_thank_you_url_parameters';
    const FILTER_NAME_THANK_YOU_POST_TYPES = 'fullstripe_thank_you_post_types';

    const PARAM_NAME_TRANSACTION_DATA = 'transaction_data';

    /** @var Database */
    private $database = null;
    /** @var TransactionDataService */
    private $transactionDataService = null;
    /** @var Options */
    private $options = null;

    public function __construct( $loggerService ) {
        $this->initLogger( $loggerService, LoggerService::MODULE_RUNTIME );
        $this->options = new Options();

        $this->initStaticContext();

        add_shortcode( self::SHORTCODE_FULLSTRIPE_THANKYOU, [ $this, 'thankYouShortCode'] );
        add_shortcode( self::SHORTCODE_FULLSTRIPE_THANKYOU_SUCCESS, [ $this, 'thankYouSuccessShortCode'] );
        add_shortcode( self::SHORTCODE_FULLSTRIPE_THANKYOU_DEFAULT, [ $this, 'thankYouDefaultShortCode'] );

        $this->database                  = new Database();
        $this->transactionDataService    = new TransactionDataService();
    }

    function thankYouShortCode($attributes, $content = null ) {
		if ( ! Utils::isConnected() ) {
			$content = '';

			if ( current_user_can( 'administrator' ) ) {
                $content = sprintf(
                    /* translators: %1$s: opening paragraph tag, %2$s: opening link tag, %3$s: closing link tag, %4$s: closing paragraph tag */
					__( '%1$sYou are not connected to Stripe. Please connect to Stripe %2$sin the plugin settings%3$s.%4$s', 'wp-full-stripe-free' ),
					'<p>',
					'<a href="' . Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE ) . '">',
					'</a>',
					'</p>'
				);
			}

			return $content;
		}

        $transactionDataKey = isset( $_REQUEST[ TransactionDataService::REQUEST_PARAM_NAME_WPFS_TRANSACTION_DATA_KEY ] ) ? $_REQUEST[ TransactionDataService::REQUEST_PARAM_NAME_WPFS_TRANSACTION_DATA_KEY ] : null;
        $transactionData    = $this->transactionDataService->retrieve( $transactionDataKey );

        if ( $transactionData !== false ) {
            $_REQUEST[self::PARAM_NAME_TRANSACTION_DATA] = $transactionData;
        }

        return do_shortcode( $content );
    }

    function thankYouDefaultShortCode($attributes, $content = null ) {
		if ( ! Utils::isConnected() ) {
			$content = '';

			if ( current_user_can( 'administrator' ) ) {
                $content = sprintf(
                    /* translators: %1$s: opening paragraph tag, %2$s: opening link tag, %3$s: closing link tag, %4$s: closing paragraph tag */
					__( '%1$sYou are not connected to Stripe. Please connect to Stripe %2$sin the plugin settings%3$s.%4$s', 'wp-full-stripe-free' ),
					'<p>',
					'<a href="' . Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE ) . '">',
					'</a>',
					'</p>'
				);
			}

			return $content;
		}

        if ( isset( $_REQUEST[self::PARAM_NAME_TRANSACTION_DATA] ) ) {
            return '';
        } else {
            return do_shortcode( $content );
        }
    }

    function thankYouSuccessShortCode($attributes, $content = null ) {
		if ( ! Utils::isConnected() ) {
			$content = '';

			if ( current_user_can( 'administrator' ) ) {
                $content = sprintf(
                    /* translators: %1$s: opening paragraph tag, %2$s: opening link tag, %3$s: closing link tag, %4$s: closing paragraph tag */
					__( '%1$sYou are not connected to Stripe. Please connect to Stripe %2$sin the plugin settings%3$s.%4$s', 'wp-full-stripe-free' ),
					'<p>',
					'<a href="' . Menu::getAdminUrlBySlug( Menu::SLUG_SETTINGS_STRIPE ) . '">',
					'</a>',
					'</p>'
				);
			}

			return $content;
		}

        if ( isset( $_REQUEST[self::PARAM_NAME_TRANSACTION_DATA] ) ) {
            $transactionData = $_REQUEST[self::PARAM_NAME_TRANSACTION_DATA];
        } else {
            $transactionData = null;
        }

        if ( ! is_null( $transactionData ) && $transactionData instanceof FormTransactionData ) {

            /* @var $procesor ThankYouPostProcessor */
            $processor = ThankYouPostProcessorFactory::create( $this->database, $transactionData, $this->loggerService );
            return do_shortcode( $processor->process( $content ));

        } else {
            return '';
        }
    }

    /**
     * @param $form
     * @return array
     */
    public static function getPagesAndPosts( $form ) : array {
        $result = [];

        $filterParams = [
            'formName'  => $form->name,
            'formType'  => Utils::getFormType( $form )
        ];
        $query = new \WP_Query( [
            'nopaging' => true,
            'update_post_meta_cache' => false,
            'post_type' => apply_filters( self::FILTER_NAME_THANK_YOU_POST_TYPES, [ 'page' ], $filterParams )
        ] );

        foreach ( $query->posts as $pageOrPost ) {
            $item = new \StdClass;
            $item->id    = $pageOrPost->ID;
            $item->title = $pageOrPost->post_title;

            array_push( $result, $item );
        }

        return $result;
    }

    /**
     * @param $redirectUrl
     * @param $transactionDataKey
     * @param $filterParams
     * @return string
     */
    public static function getRedirectUrl( $redirectUrl, $transactionDataKey, $filterParams ) {
           $filteredResult = apply_filters( self::FILTER_NAME_THANK_YOU_URL_PARAMETERS, [], $filterParams );
           if ( !empty( $transactionDataKey ) ) {
               $filteredResult[ TransactionDataService::REQUEST_PARAM_NAME_WPFS_TRANSACTION_DATA_KEY ] = $transactionDataKey;
           }

           return add_query_arg( $filteredResult, $redirectUrl );
    }
}
