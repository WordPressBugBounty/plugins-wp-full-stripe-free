<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

use ThemeIsle\WPFullPay\Admin\Menu;
use ThemeIsle\WPFullPay\Context\StaticContextAddOn;
use ThemeIsle\WPFullPay\Database;
use ThemeIsle\WPFullPay\Logging\LoggerAddOn;
use ThemeIsle\WPFullPay\Logging\LoggerService;
use ThemeIsle\WPFullPay\Options;

abstract class TransactionsTable extends \WP_List_Table {
	use LoggerAddOn;
	use StaticContextAddOn;

	const ITEMS_PER_PAGE = 10;

	/** @var array<string, string> Header classes that some columns add to wpfs-data-table__th, by column. */
	const HEADER_CLASSES = [];

	/** @var Database */
	protected $db = null;

	/** @var Options */
	protected $options = null;

	/** @var array */
	protected $formDisplayNameCache;

	public function __construct( $loggerService, $args ) {
		parent::__construct( $args );

		$this->initLogger( $loggerService, LoggerService::MODULE_ADMIN );
		$this->options = new Options();
		$this->db = new Database();

		$this->initStaticContext();

		$this->initFormDisplayNameCache();
	}

	abstract protected function getForms(): array;

	protected function initFormDisplayNameCache() {
		$this->formDisplayNameCache = [];

		$forms = $this->getForms();
		foreach ( $forms as $form ) {
			$this->formDisplayNameCache[ $form->name ] = $form->displayName;
		}
	}

	/**
	 * @throws \Exception
	 */
	public function prepare_items() {
		$query = $this->getQuery();
		$numItems = $this->db->runQuery( $query );

		$this->configurePagination( $numItems );
		$this->addPageOffsetToQuery( $query );

		$this->configureColumnHeaders();

		$this->items = $this->db->getResults( $query );
	}

	/**
	 * @return array
	 */
	protected function getOrderParameters(): array {
		$orderByParam = isset( $_REQUEST[ Menu::PARAM_NAME_ORDER_BY ] ) ? $_REQUEST[ Menu::PARAM_NAME_ORDER_BY ] : null;
		$orderBy      = 'created';
		
		if ( is_string( $orderByParam ) && ! empty( $orderByParam ) ) {
			$orderBy = trim( sanitize_sql_orderby( $orderByParam ) );
		}
		$order = ! empty( $_REQUEST[ Menu::PARAM_NAME_ORDER ] ) ? trim( $_REQUEST[ Menu::PARAM_NAME_ORDER ] ) : 'desc';

		if ( ! in_array( $order, [ 'desc', 'asc' ], true ) ) {
			$order = 'desc';
		}

		return [ $orderBy, $order ];
	}

	protected function addOrderByStatementToQuery( &$query ) {
		list( $orderBy, $order ) = $this->getOrderParameters();

		if ( ! empty( $orderBy ) && ! empty( $order ) ) {
			$query .= ' ORDER BY ' . $orderBy . ' ' . $order;
		}
	}

	/**
	 * @param $whereStatement
	 * @param $condition
	 */
	protected function extendWhereStatement( &$whereStatement, $condition ) {
		if ( ! isset( $whereStatement ) ) {
			$whereStatement = ' WHERE ';
		} else {
			$whereStatement .= ' AND ';
		}

		$whereStatement .= $condition;
	}

	/**
	 * @param $numItems
	 */
	protected function configurePagination( $numItems ) {
		$totalPages = (int) ceil( $numItems / self::ITEMS_PER_PAGE );

		$this->set_pagination_args( [
			"total_items" => $numItems,
			"total_pages" => $totalPages,
			"per_page" => self::ITEMS_PER_PAGE,
		] );
	}

	protected function addPageOffsetToQuery( &$query ) {
		$currentPage = $this->get_pagenum();
		if ( ! empty( $currentPage ) ) {
			$offset = ( $currentPage - 1 ) * self::ITEMS_PER_PAGE;
			$query .= ' LIMIT ' . (int) $offset . ',' . (int) self::ITEMS_PER_PAGE;
		}
	}

	/**
	 *
	 */
	protected function configureColumnHeaders() {
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, $hidden, $sortable ];
	}

	protected function createCompositeColumnLabel( $column1, $column2 ) {
		return "{$column1} / {$column2}";
	}

	public function display() {
		?>
		<table class="<?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<thead>
				<?php $this->print_column_headers(); ?>
			</thead>

			<tbody id="the-list">
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>
		</table>
		<?php
		$this->pagination( 'bottom' );
	}

	/**
	 * @param string $which
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}
		if ( $this->_pagination_args['total_items'] == 0 ) {
			return;
		}

		$removableQueryArgs = wp_removable_query_args();
		$currentUrl = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$currentUrl = remove_query_arg( $removableQueryArgs, $currentUrl );

		$totalItems = $this->_pagination_args['total_items'];
		$totalPages = $this->_pagination_args['total_pages'];
		$currentPage = $this->get_pagenum();

		$firstItem = ( $currentPage - 1 ) * self::ITEMS_PER_PAGE + 1;
		$lastItem = $firstItem + self::ITEMS_PER_PAGE - 1 < $totalItems ? $firstItem + self::ITEMS_PER_PAGE - 1 : $totalItems;

		$disableFirst = $disableLast = $disablePrev = $disableNext = false;

		if ( $currentPage == 1 ) {
			$disableFirst = true;
			$disablePrev = true;
		}
		if ( $currentPage == 2 ) {
			$disableFirst = true;
		}
		if ( $currentPage == $totalPages ) {
			$disableLast = true;
			$disableNext = true;
		}
		if ( $currentPage == $totalPages - 1 ) {
			$disableLast = true;
		}

		if ( 1 === $totalItems ) {
			$showingLabel =
			/* translators: Paging label indicating which items are displayed when there is only one item
			 */
				__( 'Showing one item',
					'wp-full-stripe-free' );
		} else {
			$showingLabel = sprintf(
			/* translators: Paging label indicating which items are displayed
			 * p1: First item displayed
			 * p2: Last item displayed
			 * p3: Total number of items
			 */
				_n( 'Showing %1$d-%2$d of %3$d item',
					'Showing %1$d-%2$d of %3$d items',
					$totalItems,
					'wp-full-stripe-free' ),
				$firstItem,
				$lastItem,
				$totalItems
			);
		}
		$output = "<div class='wpfs-data-table-foot'>\n";
		$output .= "  <div class='wpfs-data-table-foot__show'>" . esc_html( $showingLabel ) . "</div>\n";
		$output .= "  <div class='wpfs-data-table-foot__pagination'>\n";
		$output .= "    <div class='wpfs-pagination'>\n";
		$output .= "      <ul class='wpfs-pagination__list'>\n";

		$output .= "        <li class='wpfs-pagination__item'>\n";
		if ( $disableFirst ) {
			$output .= "          ←←";
		} else {
			$firstPageUrl = esc_url( remove_query_arg( 'paged', $currentUrl ) );
			$firstPageTitle =
				/* translators: Label of the button showing the first page of the list */
				__( 'First page', 'wp-full-stripe-free' );
			$output .= "          <a class='wpfs-pagination__link' href='{$firstPageUrl}'>←←</a>";
		}
		$output .= "        </li>\n";

		$output .= "        <li class='wpfs-pagination__item'>\n";
		if ( $disablePrev ) {
			$output .= "          ←";
		} else {
			$prevPageUrl = esc_url( add_query_arg( 'paged', max( 1, $currentPage - 1 ), $currentUrl ) );
			$prevPageTitle =
				/* translators: Label of the button showing the previous page of the list */
				__( 'Previous page', 'wp-full-stripe-free' );
			$output .= "          <a class='wpfs-pagination__link' href='{$prevPageUrl}'>←</a>";
		}
		$output .= "        </li>\n";

		$currentPageTitle =
			/* translators: Label of the button showing the current page of the list */
			__( 'Current Page', 'wp-full-stripe-free' );
		$output .= "        <li class='wpfs-pagination__item wpfs-pagination__item--active'>\n";
		$output .= "          {$currentPage}\n";
		$output .= "        </li>\n";

		$output .= "        <li class='wpfs-pagination__item'>\n";
		if ( $disableNext ) {
			$output .= "          →";
		} else {
			$nextPageUrl = esc_url( add_query_arg( 'paged', min( $totalPages, $currentPage + 1 ), $currentUrl ) );
			$nextPageTitle =
				/* translators: Label of the button showing the next page of the list */
				__( 'Next page', 'wp-full-stripe-free' );
			$output .= "          <a class='wpfs-pagination__link' href='{$nextPageUrl}'>→</a>";
		}
		$output .= "        </li>\n";

		$output .= "        <li class='wpfs-pagination__item'>\n";
		if ( $disableLast ) {
			$output .= "          →→";
		} else {
			$lastPageUrl = esc_url( add_query_arg( 'paged', $totalPages, $currentUrl ) );
			$lastPageTitle =
				/* translators: Label of the button showing the last page of the list */
				__( 'Last page', 'wp-full-stripe-free' );
			$output .= "          <a class='wpfs-pagination__link' href='{$lastPageUrl}'>→→</a>";
		}
		$output .= "        </li>\n";

		$output .= "      </ul>\n";
		$output .= "    </div>\n";
		$output .= "  </div>\n";
		$output .= "</div>\n";

		$this->__set( '_pagination', $output );

		echo $output;
	}

	/**
	 * @param object $row A row of the table's query.
	 *
	 * @return array{0: mixed, 1: mixed} The row's database id and Stripe id.
	 */
	abstract protected function rowIds( $row ): array;

	/**
	 * @return array<string, string> The method that renders each column's cell, by column.
	 */
	abstract protected function cellRenderers(): array;

	/**
	 * @param bool $withId
	 */
	public function print_column_headers( $withId = true ): void {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$columnHeader = '<tr class="wpfs-data-table__tr">';
		foreach ( $columns as $key => $displayName ) {
			$cssClasses = [ 'wpfs-data-table__th' ];
			if ( isset( static::HEADER_CLASSES[ $key ] ) ) {
				array_push( $cssClasses, static::HEADER_CLASSES[ $key ] );
			}

			$classAttributeValue = implode( " ", $cssClasses );
			$columnHeader .= "<th class='{$classAttributeValue}'>" . esc_html( $displayName ) . "</th>";
		}
		$columnHeader .= '</tr>';

		echo $columnHeader;
	}

	public function display_rows(): void {
		$rows = $this->items;
		list( $columns, $hidden ) = $this->get_column_info();
		$cellRenderers = $this->cellRenderers();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				list( $dbId, $stripeId ) = $this->rowIds( $row );
				$rowHtml = "<tr class='wpfs-data-table__tr' data-db-id='" . esc_attr( $dbId ) . "' data-stripe-id='" . esc_attr( $stripeId ) . "'>";

				foreach ( $columns as $key => $displayName ) {
					if ( isset( $cellRenderers[ $key ] ) ) {
						$rowHtml .= $this->{$cellRenderers[ $key ]}( $row );
					}
				}

				$rowHtml .= "</tr>";
				echo $rowHtml;
			}
		}
	}

	/**
	 * A filter from the transactions page's search form: trimmed, or null when it's empty.
	 */
	protected function requestFilter( string $param ): ?string {
		return ! empty( $_REQUEST[ $param ] ) ? trim( $_REQUEST[ $param ] ) : null;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	protected function lookupFormDisplayName( $name ) {
		return array_key_exists( $name, $this->formDisplayNameCache ) ? $this->formDisplayNameCache[ $name ] : $name;
	}

	/**
	 * @param $transaction
	 *
	 * @return string
	 */
	protected function renderPaymentMethodCell( $transaction ): string {
		$class = 'wpfs-credit-card--generic';
		if ( isset( $transaction->payment_method ) ) {
			switch ( $transaction->payment_method ) {
				case 'alipay':
					$class = 'wpfs-alipay';
					break;
				case 'amazon_pay':
					$class = 'wpfs-amazonpay';
					break;
				case 'bancontact':
					$class = 'wpfs-bancontact';
					break;
				case 'blik':
					$class = 'wpfs-blik';
					break;
				case 'cashapp':
					$class = 'wpfs-cashapp';
					break;
				case 'eps':
					$class = 'wpfs-eps';
					break;
				case 'grabpay':
					$class = 'wpfs-grabpay';
					break;
				case 'ideal':
					$class = 'wpfs-ideal';
					break;
				case 'klarna':
					$class = 'wpfs-klarna';
					break;
				case 'mobilepay':
					$class = 'wpfs-mobilepay';
					break;
				case 'p24':
					$class = 'wpfs-przelewy24';
					break;
				case 'paynow':
					$class = 'wpfs-paynow';
					break;
				case 'revolut_pay':
					$class = 'wpfs-revolut_pay';
					break;
				case 'twint':
					$class = 'wpfs-twint';
					break;
				case 'wechat_pay':
					$class = 'wpfs-wechatpay';
					break;
				default:
					// default is card
					break;
			}
		}
		$html = "
        <td class='wpfs-data-table__td wpfs-data-table__td--w40'>
            <div class='wpfs-credit-card " . $class . "'></div>
        </td>";

		return $html;
	}

	/**
	 * @return string[]
	 */
	protected function get_table_classes() {
		return [ 'wpfs-data-table' ];
	}

}
