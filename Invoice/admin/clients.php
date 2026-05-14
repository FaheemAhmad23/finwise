<?php
$pageTitle = 'Clients';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$error = '';

// Handle exports
if (isset($_GET['export'])) {
    $pdo = getDB();
    $exportSql = "SELECT c.name, c.email, c.whatsapp, c.remarks, c.created_at,
        GROUP_CONCAT(DISTINCT o.mfa_id SEPARATOR ', ') AS order_ids,
        GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') AS products,
        GROUP_CONCAT(DISTINCT o.payment_method_name SEPARATOR ', ') AS payment_methods,
        MAX(o.created_at) AS last_order_date,
        MAX(o.warranty_expiry_date) AS warranty_expiry,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.final_amount ELSE 0 END) AS paid_amount,
        SUM(CASE WHEN o.payment_status = 'pending' THEN o.final_amount ELSE 0 END) AS pending_amount,
        MAX(o.payment_status) AS payment_status
    FROM clients c
    LEFT JOIN orders o ON c.id = o.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC";
    $rows = $pdo->query($exportSql)->fetchAll();

    $separator = $_GET['export'] === 'excel' ? "\t" : ",";
    $ext = $_GET['export'] === 'excel' ? 'xls' : 'csv';
    $mime = $_GET['export'] === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';

    header('Content-Type: ' . $mime . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients_export_' . date('Y-m-d') . '.' . $ext . '"');

    $headers = ['Name','Email','WhatsApp','Order IDs','Products','Payment Methods','Paid Amount','Pending Amount','Payment Status','Warranty Expiry','Last Order','Remarks','Client Since'];
    echo implode($separator, $headers) . "\n";
    foreach ($rows as $r) {
        echo implode($separator, [
            '"' . str_replace('"', '""', $r['name']) . '"',
            $r['email'],
            $r['whatsapp'],
            '"' . str_replace('"', '""', $r['order_ids'] ?? '') . '"',
            '"' . str_replace('"', '""', $r['products'] ?? '') . '"',
            '"' . str_replace('"', '""', $r['payment_methods'] ?? '') . '"',
            number_format((float)$r['paid_amount'], 2),
            number_format((float)$r['pending_amount'], 2),
            $r['payment_status'] ?? '-',
            $r['warranty_expiry'] ?? '-',
            $r['last_order_date'] ?? '-',
            '"' . str_replace('"', '""', $r['remarks'] ?? '') . '"',
            $r['created_at']
        ]) . "\n";
    }
    exit;
}

// Handle CSV/JSON import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $pdo = getDB();
    $imported = 0;
    $skipped = 0;

    if (!empty($_FILES['import_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        $content = file_get_contents($_FILES['import_file']['tmp_name']);

        $records = [];
        if ($ext === 'json') {
            $records = json_decode($content, true) ?: [];
        } elseif ($ext === 'csv') {
            $lines = explode("\n", $content);
            $headers = str_getcsv(array_shift($lines));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $values = str_getcsv($line);
                if (count($values) >= count($headers)) {
                    $records[] = array_combine($headers, array_slice($values, 0, count($headers)));
                }
            }
        }

        foreach ($records as $rec) {
            $name = trim($rec['name'] ?? $rec['Name'] ?? '');
            $email = trim($rec['email'] ?? $rec['Email'] ?? '');
            $whatsapp = trim($rec['whatsapp'] ?? $rec['WhatsApp'] ?? '');
            $remarks = trim($rec['remarks'] ?? $rec['Remarks'] ?? '');

            if (empty($name) || empty($email)) { $skipped++; continue; }

            // Check duplicate
            $check = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) { $skipped++; continue; }

            $stmt = $pdo->prepare("INSERT INTO clients (name, email, whatsapp, remarks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $whatsapp, $remarks]);
            $imported++;
        }
        $message = "Import complete: $imported added, $skipped skipped (duplicate or invalid).";
    } else {
        $error = 'Please select a file to import.';
    }
}

// Handle remarks update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remarks') {
    $pdo = getDB();
    $id = (int)$_POST['id'];
    $remarks = trim($_POST['remarks'] ?? '');
    try {
        $pdo->prepare("UPDATE clients SET remarks = ? WHERE id = ?")->execute([$remarks, $id]);
        $message = 'Client remarks updated.';
    } catch (Exception $e) {
        $error = 'Error updating remarks.';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_client') {
    $pdo = getDB();
    $id = (int)$_POST['id'];
    try {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        logAuditAction('client_delete', 'client', $id, 'Deleted client');
        $message = 'Client deleted.';
    } catch (Exception $e) {
        $error = 'Error deleting client.';
    }
}

// Filters
$filterSearch = $_GET['search'] ?? '';
$filterProduct = $_GET['product'] ?? '';
$filterMethod = $_GET['method'] ?? '';
$filterPaymentStatus = $_GET['payment_status'] ?? '';
$filterMonth = $_GET['month'] ?? '';
$filterYear = $_GET['year'] ?? '';

// Build query
$where = [];
$params = [];
$having = [];

if ($filterSearch) {
    $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ?)";
    $st = '%' . $filterSearch . '%';
    $params = array_merge($params, [$st, $st, $st]);
}
if ($filterProduct) {
    $having[] = "GROUP_CONCAT(o.product_id) LIKE ?";
    $params[] = '%' . (int)$filterProduct . '%';
}
if ($filterMethod) {
    $having[] = "GROUP_CONCAT(o.payment_method_name) LIKE ?";
    $params[] = '%' . $filterMethod . '%';
}
if ($filterPaymentStatus) {
    $having[] = "MAX(CASE WHEN o.payment_status = ? THEN 1 ELSE 0 END) = 1";
    $params[] = $filterPaymentStatus;
}
if ($filterMonth) {
    $having[] = "MAX(MONTH(o.created_at)) = ?";
    $params[] = (int)$filterMonth;
}
if ($filterYear) {
    $having[] = "MAX(YEAR(o.created_at)) = ?";
    $params[] = (int)$filterYear;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$havingClause = $having ? "HAVING " . implode(" AND ", $having) : "";

try {
    $pdo = getDB();

    $sql = "SELECT c.*, 
        GROUP_CONCAT(DISTINCT o.mfa_id SEPARATOR ', ') AS order_ids,
        GROUP_CONCAT(DISTINCT o.product_name SEPARATOR ', ') AS products,
        GROUP_CONCAT(DISTINCT o.payment_method_name SEPARATOR ', ') AS payment_methods,
        MAX(o.created_at) AS last_order_date,
        MAX(o.warranty_expiry_date) AS warranty_expiry,
        MAX(o.warranty_enabled) AS has_warranty,
        SUM(CASE WHEN o.payment_status = 'paid' THEN o.final_amount ELSE 0 END) AS paid_amount,
        SUM(CASE WHEN o.payment_status = 'pending' THEN o.final_amount ELSE 0 END) AS pending_amount,
        COUNT(o.id) AS order_count,
        MAX(o.payment_status) AS latest_payment_status
    FROM clients c
    LEFT JOIN orders o ON c.id = o.client_id
    $whereClause
    GROUP BY c.id
    $havingClause
    ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // Summary stats
    $totalRevenue = 0; $totalPending = 0; $paidClients = 0; $pendingClients = 0; $cancelledOrders = 0;
    $thisMonthRevenue = 0;
    $currentMonth = date('Y-m');
    foreach ($clients as $c) {
        $totalRevenue += (float)$c['paid_amount'];
        $totalPending += (float)$c['pending_amount'];
        if ($c['paid_amount'] > 0) $paidClients++;
        if ($c['pending_amount'] > 0) $pendingClients++;
    }

    // Get cancelled orders count and this month revenue globally
    $cancelledOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();
    $thisMonthRevenue = (float)$pdo->query("SELECT IFNULL(SUM(final_amount), 0) FROM orders WHERE payment_status = 'paid' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'")->fetchColumn();

    // Products for filter
    $productsList = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();
    $methodsList = $pdo->query("SELECT DISTINCT payment_method_name FROM orders WHERE payment_method_name IS NOT NULL AND payment_method_name != '' ORDER BY payment_method_name")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $clients = [];
    $productsList = [];
    $methodsList = [];
    $error = 'Database error: ' . $e->getMessage();
}
?>

<div class="max-w-full mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold mb-1">Clients</h1>
            <p class="text-sm text-white/40">All clients and their purchase history.</p>
        </div>
        <div class="flex gap-2">
            <a href="?export=csv" class="bg-white/5 hover:bg-white/10 text-white px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-wider transition"><i class="fa-solid fa-download mr-1"></i> CSV</a>
            <a href="?export=excel" class="bg-white/5 hover:bg-white/10 text-white px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-wider transition"><i class="fa-solid fa-file-excel mr-1"></i> Excel</a>
            <button onclick="document.getElementById('importModal').classList.remove('hidden'); document.getElementById('importModal').classList.add('flex')" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-wider transition"><i class="fa-solid fa-upload mr-1"></i> Import</button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 p-3 bg-green-500/10 border border-green-500/20 rounded-xl text-green-400 text-sm"><?php echo sanitize($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-6 p-3 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400 text-sm"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Total Revenue</p>
            <p class="text-xl font-black text-orange-500">$<?php echo number_format($totalRevenue, 2); ?></p>
        </div>
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Pending Amount</p>
            <p class="text-xl font-black text-yellow-500">$<?php echo number_format($totalPending, 2); ?></p>
        </div>
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Paid Clients</p>
            <p class="text-xl font-black text-green-500"><?php echo $paidClients; ?></p>
        </div>
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Pending Clients</p>
            <p class="text-xl font-black text-yellow-500"><?php echo $pendingClients; ?></p>
        </div>
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Cancelled Orders</p>
            <p class="text-xl font-black text-red-500"><?php echo $cancelledOrders; ?></p>
        </div>
        <div class="bg-white/5 border border-white/5 rounded-2xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">This Month</p>
            <p class="text-xl font-black text-white/80">$<?php echo number_format($thisMonthRevenue, 2); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-6 items-end">
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Search</label>
        <input type="text" name="search" value="<?php echo sanitize($filterSearch); ?>" placeholder="Name, email, WhatsApp..." class="bg-white/5 border border-white/5 rounded-xl px-4 py-2 text-sm focus:border-orange-500/50 focus:outline-none w-48"></div>
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Product</label>
        <select name="product" class="bg-white/5 border border-white/5 rounded-xl px-3 py-2 text-sm focus:border-orange-500/50 focus:outline-none">
            <option value="">All Products</option>
            <?php foreach ($productsList as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo $filterProduct == $p['id'] ? 'selected' : ''; ?>><?php echo sanitize($p['name']); ?></option><?php endforeach; ?>
        </select></div>
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Method</label>
        <select name="method" class="bg-white/5 border border-white/5 rounded-xl px-3 py-2 text-sm focus:border-orange-500/50 focus:outline-none">
            <option value="">All Methods</option>
            <?php foreach ($methodsList as $m): ?><option value="<?php echo sanitize($m); ?>" <?php echo $filterMethod === $m ? 'selected' : ''; ?>><?php echo sanitize($m); ?></option><?php endforeach; ?>
        </select></div>
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Payment</label>
        <select name="payment_status" class="bg-white/5 border border-white/5 rounded-xl px-3 py-2 text-sm focus:border-orange-500/50 focus:outline-none">
            <option value="">All</option>
            <option value="paid" <?php echo $filterPaymentStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="pending" <?php echo $filterPaymentStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="unpaid" <?php echo $filterPaymentStatus === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
        </select></div>
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Month</label>
        <select name="month" class="bg-white/5 border border-white/5 rounded-xl px-3 py-2 text-sm focus:border-orange-500/50 focus:outline-none">
            <option value="">All</option>
            <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?>
        </select></div>
        <div><label class="block text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Year</label>
        <select name="year" class="bg-white/5 border border-white/5 rounded-xl px-3 py-2 text-sm focus:border-orange-500/50 focus:outline-none">
            <option value="">All</option>
            <?php for ($y = date('Y'); $y >= 2023; $y--): ?><option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?>
        </select></div>
        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-xl font-bold text-sm transition">Filter</button>
        <?php if ($filterSearch || $filterProduct || $filterMethod || $filterPaymentStatus || $filterMonth || $filterYear): ?>
        <a href="/admin/clients" class="bg-white/5 hover:bg-white/10 text-white/60 px-4 py-2 rounded-xl font-bold text-sm transition">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Clients Table -->
    <div class="bg-white/5 border border-white/5 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5 text-[10px] font-bold uppercase tracking-widest text-white/30">
                        <th class="text-left p-4">Name</th>
                        <th class="text-left p-4">Contact</th>
                        <th class="text-left p-4">MFA IDs</th>
                        <th class="text-left p-4">Products</th>
                        <th class="text-left p-4">Method</th>
                        <th class="text-left p-4">Warranty</th>
                        <th class="text-left p-4">Payment</th>
                        <th class="text-right p-4">Paid</th>
                        <th class="text-right p-4">Pending</th>
                        <th class="text-left p-4">Remarks</th>
                        <th class="text-center p-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                    <tr><td colspan="11" class="p-12 text-center text-white/30">No clients found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                    <?php
                        $wActive = $client['has_warranty'] && $client['warranty_expiry'] && strtotime($client['warranty_expiry']) >= strtotime('today');
                        $wExpired = $client['has_warranty'] && $client['warranty_expiry'] && strtotime($client['warranty_expiry']) < strtotime('today');
                    ?>
                    <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
                        <td class="p-4 font-medium"><?php echo sanitize($client['name']); ?></td>
                        <td class="p-4">
                            <div class="text-xs text-white/50"><?php echo sanitize($client['email']); ?></div>
                            <?php if ($client['whatsapp']): ?><div class="text-xs text-green-500"><i class="fa-brands fa-whatsapp mr-1"></i><?php echo sanitize($client['whatsapp']); ?></div><?php endif; ?>
                        </td>
                        <td class="p-4 text-xs text-orange-500"><?php echo sanitize($client['order_ids'] ?? '-'); ?></td>
                        <td class="p-4 text-xs text-white/60 max-w-[150px] truncate"><?php echo sanitize($client['products'] ?? '-'); ?></td>
                        <td class="p-4 text-xs text-white/50"><?php echo sanitize($client['payment_methods'] ?? '-'); ?></td>
                        <td class="p-4">
                            <?php if ($wActive): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider text-green-500 bg-green-500/10">Active</span>
                            <?php elseif ($wExpired): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider text-red-500 bg-red-500/10">Expired</span>
                            <?php else: ?><span class="text-white/20">—</span><?php endif; ?>
                        </td>
                        <td class="p-4">
                            <?php 
                            $lps = $client['latest_payment_status'] ?? '-';
                            $lpColors = ['paid' => 'text-green-500', 'pending' => 'text-yellow-500', 'unpaid' => 'text-red-500'];
                            ?>
                            <span class="text-xs font-bold <?php echo $lpColors[$lps] ?? 'text-white/30'; ?>"><?php echo ucfirst($lps); ?></span>
                        </td>
                        <td class="p-4 text-right font-bold text-green-500">$<?php echo number_format((float)$client['paid_amount'], 2); ?></td>
                        <td class="p-4 text-right font-bold text-yellow-500">$<?php echo number_format((float)$client['pending_amount'], 2); ?></td>
                        <td class="p-4 text-xs text-white/40 max-w-[120px] truncate" title="<?php echo sanitize($client['remarks'] ?? ''); ?>"><?php echo sanitize($client['remarks'] ?? '-'); ?></td>
                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="editRemarks(<?php echo $client['id']; ?>, '<?php echo addslashes($client['remarks'] ?? ''); ?>')" class="w-7 h-7 bg-white/5 hover:bg-white/10 rounded-lg flex items-center justify-center transition" title="Edit Remarks">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this client?')">
                                    <input type="hidden" name="action" value="delete_client">
                                    <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
                                    <button type="submit" class="w-7 h-7 bg-red-500/10 hover:bg-red-500/20 rounded-lg flex items-center justify-center transition text-red-400" title="Delete">
                                        <i class="fa-solid fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Remarks Edit Modal -->
<div id="remarksModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[#0a0a0a] border border-white/10 rounded-[32px] max-w-md w-full p-8">
        <form method="POST">
            <input type="hidden" name="action" value="update_remarks">
            <input type="hidden" name="id" id="remarksClientId">
            <h2 class="text-xl font-bold mb-6">Edit Remarks</h2>
            <textarea name="remarks" id="remarksText" rows="4" class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm focus:border-orange-500/50 focus:outline-none transition resize-none mb-6" placeholder="Add notes about this client..."></textarea>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('remarksModal').classList.add('hidden'); document.getElementById('remarksModal').classList.remove('flex')" class="flex-1 bg-white/5 hover:bg-white/10 py-3 rounded-2xl font-bold text-sm transition">Cancel</button>
                <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-2xl font-bold text-sm transition">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[#0a0a0a] border border-white/10 rounded-[32px] max-w-md w-full p-8">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <h2 class="text-xl font-bold mb-2">Import Clients</h2>
            <p class="text-xs text-white/40 mb-6">Upload a CSV or JSON file. Required columns: <strong>name</strong>, <strong>email</strong>. Optional: <strong>whatsapp</strong>, <strong>remarks</strong>. Duplicates by email will be skipped.</p>
            <input type="file" name="import_file" accept=".csv,.json" required class="w-full bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-xs focus:border-orange-500/50 focus:outline-none transition mb-6 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-white/10 file:text-white file:text-[10px] file:font-bold file:uppercase file:cursor-pointer">
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden'); document.getElementById('importModal').classList.remove('flex')" class="flex-1 bg-white/5 hover:bg-white/10 py-3 rounded-2xl font-bold text-sm transition">Cancel</button>
                <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-2xl font-bold text-sm transition"><i class="fa-solid fa-upload mr-2"></i>Import</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRemarks(id, currentRemarks) {
    document.getElementById('remarksClientId').value = id;
    document.getElementById('remarksText').value = currentRemarks;
    document.getElementById('remarksModal').classList.remove('hidden');
    document.getElementById('remarksModal').classList.add('flex');
}

document.getElementById('remarksModal').addEventListener('click', function(e) { if (e.target === this) { this.classList.add('hidden'); this.classList.remove('flex'); } });
document.getElementById('importModal').addEventListener('click', function(e) { if (e.target === this) { this.classList.add('hidden'); this.classList.remove('flex'); } });
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
