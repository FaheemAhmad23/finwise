<?php
$pageTitle = 'Invoice';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

$orderId = (int)($_GET['order_id'] ?? 0);
$order = null;
$requirements = [];

if ($orderId) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT o.*, c.name AS client_name, c.whatsapp AS client_whatsapp, c.email AS client_email
            FROM orders o LEFT JOIN clients c ON o.client_id = c.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $rr = $pdo->prepare("SELECT * FROM order_requirement_responses WHERE order_id = ? ORDER BY id ASC");
            $rr->execute([$orderId]);
            $requirements = $rr->fetchAll();
        }
    } catch (Exception $e) {
        // ignore
    }
}

$siteWhatsApp = getWhatsAppNumber();

// Currency formatting helper — uses final_amount (the actual paid value in order currency)
$orderCurrency = $order ? ($order['currency'] ?? 'USD') : 'USD';
$paidAmount = $order ? (float)($order['final_amount'] ?? $order['total_price']) : 0;

function fmtCurrency($amount, $currency) {
    $symbols = ['USD' => '$', 'PKR' => 'PKR ', 'INR' => '₹'];
    $prefix = $symbols[$currency] ?? '';
    $decimals = ($currency === 'USD') ? 2 : 0;
    return $prefix . number_format((float)$amount, $decimals);
}
?>

<?php if (!$order): ?>
<div class="max-w-xl mx-auto text-center py-20">
    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-6">
        <i class="fa-solid fa-file-invoice text-3xl text-white/20"></i>
    </div>
    <h2 class="text-xl font-bold mb-2">No Order Selected</h2>
    <p class="text-sm text-white/40 mb-6">Go to Orders and click the invoice icon to view an invoice.</p>
    <a href="/admin/orders.php" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition">Go to Orders</a>
</div>
<?php else: ?>

<style>
    @media print {
        body { background: white !important; color: black !important; }
        .no-print, nav, aside, header, .admin-sidebar, .sidebar { display: none !important; }
        main { margin-left: 0 !important; padding: 0 !important; }
        .print-area { margin: 0 !important; padding: 20px !important; border: none !important; background: white !important; box-shadow: none !important; }
        .print-area * { color: #111 !important; border-color: #ddd !important; background-color: transparent !important; }
        .print-accent { color: #f97316 !important; }
        .print-area .warranty-section { border: 1px solid #86efac !important; padding: 20px !important; border-radius: 12px !important; margin-bottom: 24px !important; display: block !important; background-color: #e6f9e6 !important; }
        .invoice-logo { filter: none !important; mix-blend-mode: normal !important; }
    }
</style>

<div class="max-w-3xl mx-auto">
    <!-- Actions -->
    <div class="flex justify-between items-center mb-6 no-print">
        <a href="/admin/orders.php" class="text-white/40 hover:text-white text-sm transition"><i class="fa-solid fa-arrow-left mr-2"></i>Back to Orders</a>
        <button onclick="window.print()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm transition"><i class="fa-solid fa-print mr-2"></i>Print Invoice</button>
    </div>

    <!-- Invoice -->
    <div class="print-area bg-white/[0.02] border border-white/[0.05] rounded-[32px] p-10 md:p-14">
        
        <!-- Header -->
        <div class="flex justify-between items-start mb-12">
            <div>
                <img src="/assets/images/mfa-logo-png.png" alt="MFA Tools One" class="invoice-logo h-12 mb-3" style="filter:none; mix-blend-mode:normal;">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/20">Invoice</p>
                <p class="text-xs text-white/30 mt-1">Malik Faheem</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-black text-orange-500 print-accent"><?php echo sanitize($order['mfa_id'] ?? $order['order_number']); ?></p>
                <p class="text-xs text-white/40 mt-1"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
            </div>
        </div>

        <!-- Client & Order Info -->
        <div class="grid grid-cols-2 gap-8 mb-12">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/20 mb-3">Bill To</p>
                <p class="font-bold text-lg"><?php echo sanitize($order['customer_name']); ?></p>
                <p class="text-sm text-white/50"><?php echo sanitize($order['customer_email']); ?></p>
                <?php if ($order['customer_whatsapp']): ?>
                <p class="text-sm text-white/50"><i class="fa-brands fa-whatsapp mr-1 text-green-500"></i><?php echo sanitize($order['customer_whatsapp']); ?></p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/20 mb-3">Invoice Details</p>
                <p class="text-sm text-white/60">Currency: <span class="font-bold"><?php echo $orderCurrency; ?></span></p>
                <p class="text-sm text-white/60">Status: <span class="font-bold"><?php echo ucfirst($order['payment_status'] ?? 'pending'); ?></span></p>
                <p class="text-sm text-white/60">Method: <span class="font-bold"><?php echo sanitize($order['payment_method_name'] ?? '-'); ?></span></p>
                <?php if ($order['completed_at']): ?>
                <p class="text-sm text-white/60">Completed: <span class="font-bold"><?php echo date('M d, Y', strtotime($order['completed_at'])); ?></span></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Line Items — uses paidAmount (stored in order currency), NOT product_price (which is USD base) -->
        <div class="mb-12">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-[10px] font-bold uppercase tracking-[0.15em] text-white/30">
                        <th class="text-left pb-4">Product / Service</th>
                        <th class="text-center pb-4">Qty</th>
                        <th class="text-right pb-4">Price</th>
                        <th class="text-right pb-4">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-white/5">
                        <td class="py-5 font-medium"><?php echo sanitize($order['product_name']); ?></td>
                        <td class="py-5 text-center text-white/50">1</td>
                        <td class="py-5 text-right text-white/60"><?php echo fmtCurrency($paidAmount, $orderCurrency); ?></td>
                        <td class="py-5 text-right font-bold"><?php echo fmtCurrency($paidAmount, $orderCurrency); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary — all values use paidAmount so subtotal = total, all in order currency -->
        <div class="flex justify-end mb-12">
            <div class="w-64">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-white/40">Subtotal</span>
                    <span><?php echo fmtCurrency($paidAmount, $orderCurrency); ?></span>
                </div>
                <div class="flex justify-between pt-4 mt-4 border-t border-white/10">
                    <span class="text-lg font-black uppercase tracking-tighter">Total</span>
                    <span class="text-2xl font-black text-orange-500 print-accent"><?php echo fmtCurrency($paidAmount, $orderCurrency); ?></span>
                </div>
            </div>
        </div>

        <?php if ($requirements): ?>
        <!-- Requirements / Details -->
        <div class="mb-12 p-6 bg-white/[0.02] border border-white/5 rounded-2xl">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/20 mb-4">Order Details</p>
            <div class="space-y-2">
                <?php foreach ($requirements as $r): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-white/40"><?php echo sanitize($r['requirement_text']); ?></span>
                    <span class="font-medium"><?php echo sanitize($r['response_value']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($order['warranty_enabled']): ?>
        <!-- Warranty -->
        <div class="warranty-section mb-12 p-6 bg-green-500/10 border border-green-500/20 rounded-2xl">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/20 mb-4">Warranty Information</p>
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-white/30 block">Duration</span>
                    <span class="font-bold"><?php echo (int)$order['warranty_duration_value']; ?> <?php echo ucfirst(str_replace('_', ' ', $order['warranty_duration_type'])); ?></span>
                </div>
                <div>
                    <span class="text-white/30 block">Start Date</span>
                    <span class="font-bold"><?php echo $order['warranty_start_date'] ? date('M d, Y', strtotime($order['warranty_start_date'])) : '—'; ?></span>
                </div>
                <div>
                    <span class="text-white/30 block">Expiry Date</span>
                    <span class="font-bold"><?php echo $order['warranty_expiry_date'] ? date('M d, Y', strtotime($order['warranty_expiry_date'])) : '—'; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center border-t border-white/5 pt-8">
            <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-white/15 mb-2">Thank you for your purchase</p>
            <p class="text-[10px] text-white/10">MFA Tools One · mfatools.one · <?php echo sanitize($siteWhatsApp); ?></p>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
