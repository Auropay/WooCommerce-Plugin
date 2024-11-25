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

/*
 * Register actions for handling order creation/place order ajax request
 */
add_action( 'wp_ajax_ajax_order', 'submitedOrderData' );
add_action( 'wp_ajax_nopriv_ajax_order', 'submitedOrderData' );
add_action( 'wp_head', 'wpbHookJavascript' );
add_action( 'wp_enqueue_scripts', 'addStyle' );

/**
 * Include css style
 *
 * @return void
 */
function addStyle() {
	wp_enqueue_style( 'hp_front_styles', WC_HP_PLUGIN_URL . '/assets/front-style.css', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_script( 'hp_js', get_site_url() . '/wp-includes/js/jquery/jquery.js', array(), WC_HP_PLUGIN_VERSION );
}

/**
 * Get he data after placed the order
 *
 * @return string
 */
function submitedOrderData() {
	$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
	// check if request come from place order or pay order
	if ( isset( $_POST['order_action'] ) && 'pay_for_order' == $_POST['order_action'] ) {

		Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_repay_order_start ' );
		if ( isset( $_POST['auropay-repay-checkout-nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['auropay-repay-checkout-nonce'] ), 'auropay_repay_checkout_form_action' ) ) {
			$wc = new WC_Auropay_Gateway();
			$params = $wc->getPaymentLinkParams( $order_id, 1 );
			Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_repay_payment_link_creation_start ' );
			$response = WC_HP_API::getPaymentLink( $params );
			Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_repay_payment_link_creation_end ' );
			if ( isset( $response['error'] ) ) {
				echo 'error';
			} else {
				//save payment link in table
				update_post_meta( $order_id, '_hp_payment_link', $response['paymentLink'] );
				update_post_meta( $order_id, '_hp_payment_link_id', $response['id'] );

				$payment_link = $response['paymentLink'];
				$response = esc_url( $payment_link );
				wp_send_json( $response );
				die();
			}
		} else {
			// Nonce verification failed, handle the error or display an error message.
			die( esc_html( __( 'Nonce repay checkout auropay verification failed.', 'woocommerce-gateway-auropay' ) ) );
		}
		Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_repay_order_end ' );
		exit;
	} else {

		if ( 'step1' == $_POST['order_action'] ) {
			Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_order_creation_start ' );
			if ( isset( $_POST['auropay-process-checkout-nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['auropay-process-checkout-nonce'] ), 'auropay_checkout_form_action' ) ) {
				$sanitizedDataFields = filter_input( INPUT_POST, 'fields', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
				if ( null !== $sanitizedDataFields && is_array( $sanitizedDataFields ) ) {
					$order = new WC_Order();
					$cart = WC()->cart;
					$checkout = WC()->checkout;
					$data = [];

					add_filter( 'woocommerce_form_field', 'checkout_fields_in_label_error', 10, 4 );
					// Loop through posted data array transmitted via jQuery
					foreach ( $sanitizedDataFields as $values ) {
						// Set each key / value pairs in an array
						$data[$values['name']] = sanitize_text_field( $values['value'] );
					}

					$cart_hash = hash( "sha512", json_encode( wc_clean( $cart->get_cart_for_session() ) ) . $cart->total );
					$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

					if ( empty( $data['billing_first_name'] ) ) {
						exit;
						echo 'error';
						exit;
					}

					// Loop through the data array
					foreach ( $data as $key => $value ) {
						// Use WC_Order setter methods if they exist
						if ( is_callable( array( $order, "set_{$key}" ) ) ) {
							$order->{"set_{$key}"}( $value );

							// Store custom fields prefixed with wither shipping_ or billing_
						} elseif ( ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) )
							&& !in_array( $key, array( 'shipping_method', 'shipping_total', 'shipping_tax' ) )
						) {
							$order->update_meta_data( '_' . $key, $value );
						}
					}
					//set order data
					$order->set_created_via( 'checkout' );
					$order->set_cart_hash( $cart_hash );
					$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : '' ) );
					$order->set_currency( get_woocommerce_currency() );
					$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
					$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
					$order->set_customer_user_agent( wc_get_user_agent() );
					$order->set_customer_note( isset( $data['order_comments'] ) ? $data['order_comments'] : '' );
					$order->set_payment_method( isset( $available_gateways[$data['payment_method']] ) ? $available_gateways[$data['payment_method']] : $data['payment_method'] );
					$order->set_shipping_total( $cart->get_shipping_total() );
					$order->set_discount_total( $cart->get_discount_total() );
					$order->set_discount_tax( $cart->get_discount_tax() );
					$order->set_cart_tax( $cart->get_cart_contents_tax() + $cart->get_fee_tax() );
					$order->set_shipping_tax( $cart->get_shipping_tax() );
					$order->set_total( $cart->get_total( 'edit' ) );

					$checkout->create_order_line_items( $order, $cart );
					$checkout->create_order_fee_lines( $order, $cart );
					$checkout->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
					$checkout->create_order_tax_lines( $order, $cart );
					$checkout->create_order_coupon_lines( $order, $cart );
					do_action( 'woocommerce_checkout_create_order', $order, $data );
					// Save the order.
					$order_id = $order->save();
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_order_creation_end ' );
					do_action( 'woocommerce_checkout_update_order_meta', $order_id, $data );
					$wc = new WC_Auropay_Gateway();
					$params = $wc->getPaymentLinkParams( $order_id );
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_payment_link_api_creation_start ' );
					$response = WC_HP_API::getPaymentLink( $params );
					Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_payment_link_api_creation_end ' );

					if ( isset( $response['error'] ) ) {
						$order->update_status( 'failed' );
						echo 'error';
					} else {
						//save payment link in table
						update_post_meta( $order_id, '_hp_payment_link', $response['paymentLink'] );
						update_post_meta( $order_id, '_hp_payment_link_id', $response['id'] );

						$payment_link = $response['paymentLink'];
						$response = esc_url( $payment_link );
						wp_send_json( $response );
						die();
					}
				}
			} else {
				// Nonce verification failed, handle the error or display an error message.
				die( esc_html( __( 'Nonce checkout auropay verification failed.', 'woocommerce-gateway-auropay' ) ) );
			}
			Custom_Functions::log( WC_HP_ORDER_ID . ':' . $order_id . '_payment_link_creation_end ' );
		}
	}
	die();
}

/**
 * Update default place order button text and implementation
 *
 * @param string $button is the button html
 *
 * @return string
 */
function showCustomButton( $button ) {

	$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );

	if ( WC_HP_PLUGIN_NAME == $chosen_payment_method ) {

		$order_button_text = __( 'Place order and Continue', 'woocommerce' );
		wp_nonce_field( 'auropay_checkout_form_action', 'auropay-process-checkout-nonce' );
		$button = '<input type="button"
        onClick="process_order()"
        class="button alt" name="woocommerce_checkout_place_order"
        id="place_order" value="' . esc_attr( $order_button_text ) . '"
        data-value="' . esc_attr( $order_button_text ) . '" />
        <div id="loading" style="" > </div>
        <div class="ap-timeline-box" align="center">
    <div class="timeline-event" id="c_step1" style="display:none;">
      <div class="timeline-event-content">
        <div class="timeline-event-title">
        <img src="' . WC_HP_PLUGIN_URL . '/assets/images/creating-your-order.gif" width="400" height="200" /> <br />
        </div>
        <div class="timeline-event-description">
        <p style="color:#0000ff;">Hey! <br /> We are creating your order</p>
        </div>
      </div>
    </div>
    <div class="timeline-event" id="c_step3" style="display:none;">
      <div class="timeline-event-content">
        <div class="timeline-event-title">
        <img src="' . WC_HP_PLUGIN_URL . '/assets/images/loading-payment-form.png" width="400" height="200" /> <br />
        </div>
        <div class="timeline-event-description">
        <p style="color:#0000ff;">Almost there... <br / > Loading your payment form</p>
        </div>
      </div>
    </div>
  </div>';
	} else {
		?>
<script>
jQuery('#hp_iframe').hide();
</script>
<?php
}
	?>
<script type="text/javascript">
(function($) {
	jQuery('form.checkout').on('change', 'input[name^="payment_method"]', function() {
		var t = {
			updateTimer: !1,
			dirtyInput: !1,
			reset_update_checkout_timer: function() {
				clearTimeout(t.updateTimer)
			},
			trigger_update_checkout: function() {
				t.reset_update_checkout_timer(), t.dirtyInput = !1,
					jQuery(document.body).trigger("update_checkout")
			}
		};
		t.trigger_update_checkout();
	});
})(jQuery);
</script>
<?php
return $button;
}

/**
 * Update default pay order button text and implementation
 *
 * @param string $buttonText is the button html
 *
 * @return string
 */
function showCustomPayButton( $buttonText ) {
	global $order_pay_id;
	wp_nonce_field( 'auropay_repay_checkout_form_action', 'auropay-repay-checkout-nonce' );
	$gateways = WC()->payment_gateways->payment_gateways();
	$options = array();
	foreach ( $gateways as $id => $gateway ) {
		$options[] = $id;
	}

	if ( 'auropay' == $options[0] ) {
		$order_button_text = __( 'Pay For Auropay Order', 'woocommerce' );
		$buttonText = '<input type="button" onClick="pay_order(' . $order_pay_id .
		')" class="button alt wp-element-button" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '"  data-value="' . esc_attr( $order_button_text ) . '" style="width=100%;text-transform: uppercase;" /><div id="loading" style="" > </div>';
	} else {
		$order_button_text = __( 'Pay for order', 'woocommerce' );
		$buttonText = '<button type="submit" class="button alt wp-element-button"
		id="place_order" value="' . esc_attr( $order_button_text ) . '"
		data-value="' . esc_attr( $order_button_text ) . '" style="width=100%;text-transform: uppercase;" />"' . $order_button_text . '"</button>';
	}
	?>

<script type="text/javascript">
var order_pay_id = <?php echo esc_js( $order_pay_id ); ?>;
(function($) {
	jQuery('input[name^="payment_method"]').change(function() {
		if (jQuery(this).val() == 'auropay') {
			jQuery("#place_order").replaceWith('<input type="button" onClick="pay_order(' + order_pay_id +
				')" class="button alt wp-element-button" name="woocommerce_checkout_place_order" id="place_order" value="Pay For Auropay Order"  data-value="Pay For Auropay Order" style="width=100%;text-transform: uppercase;" /><div id="loading" style="" > </div>'
			)
		} else {
			jQuery("#place_order").replaceWith(
				'<button type="submit" class="button alt wp-element-button" id="place_order" value="Pay for order" data-value="Pay for order" style="width=100%;text-transform: uppercase;">Pay for order</button>'
			)
		}
	});
})(jQuery);
</script>

<?php
return $buttonText;
}

/**
 * Handle all JS functions related place order
 *
 * @return void
 */
function wpbHookJavascript() {
	$options = get_option( 'woocommerce_auropay_settings' );
	$expiry = $options['expiry'];
	?>
<script type="text/javascript">
var expiry_min = <?php echo esc_js( $expiry ); ?>;
var timeoutInMiliseconds = expiry_min * 60000;
var timeoutId;

function startTimer() {
	// window.setTimeout returns an Id that can be used to start and stop a timer
	timeoutId = window.setTimeout(doInactive, timeoutInMiliseconds)
}

function doInactive() {
	alert("Found no activity, so reloading checkout page again");
	// does whatever you need it to actually do - probably signs them out or stops polling the server for info
	window.location.reload();
}

function setupTimers() {
	document.addEventListener("mousemove", resetTimer, false);
	document.addEventListener("mousedown", resetTimer, false);
	document.addEventListener("keypress", resetTimer, false);
	document.addEventListener("touchmove", resetTimer, false);

	startTimer();
}

function resetTimer() {
	window.clearTimeout(timeoutId)
	startTimer();
}

function hide_iframe() {
	jQuery('.wc_payment_method').show();
	jQuery('.wc_payment_methods').show();
	jQuery('.form-row').show();
	jQuery('#hp_iframe').hide();
	jQuery('#err-msg').hide();
	jQuery('#close_iframe').hide();
	jQuery('#place_order').show();
	jQuery('.ap-timeline-box').hide();
	jQuery('#billing_phone').prop("readonly", false);
	jQuery('#billing_email').prop("readonly", false);
}

function alertFunc() {
	//alert("alertFunc!");
	var delimg =
		"<img src='<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/close.png' width='20' height='20' onClick='hide_iframe()' style='cursor:pointer' >";

	jQuery('#place_order').hide();
	jQuery('.woocommerce-privacy-policy-text').hide();
	jQuery('#loading').hide();
	jQuery('.wc_payment_method').hide();
	jQuery('.wc_payment_methods').hide();
	jQuery('.form-row.place-order').hide();
	jQuery('#close_iframe').show();
	jQuery('#hp_iframe').show();
}

function isPhoneAndEmailInputsReadonly(isReadonly) {
	jQuery('#billing_phone').prop("readonly", isReadonly);
	jQuery('#billing_email').prop("readonly", isReadonly);
}

function showPaymentMethods(isShow) {
	if (isShow == true) {
		jQuery('.wc_payment_method').show();
		jQuery('.wc_payment_methods').show();
	}
	if (isShow == false) {
		jQuery('.wc_payment_method').hide();
		jQuery('.wc_payment_methods').hide();
	}
}

/*
 * Ajax request to create order
 */
function process_order() {
	var self = jQuery('#place_order');
	var any_invalid = false;
	var $div_data = '';

	if (jQuery(".woocommerce-NoticeGroup-checkout")[0]) {
		jQuery('.woocommerce-NoticeGroup-checkout').remove();
	}
	jQuery('.woocommerce-form-coupon-toggle').append(
		'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>');
	$div_data += '<ul class="woocommerce-error" role="alert">';

	jQuery('.validate-required').each(function() {

		var $this = jQuery(this).find('input[type=checkbox],select,.input-text'),
			$parent = $this.closest('.form-row'),
			validated = true,
			validate_required = $parent.is('.validate-required'),
			validate_email = $parent.is('.validate-email');

		if (validate_required) {
			if ('checkbox' === $this.attr('type') && !$this.is(':checked')) {
				jQuery.each($this, function(key, value) {
					if (value.id == 'terms') {
						$div_data += '<li data-id="' + value.id +
							'">Please read and accept the ' + jQuery(
								"label[for='" + value.id + "']").text() +
							'terms and conditions to proceed with your order.</li>';
						$parent.removeClass('woocommerce-validated').addClass(
							'woocommerce-invalid woocommerce-invalid-required-field');
						validated = false;
						any_invalid = true;
					}
				});
			} else if ($this.val().trim() == '') {
				jQuery.each($this, function(key, value) {
					if (value.id == 'billing_first_name' || value.id == 'billing_last_name' || value
						.id == 'billing_state' || value
						.id == 'billing_postcode' || value.id == 'billing_phone' || value.id ==
						'billing_email' || value.id == 'billing_address_1' || value.id == 'billing_city'
					) {
						$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
								"label[for='" + value.id + "']").text() +
							'</strong> is a required field.</li>';
						$parent.removeClass('woocommerce-validated').addClass(
							'woocommerce-invalid woocommerce-invalid-required-field');
						validated = false;
						any_invalid = true;
					}
				});
			} else if ($this.val() != '') {
				var email_regex = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
				var phone_number_regex = /^[0-9-+]+$/;
				var pincode_regex = /^[0-9]+$/;
				jQuery.each($this, function(key, value) {
					if (value.id == 'billing_postcode') {
						if (pincode_regex.test(value.value) === false) {
							$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
									"label[for='" + value.id + "']").text() +
								'</strong> is not valid.</li>';
							$parent.removeClass('woocommerce-validated').addClass(
								'woocommerce-invalid woocommerce-invalid-required-field');
							validated = false;
							any_invalid = true;
						} else if (value.value.length != 6) {
							$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
									"label[for='" + value.id + "']").text() +
								'</strong> is not valid.</li>';
							$parent.removeClass('woocommerce-validated').addClass(
								'woocommerce-invalid woocommerce-invalid-required-field');
							validated = false;
							any_invalid = true;
						}
					}
					if (value.id == 'billing_phone') {
						if (phone_number_regex.test(value.value) === false) {
							$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
									"label[for='" + value.id + "']").text() +
								'</strong> is not valid.</li>';
							$parent.removeClass('woocommerce-validated').addClass(
								'woocommerce-invalid woocommerce-invalid-required-field');
							validated = false;
							any_invalid = true;
						} else if (value.value.length != 10) {
							$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
									"label[for='" + value.id + "']").text() +
								'</strong> is not valid. Enter 10 digit number</li>';
							$parent.removeClass('woocommerce-validated').addClass(
								'woocommerce-invalid woocommerce-invalid-required-field');
							validated = false;
							any_invalid = true;
						}
					}
					if (value.id == 'billing_email') {
						if (email_regex.test(value.value) === false) {
							$div_data += '<li data-id="' + value.id + '"><strong>Billing ' + jQuery(
									"label[for='" + value.id + "']").text() +
								'</strong> is not valid.</li>';
							$parent.removeClass('woocommerce-validated').addClass(
								'woocommerce-invalid woocommerce-invalid-required-field');
							validated = false;
							any_invalid = true;
						}
					}
				});
			}
		}

		if (validated) {
			$parent.removeClass(
					'woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email')
				.addClass('woocommerce-validated');
		}
	});

	$div_data += '</ul>';

	jQuery('.woocommerce-NoticeGroup-checkout').html($div_data);

	if (any_invalid) {
		// Scroll to first invalid input
		var $first_invalid = jQuery('.woocommerce-invalid:first');

		jQuery('html,body').animate({
			scrollTop: $first_invalid.offset().top - 400
		}, 1000);

		jQuery('.woocommerce-invalid').find('input,select').on('input change', function() {
			jQuery(this).closest('.form-row').removeClass(
				'woocommerce-invalid woocommerce-invalid-required-field');
		});
		return false;
	}

	var delimg =
		"<img src='<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/close.png' width='20' height='20' onClick='hide_iframe()' style='cursor:pointer' >";

	jQuery('#place_order').hide();
	jQuery('#c_step1').removeClass();
	jQuery('#c_step3').removeClass();
	showPaymentMethods(false);
	jQuery('#c_step1').css('display', 'none');
	jQuery('#c_step3').css('display', 'none');
	jQuery('#c_step1').css('display', 'block');

	jQuery('.ap-timeline-box').show();
	isPhoneAndEmailInputsReadonly(true);
	jQuery('.woocommerce-NoticeGroup-checkout ul').removeClass('woocommerce-error')

	jQuery.ajax({
		type: 'POST',
		url: wc_checkout_params.ajax_url,
		contentType: "application/x-www-form-urlencoded; charset=UTF-8",
		enctype: 'multipart/form-data',
		data: {
			'action': 'ajax_order',
			'fields': jQuery('form.checkout').serializeArray(),
			'auropay-process-checkout-nonce': jQuery('#auropay-process-checkout-nonce').val(),
			'user_id': <?php echo esc_js( get_current_user_id() ); ?>,
			'order_action': 'step1',
		},
		success: function(result) {
			jQuery('#c_step1').css('display', 'none');
			jQuery('#c_step3').css('display', 'block');

			if (result != 'error') {
				jQuery('#payment').append(
					'<div class="del-img" id="close_iframe" style="display:none">' +
					delimg + '</div>');
				if (jQuery('#hp_iframe').length == 0) {
					jQuery('#payment').append('<iframe src="' + result +
						'" name="hp_iframe" id="hp_iframe" scrolling="yes" frameborder=0 class="iframe-cs" style="display:none"></iframe>'
					);
				} else {
					jQuery('#hp_iframe').remove();
					jQuery('#payment').append('<iframe src="' + result +
						'" name="hp_iframe" id="hp_iframe" scrolling="yes" frameborder=0 class="iframe-cs" style="display:none"></iframe>'
					);
				}
				var myVar;
				myVar = setTimeout(alertFunc, 5000);
			} else {
				jQuery('#payment').append(
					'<div id="err-msg">Error when loading the payment form, please contact support team!</div>'
				);
				showPaymentMethods(true);
				isPhoneAndEmailInputsReadonly(false);
			}
			//check inactivity
			setupTimers();
		},
		error: function(error) {
			console.log(error); // For testing (to be removed)
			showPaymentMethods(true);
			isPhoneAndEmailInputsReadonly(false);
		}
	});
}

/*
 * Ajax request to pay order
 */
function pay_order(order_id) {
	var self = jQuery('#place_order');
	var any_invalid = false;
	var $div_data = '';

	$div_data += '<ul class="woocommerce-error" role="alert">';

	jQuery('.validate-required').each(function() {

		var $this = jQuery(this).find('input[type=checkbox],select,.input-text'),
			$parent = $this.closest('.form-row'),
			validated = true,
			validate_required = $parent.is('.validate-required');

		if (validate_required) {
			if ('checkbox' === $this.attr('type') && !$this.is(':checked')) {
				jQuery.each($this, function(key, value) {
					if (value.id == 'terms') {
						$div_data += '<li data-id="' + value.id +
							'">Please read and accept the ' + jQuery(
								"label[for='" + value.id + "']").text() +
							'terms and conditions to proceed with your order.</li>';
						$parent.removeClass('woocommerce-validated').addClass(
							'woocommerce-invalid woocommerce-invalid-required-field');
						validated = false;
						any_invalid = true;
					}
				});
			}
		}

		if (validated) {
			$parent.removeClass(
					'woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email')
				.addClass('woocommerce-validated');
		}
	});

	$div_data += '</ul>';

	jQuery('.woocommerce-notices-wrapper').html($div_data);

	if (any_invalid) {
		// Scroll to first invalid input
		var $first_invalid = jQuery('.woocommerce-invalid:first');

		jQuery('html,body').animate({
			scrollTop: $first_invalid.offset().top - 400
		}, 1000);

		jQuery('.woocommerce-invalid').find('input,select').on('input change', function() {
			jQuery(this).closest('.form-row').removeClass(
				'woocommerce-invalid woocommerce-invalid-required-field');
		});
		return false;
	}

	jQuery('.woocommerce-notices-wrapper ul').removeClass('woocommerce-error')

	var delimg =
		"<img src='<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/close.png' width='20' height='20' onClick='hide_iframe()' style='cursor:pointer' >";

	jQuery('#place_order').hide();
	showPaymentMethods(false);
	jQuery('#loading').show();

	jQuery.ajax({
		type: 'POST',
		url: wc_checkout_params.ajax_url,
		contentType: "application/x-www-form-urlencoded; charset=UTF-8",
		enctype: 'multipart/form-data',
		data: {
			'action': 'ajax_order',
			'auropay-repay-checkout-nonce': jQuery('#auropay-repay-checkout-nonce').val(),
			'user_id': <?php echo esc_js( get_current_user_id() ); ?>,
			'order_id': order_id,
			'order_action': 'pay_for_order',
		},
		success: function(result) {
			jQuery('#place_order').hide();
			jQuery('.woocommerce-privacy-policy-text').hide();
			jQuery('#loading').hide();
			jQuery('.wc_payment_method').hide();
			jQuery('.wc_payment_methods').hide();
			jQuery('#payment').append('<div class="del-img" id="close_iframe">' + delimg + '</div>');
			jQuery('#close_iframe').show();

			if (result != 'error') {
				if (jQuery('#hp_iframe').length == 0) {
					jQuery('#payment').append('<iframe src="' + result +
						'" name="hp_iframe" id="hp_iframe" scrolling="yes" frameborder=0 class="iframe-cs" ></iframe>'
					);
				} else {
					jQuery('#hp_iframe').remove();
					jQuery('#payment').append('<iframe src="' + result +
						'" name="hp_iframe" id="hp_iframe" scrolling="yes" frameborder=0 class="iframe-cs" ></iframe>'
					);
				}
			} else {
				// console.log(jQuery('#err-msg').length);
				if (jQuery('#err-msg').length == 0) {
					jQuery('#payment').append(
						'<div id="err-msg">Error when loading the payment form, please contact support team!</div>'
					);
				} else {
					jQuery('#err-msg').show();
				}
				showPaymentMethods(true);
			}
			//check inactivity
			setupTimers();
		},
		error: function(error) {
			// console.log(error); // For testing (to be removed)
			showPaymentMethods(true);
		}
	});
}
</script>
<?php
}
?>
