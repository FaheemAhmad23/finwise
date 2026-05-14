<?php
$pageTitle = 'Audit Logs';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

// Only admins can view audit logs
if ($_SESSION['user_role'] !== 'admin') {
    redirect('/admin/');
}

// Perform cleanup on page load (lazy cleanup)
$cleanedCount = cleanupAuditLogs();

// Handle manual cleanup
if (isset($_GET['cleanup'])) {
    $cleanedCount = cleanupAuditLogs();
    $success = "Cleaned up $cleanedCount old log entries.";
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$filterAction = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';
$filterUser = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';

// Get logs
try {
    $pdo = getDB();
    
    // Build query with filters
    $where = [];
    $params = [];
    
    if (!empty($filterAction)) {
        $where[] = "action LIKE ?";
        $params[] = "%$filterAction%";
    }
    
    if (!empty($filterUser)) {
        $where[] = "(user_email LIKE ? OR user_id = ?)";
        $params[] = "%$filterUser%";
        $params[] = is_numeric($filterUser) ? (int)$filterUser : 0;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereClause");
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $perPage);
    
    // Get logs
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $pdo->prepare("SELECT * FROM audit_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get unique actions for filter
    $actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $logs = [];
    $totalPages = 1;
    $actions = [];
}

// Action type labels and colors
$actionLabels = [
    'login' => ['label' => 'Login', 'color' => 'green'],
    'logout' => ['label' => 'Logout', 'color' => 'gray'],
    'login_failed' => ['label' => 'Failed Login', 'color' => 'red'],
    'post_create' => ['label' => 'Post Created', 'color' => 'blue'],
    'post_update' => ['label' => 'Post Updated', 'color' => 'blue'],
    'post_delete' => ['label' => 'Post Deleted', 'color' => 'red'],
    'media_upload' => ['label' => 'Media Upload', 'color' => 'purple'],
    'media_delete' => ['label' => 'Media Delete', 'color' => 'red'],
    'settings_update' => ['label' => 'Settings Updated', 'color' => 'orange'],
    'user_create' => ['label' => 'User Created', 'color' => 'green'],
    'user_update' => ['label' => 'User Updated', 'color' => 'blue'],
    'user_delete' => ['label' => 'User Deleted', 'color' => 'red'],
    'popup_create' => ['label' => 'Popup Created', 'color' => 'purple'],
    'popup_update' => ['label' => 'Popup Updated', 'color' => 'purple'],
    'popup_delete' => ['label' => 'Popup Deleted', 'color' => 'red'],
    '2fa_enabled' => ['label' => '2FA Enabled', 'color' => 'green'],
    '2fa_disabled' => ['label' => '2FA Disabled', 'color' => 'orange'],
    'session_logout_all' => ['label' => 'All Sessions Logged Out', 'color' => 'orange'],
    'message_status' => ['label' => 'Message Status Changed', 'color' => 'blue'],
    'message_archive' => ['label' => 'Message Archived', 'color' => 'gray'],
    'message_pin' => ['label' => 'Message Pinned', 'color' => 'yellow'],
];

function getActionInfo($action) {
    global $actionLabels;
    return $actionLabels[$action] ?? ['label' => ucwords(str_replace('_', ' ', $action)), 'color' => 'gray'];
}
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-10">
        <div>
            <h1 class="text-3xl font-bold mb-2">Audit Logs</h1>
            <p class="text-white/50">Track admin actions and security events. Logs older than 15 days are automatically deleted.</p>
        </div>
        <a href="?cleanup=1" class="btn-secondary" onclick="return confirm('Clean up old logs now?')">
            <i class="fa-solid fa-broom mr-2"></i> Manual Cleanup
        </a>
    </div>
    
    <?php if (isset($success)): ?>
    <div class="alert-success mb-6">
        <i class="fa-solid fa-check-circle mr-2"></i> <?php echo sanitize($success); ?>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-white/50 text-sm mb-2">Filter by Action</label>
                <select name="action_filter" class="input-field">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                    <option value="<?php echo sanitize($action); ?>" <?php echo $filterAction === $action ? 'selected' : ''; ?>>
                        <?php echo getActionInfo($action)['label']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-white/50 text-sm mb-2">Filter by User</label>
                <input type="text" name="user_filter" value="<?php echo sanitize($filterUser); ?>" 
                       class="input-field" placeholder="Email or User ID">
            </div>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-filter mr-2"></i> Filter
            </button>
            <?php if (!empty($filterAction) || !empty($filterUser)): ?>
            <a href="audit-logs.php" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Logs Table -->
    <div class="card p-6">
        <?php if (empty($logs)): ?>
        <div class="text-center py-16">
            <i class="fa-solid fa-clipboard-list text-6xl text-white/10 mb-6"></i>
            <h3 class="text-xl font-bold mb-2">No Audit Logs</h3>
            <p class="text-white/50">Admin actions will be logged here.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-white/50 text-sm border-b border-white/10">
                        <th class="pb-4 pr-4">Action</th>
                        <th class="pb-4 pr-4">User</th>
                        <th class="pb-4 pr-4">Details</th>
                        <th class="pb-4 pr-4">IP Address</th>
                        <th class="pb-4">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $actionInfo = getActionInfo($log['action']);
                        $colorClasses = [
                            'green' => 'bg-green-500/20 text-green-500',
                            'red' => 'bg-red-500/20 text-red-500',
                            'blue' => 'bg-blue-500/20 text-blue-500',
                            'orange' => 'bg-orange-500/20 text-orange-500',
                            'purple' => 'bg-purple-500/20 text-purple-500',
                            'yellow' => 'bg-yellow-500/20 text-yellow-500',
                            'gray' => 'bg-white/10 text-white/50',
                        ];
                        $colorClass = $colorClasses[$actionInfo['color']] ?? $colorClasses['gray'];
                    ?>
                    <tr class="border-b border-white/5 hover:bg-white/5">
                        <td class="py-4 pr-4">
                            <span class="px-3 py-1 <?php echo $colorClass; ?> text-xs font-bold rounded-lg">
                                <?php echo $actionInfo['label']; ?>
                            </span>
                        </td>
                        <td class="py-4 pr-4">
                            <p class="text-sm"><?php echo sanitize($log['user_email'] ?? 'Unknown'); ?></p>
                            <p class="text-xs text-white/30">ID: <?php echo $log['user_id']; ?></p>
                        </td>
                        <td class="py-4 pr-4">
                            <p class="text-sm text-white/70 max-w-xs truncate" title="<?php echo sanitize($log['details'] ?? ''); ?>">
                                <?php echo sanitize($log['details'] ?? '-'); ?>
                            </p>
                            <?php if ($log['target_type']): ?>
                            <p class="text-xs text-white/30"><?php echo sanitize($log['target_type']); ?> #<?php echo $log['target_id']; ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 pr-4 text-white/50 text-sm font-mono">
                            <?php echo sanitize($log['ip_address']); ?>
                        </td>
                        <td class="py-4 text-white/50 text-sm">
                            <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center gap-2 mt-6">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>" 
               class="px-4 py-2 bg-white/5 rounded-lg hover:bg-white/10">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <span class="px-4 py-2 text-white/50">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </span>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&user_filter=<?php echo urlencode($filterUser); ?>" 
               class="px-4 py-2 bg-white/5 rounded-lg hover:bg-white/10">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <p class="text-center text-white/30 text-sm mt-4">
            Showing <?php echo count($logs); ?> of <?php echo number_format($totalLogs); ?> total logs
        </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
