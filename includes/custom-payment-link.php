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
 * This is for creating custom submenu - for showing payment transaction details
 *
 * @return void
 */
function registerPaymentLink() {
	$options = get_option( 'woocommerce_auropay_settings' );
	if ( 'yes' == $options['payments'] ) {
		add_submenu_page( 'woocommerce', 'Payment Overview', 'Payments Overview', 'manage_options', 'wc-payment_overview', 'paymentLinkCallback' );
	}
}

add_action( 'admin_enqueue_scripts', 'addAdminStylesJs' );
/**
 * Register required css and js file
 *
 * @return void
 */
function addAdminStylesJs() {
	wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_style( 'hp_admin_styles', WC_HP_PLUGIN_URL . '/assets/style.css', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_style( 'jquery-ui-style', WC()->plugin_url() . '/assets/css/jquery-ui/jquery-ui.min.css', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'jquery', get_site_url() . '/wp-includes/js/jquery/jquery.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'jquery-blockui-js', WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'jquery-ui-datepicker', get_site_url() . '/wp-includes/js/jquery/ui/datepicker.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'wc-reports', WC()->plugin_url() . '/assets/js/admin/reports.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'flot-js', WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'flot-resize-js', WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.resize.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'flot-time-js', WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.time.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'flot-pie-js', WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.pie.min.js', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'flot-stack-js', WC()->plugin_url() . '/assets/js/jquery-flot/jquery.flot.stack.min.js', array(), WC_HP_PLUGIN_VERSION );
}

/**
 * This will generate query for selcted date range filter on payment page
 *
 * @param string $current_range date range
 *
 * @return string
 */
function calculateCurrentRange( $current_range ) {
	global $start_date;
	global $end_date;

	switch ( $current_range ) {
		case 'custom':
			if ( !empty( $_GET['start_date'] ) ) {
				$start_date = max( strtotime( '-20 years' ), strtotime( sanitize_text_field( $_GET['start_date'] ) ) );
			}

			if ( empty( $_GET['end_date'] ) ) {
				$end_date = strtotime( 'midnight', current_time( 'timestamp' ) );
			} else {
				$end_date = strtotime( 'midnight', strtotime( sanitize_text_field( $_GET['end_date'] ) ) );
			}
			break;
		case 'year':
			$start_date = strtotime( gmdate( 'Y-01-01', current_time( 'timestamp' ) ) );
			$end_date = strtotime( 'midnight', current_time( 'timestamp' ) );
			break;
		case 'last_month':
			$first_day_current_month = strtotime( gmdate( 'Y-m-01', current_time( 'timestamp' ) ) );
			$start_date = strtotime( gmdate( 'Y-m-01', strtotime( '-1 DAY', $first_day_current_month ) ) );
			$end_date = strtotime( gmdate( 'Y-m-t', strtotime( '-1 DAY', $first_day_current_month ) ) );
			break;
		case 'month':
			$start_date = strtotime( gmdate( 'Y-m-01', current_time( 'timestamp' ) ) );
			$end_date = strtotime( 'midnight', current_time( 'timestamp' ) );
			break;
		case '7day':
			$start_date = strtotime( '-6 days', strtotime( 'midnight', current_time( 'timestamp' ) ) );
			$end_date = strtotime( 'midnight', current_time( 'timestamp' ) );
			break;
		default:
			break;
	}
	$end_date = gmdate( 'Y-m-d', $end_date ) . ' 23:59:59';
	return gmdate( 'Y-m-d', $start_date ) . '###' . $end_date;
}

/**
 * This will generate breakdown query - creditcard/debitcard/ACH
 *
 * @param string $type type of order
 *
 * @return array
 */
function getBrekdownDetailQuery( $type ) {
	global $wpdb;
	global $tot_payments;
	global $tot_refunded;
	global $tot_failed;
	global $wc_stats_table;
	global $post_meta_table;
	global $payment_method;
	global $and_where;
	global $start_date;
	global $end_date;

	//check type of transaction
	if ( 'sale' == $type ) {
		$pay_check = $tot_payments;
		//get total creditcard payment
		$total_credit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-processing' OR os.status='wc-completed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				3,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_credit_card_payments = round( isset( $total_credit_card_payments['total_payments'] ) ? $total_credit_card_payments['total_payments'] : 0, 2 );

		//get total debit card payment
		$total_debit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-processing' OR os.status='wc-completed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				4,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_debit_card_payments = round( isset( $total_debit_card_payments['total_payments'] ) ? $total_debit_card_payments['total_payments'] : 0, 2 );

		$total_netbanking_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value = %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value = %d
				AND (os.status='wc-processing' OR os.status='wc-completed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				7,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_netbanking_payments = round( isset( $total_netbanking_payments['total_payments'] ) ? $total_netbanking_payments['total_payments'] : 0, 2 );

		//get total UPI payment
		$total_upi_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-processing' OR os.status='wc-completed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				6,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_upi_payments = round( isset( $total_upi_payments['total_payments'] ) ? $total_upi_payments['total_payments'] : 0, 2 );

		//get total Wallet payment
		$total_wallet_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-processing' OR os.status='wc-completed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				8,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_wallet_payments = round( isset( $total_wallet_payments['total_payments'] ) ? $total_wallet_payments['total_payments'] : 0, 2 );
	} elseif ( 'refunded' == $type ) {
		$pay_check = $tot_refunded;
		//get total creditcard payment
		$total_credit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-refunded')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				3,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_credit_card_payments = round( isset( $total_credit_card_payments['total_payments'] ) ? $total_credit_card_payments['total_payments'] : 0, 2 );

		//get total debit card payment
		$total_debit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-refunded')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				4,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_debit_card_payments = round( isset( $total_debit_card_payments['total_payments'] ) ? $total_debit_card_payments['total_payments'] : 0, 2 );

		$total_netbanking_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value = %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value = %d
				AND (os.status='wc-refunded')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				7,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_netbanking_payments = round( isset( $total_netbanking_payments['total_payments'] ) ? $total_netbanking_payments['total_payments'] : 0, 2 );

		//get total UPI payment
		$total_upi_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-refunded')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				6,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_upi_payments = round( isset( $total_upi_payments['total_payments'] ) ? $total_upi_payments['total_payments'] : 0, 2 );

		//get total Wallet payment
		$total_wallet_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-refunded')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				8,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_wallet_payments = round( isset( $total_wallet_payments['total_payments'] ) ? $total_wallet_payments['total_payments'] : 0, 2 );
	} elseif ( 'failed' == $type ) {
		$pay_check = $tot_failed;
		//get total creditcard payment
		$total_credit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-failed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				3,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_credit_card_payments = round( isset( $total_credit_card_payments['total_payments'] ) ? $total_credit_card_payments['total_payments'] : 0, 2 );

		//get total debit card payment
		$total_debit_card_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-failed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				4,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_debit_card_payments = round( isset( $total_debit_card_payments['total_payments'] ) ? $total_debit_card_payments['total_payments'] : 0, 2 );

		$total_netbanking_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value = %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value = %d
				AND (os.status='wc-failed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				7,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_netbanking_payments = round( isset( $total_netbanking_payments['total_payments'] ) ? $total_netbanking_payments['total_payments'] : 0, 2 );

		//get total UPI payment
		$total_upi_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-failed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				6,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$tot_upi_payments = round( isset( $total_upi_payments['total_payments'] ) ? $total_upi_payments['total_payments'] : 0, 2 );

		//get total Wallet payment
		$total_wallet_payments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(`total_sales`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				INNER JOIN {$wpdb->prefix}postmeta pm1
				ON os.order_id = pm1.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND pm1.meta_key = '_hp_transaction_channel_type'
				AND pm1.meta_value= %d
				AND (os.status='wc-failed')
				AND os.net_total > 0
				AND os.date_created > %s
				AND os.date_created < %s ",
				$payment_method,
				8,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
		$tot_wallet_payments = round( isset( $total_wallet_payments['total_payments'] ) ? $total_wallet_payments['total_payments'] : 0, 2 );
	}
	//get total creditcard partial refund
	$total_credit_card_partial_refund_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_hp_transaction_channel_type'
			AND pm.meta_value= %d
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			3,
			$start_date,
			$end_date
		),
		ARRAY_A
	);
	$tot_credit_card_partial_refund_payments = round( isset( $total_credit_card_partial_refund_payments['total_payments'] ) ? $total_credit_card_partial_refund_payments['total_payments'] : 0, 2 );

	if ( 'sale' == $type ) {
		$tot_credit_card_payments = $tot_credit_card_payments - $tot_credit_card_partial_refund_payments;
	} elseif ( 'refunded' == $type ) {
		$tot_credit_card_payments = $tot_credit_card_payments + $tot_credit_card_partial_refund_payments;
	}

	//get debit card partial refund
	$total_debit_card_partial_refund_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_hp_transaction_channel_type'
			AND pm.meta_value= %d
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			4,
			$start_date,
			$end_date
		),
		ARRAY_A
	);
	$tot_debit_card_partial_refund_payments = round( isset( $total_debit_card_partial_refund_payments['total_payments'] ) ? $total_debit_card_partial_refund_payments['total_payments'] : 0, 2 );

	//get Total Net Banking partial refund
	$total_netbanking_partial_refund_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_hp_transaction_channel_type'
			AND pm.meta_value= %d
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			7,
			$start_date,
			$end_date
		),
		ARRAY_A
	);
	$tot_netbanking_partial_refund_payments = round( isset( $total_netbanking_partial_refund_payments['total_payments'] ) ? $total_netbanking_partial_refund_payments['total_payments'] : 0, 2 );

	//get Total UPI partial refund
	$total_upi_partial_refund_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_hp_transaction_channel_type'
			AND pm.meta_value= %d
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			6,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	$tot_upi_partial_refund_payments = round( isset( $total_upi_partial_refund_payments['total_payments'] ) ? $total_upi_partial_refund_payments['total_payments'] : 0, 2 );

	//get Total Wallet partial refund
	$total_wallet_partial_refund_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_hp_transaction_channel_type'
			AND pm.meta_value= %d
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			8,
			$start_date,
			$end_date
		),
		ARRAY_A
	);
	$tot_wallet_partial_refund_payments = round( isset( $total_wallet_partial_refund_payments['total_payments'] ) ? $total_wallet_partial_refund_payments['total_payments'] : 0, 2 );

	//check if any sale diff
	$diff_sale_amount = $pay_check - ( $tot_credit_card_payments + $tot_debit_card_payments + $tot_netbanking_payments + $tot_upi_payments + $tot_wallet_payments );
	if ( $diff_sale_amount > 0 ) {
		$tot_credit_card_payments = $tot_credit_card_payments + $diff_sale_amount;
	}

	return array(
		'creditcard' => $tot_credit_card_payments,
		'debitcard' => $tot_debit_card_payments,
		'netbanking' => $tot_netbanking_payments,
		'upi' => $tot_upi_payments,
		'wallet' => $tot_wallet_payments,
	);
}

/**
 * This will show page transaction details - custom payment submenu page
 *
 * @return void
 */
function paymentLinkCallback() {
	global $wpdb;
	global $tot_payments;
	global $tot_refunded;
	global $tot_failed;
	global $tot_credit_card_payments;
	global $tot_debit_card_payments;
	global $tot_netbanking_payments;
	global $tot_upi_payments;
	global $tot_wallet_payments;

	global $wc_stats_table;
	global $post_meta_table;
	global $payment_method;
	global $and_where;

	global $sale_tot_credit_card_payments;
	global $sale_tot_debit_card_payments;
	global $sale_tot_netbanking_payments;
	global $sale_tot_upi_payments;
	global $sale_tot_wallet_payments;
	global $refunded_tot_credit_card_payments;
	global $refunded_tot_debit_card_payments;
	global $refunded_tot_netbanking_payments;
	global $refunded_tot_upi_payments;
	global $refunded_tot_wallet_payments;
	global $failed_tot_credit_card_payments;
	global $failed_tot_debit_card_payments;
	global $failed_tot_netbanking_payments;
	global $failed_tot_upi_payments;
	global $failed_tot_wallet_payments;
	global $start_date;
	global $end_date;

	$and_where = '';
	//check if range is set
	if ( isset( $_GET['range'] ) ) {
		$range = sanitize_text_field( $_GET['range'] );
	} else {
		$range = '7day';
	}

	$and_where = calculateCurrentRange( $range );
	$apDates = explode( '###', $and_where );
	$start_date = $apDates[0];
	$end_date = $apDates[1];
	$range_filter = 'range=' . $range;

	//check start date and end date
	if ( 'custom' == $range ) {
		$cstart_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
		$cend_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : '';
		$_GET['wc_reports_nonce'] = isset( $_GET['wc_reports_nonce'] ) ? sanitize_text_field( $_GET['wc_reports_nonce'] ) : '';
		$range_filter .= '&start_date=' . $cstart_date . '&end_date=' . $cend_date . '&wc_reports_nonce=' . sanitize_text_field( $_GET['wc_reports_nonce'] );
	}

	$wc_stats_table = $wpdb->prefix . 'wc_order_stats';
	$post_meta_table = $wpdb->postmeta;
	$payment_method = 'auropay';

	//Sales queries
	$total_payments = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(`total_sales`) AS total_payments
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( (os.order_id = pm.post_id ) )
			WHERE  pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND os.total_sales > 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//Partial Refunded queries
	$total_partial_refunds = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(os1.total_sales) * (-1) AS total_partial_refunds
			FROM {$wpdb->prefix}wc_order_stats AS os1
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os1.parent_id = pm.post_id)
			INNER JOIN {$wpdb->prefix}wc_order_stats AS os
			ON ( os.order_id = os1.parent_id)
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND (os1.parent_id > 0 )
			AND os1.total_sales < 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//Refunded queries
	$total_refunded = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(`total_sales`)  AS total_refunded
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON ( os.order_id = pm.post_id)
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-refunded')
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//Failed queries
	$total_failed = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(`net_total` +`tax_total` +`shipping_total`) AS total_failed
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON os.order_id = pm.post_id
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-failed')
			AND os.net_total > 0
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	if ( $total_partial_refunds['total_partial_refunds'] > 0 ) {
		$tot_partial_refunds = round( isset( $total_partial_refunds['total_partial_refunds'] ) ? $total_partial_refunds['total_partial_refunds'] : 0, 2 );
	} else {
		$tot_partial_refunds = 0;
	}

	if ( $total_payments['total_payments'] > 0 ) {
		$tot_payments = round( isset( $total_payments['total_payments'] ) ? $total_payments['total_payments'] : 0, 2 );
		$tot_payments = $tot_payments - $tot_partial_refunds;
	} else {
		$tot_payments = 0;
	}

	if ( $total_refunded['total_refunded'] > 0 ) {
		$tot_refunded = round( isset( $total_refunded['total_refunded'] ) ? $total_refunded['total_refunded'] : 0, 2 );
		$tot_refunded = $tot_refunded + $tot_partial_refunds;
	} else {
		$tot_refunded = 0;
	}

	if ( $total_failed['total_failed'] > 0 ) {
		$tot_failed = round( isset( $total_failed['total_failed'] ) ? $total_failed['total_failed'] : 0, 2 );
	} else {
		$tot_failed = 0;
	}

	//get brekdown details
	$breakdown = getBrekdownDetailQuery( 'sale' );
	$sale_tot_credit_card_payments = $breakdown['creditcard'];
	$sale_tot_debit_card_payments = $breakdown['debitcard'];
	$sale_tot_netbanking_payments = $breakdown['netbanking'];
	$sale_tot_upi_payments = $breakdown['upi'];
	$sale_tot_wallet_payments = $breakdown['wallet'];

	$breakdown = getBrekdownDetailQuery( 'refunded' );
	$refunded_tot_credit_card_payments = $breakdown['creditcard'];
	$refunded_tot_debit_card_payments = $breakdown['debitcard'];
	$refunded_tot_netbanking_payments = $breakdown['netbanking'];
	$refunded_tot_upi_payments = $breakdown['upi'];
	$refunded_tot_wallet_payments = $breakdown['wallet'];

	$breakdown = getBrekdownDetailQuery( 'failed' );
	$failed_tot_credit_card_payments = $breakdown['creditcard'];
	$failed_tot_debit_card_payments = $breakdown['debitcard'];
	$failed_tot_netbanking_payments = $breakdown['netbanking'];
	$failed_tot_upi_payments = $breakdown['upi'];
	$failed_tot_wallet_payments = $breakdown['wallet'];

	//get total payments amount
	$tot_payments = number_format( (float) $tot_payments, 2, '.', '' );
	$tot_refunded = number_format( (float) $tot_refunded, 2, '.', '' );
	$tot_failed = number_format( (float) $tot_failed, 2, '.', '' );

	$sale_tot_credit_card_payments = number_format( (float) $sale_tot_credit_card_payments, 2, '.', '' );
	$sale_tot_debit_card_payments = number_format( (float) $sale_tot_debit_card_payments, 2, '.', '' );
	$sale_tot_netbanking_payments = number_format( (float) $sale_tot_netbanking_payments, 2, '.', '' );
	$sale_tot_upi_payments = number_format( (float) $sale_tot_upi_payments, 2, '.', '' );
	$sale_tot_wallet_payments = number_format( (float) $sale_tot_wallet_payments, 2, '.', '' );

	$refunded_tot_credit_card_payments = number_format( (float) $refunded_tot_credit_card_payments, 2, '.', '' );
	$refunded_tot_debit_card_payments = number_format( (float) $refunded_tot_debit_card_payments, 2, '.', '' );
	$refunded_tot_netbanking_payments = number_format( (float) $refunded_tot_netbanking_payments, 2, '.', '' );
	$refunded_tot_upi_payments = number_format( (float) $refunded_tot_upi_payments, 2, '.', '' );
	$refunded_tot_wallet_payments = number_format( (float) $refunded_tot_wallet_payments, 2, '.', '' );

	$failed_tot_credit_card_payments = number_format( (float) $failed_tot_credit_card_payments, 2, '.', '' );
	$failed_tot_debit_card_payments = number_format( (float) $failed_tot_debit_card_payments, 2, '.', '' );
	$failed_tot_netbanking_payments = number_format( (float) $failed_tot_netbanking_payments, 2, '.', '' );
	$failed_tot_upi_payments = number_format( (float) $failed_tot_upi_payments, 2, '.', '' );
	$failed_tot_wallet_payments = number_format( (float) $failed_tot_wallet_payments, 2, '.', '' );

	//get count of total transaction records
	$total_all_records = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total_records
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON os.order_id = pm.post_id
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-failed' OR os.status='wc-refunded')
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//get count of sale transaction
	$total_completed_records = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total_records
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON os.order_id = pm.post_id
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-processing' OR os.status='wc-completed')
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//get count of refund transaction
	$total_refunded_records = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total_records
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON os.order_id = pm.post_id
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-refunded')
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	//get count of fail transaction
	$total_failed_records = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS total_records
			FROM {$wpdb->prefix}wc_order_stats AS os
			INNER JOIN {$wpdb->prefix}postmeta pm
			ON os.order_id = pm.post_id
			WHERE pm.meta_key = '_payment_method'
			AND pm.meta_value= %s
			AND (os.status='wc-failed')
			AND os.date_created > %s
			AND os.date_created < %s ",
			$payment_method,
			$start_date,
			$end_date
		),
		ARRAY_A
	);

	if ( isset( $_GET['order'] ) ) {
		$order = sanitize_text_field( $_GET['order'] );
	} else {
		$order = 'desc';
	}

	if ( 'asc' == $order ) {
		$link_order = 'desc';
	} else {
		$link_order = 'asc';
	}

	if ( isset( $_GET['orderby'] ) ) {
		$order_by = sanitize_text_field( $_GET['orderby'] );
	} else {
		$order_by = 'date_created';
	}

	$completed_current_class = '';
	$refunded_current_class = '';
	$failed_current_class = '';
	$all_current_class = '';

	$page_num = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
	$limit = 10; // Number of rows in page
	$offset = ( $page_num - 1 ) * $limit;

	if ( isset( $_GET['transaction_status'] ) ) {
		if ( 'completed' == $_GET['transaction_status'] ) {
			$completed_current_class = 'class="current" aria-current="page"';

			$total_result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id DESC",
					$payment_method,
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		} elseif ( 'refunded' == $_GET['transaction_status'] ) {
			$refunded_current_class = 'class="current" aria-current="page"';
			$total_result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id DESC",
					$payment_method,
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		} elseif ( 'failed' == $_GET['transaction_status'] ) {
			$failed_current_class = 'class="current" aria-current="page"';
			$total_result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id DESC",
					$payment_method,
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		} else {
			$all_current_class = 'class="current" aria-current="page"';
			$total_result = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id DESC",
					$payment_method,
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		}
	} else {
		$all_current_class = 'class="current" aria-current="page"';
		$total_result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.order_id DESC",
				$payment_method,
				$start_date,
				$end_date
			),
			ARRAY_A
		);
	}

	$total = count( $total_result );
	$num_of_pages = ceil( $total / $limit );

	if ( isset( $_GET['transaction_status'] ) ) {
		if ( 'completed' == $_GET['transaction_status'] ) {
			if ( 'asc' == $order ) {

				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			} else {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			}
		} elseif ( 'refunded' == $_GET['transaction_status'] ) {
			if ( 'asc' == $order ) {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			} else {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-refunded'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			}
		} elseif ( 'failed' == $_GET['transaction_status'] ) {
			if ( 'asc' == $order ) {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			} else {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND os.status='wc-failed'
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			}
		} else {
			if ( 'asc' == $order ) {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status asc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			} else {
				if ( 'transaction_total' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY total_payments desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'order_id' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.order_id desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'date_created' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.date_created desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				} elseif ( 'status' == $order_by ) {
					$lists = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT *
					FROM {$wpdb->prefix}wc_order_stats AS os
					INNER JOIN {$wpdb->prefix}postmeta pm
					ON os.order_id = pm.post_id
					WHERE pm.meta_key = '_payment_method'
					AND pm.meta_value= %s
					AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
					AND os.date_created > %s
					AND os.date_created < %s
					ORDER BY os.status desc
					LIMIT %d,%d",
							$payment_method,
							$start_date,
							$end_date,
							$offset,
							$limit
						)
					);
				}
			}
		}
	} else {
		if ( 'asc' == $order ) {
			if ( 'transaction_total' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY total_payments asc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'order_id' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.order_id asc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'date_created' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.date_created asc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'status' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.status asc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			}
		} else {
			if ( 'transaction_total' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *,(`net_total` +`tax_total` +`shipping_total`) AS total_payments
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY total_payments desc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'order_id' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.order_id desc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'date_created' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.date_created desc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			} elseif ( 'status' == $order_by ) {
				$lists = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT *
				FROM {$wpdb->prefix}wc_order_stats AS os
				INNER JOIN {$wpdb->prefix}postmeta pm
				ON os.order_id = pm.post_id
				WHERE pm.meta_key = '_payment_method'
				AND pm.meta_value= %s
				AND (os.status='wc-processing' OR os.status='wc-completed' OR os.status='wc-refunded' OR os.status='wc-failed' OR os.status='wc-pending' OR os.status='wc-cancelled' OR os.status='wc-on-hold')
				AND os.date_created > %s
				AND os.date_created < %s
				ORDER BY os.status desc
				LIMIT %d,%d",
						$payment_method,
						$start_date,
						$end_date,
						$offset,
						$limit
					)
				);
			}
		}
	}

	//generate pagination link
	$page_links = paginate_links(
		array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '?paged=%#%',
			'prev_text' => __( '<div class="next-page button"><</div>' ),
			'next_text' => __( '<div class="next-page button">></div>' ),
			'total' => $num_of_pages,
			'current' => $page_num,
			'show_all' => false,
			'type' => 'plain',
			'end_size' => 2,
			'mid_size' => 2,
			'prev_next' => true,
			'add_args' => false,
			'add_fragment' => '',
		)
	);

	//when click of down load execel button
	if ( isset( $_POST['Export'] ) ) {
		if ( isset( $_POST['export_form_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( $_POST['export_form_nonce_field'] ), 'export_form_nonce' ) ) {
			// Nonce verification passed, proceed with processing the form data.
			$exportType = isset( $_POST['export_type'] ) ? sanitize_text_field( $_POST['export_type'] ) : 'csv';
			global $start_date;
			global $end_date;
			exportData( $exportType, $total_result );
		} else {
			// Nonce verification failed, handle the error or display an error message.
			die( esc_html( __( 'Nonce excel auropay verification failed.', 'woocommerce-gateway-auropay' ) ) );
		}
	}

	$current_range = !empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( !in_array( $current_range, array( 'custom', 'year', 'last_month', 'month' ), true ) ) {
		$current_range = '7day';
	}
	$ranges = array(
		'year' => __( 'Year', 'woocommerce' ),
		'last_month' => __( 'Last month', 'woocommerce' ),
		'month' => __( 'This month', 'woocommerce' ),
		'7day' => __( 'Last 7 days', 'woocommerce' ),
	);

	//This is needed for generating graph report
	include_once WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php';
	include_once WC_HP_PLUGIN_PATH . '/includes/class-payment-overview-report.php';
	include_once WC_HP_PLUGIN_PATH . '/includes/view/payment-overview-view.php';
}
