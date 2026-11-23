<?php

namespace ThemeIsle\WPFullPay\Admin\Tables;

class BaseTable extends \WP_List_Table {

	const HTTPS_DASHBOARD_STRIPE_COM = "https://dashboard.stripe.com/";
	const PATH_TEST = "test/";
	const PATH_CUSTOMERS = 'customers/';
	const PATH_CHARGES = 'charges/';
	const PATH_PAYMENTS = 'payments/';
	const PATH_SUBSCRIPTIONS = 'subscriptions/';

	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url = remove_query_arg( 'paged', $current_url );

		if ( isset( $_GET['orderby'] ) ) {
			$current_orderby = $_GET['orderby'];
		} else {
			$current_orderby = '';
		}

		if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
			$current_order = 'desc';
		} else {
			$current_order = 'asc';
		}

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' .
				/* translators: Label for the 'Select all' option of status filter on list pages */
				__( 'Select All', 'wp-full-stripe-free' ) . '</label>'
				. '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = [ 'manage-column', "column-$column_key" ];

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, [ 'posts', 'comments', 'links' ] ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];

				if ( $current_orderby === $orderby ) {
					$order = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				} else {
					$order = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				if ( strpos( $column_display_name, '<br>' ) ) {
					$column_display_name_parts = explode( '<br>', $column_display_name );
					foreach ( $column_display_name_parts as $i => $part ) {
						if ( $i == 0 ) {
							$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $part . '</span><span class="sorting-indicator"></span></a>';
						} else {
							$column_display_name .= '<span class="wpfs-table-sub-header">' . $part . '</span>';
						}
					}
				} else {
					$column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
				}
			}

			$tag = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}

	/**
	 * @param $live_mode
	 *
	 * @return string
	 */
	protected function build_stripe_base_url( $live_mode ) {
		$href = self::HTTPS_DASHBOARD_STRIPE_COM;
		if ( $live_mode == 0 ) {
			$href .= self::PATH_TEST;
		}

		return $href;
	}

	/**
	 * Add extra markup in the toolbars before or after the list
	 *
	 * @param string $which , helps you decide if you add the markup after (bottom) or before (top) the list
	 */
	protected function extra_tablenav( $which ) {
		if ( $which == "top" ) {
			echo '<div class="wrap">';
		}
		if ( $which == "bottom" ) {
			echo '</div>';
		}
	}

}
