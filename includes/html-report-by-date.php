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
	<div class="postbox" style="background-color:#f0f0f1;border:none;border-top:1px solid #ccc;">

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
					echo '<li class="' . ( $current_range == $range ? 'active' : '' ) . '"><a href="' . esc_url( remove_query_arg( array( 'start_date', 'end_date' ), add_query_arg( 'range', $range ) ) ) . '">' . esc_html( $name ) . '</a></li>';
				}
				?>
				<li class=" custom <?php echo ( 'custom' === $current_range ) ? 'active' : ''; ?>">
					<?php esc_html_e('Custom:', 'woocommerce'); ?>
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
							<input type="text" size="11" placeholder="yyyy-mm-dd" value="<?php echo ( !empty( $_GET['start_date'] ) ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) ) : ''; ?>" name="start_date" class="range_datepicker from" autocomplete="off" />
							<span>&ndash;</span>
							<input type="text" size="11" placeholder="yyyy-mm-dd" value="<?php echo ( !empty( $_GET['end_date'] ) ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) ) : ''; ?>" name="end_date" class="range_datepicker to" autocomplete="off" />
							<button type="submit" class="button" value="<?php esc_attr_e('Go', 'woocommerce'); ?>"><?php esc_html_e('Go', 'woocommerce'); ?></button>
							<?php wp_nonce_field('custom_range', 'wc_reports_nonce', false); ?>
						</div>
					</form>
				</li>
			</ul>
		</div>
		<div clas="" style="float:left;width:96.5%;border:1px solid #e0e0e0;padding:20px;box-shadow: inset -1px -1px 0 #e0e0e0;background-color:white;margin-top:20px">

			<?php
			global $tot_payments;
			global $tot_refunded;
			global $tot_failed;
			?>

			<div style="float:left;width:30%;margin-left:0px;border:1px solid #ccc;padding:10px;margin-right:10px;cusrsor:pointer" id="sale_box">
				<div style="float:left;width:15%;margin-right:5px">
					<img alt="Sales" src="<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/summary/calculator_color.png" width="40" height="40" />
				</div>
				<div style="float:left;width:65%">
					<div style="font-size:16px;padding:0px;margin-bottom:20px;width:100%;">
						<strong> Sales</strong>
					</div>
					<div style="font-size:20px;padding:0px;">
						<strong><span style="color:green"><span class="woocommerce-Price-currencySymbol">$</span><?php echo esc_html( $tot_payments ); ?>
							</span></strong>
					</div>
				</div>
			</div>
			<div style="float:left;width:30%;border:1px solid #ccc;padding:10px;margin-right:10px" id="refunded_box">
				<div style="float:left;width:15%;margin-right:5px">
					<img alt="Refunds" src="<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/summary/calendar_refund_color.png" width="40" height="40" />
				</div>
				<div style="float:left;width:65%;">
					<div style="font-size:16px;padding:0px;margin-bottom:20px">
						<strong>Refund</strong>
					</div>
					<div style="font-size:20px;padding:0px;">
						<strong><span style="color:orange"><span class="woocommerce-Price-currencySymbol">$</span><?php echo esc_html( $tot_refunded ); ?></span></strong>
					</div>
				</div>
			</div>
			<div style="float:left;width:31.5%;border:1px solid #ccc;padding:10px;margin-right:0px" id="failed_box">
				<div style="float:left;width:10%;margin-right:30px">
					<img alt="Failed" src="<?php echo esc_url( WC_HP_PLUGIN_URL ); ?>/assets/images/summary/calendar_decline_color.png" width="40" height="40" />
				</div>
				<div style="float:left;">
					<div style="font-size:16px;padding:0px;;margin-bottom:20px">
						<strong>Failed</strong>
					</div>
					<div style="font-size:20px;padding:0px;">
						<strong><span style="color:red"><span class="woocommerce-Price-currencySymbol">$</span><?php echo esc_html( $tot_failed ); ?>
							</span></strong>
					</div>
				</div>
			</div>

		</div>
		<input type="hidden" value="line" id="chart_type">
		<input type="hidden" value="sale" id="data_type">
		<div role="menubar" aria-orientation="horizontal" class="woocommerce-chart__types" style="float:right;margin-top:10px">
			<button type="button" id="line_chart" title="Line chart" aria-checked="false" role="menuitemradio" tabindex="-1" class="components-button woocommerce-chart__type-button"><svg class="gridicon gridicons-line-graph" height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
					<g>
						<path d="M3 19h18v2H3zm3-3c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3L8.8 10H9c.5 0 1-.2 1.3-.5l2.7 1.4v.1c0 1.1.9 2 2 2s2-.9 2-2c0-.5-.2-.9-.5-1.3L17.8 7h.2c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2c0 .5.2 1 .5 1.3L15.2 9H15c-.5 0-1 .2-1.3.5L11 8.2V8c0-1.1-.9-2-2-2s-2 .9-2 2c0 .5.2 1 .5 1.3L6.2 12H6c-1.1 0-2 .9-2 2s.9 2 2 2z">
						</path>
					</g>
				</svg></button>
			<button type="button" id="bar_chart" title="Bar chart" aria-checked="true" role="menuitemradio" tabindex="0" class="components-button woocommerce-chart__type-button woocommerce-chart__type-button-selected"><svg class="gridicon gridicons-stats-alt" height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
					<g>
						<path d="M21 21H3v-2h18v2zM8 10H4v7h4v-7zm6-7h-4v14h4V3zm6 3h-4v11h4V6z"></path>
					</g>
				</svg></button>
		</div>
		<br class="clear">
		<?php if (empty($hide_sidebar)) : ?>
			<div class="inside chart-with-sidebar">
				<div class="chart-sidebar">
					<?php if ($legends == $this->get_chart_legend()) : ?>
						<ul class="chart-legend">
							<?php foreach ($legends as $legend) : ?>
								<li style="border-color:<?php echo esc_attr( $legend['color'] ); ?>" <?php if ( isset( $legend['highlight_series'] ) ) { ?> 
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
