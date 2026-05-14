<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';

// Check if already logged in
if (isLoggedIn()) {
    redirect('/admin/');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    try {
        $pdo = getDB();
        
        // 1. Check main admin from settings
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_email'");
        $stmt->execute();
        $storedEmail = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
        $stmt->execute();
        $storedHash = $stmt->fetchColumn();
        
        if ($email === $storedEmail && custom_verify($password, $storedHash)) {
            $_SESSION['admin_user'] = $email;
            $_SESSION['user_id'] = 0; // Main admin ID
            $_SESSION['user_role'] = 'admin';
            
            // Check if 2FA is enabled for main admin
            $totpEnabled = getSetting('admin_totp_enabled', '0') === '1';
            $totpSecret = getSetting('admin_totp_secret', '');
            
            if ($totpEnabled && !empty($totpSecret)) {
                // Redirect to 2FA verification
                $_SESSION['2fa_pending'] = true;
                $_SESSION['2fa_secret'] = $totpSecret;
                redirect('/admin/verify-2fa.php');
            } else {
                // No 2FA, complete login
                $_SESSION['admin_logged_in'] = true;
                registerSession(0);
                session_regenerate_id(true);
                logAuditAction('login', 'user', 0, 'Main admin login');
                redirect('/admin/');
            }
        }
        
        // 2. Check users table for moderators/other admins
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && custom_verify($password, $user['password'])) {
            $_SESSION['admin_user'] = $user['email'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            
            // Check if 2FA is enabled for this user
            if ($user['totp_enabled'] && !empty($user['totp_secret'])) {
                // Redirect to 2FA verification
                $_SESSION['2fa_pending'] = true;
                $_SESSION['2fa_secret'] = $user['totp_secret'];
                redirect('/admin/verify-2fa.php');
            } else {
                // No 2FA, complete login
                $_SESSION['admin_logged_in'] = true;
                registerSession($user['id']);
                session_regenerate_id(true);
                logAuditAction('login', 'user', $user['id'], 'User login: ' . $user['email']);
                redirect('/admin/');
            }
        } else {
            // Log failed login attempt
            logAuditAction('login_failed', 'user', null, 'Failed login attempt for: ' . $email);
            $error = 'Invalid email or password';
        }
    } catch (Exception $e) {
        $error = 'An error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | MFA Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #000 0%, #1a1a2e 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center p-6">
    
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="flex items-center justify-center gap-3 mb-12">
            <div class="px-4 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center font-bold text-white shadow-lg shadow-orange-500/20">MFA</div>
            <div class="flex items-center text-2xl font-bold tracking-tighter">
                <span class="text-white">TOOLS</span>
            </div>
        </div>

        <!-- Login Card -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-10">
            <h1 class="text-2xl font-bold text-white mb-2">Admin Login</h1>
            <p class="text-white/50 mb-8">Sign in to manage your website</p>

            <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-6">
                <i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo sanitize($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Email Address</label>
                    <input type="email" name="email" required 
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:border-orange-500 focus:outline-none transition"
                           placeholder="Enter email">
                </div>
                
                <div>
                    <label class="block text-white/50 text-sm mb-2">Password</label>
                    <input type="password" name="password" required 
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:border-orange-500 focus:outline-none transition"
                           placeholder="Enter password">
                </div>

                <button type="submit" class="w-full bg-white text-black font-bold py-4 rounded-xl hover:bg-orange-500 hover:text-white transition-all">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-white/30 text-sm mt-8">
            <a href="/" class="hover:text-white transition"><i class="fa-solid fa-arrow-left mr-2"></i> Back to Website</a>
        </p>
    </div>

</body>
</html>
