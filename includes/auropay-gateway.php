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
 * Required minimums and constants
 */
require_once WC_HP_PLUGIN_PATH . '/includes/class-wc-hp-api.php';
require_once WC_HP_PLUGIN_PATH . '/includes/functions.php';
require_once WC_HP_PLUGIN_PATH . '/includes/place-order.php';
require_once WC_HP_PLUGIN_PATH . '/includes/export.php';
require_once WC_HP_PLUGIN_PATH . '/cron/sync-order-status.php';

add_filter( 'woocommerce_payment_gateways', 'auropayAddGatewayClass' );
/**
 * This action hook registers this PHP class as a WooCommerce payment gateway
 *
 * @param array $gateways is payment methods array
 *
 * @return array
 */
function auropayAddGatewayClass( $gateways ) {
	$gateways[] = 'WC_Auropay_Gateway'; // This class name
	return $gateways;
}

add_filter( 'http_request_timeout', 'wp9838cTimeoutExtend' );
/**
 * This sets Wordpress timeout to 30 seconds to counter Curl timeout error
 *
 * @param string $time is checkout time
 *
 * @return int
 */
function wp9838cTimeoutExtend() {
	// Default timeout is 5
	return 30;
}

add_action( 'rest_api_init', 'registerPaymentOrderRoutes' );
/**
 * This action needed for register rest api route
 *
 * @return int
 */
function registerPaymentOrderRoutes() {
	include_once WC_HP_PLUGIN_PATH . '/includes/class-wc-api.php';
	myRegisterRoute();
}

add_action( 'admin_menu', 'registerCustomSubmenuPage' );
/**
 * This action needed for create submenu under WooCommerce tab in admin
 *
 * @return void
 */
function registerCustomSubmenuPage() {
	include_once WC_HP_PLUGIN_PATH . '/includes/custom-payment-link.php';
	registerPaymentLink();
}

add_action( 'plugins_loaded', 'auropayInitGatewayClass' );
/**
 * WC_Auropay_Gateway class itself should reside inside plugins_loaded action hook
 *
 * @return void
 */
function auropayInitGatewayClass() {
	/**
	 * WC_Auropay_Gateway class extends from the woocommerce
	 *
	 * @package AuroPay_Gateway_For_WooCommerce
	 * @link    https://auropay.net/
	 */
	class WC_Auropay_Gateway extends WC_Payment_Gateway {
		/**
		 * Class Constructor
		 */
		public function __construct() {
			$this->id = WC_HP_PLUGIN_NAME; // payment gateway plugin ID
			$this->icon = WC_HP_PLUGIN_URL . '/assets/images/logo.png'; // URL of the icon that will be displayed on checkout page
			$this->has_fields = false;
			$this->method_title = 'AuroPay Gateway';
			$this->method_description = 'Enable Payments for Customers using Debit Card, Credit Card';
			$this->supports = array(
				'subscriptions',
				'products',
				'refunds',
				'tokenization',
				'addPaymentMethod',
			);

			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->testmode = $this->get_option( 'testmode' );
			$this->api_url = $this->get_option( 'api_url' );
			$this->logging = wc_string_to_bool( $this->get_option( 'logging', 'no' ) );
			$this->allowed_card_types = $this->get_option( 'allowed_card_types', array( 'visa', 'mastercard' ) );
			$this->expiry = $this->get_option( 'expiry' );

			// Load the settings
			$this->initFormFields();
			$this->init_settings();

			if ( isset( $_POST['save'] ) ) {
				$accessKey = isset( $_POST['woocommerce_auropay_access_key'] ) ? sanitize_text_field( $_POST['woocommerce_auropay_access_key'] ) : $this->get_option( 'access_key' );
				$secretKey = isset( $_POST['woocommerce_auropay_secret_key'] ) ? sanitize_text_field( $_POST['woocommerce_auropay_secret_key'] ) : $this->get_option( 'secret_key' );
				$apiUrl = isset( $_POST['woocommerce_auropay_api_url'] ) ? sanitize_text_field( $_POST['woocommerce_auropay_api_url'] ) : $this->get_option( 'api_url' );
				$this->checkLoginCredential( $accessKey, $secretKey, $apiUrl );
			}

			//If setting is set as disable the plugin from setting page
			if ( 'no' == $this->enabled ) {
				return;
			}

			// This action hook handle response - callback url from HP end
			add_action( 'woocommerce_api_hp_response', array( $this, 'hp_response' ) );
			// Used for customize the Place order button functionality
			add_filter( 'woocommerce_order_button_html', array( $this, 'customOrderButtonHtml' ) );
			// Used for customize the Pay order button functionality
			add_filter( 'woocommerce_pay_order_button_html', array( $this, 'customPayOrderButtonHtml' ) );

			//To get the order id when reprocessing the payment
			global $wp;
			global $order_pay_id;

			if ( isset( $wp->query_vars['order-pay'] ) && absint( $wp->query_vars['order-pay'] ) > 0 ) {
				$order_pay_id = absint( $wp->query_vars['order-pay'] ); // The order ID
			}
		}

		/**
		 * Check the login credentials are correct
		 *
		 * @return string
		 */
		public function checkLoginCredential( $accessKey, $secretkey, $apiUrl ) {
			$refId = 'abcd';
			$error_code = WC_HP_API::validateApiKey( $refId, $accessKey, $secretkey, $apiUrl );

			if ( 401 != $error_code ) {
				// This action hook saves the settings
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			} else {
				echo "<script>alert('Invalid settings')</script>";
			}
		}

		/**
		 * Return error notice
		 *
		 * @return void
		 */
		public function addPaymentMethod() {
			wc_add_notice( __( "You can't add payment method here, you can add from checkout page" ), 'error' );
		}

		/**
		 * Define plugin options that will be also available on Wordpress Admin portal for further configuration of this plugin
		 *
		 * @return void
		 */
		public function initFormFields() {
			$this->form_fields = include WC_HP_PLUGIN_PATH . '/includes/admin-settings.php';
		}

		/**
		 * Function create request param to get the HP payment URL
		 *
		 * @param int $order_id is the unique order id
		 * @param int $repay    checks arledy paid or first time
		 *
		 * @return array
		 */
		public function getPaymentLinkParams( $order_id, $repay = 0 ) {
			$order = new WC_Order( $order_id );
			$paymentPlanFlag = '';
			$paymentPlanId = '';
			$line_items = array();
			update_post_meta( $order_id, '_ap_order_currency', $order->get_currency() );

			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product->is_type( 'product_pack' ) ) {
					$paymentPlanFlag = 'payment_plan';
					$paymentPlan = $product->name;
				}
				if ( !is_object( $product ) ) {
					continue;
				}
				$line_item['LineItemType'] = 0;
				$line_item['ProductId'] = $product->get_sku() ? substr( $product->get_sku(), 0, 31 ) : substr( $product->get_id(), 0, 31 );
				$line_item['Description'] = substr( $item->get_name(), 0, 31 );
				$line_item['Quantity'] = $item->get_quantity();
				$line_item['Rate'] = $order->get_item_total( $item );
				$line_items[] = $line_item;
			}

			if ( 'payment_plan' == $paymentPlanFlag ) {
				$amountMapping = ['monthly' => 12, 'quarterly' => 4, 'half_yearly' => 2, 'yearly' => 1];
				if ( 'Half Yearly' == $paymentPlan ) {
					$paymentPlan = 'half_yearly';
				} else {
					$paymentPlan = strtolower( $paymentPlan );
				}

				$paymentPlanId = $this->get_option( $paymentPlan );
				$amount = $amountMapping[$paymentPlan] * $order->get_total();
				$amount = number_format( $amount, 2, '.', '' );

				$enablePartialPayment = true;
				$enableMultiplePayment = true;
				$expireOn = null;
			} else {
				//get payment link expiry time
				$curr_date = gmdate( 'Y-m-d H:i:s' );
				$expire_date = strtotime( $curr_date . ' + ' . $this->expiry . ' minutes' );
				$expire_date1 = $expire_date + ( 30 * 60 ); // Adding 30 minutes
				$expire_date2 = $expire_date1 + ( 5 * 3600 ); // Adding 5 hours
				$expireOn = gmdate( 'd-m-Y H:i:s', $expire_date2 );

				$amount = number_format( $order->get_total(), 2, '.', '' );
				$enablePartialPayment = false;
				$enableMultiplePayment = false;
			}

			$user_id = get_post_meta( $order_id, '_customer_user', true );

			$customer_array = array();

			if ( $user_id ) {
				// Get an instance of the WC_Customer Object from the user ID
				$customer = new WC_Customer( $user_id );
				$email = $customer->get_email();
				$first_name = $customer->get_first_name();
				$last_name = $customer->get_last_name();
				$phone = $order->get_billing_phone();

				$customer_array = array(
					array(
						'firstName' => $first_name,
						'lastName' => $last_name,
						'email' => $email,
						'phone' => $phone,
						'paymentPlans' => $paymentPlanId,
					),
				);

				$save_customer = true;
				$save_customer_account = true;
			} else {
				$email = $order->get_billing_email();
				$first_name = $order->get_billing_first_name();
				$last_name = $order->get_billing_last_name();
				$phone = $order->get_billing_phone();

				$customer_array = array(
					array(
						'firstName' => $first_name,
						'lastName' => $last_name,
						'email' => $email,
						'phone' => $phone,
						'paymentPlans' => $paymentPlanId,
					),
				);
				$save_customer = false;
				$save_customer_account = false;
			}

			if ( $repay ) {
				$title = 'WCom_RePay_' . $order->get_id() . '_' . time();
				$refNo = 'WCom_RePay_' . $order->get_id() . '_' . time();
			} else {
				$title = 'WCom_' . $order->get_id();
				$refNo = $order->get_id();
			}

			if ( 'payment_plan' == $paymentPlanFlag ) {
				update_post_meta( $order_id, '_ap_product_type', 'Subscription' );
			} else {
				update_post_meta( $order_id, '_ap_product_type', 'Product' );
			}

			update_post_meta( $order_id, '_ap_transaction_reference_number', $refNo );

			return array(
				'amount' => $amount,
				'title' => $title,
				'shortDescription' => '',
				'paymentDescription' => '',
				'enablePartialPayment' => $enablePartialPayment,
				'enableMultiplePayment' => $enableMultiplePayment,
				'enableProtection' => false,
				'displayReceipt' => false,
				'expireOn' => $expireOn,
				'applyPaymentAdjustments' => false,
				'customers' => $customer_array,
				'lineItems' => $line_items,
				'responseType' => 1,
				'source' => 'ecommerce',
				'platform' => 'woocommerce',
				'callbackParameters' => array(
					'CallbackSuccessUrl' => $this->getReturnUrl() . '&ORDERID=' . $order_id . '&transactionId={transactionId}&type=1',
					'CallbackFailureUrl' => $this->getReturnUrl() . '&ORDERID=' . $order_id . '&type=2',
					'ReferenceNo' => $refNo,
					'ReferenceType' => 'WoocommerceOrder',
					'TransactionId' => '',
					'CallbackApiUrl' => $this->getCallbackUrl(), //$this->get_callbackapiUrl()
				),
				'settings' => array(
					'displaySummary' => false,
					'saveCustomer' => $save_customer,
					'saveCustomerAccount' => $save_customer_account,
				),
			);
		}

		/**
		 * This will handle callback url response from HP end - success or fail
		 *
		 * @return string
		 */
		public function hp_response() {
			$order_id = 0;
			if ( isset( $_REQUEST['refNo'] ) ) {
				$refNo = sanitize_text_field( $_REQUEST['refNo'] );
				if ( strpos( $refNo, 'RePay' ) ) {
					$order_id = explode( '_', $refNo )[2];
				} else {
					$order_id = $refNo;
				}
			}

			$order = wc_get_order( $order_id );

			if ( !$order_id || !$order ) {
				die( esc_html( __( 'Error: order not found.', 'woocommerce-gateway-auropay' ) ) );
			}
			$auropayTransactionId = isset( $_REQUEST['id'] ) ? sanitize_text_field( $_REQUEST['id'] ) : '';
			//Get the current order status
			$status = WC_HP_API::getPaymentStatus( $auropayTransactionId, $order_id );
			$paymentStatusError = isset( $_REQUEST['error'] ) ? sanitize_text_field( $_REQUEST['error'] ) : '';
			if ( 2 == $status ) {
				$order->payment_complete();
				$order->add_order_note( sprintf( __( 'Payment was successfully processed by Auropay Payments.', 'woocommerce-gateway-auropay' ) ) );
			} else {
				if ( 'Fail' != $status ) {
					$statusArr = orderStatusMapping();
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_order_status_number ' . $status );
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_order_status ' . $statusArr[$status] );
					$order->update_status( $statusArr[$status] );
					$order->add_order_note( sprintf(
						/* translators: Error message displayed when payment status is not 'expiry'. */
						__( 'Error message status number:%s', 'woocommerce-gateway-auropay' ), $statusArr[$status] ) );
				} else {
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_order_not_found ' . $status );
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_error' . $paymentStatusError );
					$order->update_status( 'failed' );
					update_post_meta( $order_id, '_hp_transaction_status', 5 );
					$order->add_order_note( sprintf(
						/* translators: Error message displayed when payment status is 'expiry'. */
						__( 'Error Message Payment expired', 'woocommerce-gateway-auropay' )
					) );
				}
			}

			$redirect = $this->get_return_url( $order );

			echo '<script>'
			. "parent.location.href = '" . esc_url_raw( $redirect ) . "'"
				. '</script>';

			exit();
		}

		/**
		 * Once the Place Order button is clicked, payments will be processed here
		 *
		 * @param int $order_id unique order id
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		/**
		 * This fired when refund is made from admin - this validate partial/full refund and sync with HP
		 *
		 * @param int    $order_id unique order id
		 * @param float  $amount   refund amount
		 * @param string $reason   reason for refund
		 *
		 * @return void
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			if ( '' == $reason ) {
				$reason = 'Refund for order' . $order_id;
			}

			$params = array(
				'UserType' => 1,
				'Amount' => $amount,
				'Remarks' => $reason,
			);

			$response = WC_HP_API::processRefund( $params, $order_id );
			if ( $response ) {
				return $response;
			} else {
				return new WP_Error( 'wc-order', __( 'System unable to Refund. Contact Auropay Support at support@auropay.net', 'Auropay' ) );
			}
		}

		/**
		 * Api url after payment done
		 *
		 * @return string
		 */
		public function getReturnUrl() {
			return str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'hp_response', home_url( '/' ) ) );
		}

		/**
		 * Return url
		 *
		 * @return string
		 */
		public function getCallbackUrl() {
			$url = $this->getReturnUrl();
			return $url . '&redirectWindow=parent';
		}

		/**
		 * Change place order button text and execution
		 *
		 * @param string $button is the button html
		 *
		 * @return void
		 */
		public function customOrderButtonHtml( $button ) {
			return showCustomButton( $button );
		}

		/**
		 * Change pay order button text and execution
		 *
		 * @param string $button is the button html
		 *
		 * @return void
		 */
		public function customPayOrderButtonHtml( $button ) {
			return showCustomPayButton( $button );
		}
	}
}
