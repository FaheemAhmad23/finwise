<?php
$pageTitle = 'Security Settings';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

$success = '';
$error = '';

// Get current user's 2FA status
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['admin_user'];

try {
    $pdo = getDB();
    
    // For main admin (user_id = 0), check settings table
    if ($userId == 0) {
        $totpEnabled = getSetting('admin_totp_enabled', '0') === '1';
        $totpSecret = getSetting('admin_totp_secret', '');
    } else {
        $stmt = $pdo->prepare("SELECT totp_enabled, totp_secret FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $totpEnabled = $user && $user['totp_enabled'] == 1;
        $totpSecret = $user ? $user['totp_secret'] : '';
    }
} catch (Exception $e) {
    $totpEnabled = false;
    $totpSecret = '';
}

// Handle 2FA setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'setup_2fa') {
        // Generate new secret
        $newSecret = generateTOTPSecret();
        $_SESSION['pending_totp_secret'] = $newSecret;
        $totpSecret = $newSecret;
        $showSetup = true;
    }
    
    elseif ($action === 'verify_2fa') {
        $code = $_POST['totp_code'] ?? '';
        $pendingSecret = $_SESSION['pending_totp_secret'] ?? '';
        
        if (empty($pendingSecret)) {
            $error = 'Setup session expired. Please try again.';
        } elseif (verifyTOTP($pendingSecret, $code)) {
            // Save the secret
            try {
                if ($userId == 0) {
                    updateSetting('admin_totp_secret', $pendingSecret);
                    updateSetting('admin_totp_enabled', '1');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
                    $stmt->execute([$pendingSecret, $userId]);
                }
                
                unset($_SESSION['pending_totp_secret']);
                $totpEnabled = true;
                $totpSecret = $pendingSecret;
                
                logAuditAction('2fa_enabled', 'user', $userId, 'Two-factor authentication enabled');
                $success = 'Two-factor authentication has been enabled successfully!';
            } catch (Exception $e) {
                $error = 'Failed to save 2FA settings.';
            }
        } else {
            $error = 'Invalid verification code. Please try again.';
            $showSetup = true;
            $totpSecret = $pendingSecret;
        }
    }
    
    elseif ($action === 'disable_2fa') {
        $code = $_POST['totp_code'] ?? '';
        
        if (verifyTOTP($totpSecret, $code)) {
            try {
                if ($userId == 0) {
                    updateSetting('admin_totp_enabled', '0');
                    updateSetting('admin_totp_secret', '');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
                    $stmt->execute([$userId]);
                }
                
                $totpEnabled = false;
                $totpSecret = '';
                
                logAuditAction('2fa_disabled', 'user', $userId, 'Two-factor authentication disabled');
                $success = 'Two-factor authentication has been disabled.';
            } catch (Exception $e) {
                $error = 'Failed to disable 2FA.';
            }
        } else {
            $error = 'Invalid verification code.';
        }
    }
}

$showSetup = $showSetup ?? false;
$pendingSecret = $_SESSION['pending_totp_secret'] ?? '';
if (!empty($pendingSecret)) {
    $showSetup = true;
    $totpSecret = $pendingSecret;
}
?>

<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="mb-10">
        <h1 class="text-3xl font-bold mb-2">Security Settings</h1>
        <p class="text-white/50">Manage your account security and two-factor authentication.</p>
    </div>
    
    <?php if ($success): ?>
    <div class="alert-success mb-6">
        <i class="fa-solid fa-check-circle mr-2"></i> <?php echo sanitize($success); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert-error mb-6">
        <i class="fa-solid fa-exclamation-circle mr-2"></i> <?php echo sanitize($error); ?>
    </div>
    <?php endif; ?>
    
    <!-- 2FA Status Card -->
    <div class="card p-8 mb-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-2xl <?php echo $totpEnabled ? 'bg-green-500/20' : 'bg-white/5'; ?> flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-2xl <?php echo $totpEnabled ? 'text-green-500' : 'text-white/30'; ?>"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold">Two-Factor Authentication</h2>
                <p class="text-sm <?php echo $totpEnabled ? 'text-green-500' : 'text-white/50'; ?>">
                    <?php echo $totpEnabled ? 'Enabled' : 'Not enabled'; ?>
                </p>
            </div>
        </div>
        
        <p class="text-white/60 mb-6">
            Two-factor authentication adds an extra layer of security to your account. When enabled, you'll need to enter a code from your authenticator app in addition to your password when logging in.
        </p>
        
        <?php if (!$totpEnabled && !$showSetup): ?>
        <!-- Enable 2FA Button -->
        <form method="POST">
            <input type="hidden" name="action" value="setup_2fa">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-lock mr-2"></i> Enable Two-Factor Authentication
            </button>
        </form>
        
        <?php elseif ($showSetup): ?>
        <!-- 2FA Setup -->
        <div class="bg-white/5 rounded-2xl p-6 mb-6">
            <h3 class="font-bold mb-4">Setup Instructions</h3>
            <ol class="list-decimal list-inside space-y-2 text-white/70 text-sm mb-6">
                <li>Install an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator</li>
                <li>Scan the QR code below or enter the secret key manually</li>
                <li>Enter the 6-digit code from your app to verify</li>
            </ol>
            
            <div class="flex flex-col md:flex-row gap-6 items-center mb-6">
                <!-- QR Code -->
                <div class="bg-white p-4 rounded-xl">
                    <img src="<?php echo getTOTPQRCodeUrl($totpSecret, $userEmail); ?>" alt="QR Code" class="w-48 h-48">
                </div>
                
                <!-- Manual Entry -->
                <div class="flex-1">
                    <p class="text-white/50 text-sm mb-2">Or enter this key manually:</p>
                    <div class="bg-black/50 rounded-xl p-4 font-mono text-lg tracking-widest break-all">
                        <?php echo chunk_split($totpSecret, 4, ' '); ?>
                    </div>
                    <p class="text-white/30 text-xs mt-2">Account: <?php echo sanitize($userEmail); ?></p>
                </div>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="verify_2fa">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Enter Verification Code</label>
                    <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}"
                           class="input-field text-center text-2xl tracking-widest font-mono" 
                           placeholder="000000" autocomplete="off">
                </div>
                <div class="flex gap-4">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fa-solid fa-check mr-2"></i> Verify & Enable
                    </button>
                    <a href="security.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        <!-- Disable 2FA -->
        <div class="bg-green-500/10 border border-green-500/20 rounded-2xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-4">
                <i class="fa-solid fa-check-circle text-green-500 text-xl"></i>
                <span class="font-bold text-green-500">Two-Factor Authentication is Active</span>
            </div>
            <p class="text-white/60 text-sm">Your account is protected with an additional layer of security.</p>
        </div>
        
        <div class="border-t border-white/10 pt-6">
            <h3 class="font-bold mb-4 text-red-400">Disable Two-Factor Authentication</h3>
            <p class="text-white/50 text-sm mb-4">
                Warning: Disabling 2FA will make your account less secure. You'll only need your password to log in.
            </p>
            
            <form method="POST" class="space-y-4" onsubmit="return confirm('Are you sure you want to disable 2FA?')">
                <input type="hidden" name="action" value="disable_2fa">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Enter Current 2FA Code to Confirm</label>
                    <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}"
                           class="input-field text-center text-xl tracking-widest font-mono max-w-xs" 
                           placeholder="000000" autocomplete="off">
                </div>
                <button type="submit" class="btn-danger">
                    <i class="fa-solid fa-unlock mr-2"></i> Disable 2FA
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Compatible Apps -->
    <div class="card p-8">
        <h2 class="text-xl font-bold mb-4">Compatible Authenticator Apps</h2>
        <p class="text-white/50 text-sm mb-6">Any TOTP-compatible authenticator app will work. Here are some popular options:</p>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white/5 rounded-xl p-4 text-center">
                <i class="fa-brands fa-google text-2xl mb-2 text-white/50"></i>
                <p class="text-sm">Google Authenticator</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 text-center">
                <i class="fa-brands fa-microsoft text-2xl mb-2 text-white/50"></i>
                <p class="text-sm">Microsoft Authenticator</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 text-center">
                <i class="fa-solid fa-key text-2xl mb-2 text-white/50"></i>
                <p class="text-sm">Authy</p>
            </div>
            <div class="bg-white/5 rounded-xl p-4 text-center">
                <i class="fa-solid fa-shield text-2xl mb-2 text-white/50"></i>
                <p class="text-sm">1Password</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
