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
add_action( 'wp_enqueue_scripts', 'addPaymentOverviewStyle' );
/**
 * This includes styles
 *
 * @return void
 */
function addPaymentOverviewStyle() {
	wp_enqueue_style( 'hp_styles', WC_HP_PLUGIN_URL . '/assets/style.css', array(), WC_HP_PLUGIN_VERSION );
	wp_enqueue_style( 'hp_bank_icon_styles', WC_HP_PLUGIN_URL . '/assets/bank-icon.css', array(), WC_HP_PLUGIN_VERSION );
}

$statusArr = orderStatusMapping();
$auropayStatusArr = auropayStatusMapping();
?>

<div id="wpwrap" style="width:97%">
	<div id="wpbody" role="main">
		<div id="wpbody-content">
			<div class="wrap">
				<h1 class="wp-heading-inline">Payments Overview</h1>

				<div class="woocommerce-section-header ">
					<?php
$report = new Payment_Overview_Report();
$report->outputReport();
?>

					<div class="trans-dtl-tbl">
						<div class="trans-dtl-tbl-head"><strong>Transaction Details</strong></div>
						<ul class="subsubsub">
							<li class="all"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=all&<?php echo esc_attr( $range_filter ); ?>" <?php echo esc_attr( $all_current_class ); ?>>All <span class="count">(<?php echo esc_html( $total_all_records['total_records'] ); ?>)</span></a>
								|
							</li>
							<li class="wc-completed"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=completed&<?php echo esc_attr( $range_filter ); ?>" <?php echo esc_attr( $completed_current_class ); ?>>Sales <span class="count">(<?php echo esc_html( $total_completed_records['total_records'] ); ?>)</span></a>
								|</li>
							<li class="wc-refunded"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=refunded&<?php echo esc_attr( $range_filter ); ?>" <?php echo esc_attr( $refunded_current_class ); ?>>Refunded <span class="count">(<?php echo esc_html( $total_refunded_records['total_records'] ); ?>)</span></a>
								|</li>
							<li class="wc-failed"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=failed&<?php echo esc_attr( $range_filter ); ?>" <?php echo esc_attr( $failed_current_class ); ?>>Failed <span class="count">(<?php echo esc_html( $total_failed_records['total_records'] ); ?>)</span></a>
							</li>
						</ul>
						<div class="export-section">
							<form class="form-horizontal" action="" enctype="multipart/form-data" method="post" name="upload_excel">
								<div class="form-group">
									<div class="col-md-4 col-md-offset-4" style="cursor:pointer">
										<?php wp_nonce_field( 'export_form_nonce', 'export_form_nonce_field' );?>
										<select name="export_type" id="export_type" onchange="this.form.submit()">
											<option selected="selected" value="0">&#8595; Export</option>
											<option value="csv">CSV</option>
											<option value="pdf">PDF</option>
										</select>
										<input class="btn btn-success export-input" name="Export" type="hidden" value="&#8595; Export" />
									</div>
								</div>
							</form>
						</div>

						<div class="post-type-shop_order1 order-lst-tp">
							<table class="wp-list-table widefat fixed striped w-auto table-view-list posts ">
								<caption><strong>Sales Report</strong></caption>
								<thead>
									<tr>
										<th scope="col" id="order_number" class="manage-column column-order_number c_order_number column-primary1 sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=order_id&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Order</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="order_date" class="manage-column column-order_number   c_order_date column-primary2 sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=date_created&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Date & Time (IST)</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="order_status" class="manage-column column-order_status  c_order_status hidden sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=status&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Status</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="order_status"
											class="manage-column column-order_status  c_order_status hidden  <?php echo esc_attr( $link_order ); ?>">
											<span>Auropay Status</span>
										</th>
										<th scope="col" id="order_total" class="manage-column column-order_status c_order_total column-primary3 sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=transaction_total&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Sale</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="order_total" class="manage-column column-order_status c_order_total column-primary3 sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=transaction_total&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Refund</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="order_type" class="manage-column column-order_status1  c_order_type sortable <?php echo esc_attr( $link_order ); ?>">
											<a href="?page=wc-payment_overview&orderby=status&order=<?php echo esc_attr( $link_order ); ?>&<?php echo esc_attr( $range_filter ); ?>">
												<span>Type</span><span class="sorting-indicator"></span>
											</a>
										</th>
										<th scope="col" id="payment_id" class="manage-column c_payment_id ">Payment Id
										</th>
										<th scope="col" id="payment_method" class="manage-column column-order_status2 c_payment_method ">Method</th>
										<th scope="col" id="payment_method" class="manage-column column-order_status2 c_card_type ">Payment Detail</th>
										<th scope="col" id="auth_code" class="manage-column column-order_status3 c_auth_code ">Auth Code</th>
										<th scope="col" id="wc_actions" class="manage-column column-wc_actions hidden">
											Actions</th>
									</tr>
								</thead>

								<tbody id="the-list">
									<?php
foreach ( $lists as $payment ) {
	if ( 'wc-refunded' == $payment->status ) {
		$refundType = 'Refund';
	} else {
		$refundType = 'Sale';
	}

	if ( 'wc-failed' == $payment->status ) {
		$paymentStatus = 'Failed';
	} elseif ( 'wc-processing' == $payment->status ) {
		$paymentStatus = 'Processing';
	} elseif ( 'wc-refunded' == $payment->status ) {
		$paymentStatus = 'Refunded';
	} elseif ( 'wc-cancelled' == $payment->status ) {
		$paymentStatus = 'Cancelled';
	} elseif ( 'wc-pending' == $payment->status ) {
		$paymentStatus = 'Pending';
	} elseif ( 'wc-on-hold' == $payment->status ) {
		$paymentStatus = 'On-hold';
	} else {
		$paymentStatus = 'Completed';
	}

	$net_total_amt = $payment->net_total + $payment->tax_total + $payment->shipping_total;
	$net_total_amt = number_format( (float) $net_total_amt, 2, '.', '' );

	$auropayOrder = wc_get_order( $payment->order_id );

	$sale = $auropayOrder->get_total();
	$refund = '0.00';

	if ( count( $auropayOrder->get_refunds() ) > 0 && 'Failed' != $paymentStatus ) {
		$refund = $auropayOrder->get_total_refunded();
		$refund = 0 - $refund;
		$refund = number_format( (float) $refund, 2, '.', '' );
	}

	$sale = number_format( (float) $sale, 2, '.', '' );

	//get type and auth code
	$type_array = array( '3' => 'Credit Card', '4' => 'Debit Card', '6' => 'UPI', '7' => 'NetBanking', '8' => 'Wallets' );
	$payment_method = get_post_meta( $payment->order_id, '_hp_transaction_channel_type', true );
	$auropayPaymentId = get_post_meta( $payment->order_id, '_hp_transaction_id', true );
	$auropayPaymentStatus = get_post_meta( $payment->order_id, '_hp_transaction_status', true );
	if ( !empty( $auropayStatusArr[$auropayPaymentStatus] ) ) {
		$auropayPaymentStatus = $auropayStatusArr[$auropayPaymentStatus];
	}
	if ( isset( $type_array[$payment_method] ) ) {
		$payment_method = $type_array[$payment_method];
	} else {
		$payment_method = '';
	}

	$auth_code = get_post_meta( $payment->order_id, '_hp_transaction_auth_code', true );
	$card_type = get_post_meta( $payment->order_id, '_hp_transaction_card_type', true );
	?>

										<tr id="post-<?php echo esc_attr( $payment->order_id ); ?>" class="iedit author-self level-0 post-146 type-shop_order post-password-required hentry">
											<td id="row_order_number" class="order_number column-order_number c_order_number has-row-actions column-primary1">
												<a href="post.php?post=<?php echo esc_attr( $payment->order_id ); ?>&amp;action=edit" class="order-view">
													<strong>#<?php echo esc_html( $payment->order_id ); ?> </strong>
												</a>
											</td>
											<td id="row_order_date" class="order_date column-order_date c_order_date">
												<time datetime="<?php echo esc_attr( $payment->date_created ); ?>" title="<?php echo esc_attr( $payment->date_created ); ?>">
													<?php echo esc_html( gmdate( 'd-m-Y H:i:s', strtotime( $payment->date_created ) ) ); ?>
												</time>
											</td>
											<td id="row_order_status" class="order_status column-order_status1 c_order_status hidden" data-colname="Status">
												<span class="woocommerce-order-status woocommerce-orders-table__status woocommerce-order-status__indicator is-processing">

													<mark class="order-status status-<?php echo esc_attr( strtolower( $paymentStatus ) ); ?> tips"><span><?php echo esc_html( $paymentStatus ); ?></span></mark>
												</span>
											</td>
											<td id="row_order_status" class="order_status column-order_status1 c_order_status"
											data-colname="Status">
												<span
												class="woocommerce-order-status woocommerce-orders-table__status woocommerce-order-status__indicator is-processing">

												<mark
													class="order-status status-<?php echo esc_attr( strtolower( $auropayPaymentStatus ) ); ?> tips"><span><?php echo esc_html( ucFirst( $auropayPaymentStatus ) ); ?></span></mark>
												</span>
											</td>
											<td id="row_order_total" class="order_total column-order_status c_order_total">
												<span><span class="woocommerce-Price-amount amount">
														<?php echo wp_kses_post( wc_price( $sale, array( 'decimal_separator' => '.', 'decimals' => 2 ) ) ); ?>
													</span>
												</span>
											</td>
											<td id="row_order_total" class="order_total column-order_status c_order_total">
												<span>
													<?php if ( 0.00 == $refund ) {?>
														<span class="woocommerce-Price-amount amount">
															<?php echo wp_kses_post( wc_price( $refund, array( 'decimal_separator' => '.', 'decimals' => 2 ) ) ); ?>
														</span>
													<?php } else {?>
														<span class="woocommerce-Price-amount amount" style="color:red">
															<?php echo wp_kses_post( wc_price( $refund, array( 'decimal_separator' => '.', 'decimals' => 2 ) ) ); ?>
														</span>
													<?php }?>
												</span>
											</td>
											<td id="row_order_type" class="order_status column-order_status1 c_order_type">
												<span><?php echo esc_html( $refundType ); ?></span>
											</td>
											<td id="row_payment_id" class="order_payment_id column-payment_id c_order_payment_id">
												<span><?php echo esc_html( $auropayPaymentId ); ?></span>
											</td>
											<td id="row_payment_method" class="order_status column-order_status2 c_payment_method" data-colname="Status2">
												<span><?php echo esc_html( $payment_method ); ?></span>
											</td>
											<td id="row_payment_card_type" class="order_status column-order_status2 c_card_type" data-colname="Status2">
												<span><?php echo esc_html( $card_type ); ?></span>
											</td>
											<td id="row_auth_code" class="order_date column-order_status2 c_auth_code" data-colname="Status2">
												<span><?php echo esc_html( $auth_code ); ?></span>
											</td>
											<td class="wc_actions column-wc_actions hidden" data-colname="Actions">
												<p></p>
											</td>
										</tr>
									<?php
}
?>
								</tbody>
							</table>
						</div>

						<div class="tablenav bottom">
							<div class="tablenav-pages order-lst-tp">
								<span class="displaying-num">Total <?php echo esc_html( $total ); ?> items</span>
								<span class="pagination_links"><?php echo wp_kses_post( $page_links ); ?></span>
							</div>
							<br class="clear">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
