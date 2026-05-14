<?php
$pageTitle = 'Compose Email';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email_engine.php';

$pdo = getDB();
$success = '';
$error = '';

// Get active accounts
$accounts = $pdo->query("SELECT * FROM email_accounts WHERE is_active = 1 ORDER BY label ASC")->fetchAll();
$templates = $pdo->query("SELECT template_key, template_name, subject, html_body, text_body FROM email_templates WHERE is_active = 1 ORDER BY template_name ASC")->fetchAll();

// Get default from account via routing rule
$defaultAccountId = null;
try {
    $stmt = $pdo->prepare("SELECT email_account_id FROM email_routing_rules WHERE feature_key = 'manual_composer_default_from' AND is_enabled = 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) $defaultAccountId = $row['email_account_id'];
} catch (Exception $e) {}

// Handle send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_email') {
    $accountId = (int)($_POST['from_account'] ?? 0);
    $to = trim($_POST['to'] ?? '');
    $cc = trim($_POST['cc'] ?? '');
    $bcc = trim($_POST['bcc'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $htmlBody = $_POST['html_body'] ?? '';
    $textBody = trim($_POST['text_body'] ?? '');
    
    if (empty($accountId) || empty($to) || empty($subject)) {
        $error = 'From account, To, and Subject are required.';
    } else {
        $result = sendEmailViaAccount($accountId, $to, $subject, $htmlBody, $textBody, 'manual_compose', $cc, $bcc);
        if ($result['success']) {
            $success = 'Email sent successfully!';
            logAudit('email_composed', 'email', null, "To: $to | Subject: $subject");
        } else {
            $error = 'Send failed: ' . $result['message'];
        }
    }
}
?>

<div class="flex items-center justify-between mb-10">
    <div>
        <h1 class="text-3xl font-black tracking-tighter">Compose Email</h1>
        <p class="text-white/30 text-sm mt-1">Send an email manually using your configured accounts</p>
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
    <p class="text-white/40 mb-4">No active email accounts. Add one first.</p>
    <a href="email-accounts.php?add=1" class="btn-primary">Add Email Account</a>
</div>
<?php else: ?>
<form method="POST" class="card p-8 space-y-6">
    <input type="hidden" name="action" value="send_email">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">From *</label>
            <select name="from_account" class="input-field" required>
                <option value="">— Select Account —</option>
                <?php foreach ($accounts as $acc): ?>
                <option value="<?php echo $acc['id']; ?>" <?php echo $acc['id'] == $defaultAccountId ? 'selected' : ''; ?>>
                    <?php echo sanitize($acc['from_name'] ? $acc['from_name'] . ' <' . $acc['email_address'] . '>' : $acc['email_address']); ?>
                    (<?php echo sanitize($acc['label']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Template (optional)</label>
            <select id="templateSelect" class="input-field" onchange="loadTemplate(this.value)">
                <option value="">— No Template —</option>
                <?php foreach ($templates as $tpl): ?>
                <option value="<?php echo htmlspecialchars(json_encode($tpl), ENT_QUOTES); ?>"><?php echo sanitize($tpl['template_name'] ?: $tpl['template_key']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">To *</label>
        <input type="text" name="to" class="input-field" placeholder="recipient@example.com (comma-separated for multiple)" required value="<?php echo sanitize($_POST['to'] ?? ''); ?>">
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">CC</label>
            <input type="text" name="cc" class="input-field" placeholder="cc@example.com" value="<?php echo sanitize($_POST['cc'] ?? ''); ?>">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">BCC</label>
            <input type="text" name="bcc" class="input-field" placeholder="bcc@example.com" value="<?php echo sanitize($_POST['bcc'] ?? ''); ?>">
        </div>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Subject *</label>
        <input type="text" name="subject" id="composeSubject" class="input-field" placeholder="Email subject" required value="<?php echo sanitize($_POST['subject'] ?? ''); ?>">
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">HTML Body</label>
        <textarea name="html_body" id="composeHtml" class="input-field font-mono text-xs" rows="14" placeholder="<div>Your email content here...</div>"><?php echo htmlspecialchars($_POST['html_body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Text Body (optional fallback)</label>
        <textarea name="text_body" id="composeText" class="input-field font-mono text-xs" rows="4" placeholder="Plain text version..."><?php echo htmlspecialchars($_POST['text_body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    
    <div class="flex gap-4 pt-4">
        <button type="submit" class="btn-primary px-8"><i class="fa-solid fa-paper-plane mr-2"></i>Send Email</button>
        <button type="button" onclick="document.getElementById('previewFrame2').srcdoc=document.getElementById('composeHtml').value;document.getElementById('previewModal2').classList.remove('hidden')" class="btn-primary !bg-blue-500/20 !text-blue-400 px-6"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
    </div>
</form>

<!-- Preview Modal -->
<div id="previewModal2" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6">
    <div class="bg-[#111] border border-white/10 rounded-3xl w-full max-w-3xl max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-white/5">
            <h3 class="font-bold text-sm">Email Preview</h3>
            <button onclick="document.getElementById('previewModal2').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
        </div>
        <iframe id="previewFrame2" class="w-full h-[60vh] bg-white"></iframe>
    </div>
</div>

<script>
function loadTemplate(jsonStr) {
    if (!jsonStr) return;
    try {
        const tpl = JSON.parse(jsonStr);
        document.getElementById('composeSubject').value = tpl.subject || '';
        document.getElementById('composeHtml').value = tpl.html_body || '';
        document.getElementById('composeText').value = tpl.text_body || '';
    } catch(e) {}
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
