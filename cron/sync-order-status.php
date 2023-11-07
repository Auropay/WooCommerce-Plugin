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

require_once plugin_dir_path( __DIR__ ) . 'includes/order-status-mapping.php';

add_filter( 'cron_schedules', 'setExecutionCronInterval' );
/**
 * Adding custome interval
 *
 * @param string $schedules the time interval
 *
 * @return array
 */
function setExecutionCronInterval( $schedules ) {
	$schedules['five_minute'] = array(
		'interval' => 300,
		'display' => esc_html__( 'Every five minutes' ),
	);
	return $schedules;
}

//Setting custom hook
add_action( 'auropay_cron_hook', 'syncOrderStatus' );

/**
 * The event function
 *
 * @return array
 */
function syncOrderStatus() {
	$args = array(
		'status' => array( 'wc-pending', 'wc-on-hold' ),
		'orderby' => 'modified',
		'order' => 'DESC',
	);
	$ordersArr = wc_get_orders( $args );

	$statusArr = orderStatusMapping();
	foreach ( $ordersArr as $order ) {
		$created_date = $order->get_date_created();
		$created_date_formatted = $created_date->format( 'Y-m-d H:i:s' );
		$time = ( strtotime( $created_date_formatted ) ) + ( 60 * 10 );
		Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_order_created_time ' . $created_date_formatted );
		Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_after_10_minutes_of_order_creation ' . gmdate( 'Y-m-d H:i:s', $time ) );
		if ( time() > $time ) {
			Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_run_timing ' . gmdate( 'Y-m-d H:i:s', time() ) );
			$order_id = $order->get_id();
			$refNo = get_post_meta( $order_id, '_ap_transaction_reference_number', true );
			Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_order_refference_number ' . $refNo );
			$paymentData = WC_HP_API::getPaymentOrderStatusByReference( $refNo, $order_id );
			if ( -1 != $paymentData ) {
				update_post_meta( $order_id, '_hp_transaction_id', $paymentData['transactionId'] );
				update_post_meta( $order_id, '_hp_transaction_status', $paymentData['transactionStatus'] );
				if ( $statusArr[$paymentData['transactionStatus']] ) {
					Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_order_status ' . $statusArr[$paymentData['transactionStatus']] );
					$order->update_status( $statusArr[$paymentData['transactionStatus']] );
				}
			} else {
				$order->update_status( 'cancelled' );
				Custom_Functions::log( 'orderid:' . $order->get_id() . '_cron_order_not_found ' );
			}
		}
	}
}

//Scheduling recurring event to prevent duplicate event
if ( !wp_next_scheduled( 'auropay_cron_hook' ) ) {
	wp_schedule_event( time(), 'five_minute', 'auropay_cron_hook' );
}
