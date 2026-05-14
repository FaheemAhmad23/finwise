<?php
$pageTitle = 'Manage Users';
require_once __DIR__ . '/includes/admin_header.php';

// Only main admin can access this page
if ($_SESSION['user_role'] !== 'admin') {
    redirect('/admin/');
}

$success = '';
$error = '';

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    
    // Add User
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            $hashed = custom_hash($password);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$email, $hashed, $role]);
                $success = 'User added successfully.';
            } catch (Exception $e) {
                $error = 'Error adding user: ' . $e->getMessage();
            }
        }
    }
    
    // Toggle Status
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['user_id'];
        $status = (int)$_POST['status'];
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $success = 'User status updated.';
    }

    // Toggle Moderator Teams Access
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_teams_access') {
        $access = $_POST['access'] === '1' ? '1' : '0';
        updateSetting('moderator_teams_access', $access);
        $success = 'Moderator teams access updated.';
    }
    
    // Delete User
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'User deleted successfully.';
    }
}

$pdo = getDB();
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$modTeamsAccess = getSetting('moderator_teams_access', '1');
?>

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-bold mb-2">Manage Users</h1>
            <p class="text-white/50">Add and manage moderators for your website.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert-success mb-6"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-error mb-6"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add User Form -->
        <div class="lg:col-span-1">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6">Add New User</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="block text-sm text-white/50 mb-2">Email Address</label>
                        <input type="email" name="email" required class="input-field" placeholder="moderator@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-white/50 mb-2">Password</label>
                        <input type="password" name="password" required class="input-field" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-sm text-white/50 mb-2">Role</label>
                        <select name="role" class="input-field">
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full">Add User</button>
                </form>
            </div>

            <!-- Global Settings -->
            <div class="card p-6 mt-8">
                <h2 class="text-xl font-bold mb-6">Moderator Permissions</h2>
                <form method="POST" class="flex items-center justify-between">
                    <input type="hidden" name="action" value="toggle_teams_access">
                    <input type="hidden" name="access" value="<?php echo $modTeamsAccess === '1' ? '0' : '1'; ?>">
                    <span class="text-sm text-white/70">Allow Moderators to Edit Teams</span>
                    <button type="submit" class="w-12 h-6 rounded-full transition-colors relative <?php echo $modTeamsAccess === '1' ? 'bg-orange-500' : 'bg-white/10'; ?>">
                        <div class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all <?php echo $modTeamsAccess === '1' ? 'left-7' : 'left-1'; ?>"></div>
                    </button>
                </form>
                <p class="text-[10px] text-white/30 mt-4 uppercase font-bold tracking-widest">When OFF, moderators cannot access the Team Links page.</p>
            </div>
        </div>

        <!-- Users List -->
        <div class="lg:col-span-2">
            <div class="card p-6">
                <h2 class="text-xl font-bold mb-6">Existing Users</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-white/50 text-sm border-b border-white/10">
                                <th class="pb-4">User</th>
                                <th class="pb-4">Role</th>
                                <th class="pb-4">Status</th>
                                <th class="pb-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="border-b border-white/5">
                                <td class="py-4">
                                    <p class="font-medium"><?php echo sanitize($user['email']); ?></p>
                                    <p class="text-[10px] text-white/30 uppercase"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                </td>
                                <td class="py-4">
                                    <span class="px-2 py-1 rounded-lg text-xs font-bold uppercase <?php echo $user['role'] === 'admin' ? 'bg-purple-500/20 text-purple-500' : 'bg-blue-500/20 text-blue-500'; ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $user['status'] ? '0' : '1'; ?>">
                                        <button type="submit" class="text-xs font-bold uppercase <?php echo $user['status'] ? 'text-green-500' : 'text-red-500'; ?>">
                                            <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="py-4 text-right">
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-400">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-white/30">No moderators added yet.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
