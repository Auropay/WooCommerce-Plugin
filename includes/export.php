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
		$header = array(
			'Order',
			'Date',
			'Status',
			'Auropay Status',
			'Sale',
			'Refund',
			'Type',
			'Payment Id',
			'Method',
			'Payment Detail',
			'Auth Code',
		);
		// Only show the Report Name and Logo on the first page.
		if ( $this->PageNo() == 1 ) {
			$header_font_size = 10;
			$this->SetFont( 'Arial', 'B', $header_font_size );
			$this->SetTextColor( 34, 43, 154 );
			// Left Spacing for the Report Name
			$individual_header_width = ( 560 / (int) count( $header ) );
			$individual_header_width = $individual_header_width * 1.9;
			// Report Name
			$this->Cell( 0, 10, 'Transaction List Auropay Payments', 0, 0, 'L' );
			// Logo
			$individual_header_width = 10; // You may need to adjust this value based on your requirements
			$pageWidth = $this->GetPageWidth();
			$imageWidth = 90; // Width of your image (adjust as needed)
			$xCoordinate = $pageWidth - ( $individual_header_width * 2 ) - $imageWidth;

			// Set the image to the right side
			$this->Image( WC_HP_PLUGIN_URL . '/assets/images/logo.png', $xCoordinate, 20, $imageWidth, 0 );
			// Line break
			$this->Ln( 20 );
		}
		// Show the Table Header on every page.
		$this->SetFont( 'Arial', 'B', 14 );
		$this->Ln();
		$table_header_font_size = 10;
		// Colors, line width and bold font
		$this->SetFillColor( 244, 209, 82 );
		$this->SetTextColor( 34, 43, 154 );
		$this->SetDrawColor( 133, 160, 196 );
		$this->SetLineWidth( .3 );
		$this->SetFont( 'Arial', 'B', $table_header_font_size );
		// 560 is A4 size width in points.
		$individual_header_width = ( 560 / (int) count( $header ) );
		// Width Multiplying factor - Adjust this when adding new column.
		$individual_header_width = $individual_header_width * 1.9;
		$individual_header_height = 30;
		$w = array();
		foreach ( $header as $col ) {
			if ( 'Order' == $col || 'Date' == $col || 'Auropay Status' == $col || 'Type' == $col || 'Payment Id' == $col || 'Method' == $col || 'Payment Detail' == $col ) {
				if ( 'Order' == $col || 'Type' == $col ) {
					array_push( $w, $individual_header_width / 2 );
				}
				if ( 'Date' == $col || 'Auropay Status' == $col || 'Method' == $col || 'Payment Detail' == $col ) {
					array_push( $w, $individual_header_width * 1.2 );
				}
				if ( 'Payment Id' == $col ) {
					array_push( $w, $individual_header_width * 2.1 );
				}
			} else {
				array_push( $w, $individual_header_width );
			}
		}
		for ( $i = 0; $i < count( $header ); $i++ ) {
			$this->Cell( $w[$i], $individual_header_height, $header[$i], 1, 0, 'C', true );
		}
		$this->Ln();
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

	$date = gmdate( 'd-m-y_H:i:s' );
	$filename = 'Transaction_List_AuroPay_Payments_' . $date . '.csv';

	$header = array(
		'Order',
		'Date',
		'Status',
		'Auropay Status',
		'Sale(₹)',
		'Refund(₹)',
		'Type',
		'Payment Id',
		'Method',
		'Payment Detail',
		'Auth Code',
	);

	foreach ( $total_result as $key => $value ) {

		if ( 'wc-refunded' == $value['status'] ) {
			$type = 'Refund';
		} else {
			$type = 'Sale';
		}

		if ( 'wc-failed' == $value['status'] ) {
			$status = 'Failed';
		} else if ( 'wc-processing' == $value['status'] ) {
			$status = 'Processing';
		} else if ( 'wc-refunded' == $value['status'] ) {
			$status = 'Refunded';
		} else if ( 'wc-cancelled' == $value['status'] ) {
			$status = 'Cancelled';
		} else if ( 'wc-pending' == $value['status'] ) {
			$status = 'Pending';
		} else if ( 'wc-on-hold' == $value['status'] ) {
			$status = 'On-hold';
		} else {
			$status = 'Completed';
		}

		$net_total_amt = $value['net_total'] + $value['tax_total'] + $value['shipping_total'];
		$net_total_amt = number_format( (float) $net_total_amt, 2, '.', '' );
		$net_total_amt = '$' . $net_total_amt;

		//get type and auth code
		$type_array = array( '3' => 'Credit Card', '4' => 'Debit Card', '6' => 'UPI', '7' => 'NetBanking', '8' => 'Wallets' );
		$payment_method = get_post_meta( $value['order_id'], '_hp_transaction_channel_type', true );
		$paymentId = get_post_meta( $value['order_id'], '_hp_transaction_id', true );
		$auropayPaymentStatus = get_post_meta( $value['order_id'], '_hp_transaction_status', true );

		if ( !empty( $paymentId ) ) {
			$paymentId = $paymentId;
		} else {
			$paymentId = '-';
		}

		$auropayStatusMapping = auropayStatusMapping();
		if ( !empty( $auropayStatusMapping[$auropayPaymentStatus] ) ) {
			$auropayPaymentStatus = $auropayStatusMapping[$auropayPaymentStatus];
		} else {
			$auropayPaymentStatus = '-';
		}

		if ( isset( $type_array[$payment_method] ) ) {
			$payment_method = $type_array[$payment_method];
		} else {
			$payment_method = '-';
		}

		$order = wc_get_order( $value['order_id'] );

		$sale = $order->get_total();
		$refund = '0.00';

		if ( count( $order->get_refunds() ) > 0 && 'Failed' != $status ) {
			$refund = $order->get_total_refunded();
			// $sale = $sale - $refund;
			$refund = 0 - $refund;
			$refund = number_format( (float) $refund, 2, '.', '' );
		}
		$sale = number_format( (float) $sale, 2, '.', '' );

		$auth_code = get_post_meta( $value['order_id'], '_hp_transaction_auth_code', true );
		$card_type = get_post_meta( $value['order_id'], '_hp_transaction_card_type', true );
		$card_type = ( !empty( $card_type ) ) ? $card_type : '-';
		$auth_code = ( !empty( $auth_code ) ) ? $auth_code : '-';
		$row_values[] = array(
			$value['order_id'],
			gmdate( 'd-m-Y H:i:s', strtotime( $value['date_created'] ) ),
			$status,
			ucfirst( $auropayPaymentStatus ),
			$sale,
			$refund,
			$type,
			$paymentId,
			$payment_method,
			$card_type,
			$auth_code,
		);
	}
	//exporting recotrds in CSV format
	if ( 'csv' == $export_type ) {
		ob_end_clean();
		$date = gmdate( 'd-m-Y' );
		$filename = WC_HP_PLUGIN_PATH . '/reports/Transaction_List_AuroPay_Payments_' . $date . '.csv';

		// create directory if it doesn't exist
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
	} else {
		//exporting record in PDF format
		ob_end_clean();
		// include  WC_HP_PLUGIN_PATH.'/includes/fpdf/fpdf.php';

		$pdf = new PDF( 'L', 'pt', 'A3' );
		$pdf->AddFont( 'Arial', '', 'Arial.php' );
		$pdf->AddPage();
		$header = array(
			'Order',
			'Date',
			'Status',
			'Auropay Status',
			'Sale',
			'Refund',
			'Type',
			'Payment Id',
			'Method',
			'Payment Detail',
			'Auth Code',
		);
		// Color and font restoration
		$table_data_font_size = 10;
		$individual_cell_width = ( 560 / (int) count( $header ) );
		$individual_cell_width = $individual_cell_width * 1.9;
		$individual_cell_height = 30;
		$pdf->SetFillColor( 167, 191, 217 );
		$pdf->SetTextColor( 0 );
		$pdf->SetDrawColor( 133, 160, 196 );
		$pdf->SetLineWidth( .3 );
		$pdf->SetFont( 'Arial', '', $table_data_font_size );

		// echo "<pre>";
		// print_r( $header );
		// exit;
		$w = array();
		foreach ( $header as $col ) {
			if ( 'Order' == $col || 'Date' == $col || 'Auropay Status' == $col || 'Type' == $col || 'Payment Id' == $col || 'Method' == $col || 'Payment Detail' == $col ) {
				if ( 'Order' == $col || 'Type' == $col ) {
					array_push( $w, $individual_cell_width / 2 );
				}
				if ( 'Date' == $col || 'Auropay Status' == $col || 'Method' == $col || 'Payment Detail' == $col ) {
					array_push( $w, $individual_cell_width * 1.2 );
				}
				if ( 'Payment Id' == $col ) {
					array_push( $w, $individual_cell_width * 2.1 );
				}
			} else {
				array_push( $w, $individual_cell_width );
			}
		}
		$fill = false;

		foreach ( $row_values as $row ) {
			$order_link = admin_url() . 'post.php?post=' . $row[0] . '&action=edit';
			$max_length = 25;
			$row[9] = mb_strimwidth( $row[9], 0, $max_length, '...' );
			$pdf->Cell( $w[0], $individual_cell_height, '#' . $row[0], 'LRB', 0, 'C', $fill, $order_link );
			$pdf->Cell( $w[1], $individual_cell_height, $row[1], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[2], $individual_cell_height, $row[2], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[3], $individual_cell_height, $row[3], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[4], $individual_cell_height, chr( 0xA4 ) . $row[4], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[5], $individual_cell_height, chr( 0xA4 ) . $row[5], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[6], $individual_cell_height, $row[6], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[7], $individual_cell_height, $row[7], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[8], $individual_cell_height, $row[8], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[9], $individual_cell_height, $row[9], 'LRB', 0, 'C', $fill );
			$pdf->Cell( $w[10], $individual_cell_height, $row[10], 'LRB', 0, 'C', $fill );
			$pdf->Ln();
			$fill = !$fill;
		}
		// Closing line

		$date = gmdate( 'd-m-Y' );
		$filename = 'Transaction_List_AuroPay_Payments_' . $date . '.pdf';
		$pdf->Output( $filename, 'D' );
		ob_end_flush();
	}
}
