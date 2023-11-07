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
 * Woocommerce order status
 *
 * @return array
 */
function orderStatusMapping() {
	//Woocommerce Status
	$statusArr = [
		0 => 'on-hold',
		1 => 'on-hold',
		2 => 'processing',
		4 => 'cancelled',
		5 => 'failed',
		9 => 'refunded',
		10 => 'refunded',
		16 => 'processing',
		18 => 'on-hold',
		22 => 'cancelled',
		23 => 'failed',
		25 => 'on-hold',
	];

	return $statusArr;
}

/**
 * Auropay transaction status
 *
 * @return array
 */
function auropayStatusMapping() {
	//Auropay Status
	$auropayStatusArr = [
		0 => 'In Process',
		1 => 'In Process',
		2 => 'Authorized',
		4 => 'Cancelled',
		5 => 'Failed',
		9 => 'RefundAttempted',
		10 => 'Refunded',
		16 => 'Success',
		18 => 'Hold',
		19 => 'RefundFailed',
		20 => 'PartialRefundAttempted',
		21 => 'PartiallyRefunded',
		22 => 'UserCancelled',
		23 => 'Expired',
		24 => 'SettlementFailed',
		25 => 'Approved',
	];

	return $auropayStatusArr;
}
