<?php

/**
 * An external standard for Auropay.
 *
 * @category Payment
 * @package  AuroPay_Gateway_For_WooCommerce
 * @author   Akshita Minocha <akshita.minocha@aurionpro.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://auropay.net/
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Woocommerce order status
 * 
 * @return array
 */
function orderStatusMapping()
{
    //Woocommerce Status
    $statusArr = [
        0 => 'cancelled',
        1 => 'cancelled',
        2 => 'processing',
        3 => 'pending payment',
        4 => 'cancelled',
        5 => 'failed',
        6 => 'failed',
        9 => 'processing',
        10 => 'refunded',
        16 => 'processing',
        17 => 'failed',
        18 => 'on hold',
        19 => 'failed',
        20 => 'processing',
        21 => 'processing',
        22 => 'cancelled',
        23 => 'cancelled',
    ];

    return $statusArr;
}
