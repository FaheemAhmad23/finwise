<?php
/**
 * MFA Tools - Invoice PDF Generator (Zero Dependencies)
 * 
 * Uses SimplePDF to generate professional A4 invoice PDFs.
 * No TCPDF, FPDF, or Composer required.
 * Returns PDF binary string or null on failure.
 */
require_once __DIR__ . '/SimplePDF.php';

/**
 * Generate an invoice PDF for a given order ID.
 * 
 * @param int $orderId The order ID
 * @return array|null ['pdf' => binary string, 'filename' => string] or null on failure
 */
function generateInvoicePDF($orderId) {
    try {
        $pdo = getDB();
        
        // Fetch order with client info
        $stmt = $pdo->prepare("SELECT o.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.email AS client_email
            FROM orders o LEFT JOIN clients c ON o.client_id = c.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            error_log("Invoice PDF: Order not found (ID: $orderId)");
            return null;
        }

        // Fetch order requirements
        $rr = $pdo->prepare("SELECT * FROM order_requirement_responses WHERE order_id = ? ORDER BY id ASC");
        $rr->execute([$orderId]);
        $requirements = $rr->fetchAll();

        // Invoice number
        $invoiceNumber = $order['invoice_number'] ?? ('INV-' . ($order['mfa_id'] ?? sprintf('%06d', $order['id'])));
        $filename = 'invoice-' . $invoiceNumber . '.pdf';

        // Currency helper
        $currency = $order['currency'] ?? 'USD';
        $symbols = ['USD' => '$', 'PKR' => 'PKR ', 'INR' => 'Rs.'];
        $prefix = $symbols[$currency] ?? ($currency . ' ');
        $decimals = ($currency === 'USD') ? 2 : 0;
        $paidAmount = (float)($order['final_amount'] ?? $order['total_price']);
        $formattedAmount = $prefix . number_format($paidAmount, $decimals);

        // WhatsApp
        $waNumber = function_exists('getWhatsAppNumber') ? getWhatsAppNumber() : '+447490916612';

        // Create PDF
        $pdf = new SimplePDF();
        $pdf->addPage();

        // Colors
        $orange = [255, 95, 31];
        $dark = [30, 30, 30];
        $gray = [100, 100, 100];
        $lightGray = [160, 160, 160];
        $green = [34, 197, 94];

        // ─── HEADER ───
        $pdf->setFont('B', 22);
        $pdf->setTextColor(...$orange);
        $pdf->cell(0, 24, 'MFA TOOLS', 'L', false, true);
        
        $pdf->setFont('', 8);
        $pdf->setTextColor(...$lightGray);
        $pdf->cell(0, 12, 'Premium Digital Solutions', 'L', false, true);
        $pdf->ln(4);

        // Divider line
        $pdf->setDrawColor(...$orange);
        $pdf->setLineWidth(1.5);
        $y = $pdf->getY();
        $pdf->line(56.69, $y, 538.59, $y);
        $pdf->ln(12);

        // ─── INVOICE TITLE + NUMBER ───
        $pdf->setFont('B', 11);
        $pdf->setTextColor(80, 80, 80);
        $pdf->setFont('B', 11);
        $pdf->cellLR('INVOICE', $invoiceNumber, 16);
        
        $pdf->setFont('', 9);
        $pdf->setTextColor(...$gray);
        $orderDate = date('F d, Y', strtotime($order['created_at']));
        $completedDate = $order['completed_at'] ? date('F d, Y', strtotime($order['completed_at'])) : '';
        $pdf->cellLR('Date: ' . $orderDate, $completedDate ? 'Completed: ' . $completedDate : '', 14);
        $pdf->ln(14);

        // ─── BILL TO / ORDER INFO ───
        $pdf->setFont('B', 8);
        $pdf->setTextColor(...$lightGray);
        $pdf->cellLR('BILL TO', 'ORDER DETAILS', 12);
        
        $customerName = $order['customer_name'] ?? 'Customer';
        $pdf->setFont('B', 11);
        $pdf->setTextColor(...$dark);
        $pdf->setFont('', 9);
        $orderId2 = $order['mfa_id'] ?? $order['order_number'];
        $pdf->cellLR($customerName, 'Order: ' . $orderId2, 14);
        
        $pdf->setFont('', 9);
        $pdf->setTextColor(...$gray);
        $pdf->cellLR($order['customer_email'], 'Currency: ' . $currency, 14);
        
        $whatsapp = !empty($order['customer_whatsapp']) ? 'WhatsApp: ' . $order['customer_whatsapp'] : '';
        $paymentStatus = 'Status: ' . ucfirst($order['payment_status'] ?? 'pending');
        $pdf->cellLR($whatsapp, $paymentStatus, 14);
        
        if (!empty($order['payment_method_name'])) {
            $pdf->cellLR('', 'Method: ' . $order['payment_method_name'], 14);
        }
        $pdf->ln(14);

        // ─── ITEMS TABLE ───
        // Table header background
        $pdf->setFillColor(240, 240, 240);
        $y = $pdf->getY();
        $pdf->rect(56.69, $y, 481.9, 20, 'F');
        
        $pdf->setFont('B', 8);
        $pdf->setTextColor(100, 100, 100);
        $pdf->cellLR('  PRODUCT / SERVICE', 'TOTAL', 20);
        
        // Table row
        $pdf->setFont('', 10);
        $pdf->setTextColor(...$dark);
        $pdf->setDrawColor(220, 220, 220);
        $y = $pdf->getY();
        $pdf->line(56.69, $y, 538.59, $y);
        $pdf->cellLR('  ' . ($order['product_name'] ?? 'Product'), $formattedAmount, 24);
        $y = $pdf->getY();
        $pdf->line(56.69, $y, 538.59, $y);
        $pdf->ln(8);

        // ─── TOTALS ───
        $pdf->setFont('', 9);
        $pdf->setTextColor(...$gray);
        $pdf->cellLR('', 'Subtotal: ' . $formattedAmount, 16);

        if ((float)($order['discount_amount'] ?? 0) > 0) {
            $pdf->setTextColor(...$green);
            $pdf->cellLR('', 'Discount: -' . $prefix . number_format((float)$order['discount_amount'], $decimals), 16);
        }

        // Total line
        $pdf->setDrawColor(...$orange);
        $pdf->setLineWidth(1);
        $y = $pdf->getY();
        $pdf->line(370, $y, 538.59, $y);
        $pdf->ln(6);
        
        $pdf->setFont('B', 14);
        $pdf->setTextColor(...$orange);
        $pdf->cellLR('', 'TOTAL: ' . $formattedAmount, 20);
        $pdf->ln(12);

        // ─── WARRANTY (if enabled) ───
        if (!empty($order['warranty_enabled'])) {
            $pdf->setFillColor(235, 250, 240);
            $y = $pdf->getY();
            $pdf->rect(56.69, $y, 481.9, 50, 'F');
            $pdf->ln(6);
            
            $pdf->setFont('B', 8);
            $pdf->setTextColor(...$green);
            $pdf->cell(0, 12, '  WARRANTY INFORMATION', 'L', false, true);
            
            $pdf->setFont('', 9);
            $pdf->setTextColor(60, 60, 60);
            $duration = (int)($order['warranty_duration_value'] ?? 0) . ' ' . ucfirst(str_replace('_', ' ', $order['warranty_duration_type'] ?? 'months'));
            $startDate = $order['warranty_start_date'] ? date('M d, Y', strtotime($order['warranty_start_date'])) : '-';
            $expiryDate = $order['warranty_expiry_date'] ? date('M d, Y', strtotime($order['warranty_expiry_date'])) : '-';
            $pdf->cell(0, 14, '  Duration: ' . $duration . '    Start: ' . $startDate . '    Expiry: ' . $expiryDate, 'L', false, true);
            
            $pdf->ln(14);
        }

        // ─── ORDER REQUIREMENTS ───
        if (!empty($requirements)) {
            $pdf->setFont('B', 8);
            $pdf->setTextColor(...$lightGray);
            $pdf->cell(0, 14, 'ORDER DETAILS', 'L', false, true);
            
            $pdf->setFont('', 9);
            foreach ($requirements as $r) {
                $pdf->setTextColor(...$gray);
                $text = ($r['requirement_text'] ?? '') . ': ' . ($r['response_value'] ?? '');
                $pdf->cell(0, 14, '  ' . $text, 'L', false, true);
            }
            $pdf->ln(8);
        }

        // ─── FOOTER ───
        $pdf->ln(8);
        $pdf->setDrawColor(220, 220, 220);
        $pdf->setLineWidth(0.5);
        $y = $pdf->getY();
        $pdf->line(56.69, $y, 538.59, $y);
        $pdf->ln(8);
        
        $pdf->setFont('B', 8);
        $pdf->setTextColor(...$lightGray);
        $pdf->cell(0, 12, 'THANK YOU FOR YOUR PURCHASE', 'C', false, true);
        
        $pdf->setFont('', 8);
        $pdf->setTextColor(...$gray);
        $pdf->cell(0, 12, 'MFA Tools  |  mfatools.one  |  ' . $waNumber, 'C', false, true);
        $pdf->cell(0, 12, 'Need help? Contact us on WhatsApp or email support@mfatools.net', 'C', false, true);
        
        $pdf->ln(4);
        $pdf->setFont('', 7);
        $pdf->setTextColor(180, 180, 180);
        $pdf->cell(0, 10, 'This is a computer-generated invoice and does not require a signature.', 'C', false, true);

        // Output as string
        $pdfContent = $pdf->output();
        
        // Log success
        error_log("Invoice PDF generated successfully: $filename (order #$orderId, " . strlen($pdfContent) . " bytes)");

        return [
            'pdf' => $pdfContent,
            'filename' => $filename,
            'invoice_number' => $invoiceNumber,
        ];

    } catch (Exception $e) {
        error_log("Invoice PDF generation error (order #$orderId): " . $e->getMessage());
        return null;
    }
}
?>
