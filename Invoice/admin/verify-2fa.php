<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if we're in 2FA pending state
if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
    redirect('/admin/login.php');
}

$error = '';

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['totp_code']) ? trim($_POST['totp_code']) : '';
    $secret = $_SESSION['2fa_secret'] ?? '';
    
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif (verifyTOTP($secret, $code)) {
        // 2FA verified successfully
        $_SESSION['admin_logged_in'] = true;
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['2fa_secret']);
        
        // Register session and log
        $userId = $_SESSION['user_id'];
        registerSession($userId);
        session_regenerate_id(true);
        
        logAuditAction('login', 'user', $userId, '2FA verified successfully');
        
        redirect('/admin/');
    } else {
        $error = 'Invalid verification code. Please try again.';
        logAuditAction('login_failed', 'user', $_SESSION['user_id'] ?? 0, '2FA verification failed');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification | MFA Tools Admin</title>
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

        <!-- Verification Card -->
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-10">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-orange-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fa-solid fa-shield-halved text-orange-500 text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Two-Factor Verification</h1>
                <p class="text-white/50">Enter the 6-digit code from your authenticator app</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-6">
                <i class="fa-solid fa-circle-exclamation mr-2"></i> <?php echo sanitize($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-4 text-white text-center text-3xl tracking-[0.5em] font-mono placeholder-white/30 focus:border-orange-500 focus:outline-none transition"
                           placeholder="000000" autocomplete="off" autofocus>
                </div>

                <button type="submit" class="w-full bg-white text-black font-bold py-4 rounded-xl hover:bg-orange-500 hover:text-white transition-all">
                    Verify
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="/admin/logout.php" class="text-white/30 text-sm hover:text-white transition">
                    <i class="fa-solid fa-arrow-left mr-2"></i> Cancel and logout
                </a>
            </div>
        </div>

        <p class="text-center text-white/30 text-sm mt-8">
            Open your authenticator app to get the code
        </p>
    </div>

</body>
</html>
