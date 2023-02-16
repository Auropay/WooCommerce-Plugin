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

require plugin_dir_path(__DIR__) . 'includes/order-status-mapping.php';

add_filter('cron_schedules', 'setExecutionCronInterval');
/**
 * Adding custome interval
 * 
 * @param string $schedules the time interval
 * 
 * @return array
 */
function setExecutionCronInterval($schedules)
{
    $schedules['five_minute'] = array(
        'interval' => 300,
        'display'  => esc_html__('Every five minutes'),
    );
    return $schedules;
}

//Setting custom hook
add_action('auropay_cron_hook', 'syncOrderStatus');

/**
 * The event function
 * 
 * @return array
 */
function syncOrderStatus()
{
    $args = array(
        'status' => array('wc-pending'),
        'orderby' => 'modified',
        'order' => 'DESC',
    );
    $ordersArr = wc_get_orders($args);

    $statusArr = orderStatusMapping();
    foreach ($ordersArr as $order) {
        date_default_timezone_set(WC_HP_TIMEZONE);
        $order_created_date = $order->order_date;
        $time =  (strtotime($order_created_date)) + (60 * 10);
        if (time() > $time) {
            Custom_Functions::log("Cron:- current date time " . date('Y-m-d H:i:s', time()));
            Custom_Functions::log("Cron:- after 10 minutes of order creating date time " . date('Y-m-d H:i:s', $time));
            $order_id = $order->get_id();
            Custom_Functions::log("Cron:- order id " . $order_id);
            $refNo = get_post_meta($order_id, '_ap_transaction_reference_number', true);
            Custom_Functions::log("Cron:-  reference number " . $refNo);
            $status = WC_HP_API::getPaymentOrderStatusByReference($refNo);
            if ($status != -1) {
                if ($status != 0 && $status != 1) {
                    Custom_Functions::log("Cron:- status number " . $status);
                    if ($statusArr[$status]) {
                        Custom_Functions::log("Cron:- status " . $statusArr[$status]);
                        $order->update_status($statusArr[$status]);
                    }
                } else {
                    $order->update_status('cancelled');
                    Custom_Functions::log("Cron:- status number " . $status);
                }
            } else {
                $order->update_status('cancelled');
                Custom_Functions::log("Cron:- status number " . $status);
            }
        }
    }
}

//Scheduling recurring event to prevent duplicate event
if (!wp_next_scheduled('auropay_cron_hook')) {
    wp_schedule_event(time(), 'five_minute', 'auropay_cron_hook');
}
