<?php

/**
 * An external standard for Auropay.
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for graph report
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */
class Payment_Overview_Report extends WC_Admin_Report {
	/**
	 * Output the report.
	 *
	 * @return void
	 */
	public function outputReport() {

		$ranges = array(
			'7day' => __( 'Last 7 Days', 'woocommerce' ),
			'month' => __( 'Day to Month', 'woocommerce' ),
			'last_month' => __( 'Last Month', 'woocommerce' ),
			'year' => __( 'Day to Year', 'woocommerce' ),
			'custom' => __( 'Custom', 'woocommerce' ),
		);

		//configure color of chart
		$this->chart_colours = array(
			'sales_amount' => 'green',
			'net_sales_amount' => '#3498db',
			'average' => '#b1d4ea',
			'net_average' => '#3498db',
			'order_count' => '#dbe1e3',
			'item_count' => '#ecf0f1',
			'shipping_amount' => '#5cc488',
			'coupon_amount' => '#f1c40f',
			'refund_amount' => 'orange',
			'failed_amount' => 'red',
		);

		$current_range = !empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

		if ( !in_array( $current_range, array( 'custom', 'year', 'last_month', '7day', 'month' ) ) ) {
			$current_range = '7day';
		}

		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );

		$hide_sidebar = 'true';
		include_once WC_HP_PLUGIN_PATH . '/includes/view/payment-overview-report-view.php';
	}

	/**
	 * Get report data
	 *
	 * @return void
	 */
	public function get_report_data() {
		if ( empty( $this->report_data ) ) {
			$this->query_report_data();
		}
		return $this->report_data;
	}

	/**
	 * Fetch data from database
	 *
	 * @return void
	 */
	private function query_report_data() {
		$this->report_data = new stdClass();

		/**
		 * Order totals by date. Charts should show GROSS amounts to avoid going -ve.
		 */
		$this->report_data->orders = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'_order_total' => array(
						'type' => 'meta',
						'function' => 'SUM',
						'name' => 'total_sales',
					),
					'post_date' => array(
						'type' => 'post_data',
						'function' => '',
						'name' => 'post_date',
					),
				),
				'where_meta' => array(
					array(
						'meta_key' => '_payment_method',
						'meta_value' => WC_HP_PLUGIN_NAME,
						'operator' => '=',
					),
				),
				'group_by' => $this->group_by_query,
				'order_by' => 'post_date ASC',
				'query_type' => 'get_results',
				'filter_range' => true,
				'order_types' => wc_get_order_types( 'sales-reports' ),
				'order_status' => array( 'completed', 'processing' ),
				'nocache' => true,
			)
		);

		/**
		 * If an order is 100% refunded we should look at the parent's totals, but the refunds dates.
		 * We also need to ensure each parent order's values are only counted/summed once.
		 */
		$this->report_data->full_refunds = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'_order_total' => array(
						'type' => 'meta',
						'function' => 'SUM',
						'name' => 'total_refund',
					),
					'post_date' => array(
						'type' => 'post_data',
						'function' => '',
						'name' => 'post_date',
					),
				),
				'where_meta' => array(
					array(
						'meta_key' => '_payment_method',
						'meta_value' => WC_HP_PLUGIN_NAME,
						'operator' => '=',
					),
				),
				'group_by' => $this->group_by_query,
				'order_by' => 'post_date ASC',
				'query_type' => 'get_results',
				'filter_range' => true,
				'order_types' => wc_get_order_types( 'sales-reports' ),
				'order_status' => array( 'refunded' ),
				'nocache' => true,

			)
		);

		/**
		 * Partial refunds
		 */
		$this->report_data->partial_refunds = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'ID' => array(
						'type' => 'post_data',
						'function' => '',
						'name' => 'refund_id',
					),
					'_refund_amount' => array(
						'type' => 'meta',
						'function' => '',
						'name' => 'total_refund',
					),
					'post_date' => array(
						'type' => 'post_data',
						'function' => '',
						'name' => 'post_date',
					),
					'order_item_type' => array(
						'type' => 'order_item',
						'function' => '',
						'name' => 'item_type',
						'join_type' => 'LEFT',
					),
					'_order_total' => array(
						'type' => 'meta',
						'function' => '',
						'name' => 'total_sales',
					),
					'_order_shipping' => array(
						'type' => 'meta',
						'function' => '',
						'name' => 'total_shipping',
						'join_type' => 'LEFT',
					),
					'_order_tax' => array(
						'type' => 'meta',
						'function' => '',
						'name' => 'total_tax',
						'join_type' => 'LEFT',
					),
					'_order_shipping_tax' => array(
						'type' => 'meta',
						'function' => '',
						'name' => 'total_shipping_tax',
						'join_type' => 'LEFT',
					),
					'_qty' => array(
						'type' => 'order_item_meta',
						'function' => 'SUM',
						'name' => 'order_item_count',
						'join_type' => 'LEFT',
					),
				),
				'group_by' => 'refund_id',
				'order_by' => 'post_date ASC',
				'query_type' => 'get_results',
				'filter_range' => true,
				'order_status' => false,
				'parent_order_status' => array( 'completed', 'processing', 'on-hold' ),
			)
		);

		foreach ( $this->report_data->partial_refunds as $key => $order ) {
			$this->report_data->partial_refunds[$key]->net_refund = $order->total_refund - ( $order->total_shipping + $order->total_tax + $order->total_shipping_tax );
		}
		$this->report_data->full_refunds = array_merge( $this->report_data->partial_refunds, $this->report_data->full_refunds );

		/**
		 * Order totals by date. Charts should show GROSS amounts to avoid going -ve.
		 */
		$this->report_data->failed = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'_order_total' => array(
						'type' => 'meta',
						'function' => 'SUM',
						'name' => 'total_sales',
					),
					'post_date' => array(
						'type' => 'post_data',
						'function' => '',
						'name' => 'post_date',
					),
				),
				'where_meta' => array(
					array(
						'meta_key' => '_payment_method',
						'meta_value' => WC_HP_PLUGIN_NAME,
						'operator' => '=',
					),
				),
				'group_by' => $this->group_by_query,
				'order_by' => 'post_date ASC',
				'query_type' => 'get_results',
				'filter_range' => true,
				'order_types' => wc_get_order_types( 'sales-reports' ),
				'order_status' => array( 'failed' ),
				'nocache' => true,
			)
		);

		foreach ( $this->report_data->full_refunds as $key => $order ) {
			$total_refund = is_numeric( $order->total_refund ) ? $order->total_refund : 0;
			$this->report_data->full_refunds[$key]->net_refund = $total_refund;
		}

		/**
		 * Total up refunds. Note: when an order is fully refunded, a refund line will be added.
		 */
		$this->report_data->total_refunds = 0;
		$this->report_data->refunded_orders = $this->report_data->full_refunds;
		foreach ( $this->report_data->refunded_orders as $key => $value ) {
			$this->report_data->total_refunds += floatval( $value->total_refund );
		}

		// Total the refunds and sales amounts. Sales subract refunds. Note - total_sales also includes shipping costs.
		$this->report_data->total_sales = wc_format_decimal( array_sum( wp_list_pluck( $this->report_data->orders, 'total_sales' ) ), 2 );
		$this->report_data->total_refunded_orders = absint( count( $this->report_data->full_refunds ) );

		// 3rd party filtering of report data
		$this->report_data = apply_filters( 'woocommerce_admin_report_data', $this->report_data );
	}

	/**
	 * Amount total in decimal
	 *
	 * @return void
	 */
	private function round_chart_totals( $amount ) {
		if ( is_array( $amount ) ) {
			return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
		} else {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}
	}

	/**
	 * Get the main chart.
	 *
	 * @return void
	 */
	public function get_main_chart() {
		global $wp_locale;

		if ( empty( $this->report_data ) ) {
			$this->query_report_data();
		}

		// Prepare data for report.
		$data = array(
			'order_amounts' => $this->prepare_chart_data( $this->report_data->orders, 'post_date', 'total_sales', $this->chart_interval, $this->start_date, $this->chart_groupby ),
			'refund_amounts' => $this->prepare_chart_data( $this->report_data->refunded_orders, 'post_date', 'total_refund', $this->chart_interval, $this->start_date, $this->chart_groupby ),
			'partial_refund_amounts' => $this->prepare_chart_data( $this->report_data->partial_refunds, 'post_date', 'total_refund', $this->chart_interval, $this->start_date, $this->chart_groupby ),
			'net_refund_amounts' => $this->prepare_chart_data( $this->report_data->refunded_orders, 'post_date', 'net_refund', $this->chart_interval, $this->start_date, $this->chart_groupby ),
			'failed_amounts' => $this->prepare_chart_data( $this->report_data->failed, 'post_date', 'total_sales', $this->chart_interval, $this->start_date, $this->chart_groupby ),
			'net_order_amounts' => array(),
			'gross_order_amounts' => array(),
		);

		foreach ( $data['order_amounts'] as $order_amount_key => $order_amount_value ) {
			$data['gross_order_amounts'][$order_amount_key] = $order_amount_value;
		}

		// 3rd party filtering of report data.
		$data = apply_filters( 'woocommerce_admin_report_chart_data', $data );

		// Encode in json format.
		$chart_data = wp_json_encode(
			array(
				'gross_order_amounts' => array_map( array( $this, 'round_chart_totals' ), array_values( $data['gross_order_amounts'] ) ),
				'refund_amounts' => array_map( array( $this, 'round_chart_totals' ), array_values( $data['refund_amounts'] ) ),
				'failed_amounts' => array_map( array( $this, 'round_chart_totals' ), array_values( $data['failed_amounts'] ) ),
			)
		);
		?>

<div class="chart-container chart-view" id="chrt">
	<div class="chart-placeholder main" style="height:300px" id="chart_box"></div>
</div>
<script type="text/javascript">
var main_chart;

jQuery(function() {
	var order_data = JSON.parse(decodeURIComponent('<?php echo rawurlencode( $chart_data ); ?>'));
	var drawGraph = function(highlight, type = 'line') {

		type = jQuery('#chart_type').val();

		if (highlight == 'sale') {

			if (type == 'bar') {
				var series = [{
					label: "<?php echo esc_js( __( 'Gross sales amount', 'woocommerce' ) ); ?>",
					data: order_data.gross_order_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['sales_amount'] ); ?>',
					bars: {
						fillColor: '<?php echo esc_html( $this->chart_colours['sales_amount'] ); ?>',
						fill: true,
						show: true,
						lineWidth: 0,
						barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
						align: 'center'
					},
					shadowSize: 0,
					enable_tooltip: true,
					stack: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Sales', 'woocommerce' ) ); ?>",
					<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>

				}];
			} else {
				var series = [{
					label: "Gross sales amount",
					data: order_data.gross_order_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['sales_amount'] ); ?>',
					points: {
						show: true,
						radius: 5,
						lineWidth: 2,
						fillColor: '#fff',
						fill: true
					},
					lines: {
						show: true,
						lineWidth: 2,
						fill: false
					},
					shadowSize: 0,
					enable_tooltip: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Sales', 'woocommerce' ) ); ?>",
					<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>

				}];
			}
		} else if (highlight == 'refunded') {

			if (type == 'bar') {

				var series = [{
					label: "<?php echo esc_js( __( 'Refund amount', 'woocommerce' ) ); ?>",
					data: order_data.refund_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['refund_amount'] ); ?>',
					bars: {
						fillColor: '<?php echo esc_html( $this->chart_colours['refund_amount'] ); ?>',
						fill: true,
						show: true,
						lineWidth: 0,
						barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
						align: 'center'
					},
					shadowSize: 0,
					enable_tooltip: true,
					stack: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Refunds', 'woocommerce' ) ); ?>",
					prepend_tooltip: "<?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?>"
				}];
			} else {
				var series = [{
					label: "<?php echo esc_js( __( 'Refund amount', 'woocommerce' ) ); ?>",
					data: order_data.refund_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['refund_amount'] ); ?>',
					points: {
						show: true,
						radius: 5,
						lineWidth: 2,
						fillColor: '#fff',
						fill: true
					},
					lines: {
						show: true,
						lineWidth: 2,
						fill: false
					},
					shadowSize: 0,
					enable_tooltip: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Refunds', 'woocommerce' ) ); ?>",
					prepend_tooltip: "<?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?>"
				}];
			}

		} else if (highlight == 'failed') {

			if (type == 'bar') {
				var series = [{
					label: "<?php echo esc_js( __( 'Failed amount', 'woocommerce' ) ); ?>",
					data: order_data.failed_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['failed_amount'] ); ?>',
					bars: {
						fillColor: '<?php echo esc_html( $this->chart_colours['failed_amount'] ); ?>',
						fill: true,
						show: true,
						lineWidth: 0,
						barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
						align: 'center'
					},
					stack: true,
					shadowSize: 0,
					enable_tooltip: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Failed', 'woocommerce' ) ); ?>",
					<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
				}];
			} else {
				var series = [{
					label: "<?php echo esc_js( __( 'Failed amount', 'woocommerce' ) ); ?>",
					data: order_data.failed_amounts,
					yaxis: 1,
					color: '<?php echo esc_js( $this->chart_colours['failed_amount'] ); ?>',
					points: {
						show: true,
						radius: 5,
						lineWidth: 2,
						fillColor: '#fff',
						fill: true
					},
					lines: {
						show: true,
						lineWidth: 2,
						fill: false
					},
					shadowSize: 0,
					enable_tooltip: true,
					append_tooltip: "<?php echo esc_html( ' ' . __( 'Failed', 'woocommerce' ) ); ?>",
					<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
				}];
			}

		} else {
			if (type == 'bar') {
				var series = [{
						label: "<?php echo esc_js( __( 'Gross sales amount', 'woocommerce' ) ); ?>",
						data: order_data.gross_order_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['sales_amount'] ); ?>',
						bars: {
							fillColor: '<?php echo esc_html( $this->chart_colours['sales_amount'] ); ?>',
							fill: true,
							show: true,
							lineWidth: 0,
							barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
							align: 'center'
						},
						shadowSize: 0,
						stack: true,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Sales', 'woocommerce' ) ); ?>",
						<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>

					},
					{
						label: "<?php echo esc_js( __( 'Refund amount', 'woocommerce' ) ); ?>",
						data: order_data.refund_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['refund_amount'] ); ?>',
						bars: {
							fillColor: '<?php echo esc_html( $this->chart_colours['refund_amount'] ); ?>',
							fill: true,
							show: true,
							lineWidth: 0,
							barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
							align: 'center'
						},
						shadowSize: 0,
						stack: true,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Refunds', 'woocommerce' ) ); ?>",
						prepend_tooltip: "<?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?>"
					},
					{
						label: "<?php echo esc_js( __( 'Failed amount', 'woocommerce' ) ); ?>",
						data: order_data.failed_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['failed_amount'] ); ?>',
						bars: {
							fillColor: '<?php echo esc_html( $this->chart_colours['failed_amount'] ); ?>',
							fill: true,
							show: true,
							lineWidth: 0,
							barWidth: <?php echo esc_html( $this->barwidth ); ?> * 0.5,
							align: 'center'
						},
						stack: true,
						shadowSize: 0,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Failed', 'woocommerce' ) ); ?>",
						<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
					},
				];

			} else {
				var series = [{
						label: "<?php echo esc_js( __( 'Gross sales amount', 'woocommerce' ) ); ?>",
						data: order_data.gross_order_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['sales_amount'] ); ?>',
						points: {
							show: true,
							radius: 5,
							lineWidth: 2,
							fillColor: '#fff',
							fill: true
						},
						lines: {
							show: true,
							lineWidth: 2,
							fill: false
						},
						shadowSize: 0,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Sales', 'woocommerce' ) ); ?>",
						<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>

					},
					{
						label: "<?php echo esc_js( __( 'Refund amount', 'woocommerce' ) ); ?>",
						data: order_data.refund_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['refund_amount'] ); ?>',
						points: {
							show: true,
							radius: 5,
							lineWidth: 2,
							fillColor: '#fff',
							fill: true
						},
						lines: {
							show: true,
							lineWidth: 2,
							fill: false
						},
						shadowSize: 0,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Refunds', 'woocommerce' ) ); ?>",
						prepend_tooltip: "<?php echo wp_kses_post( get_woocommerce_currency_symbol() ); ?>"
					},
					{
						label: "<?php echo esc_js( __( 'Failed amount', 'woocommerce' ) ); ?>",
						data: order_data.failed_amounts,
						yaxis: 1,
						color: '<?php echo esc_js( $this->chart_colours['failed_amount'] ); ?>',
						points: {
							show: true,
							radius: 5,
							lineWidth: 2,
							fillColor: '#fff',
							fill: true
						},
						lines: {
							show: true,
							lineWidth: 2,
							fill: false
						},
						shadowSize: 0,
						enable_tooltip: true,
						append_tooltip: "<?php echo esc_html( ' ' . __( 'Failed', 'woocommerce' ) ); ?>",
						<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
					},
				];
			}
		}

		if (highlight !== 'undefined' && series[highlight]) {
			highlight_series = series[highlight];

			highlight_series.color = '#9c5d90';

			if (highlight_series.bars) {
				highlight_series.bars.fillColor = '#9c5d90';
			}

			if (highlight_series.lines) {
				highlight_series.lines.lineWidth = 5;
			}
		}

		main_chart = jQuery.plot(
			jQuery('.chart-placeholder.main'),
			series, {
				legend: {
					show: false
				},
				grid: {
					color: '#aaa',
					borderColor: 'transparent',
					borderWidth: 0,
					hoverable: true
				},
				xaxes: [{
					color: '#aaa',
					position: "bottom",
					tickColor: 'transparent',
					mode: "time",
					timeformat: "<?php echo ( 'day' === $this->chart_groupby ) ? '%d %b' : '%b'; ?>",
					monthNames: JSON.parse(decodeURIComponent(
						'<?php echo rawurlencode( wp_json_encode( array_values( $wp_locale->month_abbrev ) ) ); ?>'
					)),
					tickLength: 1,
					minTickSize: [1, "<?php echo esc_js( $this->chart_groupby ); ?>"],
					font: {
						color: "#aaa"
					}
				}],
				yaxes: [{
						min: 0,
						minTickSize: 1,
						tickDecimals: 2,
						color: '#d4d9dc',
						font: {
							color: "#aaa"
						},
						tickFormatter: function(v, axis) {
							return v.toFixed(axis.tickDecimals)
						}

					},
					{
						position: "right",
						min: 0,
						tickDecimals: 2,
						alignTicksWithAxis: 1,
						color: 'transparent',
						font: {
							color: "#aaa"
						}
					}
				],
			}
		);
		jQuery('.chart-placeholder').resize();
	}

	drawGraph('sale');
	jQuery('.highlight_series').hover(
		function() {
			drawGraph(jQuery(this).data('series'));
		},
		function() {
			drawGraph();
		}
	);
	jQuery('#sale_box').hover(
		function() {
			jQuery(this).css('cursor', 'pointer');
			jQuery(this).css('background-color', '#f0f0f1');
			jQuery('#refunded_box').css('background-color', '#fff');
			jQuery('#failed_box').css('background-color', '#fff');

		}
	);
	jQuery('#refunded_box').hover(
		function() {
			jQuery(this).css('cursor', 'pointer');
			jQuery(this).css('background-color', '#f0f0f1');
			jQuery('#failed_box').css('background-color', '#fff');
			jQuery('#sale_box').css('background-color', '#fff');

		}
	);
	jQuery('#failed_box').hover(
		function() {
			jQuery(this).css('cursor', 'pointer');
			jQuery(this).css('background-color', '#f0f0f1');
			jQuery('#refunded_box').css('background-color', '#fff');
			jQuery('#sale_box').css('background-color', '#fff');

		}
	);
	jQuery('#sale_box').click(
		function() {
			drawGraph('sale');
			jQuery('#data_type').val('sale');
			jQuery('#show_all_type').prop('checked', false);
			jQuery('#summary_box_type').html('Sales');
			jQuery('#sales_stat_details').show();
			jQuery('#refunded-stat-details').hide();
			jQuery('#failed-stat-details').hide();
		}
	);
	jQuery('#refunded_box').click(
		function() {
			drawGraph('refunded');
			jQuery('#data_type').val('refunded');
			jQuery('#show_all_type').prop('checked', false);
			jQuery('#summary_box_type').html('Refunded');
			jQuery('#sales_stat_details').hide();
			jQuery('#failed-stat-details').hide();
			jQuery('#refunded-stat-details').show();
		}
	);
	jQuery('#failed_box').click(
		function() {
			drawGraph('failed');
			jQuery('#data_type').val('failed');
			jQuery('#show_all_type').prop('checked', false);
			jQuery('#summary_box_type').html('Failed');
			jQuery('#sales_stat_details').hide();
			jQuery('#refunded-stat-details').hide();
			jQuery('#failed-stat-details').show();
		}
	);
	jQuery('#line_chart').click(
		function() {
			jQuery('#chart_type').val('line');
			drawGraph(jQuery('#data_type').val());
			jQuery('#line_chart').prop('disabled', true);
			jQuery('#line_chart').css('background-color', 'gray');
			jQuery('#bar_chart').css('background-color', '');
			jQuery('#bar_chart').prop('disabled', false);
		}
	);
	jQuery('#bar_chart').click(
		function() {
			jQuery('#chart_type').val('bar');
			drawGraph(jQuery('#data_type').val());
			jQuery('#line_chart').prop('disabled', false);
			jQuery('#bar_chart').prop('disabled', true);
			jQuery('#bar_chart').css('background-color', 'gray');
			jQuery('#line_chart').css('background-color', '');
		}
	);
	jQuery('#show_all_type').click(
		function() {
			if (jQuery(this).is(":checked")) {
				drawGraph('all');
			} else {
				drawGraph(jQuery('#data_type').val());
			}
		}
	);
	jQuery('#custom').click(
		function() {
			jQuery('#custom-box').show();
			jQuery('#custom').addClass('active');
			jQuery('.odate_range').removeClass('active');
		}
	);

	jQuery(window).resize(function() {
		if (jQuery(document).width() < 700) {
			jQuery('#chart_box').css('height', '150px');
			jQuery('#chrt').css('width', '98.8%');

			jQuery('#sale_img').hide();
			jQuery('#refunded_img').hide();
			jQuery('#failed_img').hide();
			jQuery('#summary_main_box_type').hide();
			jQuery('#year').hide();

			jQuery('.c_order_status').hide();
			jQuery('.c_order_type').show();
			jQuery('.c_payment_method').hide();
			jQuery('.c_auth_code').hide();
			jQuery('.c_card_type').hide();

			jQuery('#order_number').css('width', '50px');
			jQuery('#order_date').css('width', '80px');

		} else {
			jQuery('#chart_box').css('height', '300px');
			jQuery('#chrt').css('width', '99.5%');
			jQuery('#sale_img').show();
			jQuery('#refunded_img').show();
			jQuery('#failed_img').show();
			jQuery('#summary_main_box_type').show();

			jQuery('#month').show();
			jQuery('#last_month').show();
			jQuery('#year').show();
			jQuery('#7day').show();

			jQuery('.c_order_status').show();
			jQuery('.c_order_type').show();
			jQuery('.c_payment_method').show();
			jQuery('.c_auth_code').show();
			jQuery('.c_card_type').show();

			jQuery('#order_number').css('width', '80px');
			jQuery('#order_date').css('width', '150px');
		}
	});
});

jQuery(window).bind("load", function() {

	jQuery('#sale_box').css('background-color', '#f0f0f1');

	if (jQuery(document).width() < 700) {
		jQuery('#chart_box').css('height', '150px');
		jQuery('#chrt').css('width', '98.8%');
		jQuery('#sale_img').hide();
		jQuery('#refunded_img').hide();
		jQuery('#failed_img').hide();
		jQuery('#summary_main_box_type').hide();
		jQuery('#year').hide();
		jQuery('.c_order_status').hide();
		jQuery('.c_order_type').show();
		jQuery('.c_payment_method').hide();
		jQuery('.c_auth_code').hide();
		jQuery('.c_card_type').hide();
		jQuery('#order_number').css('width', '50px');
		jQuery('#order_date').css('width', '80px');

	} else {
		jQuery('#chart_box').css('height', '300px');
		jQuery('#chrt').css('width', '99.5%');
		jQuery('#sale_img').show();
		jQuery('#refunded_img').show();
		jQuery('#failed_img').show();
		jQuery('#summary_main_box_type').show();
		jQuery('#month').show();
		jQuery('#last_month').show();
		jQuery('#year').show();
		jQuery('#7day').show();
		jQuery('.c_order_status').show();
		jQuery('.c_order_type').show();
		jQuery('.c_payment_method').show();
		jQuery('.c_auth_code').show();
		jQuery('.c_card_type').show();
		jQuery('#order_number').css('width', '80px');
		jQuery('#order_date').css('width', '150px');
	}
});
</script>
<?php
	}
}
?>
