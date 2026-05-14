<?php
$pageTitle = 'Email Logs';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email_engine.php';

$pdo = getDB();
$success = '';
$error = '';

// Handle retry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry') {
    $logId = (int)($_POST['log_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ? AND status = 'failed'");
        $stmt->execute([$logId]);
        $log = $stmt->fetch();
        if ($log && $log['email_account_id']) {
            $result = sendEmailViaAccount($log['email_account_id'], $log['to_email'], $log['subject'], $log['html_body'] ?? '', $log['text_body'] ?? '', $log['feature_key'] . '_retry', $log['cc'] ?? '', $log['bcc'] ?? '');
            if ($result['success']) {
                $pdo->prepare("UPDATE email_logs SET status = 'sent', error_message = 'Retried successfully' WHERE id = ?")->execute([$logId]);
                $success = 'Email retried and sent successfully!';
            } else {
                $error = 'Retry failed: ' . $result['message'];
            }
        } else {
            $error = 'Log entry not found or no account to retry with.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if ($statusFilter) {
    $where .= ' AND status = ?';
    $params[] = $statusFilter;
}

$totalCount = $pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE $where");
$totalCount->execute($params);
$total = (int)$totalCount->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT l.*, a.label as account_label FROM email_logs l LEFT JOIN email_accounts a ON l.email_account_id = a.id WHERE $where ORDER BY l.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="flex items-center justify-between mb-10">
    <div>
        <h1 class="text-3xl font-black tracking-tighter">Email Logs</h1>
        <p class="text-white/30 text-sm mt-1"><?php echo number_format($total); ?> total emails logged</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="?status=" class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo !$statusFilter ? 'bg-orange-500 text-white' : 'bg-white/5 text-white/40'; ?>">All</a>
        <a href="?status=sent" class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo $statusFilter === 'sent' ? 'bg-green-500 text-white' : 'bg-white/5 text-white/40'; ?>">Sent</a>
        <a href="?status=failed" class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo $statusFilter === 'failed' ? 'bg-red-500 text-white' : 'bg-white/5 text-white/40'; ?>">Failed</a>
        <a href="?status=queued" class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider <?php echo $statusFilter === 'queued' ? 'bg-yellow-500 text-black' : 'bg-white/5 text-white/40'; ?>">Queued</a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert-success mb-6"><i class="fa-solid fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error mb-6"><i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
<?php endif; ?>

<?php if (empty($logs)): ?>
<div class="card p-12 text-center">
    <div class="w-16 h-16 bg-white/5 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-inbox text-white/20 text-2xl"></i>
    </div>
    <h3 class="text-xl font-bold mb-2">No Logs</h3>
    <p class="text-white/40 text-sm">No emails have been sent yet.</p>
</div>
<?php else: ?>

<div class="space-y-3">
    <?php foreach ($logs as $log): ?>
    <div class="card p-5">
        <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <?php
                    $statusColors = ['sent' => 'bg-green-500/20 text-green-400', 'failed' => 'bg-red-500/20 text-red-400', 'queued' => 'bg-yellow-500/20 text-yellow-400'];
                    ?>
                    <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md <?php echo $statusColors[$log['status']] ?? 'bg-white/10 text-white/40'; ?>">
                        <?php echo strtoupper($log['status']); ?>
                    </span>
                    <code class="text-[10px] text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded"><?php echo sanitize($log['feature_key']); ?></code>
                    <?php if ($log['account_label']): ?>
                    <span class="text-[10px] text-white/30"><?php echo sanitize($log['account_label']); ?></span>
                    <?php endif; ?>
                </div>
                <h4 class="font-bold text-sm truncate"><?php echo sanitize($log['subject']); ?></h4>
                <p class="text-white/30 text-xs mt-1">
                    <span class="text-white/50"><?php echo sanitize($log['from_email']); ?></span>
                    → <span class="text-white/50"><?php echo sanitize($log['to_email']); ?></span>
                    <?php if ($log['cc']): ?> CC: <?php echo sanitize($log['cc']); ?><?php endif; ?>
                </p>
                <?php if ($log['error_message']): ?>
                <p class="text-red-400/80 text-xs mt-1 truncate"><i class="fa-solid fa-exclamation-triangle mr-1"></i><?php echo sanitize(substr($log['error_message'], 0, 200)); ?></p>
                <?php endif; ?>
                <p class="text-white/20 text-[10px] mt-1"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if ($log['status'] === 'failed' && $log['email_account_id']): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="retry">
                    <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                    <button type="submit" class="px-3 py-2 bg-orange-500/10 text-orange-400 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-orange-500/20 transition">Retry</button>
                </form>
                <?php endif; ?>
                <?php if ($log['html_body']): ?>
                <button onclick="document.getElementById('viewFrame<?php echo $log['id']; ?>').srcdoc=this.dataset.html;document.getElementById('viewModal<?php echo $log['id']; ?>').classList.remove('hidden')" 
                        data-html="<?php echo htmlspecialchars($log['html_body'], ENT_QUOTES); ?>"
                        class="px-3 py-2 bg-white/5 text-white/40 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-white/10 transition">View</button>
                <!-- View Modal -->
                <div id="viewModal<?php echo $log['id']; ?>" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6">
                    <div class="bg-[#111] border border-white/10 rounded-3xl w-full max-w-3xl max-h-[80vh] overflow-hidden">
                        <div class="flex items-center justify-between p-4 border-b border-white/5">
                            <h3 class="font-bold text-sm"><?php echo sanitize($log['subject']); ?></h3>
                            <button onclick="this.closest('[id^=viewModal]').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <iframe id="viewFrame<?php echo $log['id']; ?>" class="w-full h-[60vh] bg-white"></iframe>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex justify-center gap-2 mt-8">
    <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-2 rounded-lg text-xs font-bold <?php echo $i === $page ? 'bg-orange-500 text-white' : 'bg-white/5 text-white/40 hover:bg-white/10'; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
