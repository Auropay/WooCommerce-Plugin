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
 * Register routes for various apis which are implemented at WC wnd 
 * 
 * @return void
 */
function myRegisterRoute()
{
    /*
     * Route for orderpayments api - this will get called from HP end to update the order status and other transaction details at WC side
    */
    register_rest_route(
        'wc/v1/',
        'orderpayments',
        array(
            'methods' => 'POST',
            'callback' => 'updateOrder',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
            'permission_callback' => function ($param) {
                return authenticateRequest($param);
            },
        )
    );

    /*
     * Route for paymenttokens api - this will get called from HP end to delete payment token at WC side
    */
    register_rest_route(
        'wc/v1/',
        'paymenttokens',
        array(
            'methods' => 'PUT',
            'callback' => 'deletePaymentToken',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return true;
                    }
                ),
            ),
            'permission_callback' => function () {
                return paymentTokenAuthenticateRequest();
            },
        )
    );
}

/**
 * Authenticate the request
 * 
 * @param array $params parameters
 * 
 * @return bool
 */
function authenticateRequest($params)
{
    $order_id =  $params['orderID'];
    $access_key = get_post_meta($order_id, '_hp_accesskey', true);

    $headers = getallheaders();
    $bearer_token = $headers['Authorization'];
    $token = explode("Bearer", $bearer_token);
    $token = trim($token[1]);

    if ($token == $access_key) {
        return true;
    } else {
        return false;
    }
}

/**
 * Authenticate the payment token request api
 * 
 * @return bool
 */
function paymentTokenAuthenticateRequest()
{
    $headers = getallheaders();

    return true;
    $token = $headers['accesskey'];
    $token_check = WC_HP_ACCESS_KEY;

    if ($token == $token_check) {
        return true;
    } else {
        return false;
    }
}

/**
 * This update status of order based on success/fail response from HP and store transaction related details
 * 
 * @param array $data order data
 * 
 * @return array
 */
function updateOrder($data)
{

    Custom_Functions::log("calling api: orderpayments");
    Custom_Functions::log("orderID:" . $data['orderID']);
    Custom_Functions::log("status:" . $data['status']);
    Custom_Functions::log("transactionID:" . $data['transactionID']);
    Custom_Functions::log("customerID:" . $data['customerID']);
    Custom_Functions::log("maskedAccount:" . $data['maskedAccount']);
    Custom_Functions::log("expiry:" . $data['expiry']);

    $order_id          = $data['orderID'];
    $status            = $data['status'];
    $transaction_id    = $data['transactionID'];
    $channel_type      = $data['channelType'];
    $card_type         = $data['cardType'];
    $account_token     = $data['accountToken'];
    $account_token_id  = $data['customerAccountId'];
    $auth_code         = $data['processorAuthCode'];
    $customer_id       = $data['customerID'];
    $card_number       = $data['maskedAccount'];
    $expiry            = $data['expiry'];

    //update order
    $order = wc_get_order($order_id);

    if (!$order_id || !$order) {
        $response = "FAILED";
        $error = "Order not found";
    } else {
        if ($status  == 'InProcess' || $status  == 'SUCCESS') {
            $order->payment_complete();

            //get customer id from order
            $wc_customer_id = $order->get_customer_id();

            //update transaction details - store in table
            update_post_meta($order_id, '_hp_transaction_id', $transaction_id);
            update_post_meta($order_id, '_hp_transaction_channel_type', $channel_type);
            update_post_meta($order_id, '_hp_transaction_card_type', $card_type);
            update_post_meta($order_id, '_hp_transaction_card_expiry', $expiry);
            update_post_meta($order_id, '_hp_transaction_account_token', $account_token);
            update_post_meta($order_id, '_hp_transaction_account_token_id', $account_token_id);
            update_post_meta($order_id, '_hp_transaction_auth_code', $auth_code);
            update_post_meta($order_id, '_hp_transaction_customer_id', $customer_id);
            update_post_meta($order_id, '_hp_transaction_card_number', $card_number);
            update_post_meta($order_id, '_hp_transaction_status', 'success');

            //check if need to add token and if already added
            if (isset($account_token) && $account_token != '' && $account_token != null) {

                global $wpdb;
                $wc_payment_tok_table = $wpdb->prefix . "woocommerce_payment_tokens";

                $payment_token = $wpdb->get_row(
                    "
                    SELECT * FROM $wc_payment_tok_table 
                    WHERE gateway_id='" . WC_HP_PLUGIN_NAME . "' 
                    AND token ='" . $account_token . "'",
                    ARRAY_A
                );




                if (!$payment_token) {
                    //get month and year
                    $month = substr($expiry, 0, 2);
                    $year = "20" . substr($expiry, 2, 2);

                    //handle for ACH 
                    if ($card_type == '') {
                        $card_type = "ACH";
                    }

                    if ($expiry == '') {
                        $month = "00";
                        $year = "0000";
                    }

                    $token = new WC_Payment_Token_CC();
                    $token->set_token($account_token);
                    $token->set_gateway_id(WC_HP_PLUGIN_NAME);
                    $token->set_card_type($card_type);
                    $token->set_last4($card_number);
                    $token->set_expiry_month($month);
                    $token->set_expiry_year($year);
                    $token->set_user_id($wc_customer_id);
                    $token->save();

                    //update token related details - store in table
                    update_metadata('payment_token', $token->get_id(), '_hp_token_customer_id', $customer_id);
                    update_metadata('payment_token', $token->get_id(), '_hp_token_account_id', $account_token_id);
                }
            }
        } else {

            //update transaction details - store in table
            update_post_meta($order_id, '_hp_transaction_channel_type', $channel_type);
            update_post_meta($order_id, '_hp_transaction_card_type', $card_type);
            update_post_meta($order_id, '_hp_transaction_card_expiry', $expiry);
            update_post_meta($order_id, '_hp_transaction_account_token', $account_token);
            update_post_meta($order_id, '_hp_transaction_account_token_id', $account_token_id);
            update_post_meta($order_id, '_hp_transaction_auth_code', $auth_code);
            update_post_meta($order_id, '_hp_transaction_customer_id', $customer_id);
            update_post_meta($order_id, '_hp_transaction_card_number', $card_number);
            update_post_meta($order_id, '_hp_transaction_status', 'failed');

            $order->update_status('failed');
        }
        $response = "SUCCESS";
        $error = null;
    }

    Custom_Functions::log("called api: orderpayments");
    return rest_ensure_response(array('status' => $response, 'error' => $error));
}

/**
 * This will delete payment token and save card related details
 * 
 * @param array $data merchant data
 * 
 * @return array
 */
function deletePaymentToken($data)
{
    global $wpdb;

    $merchant_id      = $data['MerchantId'];
    $account_token_id = $data['Id'];
    $customer_id      = $data['CustomerId'];

    //checkif valid token
    $row = $wpdb->get_row(
        "SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokenmeta 
        WHERE meta_key='_hp_token_account_id'
        AND meta_value='" . $account_token_id . "' LIMIT 1"
    );

    if ($row) {
        $data = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id ='" . $row->payment_token_id . "' LIMIT 1");
        if ($data) {
            $wpdb->delete($wpdb->prefix . 'woocommerce_payment_tokens', array('token_id' => $data->token_id), array('%d'));
            $wpdb->delete($wpdb->prefix . 'woocommerce_payment_tokenmeta', array('payment_token_id' => $data->token_id), array('%d'));

            $response = "Success";
            $error = null;
        } else {
            $response = "Fail";
            $error = "Invalid payment token";
        }
    } else {
        $response = "Fail";
        $error = "Invalid payment data123" . $data['Id'] . "#" . $data['id'];
    }

    return rest_ensure_response(array('status' => $response, 'error' => $error));
}
