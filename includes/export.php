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

require_once WC_HP_PLUGIN_PATH . '/includes/fpdf/fpdf.php';

/**
 * Export data in csv and pdf format
 *
 * @package  AuroPay_Gateway_For_WooCommerce
 * @link     https://auropay.net/
 */
class PDF extends FPDF {
	/**
	 * Page Header
	 *
	 * @return void
	 */
	public function Header() {
		$header = $this->getHeaderArray();
		$this->addReportTitleAndLogo();
		$this->addTableHeader( $header );
	}
	/**
	 * Get Header Array
	 *
	 * @return array
	 */
	private function getHeaderArray() {
		return array(
			'Order', 'Date', 'Status', WC_HP_AUROPAY_STATUS, 'Sale',
			'Refund', 'Type', WC_HP_PAYMENT_ID, 'Method', WC_HP_PAYMENT_DETAIL, 'Auth Code',
		);
	}

	/**
	 * Add Title
	 *
	 * @return void
	 */
	private function addReportTitleAndLogo() {
		if ( $this->PageNo() == 1 ) {
			$header_font_size = 10;
			$this->SetFont( 'Arial', 'B', $header_font_size );
			$this->SetTextColor( 34, 43, 154 );
			$this->Cell( 0, 10, 'Transaction List Auropay Payments', 0, 0, 'L' );

			$pageWidth = $this->GetPageWidth();
			$imageWidth = 90; // Width of your image (adjust as needed)
			$xCoordinate = $pageWidth - 20 - $imageWidth;

			$this->Image( WC_HP_PLUGIN_URL . '/assets/images/logo.png', $xCoordinate, 20, $imageWidth, 0 );
			$this->Ln( 20 );
		}
	}

	/**
	 * Add Table Header
	 *
	 * @return void
	 */
	private function addTableHeader( $header ) {
		$this->SetFont( 'Arial', 'B', 14 );
		$this->Ln();
		$table_header_font_size = 10;
		$this->SetFillColor( 244, 209, 82 );
		$this->SetTextColor( 34, 43, 154 );
		$this->SetDrawColor( 133, 160, 196 );
		$this->SetLineWidth( .3 );
		$this->SetFont( 'Arial', 'B', $table_header_font_size );

		$w = $this->calculateCellWidths( $header );

		foreach ( $header as $i => $col ) {
			$this->Cell( $w[$i], 30, $col, 1, 0, 'C', true );
		}
		$this->Ln();
	}

	/**
	 * Calculate Cell Width
	 *
	 * @return array
	 */
	private function calculateCellWidths( $header ) {
		$individual_header_width = ( 560 / (int) count( $header ) ) * 1.9;
		$w = array();

		foreach ( $header as $col ) {
			if ( in_array( $col, array( 'Order', 'Type' ) ) ) {
				$w[] = $individual_header_width / 2;
			} elseif ( in_array( $col, array( 'Date', WC_HP_AUROPAY_STATUS, 'Method', WC_HP_PAYMENT_DETAIL ) ) ) {
				$w[] = $individual_header_width * 1.2;
			} elseif ( WC_HP_PAYMENT_ID == $col ) {
				$w[] = $individual_header_width * 2.1;
			} else {
				$w[] = $individual_header_width;
			}
		}
		return $w;
	}

	/**
	 * Page Footer
	 *
	 * @return void
	 */
	public function Footer() {
		$footer_font_size = 10;
		// Position at 1.5 cm from bottom
		$this->SetY( -25 );
		$this->SetFont( 'Arial', '', $footer_font_size );
		$this->Cell( 0, 14, 'Transaction List Auropay Payments', 0, 0, 'L' );
		$this->SetX( $this->lMargin );
		$this->Cell( 0, 14, 'Page ' . $this->PageNo(), 0, 0, 'C' );
		$this->SetX( $this->lMargin );
		$this->Cell( 0, 14, 'Powered by Auropay for Woocommerce ', 0, 0, 'R' );
	}
}

/**
 * Page Header
 *
 * @param $export_type  type of file
 * @param $total_result data to export
 *
 * @return void
 */
function exportData( $export_type, $total_result ) {
	global $start_date;
	global $end_date;

	$header = array(
		'Order', 'Date', 'Status', WC_HP_AUROPAY_STATUS, 'Sale(₹)',
		'Refund(₹)', 'Type', WC_HP_PAYMENT_ID, 'Method', WC_HP_PAYMENT_DETAIL, 'Auth Code',
	);

	$row_values = array_map( 'processResult', $total_result );

	if ( 'csv' == $export_type ) {
		exportCSV( $header, $row_values );
	} else {
		exportPDF( $header, $row_values );
	}
}

/**
 * Process Result
 *
 * @return array
 */
function processResult( $value ) {
	$type = ( 'wc-refunded' == $value['status'] ) ? 'Refund' : 'Sale';
	$status = mapStatus( $value['status'] );
	$net_total_amt = calculateNetTotal( $value );
	$payment_info = getPaymentInfo( $value['order_id'] );

	$order = wc_get_order( $value['order_id'] );
	$sale = formatAmount( $order->get_total() );
	$refund = calculateRefund( $order, $status );

	return array(
		$value['order_id'],
		gmdate( 'd-m-Y H:i:s', strtotime( $value['date_created'] ) ),
		$status,
		ucfirst( $payment_info['auropayPaymentStatus'] ),
		$sale,
		$refund,
		$type,
		$payment_info['pay_id'],
		$payment_info['payment_method'],
		$payment_info['card_type'],
		$payment_info['auth_code'],
	);
}

/**
 * Map Status
 *
 * @return array
 */
function mapStatus( $status ) {
	$status_mapping = array(
		'wc-failed' => 'Failed',
		'wc-processing' => 'Processing',
		'wc-refunded' => 'Refunded',
		'wc-cancelled' => 'Cancelled',
		'wc-pending' => 'Pending',
		'wc-on-hold' => 'On-hold',
		'default' => 'Completed',
	);
	return isset( $status_mapping[$status] ) ? $status_mapping[$status] : $status_mapping['default'];
}

/**
 * Calculate Net Total
 *
 * @return void
 */
function calculateNetTotal( $value ) {
	$net_total_amt = $value['net_total'] + $value['tax_total'] + $value['shipping_total'];
	return '$' . number_format( (float) $net_total_amt, 2, '.', '' );
}

/**
 * Get Payment Info
 *
 * @return array
 */
function getPaymentInfo( $order_id ) {
	$type_array = array( '3' => 'Credit Card', '4' => 'Debit Card', '6' => 'UPI', '7' => 'NetBanking', '8' => 'Wallets' );
	$payment_method = get_post_meta( $order_id, '_hp_transaction_channel_type', true );
	$payment_id = get_post_meta( $order_id, '_hp_transaction_id', true );
	$auropayPaymentStatus = get_post_meta( $order_id, '_hp_transaction_status', true );
	$auth_code = get_post_meta( $order_id, '_hp_transaction_auth_code', true );
	$card_type = get_post_meta( $order_id, '_hp_transaction_card_type', true );

	$auropayStatusMapping = auropayStatusMapping();
	$payment_method = isset( $type_array[$payment_method] ) ? $type_array[$payment_method] : '-';
	$pay_id = !empty( $payment_id ) ? $payment_id : '-';
	$auropayPaymentStatus = !empty( $auropayStatusMapping[$auropayPaymentStatus] ) ? $auropayStatusMapping[$auropayPaymentStatus] : '-';
	$card_type = !empty( $card_type ) ? $card_type : '-';
	$auth_code = !empty( $auth_code ) ? $auth_code : '-';

	return compact( 'payment_method', 'pay_id', 'auropayPaymentStatus', 'auth_code', 'card_type' );
}

/**
 * Calculate Refund
 *
 * @return void
 */
function calculateRefund( $order, $status ) {
	if ( count( $order->get_refunds() ) > 0 && 'Failed' != $status ) {
		$refund = 0 - $order->get_total_refunded();
		return number_format( (float) $refund, 2, '.', '' );
	}
	return '0.00';
}

/**
 * Format Amount
 *
 * @return Void
 */
function formatAmount( $amount ) {
	return number_format( (float) $amount, 2, '.', '' );
}

/**
 * Export Csv
 *
 * @return void
 */
function exportCSV( $header, $row_values ) {
	ob_end_clean();
	$date = gmdate( 'd-m-Y' );
	$filename = WC_HP_PLUGIN_PATH . '/reports/Transaction_List_AuroPay_Payments_' . $date . '.csv';

	if ( !file_exists( dirname( $filename ) ) ) {
		mkdir( dirname( $filename ), 0777, true );
	}

	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '";' );

	$fh = @fopen( $filename, 'w' );
	fputcsv( $fh, $header );

	foreach ( $row_values as $row ) {
		fputcsv( $fh, $row );
	}

	fclose( $fh );
	readfile( $filename );
	ob_end_flush();
	die();
}

/**
 * Export Pdf
 *
 * @return void
 */
function exportPDF( $header, $row_values ) {
	ob_end_clean();

	$pdf = new PDF( 'L', 'pt', 'A3' );
	$pdf->AddFont( 'Arial', '', 'Arial.php' );
	$pdf->AddPage();
	$table_data_font_size = 10;
	$individual_cell_width = ( 560 / (int) count( $header ) ) * 1.9;
	$individual_cell_height = 30;
	$pdf->SetFillColor( 167, 191, 217 );
	$pdf->SetTextColor( 0 );
	$pdf->SetDrawColor( 133, 160, 196 );
	$pdf->SetLineWidth( .3 );
	$pdf->SetFont( 'Arial', '', $table_data_font_size );

	$w = calculateCellWidths( $header, $individual_cell_width );
	$fill = false;

	foreach ( $row_values as $row ) {
		$order_link = admin_url() . 'post.php?post=' . $row[0] . '&action=edit';
		$max_length = 25;
		$row[9] = mb_strimwidth( $row[9], 0, $max_length, '...' );
		$pdf->Cell( $w[0], $individual_cell_height, '#' . $row[0], 'LRB', 0, 'C', $fill, $order_link );
		for ( $i = 1; $i < count( $row ); $i++ ) {
			if ( 4 == $i || 5 == $i ) {
				$pdf->Cell( $w[$i], $individual_cell_height, chr( 0xA4 ) . $row[$i], 'LRB', 0, 'C', $fill );
			} else {
				$pdf->Cell( $w[$i], $individual_cell_height, $row[$i], 'LRB', 0, 'C', $fill );
			}
		}
		$pdf->Ln();
		$fill = !$fill;
	}

	$date = gmdate( 'd-m-Y' );
	$filename = 'Transaction_List_AuroPay_Payments_' . $date . '.pdf';
	$pdf->Output( $filename, 'D' );
	ob_end_flush();
}

/**
 * Calculate Cell Width
 *
 * @return array
 */
function calculateCellWidths( $header, $individual_cell_width ) {
	$w = array();
	foreach ( $header as $col ) {
		if ( in_array( $col, array( 'Order', 'Date', WC_HP_AUROPAY_STATUS, 'Type', WC_HP_PAYMENT_ID, 'Method', WC_HP_PAYMENT_DETAIL ) ) ) {
			if ( in_array( $col, array( 'Order', 'Type' ) ) ) {
				$w[] = $individual_cell_width / 2;
			} elseif ( in_array( $col, array( 'Date', WC_HP_AUROPAY_STATUS, 'Method', WC_HP_PAYMENT_DETAIL ) ) ) {
				$w[] = $individual_cell_width * 1.2;
			} elseif ( WC_HP_PAYMENT_ID == $col ) {
				$w[] = $individual_cell_width * 2.1;
			}
		} else {
			$w[] = $individual_cell_width;
		}
	}
	return $w;
}
