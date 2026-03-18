<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Bulk_Invoice_Downloader {

	/**
	 * Initialize the plugin hooks
	 */
	public function init() {
		// Add button to WooCommerce order list page
		add_action( 'restrict_manage_posts', array( $this, 'add_download_button' ), 20 );
		
		// Add button to single order sidebar
		add_action( 'add_meta_boxes', array( $this, 'add_invoice_meta_box' ) );
		
		// Handle the download requests
		add_action( 'admin_init', array( $this, 'handle_downloads' ) );
		
		// Add some CSS for the buttons
		add_action( 'admin_head', array( $this, 'add_button_css' ) );
	}

	/**
	 * Add "Download All Invoices" button at the top of orders list
	 */
	public function add_download_button( $post_type ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$nonce = wp_create_nonce( 'wc_bulk_download_nonce' );
		$url   = add_query_arg( array(
			'action'   => 'wc_bulk_download_all',
			'wc_nonce' => $nonce
		), admin_url( 'edit.php?post_type=shop_order' ) );

		echo '<a href="' . esc_url( $url ) . '" class="button wc-bulk-download-btn">' . esc_html__( 'Download All Invoices (ZIP)', 'wc-bulk-invoice-downloader' ) . '</a>';
	}

	/**
	 * Add Invoice meta box to order sidebar
	 */
	public function add_invoice_meta_box() {
		// Compatible with both Legacy and HPOS
		$screen = class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() 
			? 'woocommerce_page_wc-orders' 
			: 'shop_order';

		add_meta_box(
			'wc_bid_invoice_box',
			__( 'Invoice Download', 'wc-bulk-invoice-downloader' ),
			array( $this, 'render_invoice_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content
	 */
	public function render_invoice_meta_box( $post_or_order ) {
		// Get order ID regardless of HPOS or legacy
		$order_id = ( $post_or_order instanceof WP_Post ) ? $post_or_order->ID : $post_or_order->get_id();
		
		$nonce = wp_create_nonce( 'wc_single_download_nonce' );
		$url = add_query_arg( array(
			'action'   => 'wc_download_single_invoice',
			'order_id' => $order_id,
			'wc_nonce' => $nonce
		), admin_url( 'edit.php?post_type=shop_order' ) );

		echo '<div style="text-align:center; padding:10px 0;">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="width:100%; text-align:center;">' . esc_html__( 'Download Invoice (PDF)', 'wc-bulk-invoice-downloader' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Add CSS to style the buttons
	 */
	public function add_button_css() {
		$screen = get_current_screen();
		if ( $screen && ( 'edit-shop_order' === $screen->id || 'woocommerce_page_wc-orders' === $screen->id ) ) {
			echo '<style>
				.wc-bulk-download-btn {
					margin-left: 5px !important;
					background-color: #007cba !important;
					color: #fff !important;
					border-color: #007cba !important;
				}
				.wc-bulk-download-btn:hover {
					background-color: #006ba1 !important;
					color: #fff !important;
				}
			</style>';
		}
	}

	/**
	 * Handle download requests (Bulk and Single)
	 */
	public function handle_downloads() {
		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		// 1. Handle Bulk Download
		if ( 'wc_bulk_download_all' === $_GET['action'] ) {
			if ( ! isset( $_GET['wc_nonce'] ) || ! wp_verify_nonce( $_GET['wc_nonce'], 'wc_bulk_download_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wc-bulk-invoice-downloader' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'wc-bulk-invoice-downloader' ) );
			}

			set_time_limit( 300 );
			ini_set( 'memory_limit', '512M' );

			$order_ids = $this->get_filtered_orders();
			if ( empty( $order_ids ) ) {
				wp_die( esc_html__( 'No orders found to download.', 'wc-bulk-invoice-downloader' ) );
			}

			$this->generate_zip( $order_ids );
		}

		// 2. Handle Single Download
		if ( 'wc_download_single_invoice' === $_GET['action'] ) {
			if ( ! isset( $_GET['wc_nonce'] ) || ! wp_verify_nonce( $_GET['wc_nonce'], 'wc_single_download_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wc-bulk-invoice-downloader' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'wc-bulk-invoice-downloader' ) );
			}

			$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				wp_die( esc_html__( 'Invalid order.', 'wc-bulk-invoice-downloader' ) );
			}

			$this->download_single_pdf( $order );
		}
	}

	/**
	 * Get filtered orders based on the current order list view
	 */
	private function get_filtered_orders() {
		$args = array(
			'limit'   => -1,
			'return'  => 'ids',
			'orderby' => 'ID',
			'order'   => 'ASC',
		);

		if ( isset( $_GET['post_status'] ) && ! empty( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
			$args['status'] = sanitize_text_field( $_GET['post_status'] );
		}
		if ( isset( $_GET['_customer_user'] ) && ! empty( $_GET['_customer_user'] ) ) {
			$args['customer_id'] = intval( $_GET['_customer_user'] );
		}
		if ( isset( $_GET['m'] ) && ! empty( $_GET['m'] ) ) {
			$year  = substr( $_GET['m'], 0, 4 );
			$month = substr( $_GET['m'], 4, 2 );
			$args['date_created'] = $year . '-' . $month . '-01...' . $year . '-' . $month . '-' . date( 't', strtotime( $year . '-' . $month . '-01' ) );
		}
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$args['s'] = sanitize_text_field( $_GET['s'] );
		}

		return wc_get_orders( $args );
	}

	/**
	 * Generate ZIP file with all invoice PDFs
	 */
	private function generate_zip( $order_ids ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is not installed on this server.', 'wc-bulk-invoice-downloader' ) );
		}

		$upload_dir = wp_upload_dir();
		$invoice_dir = $upload_dir['basedir'] . '/invoices/';
		
		if ( ! file_exists( $invoice_dir ) ) {
			wp_mkdir_p( $invoice_dir );
		}

		$can_generate = class_exists( 'Dompdf\\Dompdf' );
		$zip = new ZipArchive();
		$zip_filename = 'invoices-' . date( 'Y-m-d-His' ) . '.zip';
		$zip_filepath = $upload_dir['basedir'] . '/' . $zip_filename;

		if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			wp_die( esc_html__( 'Could not create ZIP file.', 'wc-bulk-invoice-downloader' ) );
		}

		$added_files_count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) continue;

			$invoice_number = $this->get_or_generate_invoice_number( $order );
			$filename = 'invoice-' . $invoice_number . '.pdf';
			$filepath = $invoice_dir . $filename;

			if ( ! file_exists( $filepath ) ) {
				if ( ! $can_generate ) continue;
				$pdf_content = $this->generate_pdf_content( $order, $invoice_number );
				if ( $pdf_content ) {
					file_put_contents( $filepath, $pdf_content );
				}
			}

			if ( file_exists( $filepath ) ) {
				if ( $zip->addFile( $filepath, $filename ) ) {
					$added_files_count++;
				}
			}
		}

		$zip->close();

		if ( $added_files_count > 0 && file_exists( $zip_filepath ) ) {
			$this->stream_file( $zip_filepath, $zip_filename, 'application/zip' );
		} else {
			wp_die( esc_html__( 'Failed to generate ZIP file or no invoices were added.', 'wc-bulk-invoice-downloader' ) );
		}
	}

	/**
	 * Download a single PDF directly
	 */
	private function download_single_pdf( $order ) {
		$upload_dir = wp_upload_dir();
		$invoice_dir = $upload_dir['basedir'] . '/invoices/';
		
		if ( ! file_exists( $invoice_dir ) ) {
			wp_mkdir_p( $invoice_dir );
		}

		$invoice_number = $this->get_or_generate_invoice_number( $order );
		$filename = 'invoice-' . $invoice_number . '.pdf';
		$filepath = $invoice_dir . $filename;

		if ( ! file_exists( $filepath ) ) {
			if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
				wp_die( esc_html__( 'Dompdf library not found. Please install via Composer.', 'wc-bulk-invoice-downloader' ) );
			}
			$pdf_content = $this->generate_pdf_content( $order, $invoice_number );
			if ( $pdf_content ) {
				file_put_contents( $filepath, $pdf_content );
			}
		}

		if ( file_exists( $filepath ) ) {
			$this->stream_file( $filepath, $filename, 'application/pdf' );
		} else {
			wp_die( esc_html__( 'Failed to generate invoice PDF.', 'wc-bulk-invoice-downloader' ) );
		}
	}

	/**
	 * Get or generate a persistent custom invoice number (Strictly Sequential)
	 */
	private function get_or_generate_invoice_number( $order ) {
		$invoice_number = $order->get_meta( '_custom_invoice_number' );

		if ( ! $invoice_number ) {
			$current_counter = get_option( 'wc_bid_global_invoice_counter' );
			
			if ( false === $current_counter ) {
				$current_counter = 1000;
				// Initial scan to find max existing suffix if any
				global $wpdb;
				$query = "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_custom_invoice_number' ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 1";
				$last_val = $wpdb->get_var( $query );
				if ( $last_val ) {
					$current_counter = ( strlen($last_val) > 8 ) ? intval( substr( $last_val, 8 ) ) : intval($last_val);
				}
			}
			
			$new_number = intval( $current_counter ) + 1;
			update_option( 'wc_bid_global_invoice_counter', $new_number );
			$invoice_number = $new_number;

			$order->update_meta_data( '_custom_invoice_number', $invoice_number );
			$order->save();
		}

		return $invoice_number;
	}

	/**
	 * Generate PDF content using the template
	 */
	private function generate_pdf_content( $order, $invoice_number ) {
		$options = new \Dompdf\Options();
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'isRemoteEnabled', true );

		$dompdf = new \Dompdf\Dompdf( $options );
		
		ob_start();
		include WC_BID_PATH . 'templates/invoice-template.php';
		$html = ob_get_clean();

		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		return $dompdf->output();
	}

	/**
	 * Stream file to browser safely
	 */
	private function stream_file( $filepath, $filename, $content_type ) {
		// Clean all output buffers to prevent corruption
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $filepath ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$handle = fopen( $filepath, 'rb' );
		if ( $handle ) {
			fpassthru( $handle );
			fclose( $handle );
		} else {
			readfile( $filepath );
		}
		
		// Only delete temporary ZIP files, keep cached invoices
		if ( strpos( $content_type, 'zip' ) !== false ) {
			unlink( $filepath );
		}
		exit;
	}
}
