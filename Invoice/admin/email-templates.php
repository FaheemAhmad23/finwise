<?php
$pageTitle = 'Email Templates';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/email_engine.php';

$pdo = getDB();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_template') {
        $id = (int)($_POST['id'] ?? 0);
        $template_key = trim($_POST['template_key'] ?? '');
        $template_name = trim($_POST['template_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $html_body = $_POST['html_body'] ?? '';
        $text_body = trim($_POST['text_body'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($template_key) || empty($subject) || empty($html_body)) {
            $error = 'Template key, subject, and HTML body are required.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE email_templates SET template_key=?, template_name=?, subject=?, html_body=?, text_body=?, is_active=? WHERE id=?");
                    $stmt->execute([$template_key, $template_name, $subject, $html_body, $text_body ?: null, $is_active, $id]);
                    $success = 'Template updated.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO email_templates (template_key, template_name, subject, html_body, text_body, is_active) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$template_key, $template_name, $subject, $html_body, $text_body ?: null, $is_active]);
                    $success = 'Template created.';
                }
                logAudit('email_template_saved', 'email_template', $id ?: $pdo->lastInsertId(), "Saved: $template_key");
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
    
    if ($action === 'test_template') {
        $templateKey = trim($_POST['template_key'] ?? '');
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!empty($templateKey) && !empty($testEmail)) {
            $vars = ['name'=>'Test User','email'=>$testEmail,'phone'=>'+1234567890','reason'=>'Test','message'=>'This is a test message.','site_name'=>SITE_NAME,'affiliate_name'=>'Test Affiliate','affiliate_code'=>'TESTCODE','affiliate_link'=>getSiteBaseUrl().'/?ref=TESTCODE','order_id'=>'MFA-999999','amount'=>'49.99'];
            $rendered = renderEmailTemplate($templateKey, $vars);
            if ($rendered) {
                $accounts = getActiveEmailAccounts();
                if (!empty($accounts)) {
                    $result = sendEmailViaAccount($accounts[0]['id'], $testEmail, $rendered['subject'], $rendered['html'], $rendered['text'], 'template_test');
                    if ($result['success']) $success = 'Test email sent to ' . $testEmail;
                    else $error = $result['message'];
                } else {
                    $error = 'No active email accounts to send from.';
                }
            } else {
                $error = 'Template not found or inactive.';
            }
        } else {
            $error = 'Template key and test email required.';
        }
    }
    
    if ($action === 'delete_template') {
        $id = (int)($_POST['delete_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM email_templates WHERE id = ?")->execute([$id]);
            $success = 'Template deleted.';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get templates
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY template_key ASC")->fetchAll();

// Check if editing
$editTemplate = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$editId]);
    $editTemplate = $stmt->fetch();
}
$showForm = isset($_GET['add']) || $editTemplate;
?>

<div class="flex items-center justify-between mb-10">
    <div>
        <h1 class="text-3xl font-black tracking-tighter">Email Templates</h1>
        <p class="text-white/30 text-sm mt-1">Manage email templates with variable support</p>
    </div>
    <?php if (!$showForm): ?>
    <a href="?add=1" class="btn-primary gap-2"><i class="fa-solid fa-plus"></i> New Template</a>
    <?php else: ?>
    <a href="email-templates.php" class="btn-primary gap-2 !bg-white/10"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert-success mb-6"><i class="fa-solid fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error mb-6"><i class="fa-solid fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
<?php endif; ?>

<?php if ($showForm): ?>
<form method="POST" class="card p-8 space-y-6">
    <input type="hidden" name="action" value="save_template">
    <input type="hidden" name="id" value="<?php echo $editTemplate['id'] ?? 0; ?>">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Template Key *</label>
            <input type="text" name="template_key" class="input-field" placeholder="e.g. support_admin_notification" value="<?php echo sanitize($editTemplate['template_key'] ?? ''); ?>" required>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Template Name</label>
            <input type="text" name="template_name" class="input-field" placeholder="e.g. New Support Message" value="<?php echo sanitize($editTemplate['template_name'] ?? ''); ?>">
        </div>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Subject *</label>
        <input type="text" name="subject" class="input-field" placeholder="e.g. New message from {name}" value="<?php echo sanitize($editTemplate['subject'] ?? ''); ?>" required>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">HTML Body *</label>
        <textarea name="html_body" class="input-field font-mono text-xs" rows="14" required placeholder="<div>Your HTML email body here...</div>"><?php echo htmlspecialchars($editTemplate['html_body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    
    <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest opacity-40 mb-2">Text Body (optional)</label>
        <textarea name="text_body" class="input-field font-mono text-xs" rows="6" placeholder="Plain text version..."><?php echo htmlspecialchars($editTemplate['text_body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    
    <div class="flex items-center gap-3">
        <input type="checkbox" name="is_active" id="tpl_active" class="w-4 h-4 accent-orange-500" <?php echo ($editTemplate['is_active'] ?? 1) ? 'checked' : ''; ?>>
        <label for="tpl_active" class="text-sm font-bold">Active</label>
    </div>
    
    <!-- Variable Reference -->
    <div class="bg-white/5 rounded-xl p-4">
        <h4 class="text-xs font-bold mb-2 text-orange-400"><i class="fa-solid fa-code mr-1"></i>Available Variables</h4>
        <div class="flex flex-wrap gap-2">
            <?php foreach (['{site_name}','{site_url}','{logo_url}','{year}','{customer_name}','{name}','{email}','{phone}','{message}','{reason}','{order_id}','{order_items}','{order_amount}','{amount}','{whatsapp_number}','{whatsapp_number_clean}','{support_email}','{affiliate_name}','{affiliate_email}','{affiliate_code}','{affiliate_link}','{temporary_password}','{dashboard_url}','{payout_amount}','{payout_method}','{pending_balance}','{transaction_ref}','{reset_url}','{created_at}'] as $v): ?>
            <code class="px-2 py-1 bg-white/5 rounded text-[10px] text-green-400"><?php echo $v; ?></code>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="flex gap-4">
        <button type="submit" class="btn-primary px-8"><i class="fa-solid fa-save mr-2"></i><?php echo $editTemplate ? 'Update' : 'Create'; ?></button>
        <?php if ($editTemplate): ?>
        <button type="button" onclick="document.getElementById('previewFrame').srcdoc=document.querySelector('[name=html_body]').value;document.getElementById('previewModal').classList.remove('hidden')" class="btn-primary !bg-blue-500/20 !text-blue-400 px-6"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
        <?php endif; ?>
    </div>
</form>

<!-- Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6">
    <div class="bg-[#111] border border-white/10 rounded-3xl w-full max-w-3xl max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-white/5">
            <h3 class="font-bold text-sm">Template Preview</h3>
            <button onclick="document.getElementById('previewModal').classList.add('hidden')" class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
        </div>
        <iframe id="previewFrame" class="w-full h-[60vh] bg-white"></iframe>
    </div>
</div>

<?php else: ?>
<?php if (empty($templates)): ?>
<div class="card p-12 text-center">
    <h3 class="text-xl font-bold mb-2">No Templates</h3>
    <p class="text-white/40 text-sm mb-6">Create your first email template.</p>
    <a href="?add=1" class="btn-primary">Create Template</a>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($templates as $tpl): ?>
    <div class="card p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-md <?php echo $tpl['is_active'] ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'; ?>"><?php echo $tpl['is_active'] ? 'Active' : 'Inactive'; ?></span>
                <code class="text-[10px] text-orange-400 bg-orange-500/10 px-2 py-0.5 rounded"><?php echo sanitize($tpl['template_key']); ?></code>
            </div>
            <h3 class="font-bold"><?php echo sanitize($tpl['template_name'] ?: $tpl['template_key']); ?></h3>
            <p class="text-white/30 text-xs mt-1">Subject: <?php echo sanitize($tpl['subject']); ?></p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <form method="POST" class="inline-flex items-center gap-2">
                <input type="hidden" name="action" value="test_template">
                <input type="hidden" name="template_key" value="<?php echo sanitize($tpl['template_key']); ?>">
                <input type="email" name="test_email" placeholder="test@email.com" class="input-field !py-2 !text-xs w-36">
                <button type="submit" class="px-3 py-2 bg-green-500/10 text-green-400 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-green-500/20 transition whitespace-nowrap">Test</button>
            </form>
            <a href="?edit=<?php echo $tpl['id']; ?>" class="px-3 py-2 bg-white/5 text-white/40 rounded-lg text-[10px] font-bold uppercase tracking-wider hover:bg-white/10 transition">Edit</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
