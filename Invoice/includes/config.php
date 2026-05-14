<?php
/**
 * MFA Tools - Database Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u740075025_d');
define('DB_USER', 'u740075025_u');
define('DB_PASS', 'Malik#2343');

// Site Configuration
define('SITE_URL', ''); // Will be set dynamically or left empty for relative paths
define('SITE_NAME', 'MFA Tools');
define('SITE_TAGLINE', 'Free Kanwa Pro for Students');

// Admin Configuration
// Note: Admin credentials are now stored in the 'users' table.
// Default Admin: faheem@mfatools.net / V9$kQ7!mR@2Zx#F4 (initialized via install.php)

// Timezone
date_default_timezone_set('UTC');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection Function
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            // Force consistent collation to prevent "Illegal mix of collations" errors
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper Functions
function sanitize($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/admin/login.php');
    }
}

// Custom Hashing Functions
function custom_hash($password) {
    $salt = "MFA_TOOLS_SECURE_SALT_2026_!@#";
    $combined = $password . $salt;
    return password_hash($combined, PASSWORD_DEFAULT);
}

function custom_verify($password, $hash) {
    $salt = "MFA_TOOLS_SECURE_SALT_2026_!@#";
    $combined = $password . $salt;
    return password_verify($combined, $hash);
}

function getSetting($key, $default = '') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function updateSetting($key, $value) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}
?>
