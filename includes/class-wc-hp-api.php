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
 * Communicates with auropay payment API
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */
class WC_HP_API {
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
	public static function validateApiKey( $referenceNo, $accessKey, $secretKey, $apiUrl ) {
		$api = 'api/payments/refno/' . $referenceNo;
		$options = get_option( 'woocommerce_auropay_settings' );
		$headers = array( 'x-version' => '1.0', 'x-access-key' => $accessKey, 'x-secret-key' => $secretKey, 'content-type' => 'application/json' );
		$endpoint = $apiUrl . $api;

		try {
			$response = wp_remote_post(
				$endpoint,
				[
					'method' => 'GET',
					'headers' => $headers,
					'timeout' => 50,
				]
			);

			if ( 200 != $response['response']['code'] && 201 != $response['response']['code'] && 204 != $response['response']['code'] ) {
				Custom_Functions::log( 'api:' . $api . '###ERROR:' . $response['body'] );
			}

			Custom_Functions::log( 'called api:' . $api );
			return $response['response']['code'];
		} catch ( Exception $e ) {
			Custom_Functions::log( 'called api:' . $api . '#response:error' );
			return array( 'error' => true );
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
	public static function apiKeyRequest( $api, $order_id, $method = 'POST', $params = array() ) {
		Custom_Functions::log( 'orderid:' . $order_id . '_calling_api ' . $api );

		$options = get_option( 'woocommerce_auropay_settings' );
		$productType = get_post_meta( $order_id, '_ap_product_type', true );
		$orderCurrency = get_post_meta( $order_id, '_ap_order_currency', true );
		Custom_Functions::log( 'orderid:' . $order_id . '_product_type ' . $productType );
		Custom_Functions::log( 'orderid:' . $order_id . '_order_currency ' . $orderCurrency );
		if ( 'Subscription' == $productType ) {
			$accessKey = $options['sub_plan_access_key'];
			$secretKey = $options['sub_plan_secret_key'];
		} else {
			if ( 'USD' == $orderCurrency ) {
				$accessKey = $options['usd_access_key'];
				$secretKey = $options['usd_secret_key'];
			} else {
				$accessKey = $options['access_key'];
				$secretKey = $options['secret_key'];
			}
		}

		$headers = array( 'x-version' => '1.0', 'x-access-key' => $accessKey, 'x-secret-key' => $secretKey, 'content-type' => 'application/json' );
		$endpoint = $options['api_url'] . $api;

		try {
			if ( 'POST' == $method ) {
				$response = wp_remote_post(
					$endpoint,
					[
						'method' => $method,
						'headers' => $headers,
						'body' => json_encode( $params ),
						'timeout' => 50,
					]
				);
			} else {
				$response = wp_remote_post(
					$endpoint,
					[
						'method' => $method,
						'headers' => $headers,
						'timeout' => 50,
					]
				);
			}

			if ( 200 != $response['response']['code'] && 201 != $response['response']['code'] && 204 != $response['response']['code'] ) {
				Custom_Functions::log( 'orderid:' . $order_id . '_api_params ' . json_encode( $params ) );
				Custom_Functions::log( 'orderid:' . $order_id . '_api_error ' . $response['body'] );
			}

			Custom_Functions::log( 'orderid:' . $order_id . '_called_api ' . $api );
			return $response;
		} catch ( Exception $e ) {
			Custom_Functions::log( 'orderid:' . $order_id . '_called_api ' . $api . '_response_error ' );
			return array( 'error' => true );
		}
	}

	/**
	 * Used to get HP payment form link
	 *
	 * @param array $params parameters
	 *
	 * @return array
	 */
	public static function getPaymentLink( $params ) {
		//api call to get the transaction link
		$api = 'api/paymentlinks';
		$order_id = isset( $params['invoiceNumber'] ) ? $params['invoiceNumber'] : 0;

		try {
			$response = self::apiKeyRequest( $api, $order_id, 'POST', $params );

			if ( 200 == $response['response']['code'] || 201 == $response['response']['code'] || 204 == $response['response']['code'] ) {
				$response = json_decode( $response['body'], true );
				return $response;
			} else {
				return array( 'error' => true );
			}
		} catch ( Exception $e ) {
			wc_add_notice( 'There is a problem with your transaction (Error Code 20001). Please try later.', 'error' );
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
	public static function getPaymentStatus( $transaction_id, $order_id ) {
		update_post_meta( $order_id, '_hp_transaction_id', $transaction_id );

		//Make the second API call to get transaction status
		$api = 'api/payments/' . $transaction_id;

		try {
			$response = self::apiKeyRequest( $api, $order_id, 'GET', array() );

			if ( 200 == $response['response']['code'] || 201 == $response['response']['code'] || 204 == $response['response']['code'] ) {

				$response = json_decode( $response['body'], true );

				update_post_meta( $order_id, '_hp_transaction_card_type', $response['tenderInfo']['cardType'] );
				if ( isset( $response['transactionResult']['processorAuthCode'] ) ) {
					update_post_meta( $order_id, '_hp_transaction_auth_code', $response['transactionResult']['processorAuthCode'] );
				}
				update_post_meta( $order_id, '_hp_transaction_channel_type', $response['channelType'] );
				update_post_meta( $order_id, '_hp_transaction_status', $response['transactionStatus'] );

				//set bankname for net banking
				if ( 7 == $response['channelType'] ) {
					update_post_meta( $order_id, '_hp_transaction_card_type', $response['tenderInfo']['bankName'] );
				}

				//set upiid for UPI
				if ( 6 == $response['channelType'] ) {
					update_post_meta( $order_id, '_hp_transaction_card_type', $response['tenderInfo']['upiId'] );
				}

				//set upiid for wallet
				if ( 8 == $response['channelType'] ) {
					update_post_meta( $order_id, '_hp_transaction_card_type', $response['tenderInfo']['walletProvider'] );
				}

				if ( 2 == $response['transactionStatus'] ) {
					return 'Success';
				} else {
					return $response['transactionStatus'];
				}
			} else {
				return 'Fail';
			}
		} catch ( Exception $e ) {
			return 'Fail';
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
	public static function processRefund( $params, $order_id ) {
		$transaction_id = get_post_meta( $order_id, '_hp_transaction_id', true );

		//check void amount - because partial refund is not supporting
		$order = wc_get_order( $order_id );
		$order_total_amount = number_format( $order->get_total(), 2, '.', '' );
		$void_amount = number_format( $params['Amount'], 2, '.', '' );

		$params['OrderId'] = $transaction_id;

		$api = 'api/refunds';

		try {
			$response = self::apiKeyRequest( $api, $order_id, 'POST', $params );
			if ( 200 == $response['response']['code'] || 201 == $response['response']['code'] || 204 == $response['response']['code'] ) {
				$response = json_decode( $response['body'], true );
				if ( 2 == $response['transactionStatus'] || 18 == $response['transactionStatus'] ) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		} catch ( Exception $e ) {
			wc_add_notice( 'There is a problem with your transaction. Please try later.', 'error' );
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
	public static function getPaymentOrderStatusByReference( $referenceNo, $order_id ) {
		$api = 'api/payments/refno/' . $referenceNo;

		try {
			$response = self::apiKeyRequest( $api, $order_id, 'GET', array() );
			if ( 200 == $response['response']['code'] || 201 == $response['response']['code'] || 204 == $response['response']['code'] ) {
				$response = json_decode( $response['body'], true );
				return $response;
			} else {
				return -1;
			}
		} catch ( Exception $e ) {
			return -1;
		}
	}
}
