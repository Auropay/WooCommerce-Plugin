<?php

/**
 * Plugin Name: AuroPay Gateway for WooCommerce
 * Plugin URI: https://auropay.net/
 * Description: Custom payment gateway powered by AuroPay.
 * Author: Akshita Minocha
 * Author URI: https://auropay.net/
 * Version: 1.2.9
 * Requires at least: 5.6
 * Tested up to: 5.6
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {
	add_filter(
		'plugin_action_links_' . plugin_basename( __FILE__ ),
		function ( $links ) {

			$plugin_links = array(
				'<a href="admin.php?page=wc-settings&tab=checkout&section=auropay">' . esc_html__( 'Settings', 'woocommerce-gateway-auropay' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}
	);
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	define( 'WC_HP_MAIN_FILE', __FILE__ );
	define( 'WC_HP_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
	define( 'WC_HP_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
	define( 'WC_HP_ACCESS_KEY', 'cGF5bWVudCBnYXRld2F5MDA5=dfsdfsdfdsfsdf432423423434+sfjejd9' );
	define( 'WC_HP_PLUGIN_NAME', 'auropay' );
	define( 'WC_HP_TIMEZONE', 'Asia/Kolkata' );
	define( 'WC_HP_PLUGIN_VERSION', '1.2.8' );
	include_once plugin_dir_path( __FILE__ ) . 'includes/auropay-gateway.php';
}
