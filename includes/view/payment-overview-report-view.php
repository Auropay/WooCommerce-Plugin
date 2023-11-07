<?php

/**
 * An external standard for Auropay.
 *
 * @package AuroPay_Gateway_For_WooCommerce
 * @link    https://auropay.net/
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
?>
<div id="poststuff" class="woocommerce-reports-wide">
	<div class="postbox pt-bx-dtl">

		<?php if ('custom' === $current_range && isset($_GET['start_date'], $_GET['end_date'])) : ?>
			<h3 class="screen-reader-text">
				<?php
				printf(
					/* translators: "%1$s": From date, "%2$s": to date is displayed'. */
					esc_html__('From %1$s to %2$s', 'woocommerce'),
					esc_html(wc_clean(wp_unslash($_GET['start_date']))),
					esc_html(wc_clean(wp_unslash($_GET['end_date'])))
				);
				?>
			</h3>
		<?php else : ?>
			<h3 class="screen-reader-text"><?php echo esc_html($ranges[$current_range]); ?></h3>
		<?php endif; ?>

		<div class="stats_range">
			<?php $this->get_export_button(); ?>
			<ul>
				<?php
				foreach ($ranges as $range => $name) {
					if ('Custom' != $name) {
						echo '<li class="' . ( $current_range == $range ? 'active' : '' ) . ' odate_range" id="' . esc_attr( $range ) . '" ><a href="' . esc_url( remove_query_arg( array( 'start_date', 'end_date' ), add_query_arg( 'range', $range) ) ) . '">' . esc_html( $name ) . '</a></li>';
					} else {
						echo '<li class="' . ( $current_range == $range ? 'active' : '' ) . ' custom_range " id="custom" ><a href="#" >' . esc_html( $name ) . '</a></li>';
					}
				}
				?>

				<li class="custom active" id="custom-box">
					<form method="GET">
						<div>
							<?php
							// Maintain query string.
							foreach ($_GET as $key => $value) {
								if (is_array($value)) {
									foreach ($value as $v) {
										echo '<input type="hidden" name="' . esc_attr( sanitize_text_field( $key ) ) . '[]" value="' . esc_attr( sanitize_text_field( $v ) ) . '" />';
									}
								} else {
									echo '<input type="hidden" name="' . esc_attr( sanitize_text_field( $key ) ) . '" value="' . esc_attr( sanitize_text_field( $value ) ) . '" />';
								}
							}
							?>
							<input type="hidden" name="range" value="custom" />
							From: <input type="text" size="11" placeholder="mm-dd-yyyy" value="<?php echo ( !empty( $_GET['start_date'] ) ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) ) : ''; ?>" name="start_date" class="range_datepicker from" autocomplete="off" />
							<span></span>To:
							<input type="text" size="11" placeholder="mm-dd-yyyy" value="<?php echo ( !empty( $_GET['end_date'] ) ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) ) : ''; ?>" name="end_date" class="range_datepicker to" autocomplete="off" />
							<button type="submit" class="button" value="<?php esc_attr_e('Go', 'woocommerce'); ?>"><?php esc_html_e('Go', 'woocommerce'); ?></button>
							<?php wp_nonce_field('custom_range', 'wc_reports_nonce', false); ?>
						</div>
					</form>
				</li>

			</ul>
		</div>

		<?php
		global $tot_payments;
		global $tot_refunded;
		global $tot_failed;

		global $sale_tot_credit_card_payments;
		global $sale_tot_debit_card_payments;
		global $sale_tot_netbanking_payments;
		global $sale_tot_upi_payments;
		global $sale_tot_wallet_payments;

		global $refunded_tot_credit_card_payments;
		global $refunded_tot_debit_card_payments;
		global $refunded_tot_netbanking_payments;
		global $refunded_tot_upi_payments;
		global $refunded_tot_wallet_payments;

		global $failed_tot_credit_card_payments;
		global $failed_tot_debit_card_payments;
		global $failed_tot_netbanking_payments;
		global $failed_tot_upi_payments;
		global $failed_tot_wallet_payments;
		?>
		<div class="pymnt-hdr">
			<div class="leftbox-sales" id="sale_box">
				<img class="pymnt-ico" alt="Sales" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/summary/calculator_color.png" id="sale_img" />
				<div class="pymnt-ico-label">
					<div class="pymnt-label">
						<strong> Sales</strong>
					</div>
					<div class="pymnt-amt clr-grn">
						<strong><span><?php echo wp_kses_post(wc_price($tot_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span></strong>
					</div>
				</div>
			</div>
			<div class="middlebox-refund" id="refunded_box">
				<img class="pymnt-ico" alt="Refunds" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/summary/calendar_refund_color.png" id="refunded_img" />
				<div class="pymnt-ico-label">
					<div class="pymnt-label">
						<strong>Refund</strong>
					</div>
					<div class="pymnt-amt clr-orng">
						<strong><span><strong><span><?php echo wp_kses_post(wc_price($tot_refunded, array('decimal_separator' => '.', 'decimals' => 2))); ?></span></strong></span></strong>
					</div>
				</div>
			</div>
			<div class="rightbox-failed" id="failed_box">
				<img class="pymnt-ico" alt="Failed" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/summary/calendar_decline_color.png" id="failed_img" />
				<div class="pymnt-ico-label">
					<div class="pymnt-label">
						<strong>Failed</strong>
					</div>
					<div class="pymnt-amt clr-rd">
						<strong><span><?php echo wp_kses_post(wc_price($tot_failed, array('decimal_separator' => '.', 'decimals' => 2))); ?></span></strong>
					</div>
				</div>
			</div>
			<input type="hidden" value="line" id="chart_type">
			<input type="hidden" value="sale" id="data_type">
			<div class="sls-row" id="summary_main_box_type">
				<div class="pymnt-sumry-lbl" id="summary_box_type">
					Sales
				</div>
				<div class="card-label" id="sales_stat_details">
					<img alt="Credit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Credit Card </strong>
						<?php echo wp_kses_post(wc_price($sale_tot_credit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Debit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Debit Card
						</strong><?php echo wp_kses_post(wc_price($sale_tot_debit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Net Banking" class="ico-nb" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-ach.png" />
					<span><strong>Net Banking
						</strong><?php echo wp_kses_post(wc_price($sale_tot_netbanking_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="UPI" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-upi.png" />
					<span><strong>UPI
						</strong><?php echo wp_kses_post(wc_price($sale_tot_upi_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="Wallet" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-wallet.png" />
					<span><strong>Wallet
						</strong><?php echo wp_kses_post(wc_price($sale_tot_wallet_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
				</div>

				<div class="card-label" id="refunded-stat-details">
					<img alt="Credit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Credit Card </strong>
						<?php echo wp_kses_post(wc_price($refunded_tot_credit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Debit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Debit Card
						</strong><?php echo wp_kses_post(wc_price($refunded_tot_debit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Net Banking" class="ico-nb" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-ach.png" />
					<span><strong>Net Banking
						</strong><?php echo wp_kses_post(wc_price($refunded_tot_netbanking_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="UPI" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-upi.png" />
					<span><strong>UPI
						</strong><?php echo wp_kses_post(wc_price($refunded_tot_upi_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="Wallet" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-wallet.png" />
					<span><strong>Wallet
						</strong><?php echo wp_kses_post(wc_price($refunded_tot_wallet_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
				</div>

				<div class="card-label" id="failed-stat-details">
					<img alt="Credit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Credit Card </strong>
						<?php echo wp_kses_post(wc_price($failed_tot_credit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Debit Card" class="ico-crd" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-card.png" />
					<span><strong>Debit Card
						</strong><?php echo wp_kses_post(wc_price($failed_tot_debit_card_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?>
						&nbsp;&nbsp;</span>
					<img alt="Net Banking" class="ico-nb" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-ach.png" />
					<span><strong>Net Banking
						</strong><?php echo wp_kses_post(wc_price($failed_tot_netbanking_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="UPI" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-upi.png" />
					<span><strong>UPI
						</strong><?php echo wp_kses_post(wc_price($sale_tot_upi_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
					<img alt="Wallet" class="ico-upi" src="<?php echo esc_url(WC_HP_PLUGIN_URL); ?>/assets/images/cards/ico-wallet.png" />
					<span><strong>Wallet
						</strong><?php echo wp_kses_post(wc_price($failed_tot_wallet_payments, array('decimal_separator' => '.', 'decimals' => 2))); ?></span>
				</div>

			</div>
			<div class="grph-cntrl" id="show_main_all">
				<div role="menubar" aria-orientation="horizontal" class="woocommerce-chart__types grph-btn">
					<button type="button" disabled id="line_chart" title="Line chart" aria-checked="false" role="menuitemradio" tabindex="-1" class="components-button woocommerce-chart__type-button pmt-btn"><svg class="gridicon gridicons-line-graph" height="15" width="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
							<g>
								<path d="M3 19h18v2H3zm3-3c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3L8.8 10H9c.5 0 1-.2 1.3-.5l2.7 1.4v.1c0 1.1.9 2 2 2s2-.9 2-2c0-.5-.2-.9-.5-1.3L17.8 7h.2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2c0 .5.2 1 .5 1.3L15.2 9H15c-.5 0-1 .2-1.3.5L11 8.2V8c0-1.1-.9-2-2-2s-2 .9-2 2c0 .5.2 1 .5 1.3L6.2 12H6c-1.1 0-2 .9-2 2s.9 2 2 2z">
								</path>
							</g>
						</svg></button>
					<button type="button" id="bar_chart" title="Bar chart" aria-checked="true" role="menuitemradio" tabindex="0" class="components-button woocommerce-chart__type-button woocommerce-chart__type-button-selected pmt-btn"><svg class="gridicon gridicons-stats-alt" height="15" width="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
							<g>
								<path d="M21 21H3v-2h18v2zM8 10H4v7h4v-7zm6-7h-4v14h4V3zm6 3h-4v11h4V6z"></path>
							</g>
						</svg></button>
				</div>
			</div>
		</div>
		<br class="clear">
		<?php if (empty($hide_sidebar)) : ?>
			<div class="inside chart-with-sidebar">
				<div class="chart-sidebar">
					<?php if ($this->get_chart_legend() == $legends) : ?>
						<ul class="chart-legend">
							<?php foreach ($legends as $legend) : ?>
								<li style="border-color:<?php echo esc_attr( $legend['color'] ); ?>" <?php if (isset( $legend['highlight_series'] )) { ?>
									<?php echo 'class="highlight_series ' . ( isset( $legend['placeholder'] ) ? 'tips' : '' ) . '" data-series="' . esc_attr( $legend['highlight_series'] ) . '"'; } ?> data-tip="<?php echo isset( $legend['placeholder'] ) ? esc_attr( $legend['placeholder'] ) : ''; ?>">
									<?php echo esc_html( $legend['title'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<ul class="chart-widgets">
						<?php foreach ($this->get_chart_widgets() as $widget) : ?>
							<li class="chart-widget">
								<?php if ($widget['title']) : ?>
									<h4><?php echo esc_html( $widget['title'] ); ?></h4>
								<?php endif; ?>
								<?php call_user_func( $widget['callback'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="main">
					<?php $this->get_main_chart(); ?>
				</div>
			</div>
		<?php else : ?>
			<div class="inside">
				<?php $this->get_main_chart(); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
