<?php
$pageTitle = 'Email Accounts';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email_engine.php';

$pdo = getDB();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_account') {
        $id = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $from_name = trim($_POST['from_name'] ?? '');
        $email_address = trim($_POST['email_address'] ?? '');
        $reply_to = trim($_POST['reply_to'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password_raw = $_POST['smtp_password'] ?? '';
        $smtp_timeout = (int)($_POST['smtp_timeout'] ?? 30);
        $incoming_enabled = isset($_POST['incoming_enabled']) ? 1 : 0;
        $incoming_type = $_POST['incoming_type'] ?? 'imap';
        $incoming_host = trim($_POST['incoming_host'] ?? '');
        $incoming_port = (int)($_POST['incoming_port'] ?? 993);
        $incoming_encryption = $_POST['incoming_encryption'] ?? 'tls';
        $incoming_username = trim($_POST['incoming_username'] ?? '');
        $incoming_password_raw = $_POST['incoming_password'] ?? '';
        $imap_folder = trim($_POST['imap_folder'] ?? 'INBOX');
        
        if (empty($label) || empty($email_address) || empty($smtp_host)) {
            $error = 'Label, email address, and SMTP host are required.';
        } else {
            try {
                if ($id > 0) {
                    // Update
                    $sql = "UPDATE email_accounts SET label=?, from_name=?, email_address=?, reply_to=?, is_active=?, smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, smtp_timeout=?, incoming_enabled=?, incoming_type=?, incoming_host=?, incoming_port=?, incoming_encryption=?, incoming_username=?, imap_folder=?";
                    $params = [$label, $from_name, $email_address, $reply_to ?: null, $is_active, $smtp_host, $smtp_port, $smtp_encryption, $smtp_username, $smtp_timeout, $incoming_enabled, $incoming_type, $incoming_host ?: null, $incoming_port ?: null, $incoming_encryption, $incoming_username ?: null, $imap_folder];
                    
                    if (!empty($smtp_password_raw)) {
                        $sql .= ", smtp_password=?";
                        $params[] = encryptEmailPassword($smtp_password_raw);
                    }
                    if (!empty($incoming_password_raw)) {
                        $sql .= ", incoming_password=?";
                        $params[] = encryptEmailPassword($incoming_password_raw);
                    }
                    $sql .= " WHERE id=?";
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = 'Email account updated.';
                    logAudit('email_account_updated', 'email_account', $id, "Updated: $email_address");
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO email_accounts (label, from_name, email_address, reply_to, is_active, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, smtp_timeout, incoming_enabled, incoming_type, incoming_host, incoming_port, incoming_encryption, incoming_username, incoming_password, imap_folder) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([
                        $label, $from_name, $email_address, $reply_to ?: null, $is_active,
                        $smtp_host, $smtp_port, $smtp_encryption, $smtp_username,
                        encryptEmailPassword($smtp_password_raw), $smtp_timeout,
                        $incoming_enabled, $incoming_type, $incoming_host ?: null,
                        $incoming_port ?: null, $incoming_encryption, $incoming_username ?: null,
                        !empty($incoming_password_raw) ? encryptEmailPassword($incoming_password_raw) : null,
                        $imap_folder
                    ]);
                    $success = 'Email account created.';
                    logAudit('email_account_created', 'email_account', $pdo->lastInsertId(), "Created: $email_address");
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'toggle_active') {
        $id = (int)($_POST['toggle_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE email_accounts SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            $success = 'Account status toggled.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($action === 'test_smtp') {
        $id = (int)($_POST['test_account_id'] ?? 0);
        $testEmail = trim($_POST['test_email'] ?? '');
        $result = testSmtpConnection($id, $testEmail ?: null);
        if ($result['success']) $success = $result['message'];
        else $error = $result['message'];
    }
    
    if ($action === 'test_incoming') {
        $id = (int)($_POST['test_account_id'] ?? 0);
        $result = testIncomingConnection($id);
        if ($result['success']) $success = $result['message'];
        else $error = $result['message'];
    }
}

// Get all accounts
try {
    $accounts = $pdo->query("SELECT * FROM email_accounts ORDER BY label ASC")->fetchAll();
} catch (Exception $e) {
    $accounts = [];
    $error = 'Email accounts table needs migration. Please run migrate.php first. Error: ' . $e->getMessage();
}

// Check if editing
$editAccount = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM email_accounts WHERE id = ?");
    $stmt->execute([$editId]);
    $editAccount = $stmt->fetch();
}

$showForm = isset($_GET['add']) || $editAccount;
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-10">
    <div>
        <h1 class="text-3xl font-black tracking-tighter">Email Accounts</h1>
        <p class="text-white/30 text-sm mt-1">Manage SMTP and incoming mail accounts</p>
    </div>
    <?php if (!$showForm): ?>
    <a href="?add=1" class="btn-primary gap-2"><i class="fa-solid fa-plus"></i> Add Account</a>
    <?php else: ?>
    <a href="email-accounts.php" class="btn-primary gap-2 !bg-white/10"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert-success mb-6"><i class="fa-solid fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error mb-6"><i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
<?php endif; ?>

<?php if ($showForm): ?>
<!-- Add/Edit Form -->
<form method="POST" class="card p-8 space-y-8">
    <input type="hidden" name="action" value="save_account">
    <input type="hidden" name="id" value="<?php echo $editAccount['id'] ?? 0; ?>">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Label / Purpose *</label>
            <input type="text" name="label" class="input-field" placeholder="e.g. Support, Orders, Billing" value="<?php echo sanitize($editAccount['label'] ?? ''); ?>" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">From Name</label>
            <input type="text" name="from_name" class="input-field" placeholder="e.g. MFA Tools Support" value="<?php echo sanitize($editAccount['from_name'] ?? ''); ?>">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Email Address *</label>
            <input type="email" name="email_address" class="input-field" placeholder="support@yourdomain.com" value="<?php echo sanitize($editAccount['email_address'] ?? ''); ?>" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Reply-To (optional)</label>
            <input type="email" name="reply_to" class="input-field" placeholder="reply@yourdomain.com" value="<?php echo sanitize($editAccount['reply_to'] ?? ''); ?>">
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <input type="checkbox" name="is_active" id="is_active" class="w-4 h-4 accent-orange-500" <?php echo ($editAccount['is_active'] ?? 1) ? 'checked' : ''; ?>>
        <label for="is_active" class="text-sm font-bold">Active</label>
    </div>
    
    <!-- SMTP Settings -->
    <div class="border-t border-white/5 pt-6">
        <h3 class="text-lg font-black mb-4 tracking-tight"><i class="fa-solid fa-paper-plane mr-2 text-orange-500"></i>SMTP (Outgoing)</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">SMTP Host *</label>
                <input type="text" name="smtp_host" class="input-field" placeholder="smtp.gmail.com" value="<?php echo sanitize($editAccount['smtp_host'] ?? ''); ?>" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Port</label>
                <input type="number" name="smtp_port" class="input-field" value="<?php echo $editAccount['smtp_port'] ?? 587; ?>">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Encryption</label>
                <select name="smtp_encryption" class="input-field">
                    <option value="tls" <?php echo ($editAccount['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo ($editAccount['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo ($editAccount['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Username</label>
                <input type="text" name="smtp_username" class="input-field" placeholder="user@gmail.com" value="<?php echo sanitize($editAccount['smtp_username'] ?? ''); ?>">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Password <?php echo $editAccount ? '(leave blank to keep)' : ''; ?></label>
                <input type="password" name="smtp_password" class="input-field" placeholder="••••••••">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Timeout (sec)</label>
                <input type="number" name="smtp_timeout" class="input-field" value="<?php echo $editAccount['smtp_timeout'] ?? 30; ?>">
            </div>
        </div>
    </div>
    
    <!-- Incoming Settings -->
    <div class="border-t border-white/5 pt-6">
        <div class="flex items-center gap-3 mb-4">
            <input type="checkbox" name="incoming_enabled" id="incoming_enabled" class="w-4 h-4 accent-orange-500" <?php echo ($editAccount['incoming_enabled'] ?? 0) ? 'checked' : ''; ?> onchange="document.getElementById('incomingFields').style.display=this.checked?'block':'none'">
            <label for="incoming_enabled" class="text-lg font-black tracking-tight"><i class="fa-solid fa-inbox mr-2 text-blue-500"></i>Enable Incoming Mail (IMAP/POP3)</label>
        </div>
        <div id="incomingFields" style="display:<?php echo ($editAccount['incoming_enabled'] ?? 0) ? 'block' : 'none'; ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Type</label>
                    <select name="incoming_type" class="input-field">
                        <option value="imap" <?php echo ($editAccount['incoming_type'] ?? 'imap') === 'imap' ? 'selected' : ''; ?>>IMAP</option>
                        <option value="pop3" <?php echo ($editAccount['incoming_type'] ?? '') === 'pop3' ? 'selected' : ''; ?>>POP3</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Host</label>
                    <input type="text" name="incoming_host" class="input-field" placeholder="imap.gmail.com" value="<?php echo sanitize($editAccount['incoming_host'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Port</label>
                    <input type="number" name="incoming_port" class="input-field" value="<?php echo $editAccount['incoming_port'] ?? 993; ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Encryption</label>
                    <select name="incoming_encryption" class="input-field">
                        <option value="tls" <?php echo ($editAccount['incoming_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($editAccount['incoming_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?php echo ($editAccount['incoming_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Username</label>
                    <input type="text" name="incoming_username" class="input-field" value="<?php echo sanitize($editAccount['incoming_username'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Password <?php echo $editAccount ? '(leave blank to keep)' : ''; ?></label>
                    <input type="password" name="incoming_password" class="input-field" placeholder="••••••••">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">IMAP Folder</label>
                    <input type="text" name="imap_folder" class="input-field" value="<?php echo sanitize($editAccount['imap_folder'] ?? 'INBOX'); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <div class="flex gap-4 pt-4">
        <button type="submit" class="btn-primary px-8"><i class="fa-solid fa-save mr-2"></i><?php echo $editAccount ? 'Update' : 'Create'; ?> Account</button>
        <a href="email-accounts.php" class="btn-primary !bg-white/5 hover:!bg-white/10">Cancel</a>
    </div>
</form>

<?php else: ?>
<!-- Accounts List -->
<?php if (empty($accounts)): ?>
<div class="card p-12 text-center">
    <div class="w-16 h-16 bg-orange-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-envelope text-orange-500 text-2xl"></i>
    </div>
    <h3 class="text-xl font-bold mb-2">No Email Accounts</h3>
    <p class="text-white/40 text-sm mb-6">Add your first email account to start sending emails.</p>
    <a href="?add=1" class="btn-primary">Add Email Account</a>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($accounts as $acc): ?>
    <div class="card p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 mb-1">
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md <?php echo $acc['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>">
                    <?php echo $acc['is_active'] ? 'Active' : 'Disabled'; ?>
                </span>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md bg-orange-500/20 text-orange-400"><?php echo sanitize($acc['label']); ?></span>
                <?php if ($acc['incoming_enabled']): ?>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md bg-blue-500/20 text-blue-400"><?php echo strtoupper($acc['incoming_type']); ?></span>
                <?php endif; ?>
            </div>
            <h3 class="font-bold text-lg truncate"><?php echo sanitize($acc['from_name'] ?: $acc['email_address']); ?></h3>
            <p class="text-white/40 text-sm"><?php echo sanitize($acc['email_address']); ?> → <?php echo sanitize($acc['smtp_host']); ?>:<?php echo $acc['smtp_port']; ?></p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <!-- Test SMTP -->
            <form method="POST" class="inline-flex items-center gap-2">
                <input type="hidden" name="action" value="test_smtp">
                <input type="hidden" name="test_account_id" value="<?php echo $acc['id']; ?>">
                <input type="email" name="test_email" placeholder="test@email.com" class="input-field !py-2 !text-xs w-40">
                <button type="submit" class="px-3 py-2 bg-green-500/10 text-green-400 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-green-500/20 transition whitespace-nowrap">Test SMTP</button>
            </form>
            <?php if ($acc['incoming_enabled']): ?>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="test_incoming">
                <input type="hidden" name="test_account_id" value="<?php echo $acc['id']; ?>">
                <button type="submit" class="px-3 py-2 bg-blue-500/10 text-blue-400 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-blue-500/20 transition whitespace-nowrap">Test <?php echo strtoupper($acc['incoming_type']); ?></button>
            </form>
            <?php endif; ?>
            <!-- Toggle -->
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="toggle_id" value="<?php echo $acc['id']; ?>">
                <button type="submit" class="px-3 py-2 bg-white/5 text-white/40 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-white/10 transition">
                    <?php echo $acc['is_active'] ? 'Disable' : 'Enable'; ?>
                </button>
            </form>
            <!-- Edit -->
            <a href="?edit=<?php echo $acc['id']; ?>" class="px-3 py-2 bg-white/5 text-white/40 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-white/10 transition">Edit</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
