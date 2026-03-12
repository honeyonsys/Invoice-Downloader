<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Bulk_Invoice_Downloader {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor logic if needed
	}

	/**
	 * Initialize the plugin hooks
	 */
	public function init() {
		// Add button to WooCommerce order list page
		add_action( 'restrict_manage_posts', array( $this, 'add_download_button' ), 20 );
		
		// Handle the download request
		add_action( 'admin_init', array( $this, 'handle_bulk_download' ) );
		
		// Add some CSS for the button
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

		echo '<a href="' . esc_url( $url ) . '" class="button wc-bulk-download-btn">' . esc_html__( 'Download Invoices (ZIP)', 'wc-bulk-invoice-downloader' ) . '</a>';
	}

	/**
	 * Add CSS to style the button
	 */
	public function add_button_css() {
		$screen = get_current_screen();
		if ( $screen && 'edit-shop_order' === $screen->id ) {
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
	 * Handle the bulk download request
	 */
	public function handle_bulk_download() {
		if ( ! isset( $_GET['action'] ) || 'wc_bulk_download_all' !== $_GET['action'] ) {
			return;
		}

		if ( ! isset( $_GET['wc_nonce'] ) || ! wp_verify_nonce( $_GET['wc_nonce'], 'wc_bulk_download_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wc-bulk-invoice-downloader' ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wc-bulk-invoice-downloader' ) );
		}

		// Increase time limit and memory for large batch
		set_time_limit( 300 );
		ini_set( 'memory_limit', '512M' );

		// Fetch orders based on current list filters if any
		$orders = $this->get_filtered_orders();

		if ( empty( $orders ) ) {
			wp_die( esc_html__( 'No orders found to download.', 'wc-bulk-invoice-downloader' ) );
		}

		$this->generate_zip( $orders );
	}

	/**
	 * Get filtered orders based on the current order list view
	 */
	private function get_filtered_orders() {
		$args = array(
			'limit'  => -1,
			'return' => 'ids',
		);

		// Status filter
		if ( isset( $_GET['post_status'] ) && ! empty( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
			$args['status'] = sanitize_text_field( $_GET['post_status'] );
		}

		// Customer filter
		if ( isset( $_GET['_customer_user'] ) && ! empty( $_GET['_customer_user'] ) ) {
			$args['customer_id'] = intval( $_GET['_customer_user'] );
		}

		// Date filter (YYYYMM)
		if ( isset( $_GET['m'] ) && ! empty( $_GET['m'] ) ) {
			$year  = substr( $_GET['m'], 0, 4 );
			$month = substr( $_GET['m'], 4, 2 );
			$args['date_created'] = $year . '-' . $month . '-01...' . $year . '-' . $month . '-' . date( 't', strtotime( $year . '-' . $month . '-01' ) );
		}

		// Search filter
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
			if ( ! wp_mkdir_p( $invoice_dir ) ) {
				wp_die( esc_html__( 'Failed to create invoices directory in uploads. Please check folder permissions.', 'wc-bulk-invoice-downloader' ) );
			}
		}

		// Check for Dompdf only if we need to generate new PDFs
		$can_generate = class_exists( 'Dompdf\\Dompdf' );

		$zip = new ZipArchive();
		$zip_filename = 'invoices-' . date( 'Y-m-d-His' ) . '.zip';
		// Using the uploads directory for ZIP file too, for better write permissions on Windows
		$zip_filepath = $upload_dir['basedir'] . '/' . $zip_filename;

		if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			wp_die( esc_html__( 'Could not create ZIP file at ' . $zip_filepath . '. Please check if the uploads directory is writable.', 'wc-bulk-invoice-downloader' ) );
		}

		$added_files_count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) continue;

			$filename = 'invoice-' . $order_id . '.pdf';
			$filepath = $invoice_dir . $filename;

			// Check if PDF already exists in cache
			if ( ! file_exists( $filepath ) ) {
				// We need Dompdf library to generate new PDF
				if ( ! $can_generate ) {
					// Stop and inform user if Dompdf is missing and we need it
					$zip->close();
					if ( file_exists( $zip_filepath ) ) unlink( $zip_filepath );
					wp_die( esc_html__( 'Dompdf library not found. Please ensure "vendor/autoload.php" exists and Dompdf is installed via Composer.', 'wc-bulk-invoice-downloader' ) );
				}

				$pdf_content = $this->generate_pdf_content( $order );
				if ( $pdf_content ) {
					file_put_contents( $filepath, $pdf_content );
				}
			}

			// Add from file to ZIP
			if ( file_exists( $filepath ) ) {
				if ( $zip->addFile( $filepath, 'invoice-' . $order->get_order_number() . '.pdf' ) ) {
					$added_files_count++;
				}
			}
		}

		if ( $added_files_count === 0 ) {
			$zip->close();
			if ( file_exists( $zip_filepath ) ) {
				unlink( $zip_filepath );
			}
			wp_die( esc_html__( 'No PDF files were added to the ZIP archive. Check if orders exist and invoices are generated.', 'wc-bulk-invoice-downloader' ) );
		}

		if ( ! $zip->close() ) {
			wp_die( esc_html__( 'Failed to close and save ZIP file. Possible disk space or permission issue.', 'wc-bulk-invoice-downloader' ) );
		}

		// Stream the ZIP file to browser
		if ( file_exists( $zip_filepath ) ) {
			// Clear ANY existing buffer (even those from other plugins/notices) to prevent ZIP corruption
			while ( ob_get_level() ) {
				ob_end_clean();
			}

			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
			header( 'Content-Length: ' . filesize( $zip_filepath ) );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			
			// Use fpassthru for binary-safe streaming
			$handle = fopen( $zip_filepath, 'rb' );
			if ( $handle ) {
				fpassthru( $handle );
				fclose( $handle );
			} else {
				readfile( $zip_filepath ); // Fallback
			}
			
			unlink( $zip_filepath ); // Delete temp ZIP file
			exit;
		} else {
			wp_die( esc_html__( 'Failed to locate the generated ZIP file after creation.', 'wc-bulk-invoice-downloader' ) );
		}
	}

	/**
	 * Generate PDF content for a single order
	 */
	private function generate_pdf_content( $order ) {
		// Use Dompdf
		$options = new Dompdf\Options();
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'isRemoteEnabled', true ); // For images if any

		$dompdf = new Dompdf\Dompdf( $options );
		
		// Load template
		ob_start();
		include WC_BID_PATH . 'templates/invoice-template.php';
		$html = ob_get_clean();

		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		return $dompdf->output();
	}
}
