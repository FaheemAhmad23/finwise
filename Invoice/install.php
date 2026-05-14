<?php
// Define database credentials
$db_host = 'localhost';
$db_name = 'u740075025_d';
$db_user = 'u740075025_u';
$db_pass = 'Malik#2343';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Envoicing Installer</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; background: #f4f4f4; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #ff5f1f; padding-bottom: 10px; }
        .success { color: green; background: #e8f5e9; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .error { color: red; background: #ffebee; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .info { color: #004085; background: #cce5ff; padding: 10px; margin: 5px 0; border-radius: 4px; }
    </style>
</head>
<body>
<div class='container'>
<h1>Envoicing System Installation</h1>";

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✓ Connected to MySQL server successfully</div>";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='success'>✓ Database '$db_name' checked/created</div>";
    
    // Select database
    $pdo->exec("USE `$db_name`");
    
    // SQL commands to create core tables
    $commands = [
        // Users Table
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            totp_secret VARCHAR(32) DEFAULT NULL,
            totp_enabled TINYINT(1) DEFAULT 0,
            role VARCHAR(50) DEFAULT 'user',
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // Settings Table
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(255) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        // Audit Logs
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            user_email VARCHAR(255),
            action VARCHAR(50),
            target_type VARCHAR(50),
            target_id INT,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // User Sessions
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL UNIQUE,
            ip_address VARCHAR(45),
            user_agent TEXT,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
    ];
    
    // Execute table commands
    foreach ($commands as $sql) {
        $pdo->exec($sql);
    }
    echo "<div class='success'>✓ Core tables created successfully</div>";
    
    // Default Admin Settings
    $email = 'faheem@mfatools.net';
    $password = 'V9$kQ7!mR@2Zx#F4';
    $salt = "MFA_TOOLS_SECURE_SALT_2026_!@#";
    $passwordHash = password_hash($password . $salt, PASSWORD_DEFAULT);
    
    $defaultSettings = [
        'site_title' => 'Envoicing - Invoice & Client Management',
        'site_description' => 'Admin panel for managing invoices and clients.',
        'admin_email' => $email,
        'admin_password' => $passwordHash,
        'maintenance_mode' => '0',
        'admin_totp_enabled' => '0'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($defaultSettings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    echo "<div class='success'>✓ Default settings inserted</div>";

    // Success Message
    echo "<div class='info'>
            <h3>Installation Complete!</h3>
            <p>Database has been set up with core tables.</p>
            <p><strong>Admin Email:</strong> $email</p>
            <p><strong>Admin Password:</strong> $password</p>
            <p><strong>Next Step:</strong> Run <a href='migrate.php'>migrate.php</a> to create all remaining tables (clients, invoices, email system).</p>
            <p style='color:red;'>Warning: Please delete this install.php file immediately for security!</p>
            <a href='admin/'>Go to Admin Panel</a>
          </div>";
          
} catch(PDOException $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>
