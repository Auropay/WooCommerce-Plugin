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
add_action('wp_enqueue_scripts', 'addPaymentOverviewStyle');
/**
 * This includes styles
 * 
 * @return void
 */
function addPaymentOverviewStyle()
{
    wp_enqueue_style('hp_styles', WC_HP_PLUGIN_URL . '/assets/style.css');
    wp_enqueue_style('hp_bank_icon_styles', WC_HP_PLUGIN_URL . '/assets/bank-icon.css');
}
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
                        <div class="trans-dtl-tbl-head"><b>Transaction Details</b></div>
                        <ul class="subsubsub">
                            <li class="all"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=all&<?php echo $range_filter ?>" <?php echo $all_current_class ?>>All <span class="count">(<?php echo $total_all_records['total_records'] ?>)</span></a> |</li>
                            <li class="wc-completed"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=completed&<?php echo $range_filter ?>" <?php echo $completed_current_class ?>>Sales <span class="count">(<?php echo $total_completed_records['total_records'] ?>)</span></a> |</li>
                            <li class="wc-refunded"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=refunded&<?php echo $range_filter ?>" <?php echo $refunded_current_class ?>>Refunded <span class="count">(<?php echo $total_refunded_records['total_records'] ?>)</span></a> |</li>
                            <li class="wc-failed"><a href="?page=wc-payment_overview&orderby=date_created&order=desc&transaction_status=failed&<?php echo $range_filter ?>" <?php echo $failed_current_class ?>>Failed <span class="count">(<?php echo $total_failed_records['total_records'] ?>)</span></a></li>
                        </ul>
                        <div class="export-section">
                            <form class="form-horizontal" action="" enctype="multipart/form-data" method="post" name="upload_excel">
                                <div class="form-group">
                                    <div class="col-md-4 col-md-offset-4" style="cursor:pointer">
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
                                <thead>
                                    <tr>
                                        <th scope="col" id="order_number" class="manage-column column-order_number c_order_number column-primary1 sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=order_id&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Order</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="order_date" class="manage-column column-order_number   c_order_date column-primary2 sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=date_created&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Date & Time (IST)</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="order_status" class="manage-column column-order_status  c_order_status hidden sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=status&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Status</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="order_total" class="manage-column column-order_status c_order_total column-primary3 sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=transaction_total&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Sale</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="order_total" class="manage-column column-order_status c_order_total column-primary3 sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=transaction_total&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Refund</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="order_type" class="manage-column column-order_status1  c_order_type sortable <?php echo $link_order ?>">
                                            <a href="?page=wc-payment_overview&orderby=status&order=<?php echo $link_order ?>&<?php echo $range_filter ?>">
                                                <span>Type</span><span class="sorting-indicator"></span>
                                            </a>
                                        </th>
                                        <th scope="col" id="payment_method" class="manage-column column-order_status2 c_payment_method ">Method</th>
                                        <th scope="col" id="payment_method" class="manage-column column-order_status2 c_card_type ">Payment Detail</th>
                                        <th scope="col" id="auth_code" class="manage-column column-order_status3 c_auth_code ">Auth Code</th>
                                        <th scope="col" id="wc_actions" class="manage-column column-wc_actions hidden">Actions</th>
                                    </tr>
                                </thead>

                                <tbody id="the-list">
                                    <?php
                                    foreach ($lists as $payment) {
                                        if ($payment->status == 'wc-refunded') {
                                            $type = "Refund";
                                        } else {
                                            $type = "Sale";
                                        }

                                        if ($payment->status == 'wc-failed') {
                                            $status = "Failed";
                                        } else if ($payment->status == 'wc-processing') {
                                            $status = "Processing";
                                        } else if ($payment->status == 'wc-refunded') {
                                            $status = "Refunded";
                                        } else if ($payment->status == 'wc-cancelled') {
                                            $status = "Cancelled";
                                        } else {
                                            $status = "Completed";
                                        }

                                        $net_total_amt = $payment->net_total + $payment->tax_total + $payment->shipping_total;
                                        $net_total_amt = number_format((float)$net_total_amt, 2, '.', '');

                                        $order = wc_get_order($payment->order_id);

                                        $sale = $order->get_total();
                                        $refund = '0.00';

                                        if (sizeof($order->get_refunds()) > 0 && $status != 'Failed') {
                                            $refund = $order->get_total_refunded();
                                            // $sale = $sale - $refund;
                                            $refund = 0 - $refund;
                                            $refund = number_format((float)$refund, 2, '.', '');
                                        }

                                        $sale = number_format((float)$sale, 2, '.', '');

                                        //get type and auth code
                                        $type_array = array('3' => 'Credit Card', '4' => 'Debit Card', '6' => 'UPI', '7' => 'NetBanking', '8' => 'Wallets');
                                        $payment_method = get_post_meta($payment->order_id, '_hp_transaction_channel_type', true);

                                        if (isset($type_array[$payment_method])) {
                                            $payment_method = $type_array[$payment_method];
                                        } else {
                                            $payment_method = "Credit Card";
                                        }

                                        $auth_code = get_post_meta($payment->order_id, '_hp_transaction_auth_code', true);
                                        $card_type = get_post_meta($payment->order_id, '_hp_transaction_card_type', true);
                                        ?>

                                        <tr id="post-<?php echo $payment->order_id ?>" class="iedit author-self level-0 post-146 type-shop_order post-password-required hentry">
                                            <td id="row_order_number" class="order_number column-order_number c_order_number has-row-actions column-primary1">
                                                <a href="post.php?post=<?php echo $payment->order_id ?>&amp;action=edit" class="order-view">
                                                    <strong>#<?php echo $payment->order_id ?> </strong>
                                                </a>
                                            </td>
                                            <td id="row_order_date" class="order_date column-order_date c_order_date">
                                                <time datetime="<?php echo $payment->date_created ?>" title="<?php echo $payment->date_created ?>">
                                                    <?php echo date('d-m-Y H:i:s', strtotime($payment->date_created)) ?>
                                                </time>
                                            </td>
                                            <td id="row_order_status" class="order_status column-order_status1 c_order_status hidden" data-colname="Status">
                                                <span class="woocommerce-order-status woocommerce-orders-table__status woocommerce-order-status__indicator is-processing">

                                                    <mark class="order-status status-<?php echo strtolower($status) ?> tips"><span><?php echo $status ?></span></mark>
                                                </span>
                                            </td>
                                            <td id="row_order_total" class="order_total column-order_status c_order_total">
                                                <span><span class="woocommerce-Price-amount amount">
                                                        <?php echo wc_price($sale, array('decimal_separator' => '.', 'decimals' => 2)) ?>
                                                    </span>
                                                </span>
                                            </td>
                                            <td id="row_order_total" class="order_total column-order_status c_order_total">
                                                <span>
                                                    <?php if ($refund == 0.00) { ?>
                                                        <span class="woocommerce-Price-amount amount">
                                                            <?php echo wc_price($refund, array('decimal_separator' => '.', 'decimals' => 2)) ?>
                                                        </span>
                                                    <?php } else { ?>
                                                        <span class="woocommerce-Price-amount amount" style="color:red">
                                                            <?php echo wc_price($refund, array('decimal_separator' => '.', 'decimals' => 2)) ?>
                                                        </span>
                                                    <?php } ?>
                                                </span>
                                            </td>
                                            <td id="row_order_type" class="order_status column-order_status1 c_order_type">
                                                <span><?php echo $type ?></span>
                                            </td>
                                            <td id="row_payment_method" class="order_status column-order_status2 c_payment_method" data-colname="Status2">
                                                <span><?php echo $payment_method ?></span>
                                            </td>
                                            <td id="row_payment_method" class="order_status column-order_status2 c_card_type" data-colname="Status2">
                                                <span><?php echo $card_type ?></span>
                                            </td>
                                            <td id="row_auth_code" class="order_date column-order_status2 c_auth_code" data-colname="Status2">
                                                <span><?php echo $auth_code ?></span>
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
                                <span class="displaying-num">Total <?php echo $total ?> items</span>
                                <span class="pagination_links"><?php echo $page_links ?></span>
                            </div>
                            <br class="clear">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>