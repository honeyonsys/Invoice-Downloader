<?php
/**
 * Invoice Template
 * 
 * Available variable: $order (WC_Order object)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_data = $order->get_data();
$billing_address = $order->get_formatted_billing_address();
$shipping_address = $order->get_formatted_shipping_address();
$items = $order->get_items();
$currency = $order->get_currency();
$date_created = $order->get_date_created();
$payment_method = $order->get_payment_method_title();
$transaction_id = $order->get_transaction_id();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .invoice-container {
            width: 100%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left h1 {
            margin: 0;
            font-size: 24px;
            color: #000;
        }
        .header-right h1 {
            margin: 0;
            font-size: 24px;
            color: #000;
            text-transform: uppercase;
            text-align: right;
        }
        .store-info {
            margin-top: 5px;
            font-size: 13px;
        }
        .spacer {
            height: 30px;
        }
        .info-table td {
            vertical-align: top;
            padding-bottom: 20px;
        }
        .info-label {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 12px;
        }
        .info-details {
            text-align: right;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th {
            background-color: #f2f2f2;
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }
        .items-table td {
            border: 1px solid #333;
            padding: 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .totals-table {
            width: 40%;
            float: right;
            margin-top: 20px;
        }
        .totals-table td {
            border: 1px solid #333;
            padding: 8px;
        }
        .totals-label {
            background-color: #f2f2f2;
            width: 40%;
            font-weight: bold;
        }
        .totals-value {
            text-align: right;
            width: 60%;
        }
        .total-row {
            font-weight: bold;
            font-size: 14px;
        }
        .clear {
            clear: both;
        }
        .footer {
            margin-top: 80px;
            text-align: right;
        }
        .signature-line {
            display: inline-block;
            width: 200px;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header Section -->
        <table class="header-table">
            <tr>
                <td class="header-left" style="width: 60%;">
                    <h1>Floating Pivot LLC</h1>
                    <div class="store-info">
                        Shams Media City Freezone<br>
                        United Arab Emirates
                    </div>
                </td>
                <td class="header-right" style="width: 40%;">
                    <h1>INVOICE</h1>
                </td>
            </tr>
        </table>

        <div class="spacer"></div>

        <!-- Info Section (Addresses and Details) -->
        <table class="info-table">
            <tr>
                <td style="width: 33%;">
                    <div class="info-label">Bill To</div>
                    <div class="info-value">
                        <?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?><br>
                        <?php echo str_replace( array( '<br/>', '<br>' ), '<br>', $billing_address ); ?><br>
                        Phone: <?php echo $order->get_billing_phone(); ?><br>
                        Email: <?php echo $order->get_billing_email(); ?>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div class="info-label">Ship To</div>
                    <div class="info-value">
                        <?php echo $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(); ?><br>
                        <?php echo str_replace( array( '<br/>', '<br>' ), '<br>', $shipping_address ); ?>
                    </div>
                </td>
                <td style="width: 34%;" class="info-details">
                    <div class="info-value">
                        <strong>Invoice:</strong> #<?php echo $order->get_order_number(); ?><br>
                        <strong>Invoice Date:</strong> <?php echo $date_created ? $date_created->date( 'F j, Y' ) : ''; ?><br>
                        <strong>Payment Method:</strong> <?php echo $payment_method; ?><br>
                        <?php if ( $transaction_id ) : ?>
                            <strong>Transaction ID:</strong> <?php echo $transaction_id; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Sr. No.</th>
                    <th style="width: 50%;">Description</th>
                    <th style="width: 15%;">Unit Price</th>
                    <th style="width: 10%;">QTY</th>
                    <th style="width: 15%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ( $items as $item_id => $item ) : 
                    $price = $order->get_item_subtotal( $item, false, true );
                    $total = $item->get_total();
                ?>
                <tr>
                    <td class="text-center"><?php echo $i++; ?></td>
                    <td><?php echo $item->get_name(); ?></td>
                    <td class="text-right"><?php echo number_format( $price, 2 ) . ' ' . $currency; ?></td>
                    <td class="text-center"><?php echo $item->get_quantity(); ?></td>
                    <td class="text-right"><?php echo number_format( $total, 2 ) . ' ' . $currency; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="totals-label">Subtotal</td>
                    <td class="totals-value"><?php echo number_format( $order->get_subtotal(), 2 ) . ' ' . $currency; ?></td>
                </tr>
                <tr class="total-row">
                    <td class="totals-label">TOTAL</td>
                    <td class="totals-value"><?php echo number_format( $order->get_total(), 2 ) . ' ' . $currency; ?></td>
                </tr>
            </table>
            <div class="clear"></div>
        </div>

        <!-- Footer Section -->
        <div class="footer">
            <div class="signature-line"></div>
        </div>
    </div>
</body>
</html>
