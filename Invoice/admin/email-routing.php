<?php
$pageTitle = 'Email Routing';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email_engine.php';

$pdo = getDB();
$success = '';
$error = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_routing') {
    try {
        $rules = $_POST['rules'] ?? [];
        foreach ($rules as $ruleId => $data) {
            $accountId = !empty($data['email_account_id']) ? (int)$data['email_account_id'] : null;
            $enabled = isset($data['is_enabled']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE email_routing_rules SET email_account_id = ?, is_enabled = ? WHERE id = ?");
            $stmt->execute([$accountId, $enabled, (int)$ruleId]);
        }
        $success = 'Routing rules saved.';
        logAudit('email_routing_updated', 'email_routing', null, 'Routing rules updated');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    $rules = $pdo->query("SELECT r.*, a.email_address as account_email, a.label as account_label FROM email_routing_rules r LEFT JOIN email_accounts a ON r.email_account_id = a.id ORDER BY r.feature_key ASC")->fetchAll();
} catch (Exception $e) {
    $rules = [];
    $error = 'Email routing table needs migration. Please run migrate.php first. Error: ' . $e->getMessage();
}
try {
    $accounts = $pdo->query("SELECT id, label, email_address, is_active FROM email_accounts ORDER BY label ASC")->fetchAll();
} catch (Exception $e) {
    $accounts = [];
}
?>

<div class="flex items-center justify-between mb-10">
    <div>
        <h1 class="text-3xl font-black tracking-tighter">Email Routing</h1>
        <p class="text-white/30 text-sm mt-1">Choose which email account is used for each feature</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert-success mb-6"><i class="fa-solid fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error mb-6"><i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
<?php endif; ?>

<?php if (empty($accounts)): ?>
<div class="card p-8 text-center">
    <p class="text-white/40 mb-4">No email accounts configured yet.</p>
    <a href="email-accounts.php?add=1" class="btn-primary">Add Email Account First</a>
</div>
<?php else: ?>

<form method="POST">
    <input type="hidden" name="action" value="save_routing">
    
    <div class="space-y-4">
        <?php foreach ($rules as $rule): ?>
        <div class="card p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-1">
                    <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md <?php echo $rule['is_enabled'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>"><?php echo $rule['is_enabled'] ? 'Enabled' : 'Disabled'; ?></span>
                </div>
                <h3 class="font-bold"><?php echo sanitize($rule['feature_label']); ?></h3>
                <p class="text-white/30 text-xs font-mono"><?php echo sanitize($rule['feature_key']); ?></p>
            </div>
            <div class="flex items-center gap-4 flex-shrink-0">
                <select name="rules[<?php echo $rule['id']; ?>][email_account_id]" class="input-field !py-2 !text-xs w-56">
                    <option value="">— Not Assigned —</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php echo $rule['email_account_id'] == $acc['id'] ? 'selected' : ''; ?> <?php echo !$acc['is_active'] ? 'disabled' : ''; ?>>
                        <?php echo sanitize($acc['label']); ?> (<?php echo sanitize($acc['email_address']); ?>)<?php echo !$acc['is_active'] ? ' [DISABLED]' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="flex items-center gap-2 cursor-pointer whitespace-nowrap">
                    <input type="checkbox" name="rules[<?php echo $rule['id']; ?>][is_enabled]" class="w-4 h-4 accent-orange-500" <?php echo $rule['is_enabled'] ? 'checked' : ''; ?>>
                    <span class="text-xs font-bold">On</span>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-8 flex justify-end">
        <button type="submit" class="btn-primary px-8"><i class="fa-solid fa-save mr-2"></i>Save Routing Rules</button>
    </div>
</form>

<!-- Info -->
<div class="card p-6 mt-8 bg-blue-500/5 border-blue-500/10">
    <h4 class="font-bold text-sm mb-2 text-blue-400"><i class="fa-solid fa-info-circle mr-2"></i>How Routing Works</h4>
    <ul class="text-white/40 text-xs space-y-1">
        <li>• Each feature sends emails using the assigned email account's SMTP credentials</li>
        <li>• If a feature is disabled, no emails will be sent for that feature</li>
        <li>• If the assigned account is inactive, the system will log an error (no crash)</li>
        <li>• <strong class="text-orange-400">Customer order emails are OFF by default</strong> — enable only if you want to send order confirmations</li>
    </ul>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
