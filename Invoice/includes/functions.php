<?php
/**
 * MFA Tools - Helper Functions
 * 
 * This file contains utility functions used throughout the application.
 */

if (!function_exists('logAudit')) {
    /**
     * Alias for logAuditAction for backward compatibility
     */
    function logAudit($action, $targetType = null, $targetId = null, $details = null) {
        return logAuditAction($action, $targetType, $targetId, $details);
    }
}

if (!function_exists('logAuditAction')) {
    /**
     * Log an action to the audit log
     * 
     * @param string $action The action performed
     * @param string $targetType The type of target (e.g., 'post', 'user', 'media')
     * @param int|null $targetId The ID of the target
     * @param string|null $details Additional details
     */
    function logAuditAction($action, $targetType = null, $targetId = null, $details = null) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, user_email, action, target_type, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'] ?? 0,
                $_SESSION['admin_user'] ?? 'system',
                $action,
                $targetType,
                $targetId,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
            ]);
        } catch (Exception $e) {
            // Silently fail - don't break the application if logging fails
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}

if (!function_exists('cleanupAuditLogs')) {
    /**
     * Delete audit logs older than 15 days
     */
    function cleanupAuditLogs() {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Audit cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('formatFileSize')) {
    /**
     * Format file size in human readable format
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

if (!function_exists('getClientIP')) {
    /**
     * Get the client's IP address
     * 
     * @return string IP address
     */
    function getClientIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('generateVisitorHash')) {
    /**
     * Generate an anonymous hash for visitor tracking
     * 
     * @return string Visitor hash
     */
    function generateVisitorHash() {
        $data = [
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            date('Y-m-d') // Include date to rotate hashes daily for privacy
        ];
        return hash('sha256', implode('|', $data));
    }
}

if (!function_exists('trackVisit')) {
    /**
     * Track a page visit (anonymous)
     * 
     * @param string $pageUrl The URL being visited
     */
    function trackVisit($pageUrl = null) {
        if ($pageUrl === null) {
            $pageUrl = $_SERVER['REQUEST_URI'] ?? '/';
        }
        
        try {
            $pdo = getDB();
            $visitorHash = generateVisitorHash();
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $deviceType = detectDevice();
            $browser = detectBrowser();
            
            // Insert visit record
            $stmt = $pdo->prepare("INSERT INTO visitor_analytics (visitor_hash, page_url, referrer, device_type, browser) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$visitorHash, $pageUrl, $referrer, $deviceType, $browser]);
            
        } catch (Exception $e) {
            // Silently fail
            error_log("Visit tracking error: " . $e->getMessage());
        }
    }
}

if (!function_exists('getActivePopups')) {
    /**
     * Get active popups for a specific page
     * 
     * @param string $currentPage The current page identifier
     * @return array Active popups
     */
    function getActivePopups($currentPage = null) {
        try {
            $pdo = getDB();
            $now = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("SELECT * FROM popups 
                                   WHERE is_enabled = 1 
                                   AND (start_datetime IS NULL OR start_datetime <= ?) 
                                   AND (end_datetime IS NULL OR end_datetime >= ?)
                                   ORDER BY id DESC");
            $stmt->execute([$now, $now]);
            $popups = $stmt->fetchAll();
            
            // Filter by target pages if specified
            if ($currentPage !== null) {
                $popups = array_filter($popups, function($popup) use ($currentPage) {
                    if (empty($popup['target_pages'])) {
                        return true; // Show on all pages if no target specified
                    }
                    // Support both JSON array and comma-separated formats
                    $targetPages = json_decode($popup['target_pages'], true);
                    if (!is_array($targetPages)) {
                        // Try comma-separated format
                        $targetPages = array_map('trim', explode(',', $popup['target_pages']));
                    }
                    if (empty($targetPages)) {
                        return true;
                    }
                    return in_array($currentPage, $targetPages) || in_array('all', $targetPages);
                });
            }
            
            return array_values($popups);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getGlobalLink')) {
    /**
     * Get a global link by key
     * 
     * @param string $key The link key
     * @param string $default Default URL if not found
     * @return string The link URL
     */
    function getGlobalLink($key, $default = '#') {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT link_url FROM global_links WHERE link_key = ? AND is_active = 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['link_url'] : getSetting($key, $default);
        } catch (Exception $e) {
            return getSetting($key, $default);
        }
    }
}

if (!function_exists('getPublishedPosts')) {
    /**
     * Get published posts (respecting scheduled publishing)
     * 
     * @param int $limit Maximum number of posts
     * @return array Published posts
     */
    function getPublishedPosts($limit = 10) {
        try {
            $pdo = getDB();
            $now = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("SELECT * FROM posts 
                                   WHERE status = 'published' 
                                   OR (status = 'scheduled' AND publish_at <= ?)
                                   ORDER BY COALESCE(publish_at, created_at) DESC 
                                   LIMIT ?");
            $stmt->execute([$now, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('isPostVisible')) {
    /**
     * Check if a post should be visible on the frontend
     * 
     * @param array $post The post data
     * @return bool Whether the post is visible
     */
    function isPostVisible($post) {
        if ($post['status'] === 'published') {
            return true;
        }
        if ($post['status'] === 'scheduled' && !empty($post['publish_at'])) {
            return strtotime($post['publish_at']) <= time();
        }
        return false;
    }
}

if (!function_exists('updateScheduledPosts')) {
    /**
     * Update scheduled posts to published status if their time has come
     */
    function updateScheduledPosts() {
        try {
            $pdo = getDB();
            $now = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("UPDATE posts SET status = 'published' 
                                   WHERE status = 'scheduled' AND publish_at <= ?");
            $stmt->execute([$now]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('registerSession')) {
    /**
     * Register a new session for tracking
     * 
     * @param int $userId The user ID
     */
    function registerSession($userId) {
        try {
            $pdo = getDB();
            $sessionId = session_id();
            $ip = getClientIP();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            
            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE last_activity = NOW(), ip_address = ?, user_agent = ?");
            $stmt->execute([$userId, $sessionId, $ip, $userAgent, $ip, $userAgent]);
        } catch (Exception $e) {
            error_log("Session registration error: " . $e->getMessage());
        }
    }
}

if (!function_exists('updateSessionActivity')) {
    /**
     * Update the last activity time for the current session
     */
    function updateSessionActivity() {
        try {
            $pdo = getDB();
            $sessionId = session_id();
            
            $stmt = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            // Silently fail
        }
    }
}

if (!function_exists('destroyUserSessions')) {
    /**
     * Destroy all sessions for a user
     * 
     * @param int $userId The user ID
     * @param string|null $exceptSessionId Session ID to keep active
     */
    function destroyUserSessions($userId, $exceptSessionId = null) {
        try {
            $pdo = getDB();
            
            if ($exceptSessionId) {
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
                $stmt->execute([$userId, $exceptSessionId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('checkSessionExpiry')) {
    /**
     * Check if the current session has expired due to inactivity
     * 
     * @param int $timeout Timeout in seconds (default 30 minutes)
     * @return bool Whether the session is still valid
     */
    function checkSessionExpiry($timeout = 36000) {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}

if (!function_exists('generateTOTPSecret')) {
    /**
     * Generate a random TOTP secret
     * 
     * @return string Base32 encoded secret
     */
    function generateTOTPSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
}

if (!function_exists('verifyTOTP')) {
    /**
     * Verify a TOTP code
     * 
     * @param string $secret The secret key
     * @param string $code The code to verify
     * @param int $window Time window for verification
     * @return bool Whether the code is valid
     */
    function verifyTOTP($secret, $code, $window = 1) {
        $timestamp = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            $expectedCode = generateTOTPCode($secret, $timestamp + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('generateTOTPCode')) {
    /**
     * Generate a TOTP code for a given timestamp
     * 
     * @param string $secret The secret key
     * @param int $timestamp The timestamp counter
     * @return string 6-digit code
     */
    function generateTOTPCode($secret, $timestamp) {
        // Decode base32 secret
        $secret = base32Decode($secret);
        
        // Pack timestamp as 64-bit big-endian
        $time = pack('N*', 0, $timestamp);
        
        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secret, true);
        
        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('base32Decode')) {
    /**
     * Decode a base32 string
     * 
     * @param string $input Base32 encoded string
     * @return string Decoded binary string
     */
    function base32Decode($input) {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);
        
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;
            
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        
        return $output;
    }
}

if (!function_exists('getTOTPQRCodeUrl')) {
    /**
     * Generate a QR code URL for TOTP setup
     * 
     * @param string $secret The secret key
     * @param string $email The user's email
     * @param string $issuer The issuer name
     * @return string QR code URL
     */
    function getTOTPQRCodeUrl($secret, $email, $issuer = 'MFA Tools') {
        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
        
        // Use Google Charts API for QR code generation
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauth);
    }
}

if (!function_exists('getGoogleVerificationMeta')) {
    /**
     * Get the Google Search Console verification meta tag
     * 
     * @return string The meta tag HTML or empty string
     */
    function getGoogleVerificationMeta() {
        $meta = getSetting('google_verification_meta', '');
        if (!empty($meta)) {
            // Sanitize to ensure it's a valid meta tag
            if (preg_match('/<meta\s+name=["\']google-site-verification["\']\s+content=["\'][^"\']+["\']\s*\/?>/i', $meta)) {
                return $meta;
            }
        }
        return '';
    }
}

if (!function_exists('getActivePopup')) {
    /**
     * Get a single active popup for a specific page
     * 
     * @param string $currentPage The current page identifier
     * @return array|null Active popup or null
     */
    function getActivePopup($currentPage = null) {
        $popups = getActivePopups($currentPage);
        return !empty($popups) ? $popups[0] : null;
    }
}

if (!function_exists('getButton')) {
    /**
     * Get a global button by key
     * 
     * @param string $key The button key
     * @return array|null Button data or null
     */
    function getButton($key) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM global_buttons WHERE button_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('detectDevice')) {
    /**
     * Detect device type from user agent
     * 
     * @return string Device type (desktop, mobile, tablet)
     */
    function detectDevice() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}

if (!function_exists('detectBrowser')) {
    /**
     * Detect browser from user agent
     * 
     * @return string Browser name
     */
    function detectBrowser() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Firefox/i', $ua)) return 'Firefox';
        if (preg_match('/Edge/i', $ua)) return 'Edge';
        if (preg_match('/Chrome/i', $ua)) return 'Chrome';
        if (preg_match('/Safari/i', $ua)) return 'Safari';
        if (preg_match('/Opera|OPR/i', $ua)) return 'Opera';
        if (preg_match('/MSIE|Trident/i', $ua)) return 'IE';
        return 'Other';
    }
}

if (!function_exists('generateMfaId')) {
    /**
     * Generate a unique MFA-XXXXXX order/invoice ID.
     * Format: MFA-{2-digit random}{4-digit sequential zero-padded}
     * 
     * @return string e.g. "MFA-421234"
     */
    function generateMfaId() {
        $pdo = getDB();
        // Insert to get next auto-increment
        $pdo->exec("INSERT INTO order_sequence VALUES (NULL)");
        $seqId = (int)$pdo->lastInsertId();
        $prefix = str_pad(random_int(10, 99), 2, '0', STR_PAD_LEFT);
        $suffix = str_pad($seqId, 4, '0', STR_PAD_LEFT);
        return 'MFA-' . $prefix . $suffix;
    }
}

if (!function_exists('getOrCreateClient')) {
    /**
     * Get or create a client record by email.
     * If a client with the given email already exists, updates name/whatsapp if newer.
     * 
     * @param string $name Client name
     * @param string $email Client email
     * @param string $whatsapp Client WhatsApp number
     * @return int Client ID
     */
    function getOrCreateClient($name, $email, $whatsapp = '') {
        $pdo = getDB();
        
        // Check if client exists
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update name and whatsapp if provided
            $upd = $pdo->prepare("UPDATE clients SET name = ?, whatsapp = CASE WHEN ? != '' THEN ? ELSE whatsapp END, updated_at = NOW() WHERE id = ?");
            $upd->execute([$name, $whatsapp, $whatsapp, $existing['id']]);
            return (int)$existing['id'];
        }
        
        // Create new client
        $stmt = $pdo->prepare("INSERT INTO clients (name, email, whatsapp) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $whatsapp]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('getWhatsAppNumber')) {
    /**
     * Get the global WhatsApp number from settings.
     * 
     * @return string WhatsApp number with + prefix
     */
    function getWhatsAppNumber() {
        return getSetting('website_whatsapp_number', '+447490916612');
    }
}

if (!function_exists('validateWhatsApp')) {
    /**
     * Validate a WhatsApp number.
     * Must start with +, followed by digits only, no spaces/letters.
     * 
     * @param string $number
     * @return bool
     */
    function validateWhatsApp($number) {
        if (empty($number)) return false;
        // Must start with +, then 7-15 digits
        return (bool)preg_match('/^\+[0-9]{7,15}$/', $number);
    }
}

if (!function_exists('calculateWarrantyExpiry')) {
    /**
     * Calculate warranty expiry date from start date and duration.
     * 
     * @param string $startDate Y-m-d format
     * @param string $durationType 'monthly', 'yearly', or 'custom_days'
     * @param int $durationValue Number of months/years/days
     * @return string Y-m-d expiry date
     */
    function calculateWarrantyExpiry($startDate, $durationType, $durationValue) {
        $date = new DateTime($startDate);
        switch ($durationType) {
            case 'monthly':
                $date->modify("+{$durationValue} months");
                break;
            case 'yearly':
                $date->modify("+{$durationValue} years");
                break;
            case 'custom_days':
                $date->modify("+{$durationValue} days");
                break;
        }
        return $date->format('Y-m-d');
    }
}
?>
