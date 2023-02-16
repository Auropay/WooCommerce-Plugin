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
 * Communicates with auropay payment API
 *
 * @category Payment
 * @package  AuroPay_Gateway_For_WooCommerce
 * @author   Akshita Minocha <akshita.minocha@aurionpro.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @link     https://auropay.net/
 */
class WC_HP_API
{
    /**
     * Check merchant keys are correct
     * 
     * @param string $referenceNo reference number of order
     * @param string $accessKey   accesskey of merchant
     * @param string $secretKey   secretKey of merchant
     * @param string $apiUrl      api url
     * 
     * @return array
     */
    public static function validateApiKey($referenceNo, $accessKey, $secretKey, $apiUrl)
    {
        $api = "api/payments/refno/" . $referenceNo;
        $options = get_option('woocommerce_auropay_settings');
        $headers = array('x-version' => '1.0', 'x-access-key' => $accessKey, 'x-secret-key' => $secretKey, 'content-type' => 'application/json');
        $apiUrl = !empty($options['api_url']) ? $options['api_url'] : $apiUrl;
        $endpoint = $apiUrl . $api;

        try {
            $response = wp_remote_post(
                $endpoint,
                [
                    'method'  => 'GET',
                    'headers' => $headers,
                    'timeout' => 50,
                ]
            );

            if ($response['response']['code'] != 200 && $response['response']['code'] != 201 && $response['response']['code'] != 204) {
                Custom_Functions::log("api:" . $api . "###ERROR:" . $response['body']);
            }

            Custom_Functions::log("called api:" . $api);
            return $response['response']['code'];
        } catch (Exception $e) {
            Custom_Functions::log("called api:" . $api . "#response:error");
            return array('error' => true);
        }
    }

    /**
     * Send the request to HP API with api key
     * 
     * @param string $api    api url
     * @param string $method method of api
     * @param array  $params parameters
     * 
     * @return array
     */
    public static function apiKeyRequest($api, $method = 'POST', $params = array())
    {
        Custom_Functions::log("calling api:" . $api);

        $options = get_option('woocommerce_auropay_settings');
        $accessKey = $options['access_key'];
        $secretKey =  $options['secret_key'];
        $headers = array('x-version' => '1.0', 'x-access-key' => $accessKey, 'x-secret-key' => $secretKey, 'content-type' => 'application/json');
        $endpoint = $options['api_url'] . $api;

        try {
            if ($method == 'POST') {
                $response = wp_remote_post(
                    $endpoint,
                    [
                        'method'  => $method,
                        'headers' => $headers,
                        'body'    => json_encode($params),
                        'timeout' => 50,
                    ]
                );
            } else {
                $response = wp_remote_post(
                    $endpoint,
                    [
                        'method'  => $method,
                        'headers' => $headers,
                        'timeout' => 50,
                    ]
                );
            }

            if ($response['response']['code'] != 200 && $response['response']['code'] != 201 && $response['response']['code'] != 204) {
                Custom_Functions::log("api:" . $api . "###params:" . json_encode($params));
                Custom_Functions::log("api:" . $api . "###ERROR:" . $response['body']);
            }

            Custom_Functions::log("called api:" . $api);
            return $response;
        } catch (Exception $e) {
            Custom_Functions::log("called api:" . $api . "#response:error");
            return array('error' => true);
        }
    }

    /**
     * Used to get HP payment form link
     * 
     * @param array $params parameters
     * 
     * @return array
     */
    public static function getPaymentLink($params)
    {
        //api call to get the transaction link
        $api = "api/paymentlinks";

        try {
            $response = self::apiKeyRequest($api, 'POST', $params);

            if ($response['response']['code'] == 200 || $response['response']['code'] == 201 || $response['response']['code'] == 204) {
                $response = json_decode($response['body'], true);
                return $response;
            } else {
                return array('error' => true);
            }
        } catch (Exception $e) {
            wc_add_notice('There is a problem with your transaction (Error Code 20001). Please try later.', 'error');
            return '';
        }
    }

    /**
     * Used to get Auropay transaction status
     * 
     * @param string $transaction_id transaction id
     * @param string $order_id       order id
     * 
     * @return string
     */
    public static function getPaymentStatus($transaction_id, $order_id)
    {
        update_post_meta($order_id, '_hp_transaction_id', $transaction_id);

        //Make the second API call to get transaction status
        $api = "api/payments/" . $transaction_id;

        try {
            $response = self::apiKeyRequest($api, 'GET');

            if ($response['response']['code'] == 200 || $response['response']['code'] == 201 || $response['response']['code'] == 204) {

                $response = json_decode($response['body'], true);

                update_post_meta($order_id, '_hp_transaction_card_type', $response['tenderInfo']['cardType']);
                update_post_meta($order_id, '_hp_transaction_auth_code', $response['transactionResult']['processorAuthCode']);
                update_post_meta($order_id, '_hp_transaction_channel_type', $response['channelType']);

                //set bankname for net banking
                if ($response['channelType'] == 7) {
                    update_post_meta($order_id, '_hp_transaction_card_type', $response['tenderInfo']['bankName']);
                }

                //set upiid for UPI
                if ($response['channelType'] == 6) {
                    update_post_meta($order_id, '_hp_transaction_card_type', $response['tenderInfo']['upiId']);
                }

                //set upiid for wallet
                if ($response['channelType'] == 8) {
                    update_post_meta($order_id, '_hp_transaction_card_type', $response['tenderInfo']['walletProvider']);
                }

                if ($response['transactionStatus'] == 2) {
                    return "Success";
                } else {
                    return "Fail";
                }
            } else {
                return "Fail";
            }
        } catch (Exception $e) {
            // wc_add_notice('There is a problem when fetching transaction status. Please try later.', 'error');
            return "Fail";
        }
    }

    /**
     * Used to sync order refund
     * 
     * @param string $params   Parameters
     * @param string $order_id order id
     * 
     * @return bool
     */
    public static function processRefund($params, $order_id)
    {
        $transaction_id = get_post_meta($order_id, '_hp_transaction_id', true);

        //check void amount - because partial refund is not supporting 
        $order = wc_get_order($order_id);
        $order_total_amount = number_format($order->get_total(), 2, '.', '');
        $void_amount = number_format($params['Amount'], 2, '.', '');

        Custom_Functions::log("order_amt" . $order_total_amount);
        Custom_Functions::log("refund_amt" . $void_amount);

        $params['OrderId'] = $transaction_id;

        Custom_Functions::log("transaction_id" . $transaction_id);

        $api = "api/refunds";

        try {
            $response = self::apiKeyRequest($api, 'POST', $params);
            if ($response['response']['code'] == 200 || $response['response']['code'] == 201 || $response['response']['code'] == 204) {
                $response = json_decode($response['body'], true);
                if ($response['transactionStatus'] == 2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            wc_add_notice('There is a problem with your transaction. Please try later.', 'error');
            return false;
        }

        return false;
    }

    /**
     * Used to get Auropay transaction order status
     * 
     * @param int $referenceNo reference number of order
     * 
     * @return bool
     */
    public static function getPaymentOrderStatusByReference($referenceNo)
    {
        $api = "api/payments/refno/" . $referenceNo;

        try {
            $response = self::apiKeyRequest($api, 'GET');
            if ($response['response']['code'] == 200 || $response['response']['code'] == 201 || $response['response']['code'] == 204) {
                $response = json_decode($response['body'], true);
                return $response['transactionStatus'];
            } else {
                return -1;
            }
        } catch (Exception $e) {
            // wc_add_notice('There is a problem when fetching transaction status. Please try later.', 'error');
            return -1;
        }
    }
}
