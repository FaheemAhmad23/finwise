<?php
/**
 * Envoicing - Database Migration Script
 * 
 * Creates all tables needed for invoicing, client management, and email system.
 * Run this file once after uploading to update the database schema.
 * DELETE THIS FILE after migration for security!
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Envoicing - Database Migration</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background: #000; color: #fff; }</style>
</head>
<body class='min-h-screen flex items-center justify-center p-6'>
<div class='max-w-3xl w-full bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-10'>";

echo "<h1 class='text-3xl font-bold mb-8 text-center'>Envoicing Database Migration</h1>";

try {
    $pdo = getDB();
    $errors = [];
    $successes = [];

    // =====================================================
    // 0. DROP ALL OLD/UNUSED TABLES
    // =====================================================
    $oldTables = [
        // Affiliate system (drop in dependency order)
        'affiliate_discount_products',
        'affiliate_discounts',
        'affiliate_login_attempts',
        'affiliate_payouts',
        'affiliate_order_attribution',
        'affiliate_clicks',
        'affiliate_commission_rules',
        'affiliate_users',
        'affiliate_accounts',
        'affiliate_form_responses',
        'affiliate_form_fields',
        'affiliate_applications',
        'affiliate_settings',
        // Commerce
        'coupon_usage',
        'coupons',
        'order_requirement_responses',
        'product_requirements',
        'products',
        'payment_methods',
        // Content
        'posts',
        'proofs',
        'testimonials',
        'media_usage',
        'media',
        // Free Tools
        'free_tool_accounts',
        'free_tools',
        // Communication & Analytics
        'contact_messages',
        'popups',
        'visitor_daily_stats',
        'visitor_analytics',
        'admin_notifications',
        'team_clicks',
        'global_links',
        'page_seo',
    ];

    foreach ($oldTables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        } catch (Exception $e) {
            // Ignore — table may have foreign key constraints, will be caught on next run
        }
    }
    $successes[] = "Dropped " . count($oldTables) . " legacy tables (if they existed)";

    // =====================================================
    // 1. USERS TABLE (base — should exist from install.php)
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `totp_secret` VARCHAR(32) DEFAULT NULL,
            `totp_enabled` TINYINT(1) DEFAULT 0,
            `role` VARCHAR(50) DEFAULT 'user',
            `status` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Users table created/verified";
    } catch (Exception $e) {
        $errors[] = "Users table: " . $e->getMessage();
    }

    // Add TOTP columns if missing
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(32) DEFAULT NULL AFTER `password`");
        $successes[] = "Users: Added totp_secret column";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $successes[] = "Users: totp_secret column already exists";
        }
    }
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) DEFAULT 0 AFTER `totp_secret`");
        $successes[] = "Users: Added totp_enabled column";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $successes[] = "Users: totp_enabled column already exists";
        }
    }

    // =====================================================
    // 2. SETTINGS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(255) PRIMARY KEY,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Settings table created/verified";
    } catch (Exception $e) {
        $errors[] = "Settings table: " . $e->getMessage();
    }

    // =====================================================
    // 3. USER SESSIONS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `session_id` VARCHAR(128) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_session` (`session_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_last_activity` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "User sessions table created/verified";
    } catch (Exception $e) {
        $errors[] = "User sessions table: " . $e->getMessage();
    }

    // =====================================================
    // 4. AUDIT LOGS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `user_email` VARCHAR(255) DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `target_type` VARCHAR(50) DEFAULT NULL,
            `target_id` INT DEFAULT NULL,
            `details` TEXT,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Audit logs table created/verified";
    } catch (Exception $e) {
        $errors[] = "Audit logs table: " . $e->getMessage();
    }

    // =====================================================
    // 5. CLIENTS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `clients` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `whatsapp` VARCHAR(30) NOT NULL DEFAULT '',
            `payment_status` VARCHAR(20) DEFAULT 'pending',
            `remarks` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_email` (`email`),
            INDEX `idx_whatsapp` (`whatsapp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Clients table created/verified";
    } catch (Exception $e) {
        $errors[] = "Clients table: " . $e->getMessage();
    }

    // Add payment_status column if missing
    try {
        $pdo->exec("ALTER TABLE `clients` ADD COLUMN `payment_status` VARCHAR(20) DEFAULT 'pending' AFTER `whatsapp`");
        $successes[] = "Clients: Added payment_status column";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $successes[] = "Clients: payment_status column already exists";
        }
    }

    // =====================================================
    // 6. ORDERS TABLE (used for invoices)
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) UNIQUE NOT NULL,
            `mfa_id` VARCHAR(20) DEFAULT NULL,
            `invoice_number` VARCHAR(30) DEFAULT NULL,
            `client_id` INT DEFAULT NULL,
            `customer_name` VARCHAR(255) NOT NULL,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_whatsapp` VARCHAR(30) DEFAULT '',
            `product_id` INT DEFAULT NULL,
            `product_name` VARCHAR(255) NOT NULL DEFAULT '',
            `product_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
            `total_price` DECIMAL(10, 2) NOT NULL DEFAULT 0,
            `final_amount` DECIMAL(10,2) DEFAULT NULL,
            `currency` ENUM('USD','PKR','INR') DEFAULT 'USD',
            `coupon_id` INT DEFAULT NULL,
            `discount_amount` DECIMAL(10, 2) DEFAULT 0.00,
            `payment_method_id` INT DEFAULT NULL,
            `payment_method_name` VARCHAR(255) DEFAULT NULL,
            `payment_method_custom` VARCHAR(100) DEFAULT NULL,
            `payment_proof` VARCHAR(500) DEFAULT NULL,
            `payment_proof_filename` VARCHAR(255) DEFAULT NULL,
            `payment_proof_size` INT DEFAULT NULL,
            `status` ENUM('pending', 'payment_pending', 'completed', 'cancelled') DEFAULT 'pending',
            `payment_status` VARCHAR(20) DEFAULT 'pending',
            `payment_verified_by` VARCHAR(255) DEFAULT NULL,
            `payment_verified_at` DATETIME DEFAULT NULL,
            `payment_notes` TEXT DEFAULT NULL,
            `admin_notes` TEXT DEFAULT NULL,
            `warranty_enabled` TINYINT(1) DEFAULT 0,
            `warranty_duration_type` ENUM('monthly','yearly','custom_days') DEFAULT NULL,
            `warranty_duration_value` INT DEFAULT NULL,
            `warranty_start_date` DATE DEFAULT NULL,
            `warranty_expiry_date` DATE DEFAULT NULL,
            `completed_at` DATETIME DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_mfa_id` (`mfa_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_client_id` (`client_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Orders table created/verified";
    } catch (Exception $e) {
        $errors[] = "Orders table: " . $e->getMessage();
    }

    // Safe ALTER: add any missing columns to orders
    $orderColumns = [
        ["mfa_id", "VARCHAR(20) DEFAULT NULL"],
        ["client_id", "INT DEFAULT NULL"],
        ["customer_whatsapp", "VARCHAR(30) DEFAULT ''"],
        ["payment_status", "VARCHAR(20) DEFAULT 'pending'"],
        ["currency", "ENUM('USD','PKR','INR') DEFAULT 'USD'"],
        ["final_amount", "DECIMAL(10,2) DEFAULT NULL"],
        ["warranty_enabled", "TINYINT(1) DEFAULT 0"],
        ["warranty_duration_type", "ENUM('monthly','yearly','custom_days') DEFAULT NULL"],
        ["warranty_duration_value", "INT DEFAULT NULL"],
        ["warranty_start_date", "DATE DEFAULT NULL"],
        ["warranty_expiry_date", "DATE DEFAULT NULL"],
        ["completed_at", "DATETIME DEFAULT NULL"],
        ["payment_method_custom", "VARCHAR(100) DEFAULT NULL"],
        ["ip_address", "VARCHAR(45) DEFAULT NULL"],
        ["product_price", "DECIMAL(10, 2) NOT NULL DEFAULT 0"],
        ["payment_method_name", "VARCHAR(255) DEFAULT NULL"],
        ["admin_notes", "TEXT DEFAULT NULL"],
        ["payment_notes", "TEXT DEFAULT NULL"],
        ["invoice_number", "VARCHAR(30) DEFAULT NULL"],
        ["total_price", "DECIMAL(10, 2) NOT NULL DEFAULT 0"],
        ["coupon_id", "INT DEFAULT NULL"],
        ["payment_proof", "VARCHAR(500) DEFAULT NULL"],
        ["discount_amount", "DECIMAL(10,2) DEFAULT 0"],
    ];
    foreach ($orderColumns as $col) {
        try {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `{$col[0]}` {$col[1]}");
            $successes[] = "Orders: Added {$col[0]} column";
        } catch (Exception $e) {
            // Column already exists — expected
        }
    }

    // =====================================================
    // 7. ORDER SEQUENCE TABLE (for MFA-XXXXXX IDs)
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `order_sequence` (
            `id` INT AUTO_INCREMENT PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Order sequence table created/verified";
    } catch (Exception $e) {
        $errors[] = "Order sequence table: " . $e->getMessage();
    }

    // Sync order_sequence with existing orders
    try {
        $maxOrder = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        if ($maxOrder > 0) {
            $currentSeq = (int)$pdo->query("SELECT IFNULL(MAX(id), 0) FROM order_sequence")->fetchColumn();
            if ($currentSeq < $maxOrder) {
                for ($i = $currentSeq; $i < $maxOrder; $i++) {
                    $pdo->exec("INSERT INTO order_sequence VALUES (NULL)");
                }
                $successes[] = "Order sequence synced to $maxOrder existing orders";
            }
        }
    } catch (Exception $e) {
        // Ignore sync errors
    }

    // Back-fill mfa_id for existing orders that don't have one
    try {
        $existingOrders = $pdo->query("SELECT id FROM orders WHERE mfa_id IS NULL OR mfa_id = '' ORDER BY id ASC")->fetchAll();
        foreach ($existingOrders as $existingOrder) {
            $pdo->exec("INSERT INTO order_sequence VALUES (NULL)");
            $seqId = (int)$pdo->lastInsertId();
            $prefix = str_pad(random_int(10, 99), 2, '0', STR_PAD_LEFT);
            $suffix = str_pad($seqId, 4, '0', STR_PAD_LEFT);
            $mfaId = 'MFA-' . $prefix . $suffix;
            $stmt = $pdo->prepare("UPDATE orders SET mfa_id = ? WHERE id = ?");
            $stmt->execute([$mfaId, $existingOrder['id']]);
        }
        if (count($existingOrders) > 0) {
            $successes[] = "Orders: Back-filled MFA IDs for " . count($existingOrders) . " existing orders";
        }
    } catch (Exception $e) {
        $errors[] = "Orders MFA ID backfill: " . $e->getMessage();
    }

    // Back-fill final_amount from total_price
    try {
        $pdo->exec("UPDATE orders SET final_amount = total_price WHERE final_amount IS NULL");
        $successes[] = "Orders: Back-filled final_amount from total_price";
    } catch (Exception $e) {}

    // Back-fill invoice_number from mfa_id
    try {
        $pdo->exec("UPDATE orders SET invoice_number = CONCAT('INV-', mfa_id) WHERE invoice_number IS NULL AND mfa_id IS NOT NULL AND mfa_id != ''");
        $successes[] = "Orders: Back-filled invoice_number from mfa_id";
    } catch (Exception $e) {}

    // Auto-create client records for existing orders
    try {
        $existingOrders = $pdo->query("SELECT DISTINCT customer_email, customer_name FROM orders WHERE client_id IS NULL OR client_id = 0 ORDER BY id ASC")->fetchAll();
        foreach ($existingOrders as $eo) {
            $email = $eo['customer_email'];
            $name = $eo['customer_name'];
            $check = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
            $check->execute([$email]);
            $existingClient = $check->fetch();
            if ($existingClient) {
                $clientId = $existingClient['id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO clients (name, email, whatsapp) VALUES (?, ?, '')");
                $ins->execute([$name, $email]);
                $clientId = $pdo->lastInsertId();
            }
            $upd = $pdo->prepare("UPDATE orders SET client_id = ? WHERE customer_email = ? AND (client_id IS NULL OR client_id = 0)");
            $upd->execute([$clientId, $email]);
        }
        if (count($existingOrders) > 0) {
            $successes[] = "Clients: Auto-created client records for " . count($existingOrders) . " unique emails";
        }
    } catch (Exception $e) {
        $errors[] = "Client backfill: " . $e->getMessage();
    }

    // =====================================================
    // 8. EMAIL ACCOUNTS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `label` VARCHAR(100) NOT NULL DEFAULT 'General',
            `from_name` VARCHAR(255) NOT NULL DEFAULT '',
            `email_address` VARCHAR(255) NOT NULL,
            `reply_to` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `smtp_host` VARCHAR(255) NOT NULL DEFAULT '',
            `smtp_port` INT NOT NULL DEFAULT 587,
            `smtp_encryption` ENUM('none','tls','ssl') DEFAULT 'tls',
            `smtp_username` VARCHAR(255) NOT NULL DEFAULT '',
            `smtp_password` TEXT NOT NULL,
            `smtp_timeout` INT NOT NULL DEFAULT 30,
            `incoming_enabled` TINYINT(1) DEFAULT 0,
            `incoming_type` ENUM('imap','pop3') DEFAULT 'imap',
            `incoming_host` VARCHAR(255) DEFAULT NULL,
            `incoming_port` INT DEFAULT NULL,
            `incoming_encryption` ENUM('none','tls','ssl') DEFAULT 'tls',
            `incoming_username` VARCHAR(255) DEFAULT NULL,
            `incoming_password` TEXT DEFAULT NULL,
            `imap_folder` VARCHAR(100) DEFAULT 'INBOX',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_email` (`email_address`),
            INDEX `idx_is_active` (`is_active`),
            INDEX `idx_label` (`label`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Email accounts table created/verified";
    } catch (Exception $e) {
        $errors[] = "Email accounts table: " . $e->getMessage();
    }

    // =====================================================
    // 9. EMAIL ROUTING RULES TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_routing_rules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `feature_key` VARCHAR(100) NOT NULL,
            `feature_label` VARCHAR(255) NOT NULL DEFAULT '',
            `email_account_id` INT DEFAULT NULL,
            `is_enabled` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_feature` (`feature_key`),
            INDEX `idx_email_account` (`email_account_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Email routing rules table created/verified";
    } catch (Exception $e) {
        $errors[] = "Email routing rules table: " . $e->getMessage();
    }

    // Seed default routing rules
    try {
        $defaultRoutes = [
            ['order_customer_emails', 'Invoice → Customer Email'],
            ['order_admin_notifications', 'Invoice → Admin Notification'],
            ['manual_composer_default_from', 'Manual Composer Default From'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO `email_routing_rules` (`feature_key`, `feature_label`) VALUES (?, ?)");
        foreach ($defaultRoutes as $route) {
            $stmt->execute($route);
        }
        $successes[] = "Email routing rules: Default routes seeded";
    } catch (Exception $e) {
        $errors[] = "Email routing rules seeding: " . $e->getMessage();
    }

    // =====================================================
    // 10. EMAIL TEMPLATES TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_templates` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `template_key` VARCHAR(100) NOT NULL,
            `template_name` VARCHAR(255) NOT NULL DEFAULT '',
            `subject` VARCHAR(500) NOT NULL DEFAULT '',
            `html_body` LONGTEXT NOT NULL,
            `text_body` LONGTEXT DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_template_key` (`template_key`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Email templates table created/verified";
    } catch (Exception $e) {
        $errors[] = "Email templates table: " . $e->getMessage();
    }

    // Seed default email templates (dark branded)
    $darkHeader = '<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0a;"><tr><td align="center" style="padding:0;"><table width="100%" cellpadding="0" cellspacing="0" style="max-width:700px;background:#111111;font-family:Arial,Helvetica,sans-serif;color:#e0e0e0;"><tr><td style="padding:32px 40px 20px;text-align:center;border-bottom:1px solid #222;"><span style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:800;color:#ff5f1f;letter-spacing:2px;">ENVOICING</span></td></tr><tr><td style="padding:36px 40px;">';
    $darkFooter = '</td></tr><tr><td style="padding:24px 40px 32px;border-top:1px solid #222;text-align:center;"><p style="margin:0 0 8px;font-size:12px;color:#666;">Envoicing — Invoice & Client Management</p><p style="margin:0 0 8px;font-size:11px;color:#555;">You are receiving this email because you interacted with our platform.</p><p style="margin:0;font-size:11px;color:#444;">© {year} {site_name}. All rights reserved.</p></td></tr></table></td></tr></table>';

    try {
        $brandedTemplates = [
            // Order completed (with invoice attachment)
            [
                'order_completed_customer',
                'Invoice Completed — Customer',
                'Your Invoice #{order_id} — {site_name}',
                '<!doctype html><html><body style="margin:0;padding:0;background:#0b0f19;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0b0f19;width:100%;"><tr><td align="center" style="padding:24px 12px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:860px;width:100%;"><tr><td style="padding:12px 0 18px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="left" style="padding:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:800;color:#ff5f1f;letter-spacing:2px;">ENVOICING</td><td align="right" style="padding:0;color:#9aa4b2;font-family:Arial,Helvetica,sans-serif;font-size:12px;"><div style="line-height:18px;">Invoice<br><span style="color:#cbd5e1;">{date}</span></div></td></tr></table></td></tr><tr><td style="padding:0 0 10px 0;font-family:Arial,Helvetica,sans-serif;"><div style="color:#ffffff;font-size:26px;line-height:34px;font-weight:800;">Invoice Completed! 🎉</div><div style="color:#9aa4b2;font-size:14px;line-height:22px;margin-top:8px;">Hi <span style="color:#ffffff;font-weight:700;">{customer_name}</span>, your invoice has been processed and your product has been delivered.</div></td></tr><tr><td style="padding:14px 0 0 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0f172a;border-radius:12px;"><tr><td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif;"><div style="color:#e2e8f0;font-size:14px;line-height:22px;"><div style="margin-bottom:8px;"><span style="color:#94a3b8;">Invoice ID:</span> <span style="color:#ffffff;font-weight:700;">#{order_id}</span></div><div style="margin-bottom:8px;"><span style="color:#94a3b8;">Product:</span> <span style="color:#ffffff;font-weight:600;">{order_items}</span></div><div style="margin-bottom:0;"><span style="color:#94a3b8;">Amount Paid:</span> <span style="color:#22c55e;font-weight:800;">${order_amount}</span></div></div></td></tr></table></td></tr><tr><td style="padding:18px 0 0 0;font-family:Arial,Helvetica,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0b1220;border-radius:12px;"><tr><td style="padding:14px 16px;"><div style="color:#e2e8f0;font-size:13px;line-height:20px;"><span style="color:#22c55e;font-weight:800;">📎 Invoice Attached</span><br>Your invoice is attached to this email as a PDF.</div></td></tr></table></td></tr><tr><td style="padding:22px 0 0 0;font-family:Arial,Helvetica,sans-serif;"><div style="border-top:1px solid rgba(148,163,184,0.25);padding-top:14px;"><div style="color:#9aa4b2;font-size:12px;line-height:18px;">© {year} {site_name}. All rights reserved.<br>Need help? Email: <span style="color:#cbd5e1;">{support_email}</span></div></div></td></tr></table></td></tr></table></body></html>',
                "Hi {customer_name},\n\nYour invoice #{order_id} has been completed!\nProduct: {order_items}\nAmount: \${order_amount}\n\nYour invoice is attached as a PDF.\n\nThank you for choosing {site_name}.\n\n{site_name} Team"
            ],
            // Order placed — customer
            [
                'order_placed_customer',
                'Invoice Created — Customer',
                'Your Invoice #{order_id} Has Been Created — {site_name}',
                '<!doctype html><html><body style="margin:0;padding:0;background:#0b0f19;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0b0f19;width:100%;"><tr><td align="center" style="padding:24px 12px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:860px;width:100%;"><tr><td style="padding:12px 0 18px 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="left" style="padding:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:800;color:#ff5f1f;letter-spacing:2px;">ENVOICING</td><td align="right" style="padding:0;color:#9aa4b2;font-family:Arial,Helvetica,sans-serif;font-size:12px;"><div style="line-height:18px;">Invoice<br><span style="color:#cbd5e1;">{date}</span></div></td></tr></table></td></tr><tr><td style="padding:0 0 10px 0;font-family:Arial,Helvetica,sans-serif;"><div style="color:#ffffff;font-size:26px;line-height:34px;font-weight:800;">New Invoice Created ✅</div><div style="color:#9aa4b2;font-size:14px;line-height:22px;margin-top:8px;">Hi <span style="color:#ffffff;font-weight:700;">{customer_name}</span>, a new invoice has been created for you.</div></td></tr><tr><td style="padding:14px 0 0 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#0f172a;border-radius:12px;"><tr><td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif;"><div style="color:#e2e8f0;font-size:14px;line-height:22px;"><div style="margin-bottom:8px;"><span style="color:#94a3b8;">Invoice ID:</span> <span style="color:#ffffff;font-weight:700;">#{order_id}</span></div><div style="margin-bottom:8px;"><span style="color:#94a3b8;">Total Amount:</span> <span style="color:#ffffff;font-weight:800;">{order_amount}</span></div><div style="margin-bottom:0;"><span style="color:#94a3b8;">Items:</span> <span style="color:#ffffff;font-weight:600;">{order_items}</span></div></div></td></tr></table></td></tr><tr><td style="padding:22px 0 0 0;font-family:Arial,Helvetica,sans-serif;"><div style="border-top:1px solid rgba(148,163,184,0.25);padding-top:14px;"><div style="color:#9aa4b2;font-size:12px;line-height:18px;">© {year} {site_name}. All rights reserved.<br>Need help? Email: <span style="color:#cbd5e1;">{support_email}</span></div></div></td></tr></table></td></tr></table></body></html>',
                "Hi {customer_name},\n\nA new invoice has been created for you.\n\nInvoice ID: #{order_id}\nProduct: {order_items}\nAmount: {order_amount}\n\n{site_name} Team"
            ],
            // Admin notification
            [
                'order_admin_new_order',
                'New Invoice Created',
                'New Invoice {order_id} — {site_name}',
                $darkHeader . '
<h1 style="margin:0 0 16px;font-size:26px;font-weight:bold;color:#fff;">New Invoice Created</h1>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#1a1a1a;border-radius:12px;margin:0 0 24px;">
<tr><td style="padding:20px 24px;border-bottom:1px solid #252525;"><span style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#666;">Invoice ID</span><br><strong style="font-size:18px;color:#ff5f1f;">{order_id}</strong></td></tr>
<tr><td style="padding:16px 24px;border-bottom:1px solid #252525;"><span style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#666;">Customer</span><br><strong style="color:#fff;">{name}</strong> (<a href="mailto:{email}" style="color:#ff5f1f;">{email}</a>)</td></tr>
<tr><td style="padding:16px 24px;"><span style="font-size:11px;text-transform:uppercase;letter-spacing:2px;color:#666;">Amount</span><br><strong style="font-size:20px;color:#4ade80;">${amount}</strong></td></tr>
</table>
<p style="margin:0;font-size:12px;color:#555;">Received at {created_at}</p>
' . $darkFooter,
                "New Invoice {order_id}\nCustomer: {name} ({email})\nAmount: \${amount}\nReceived: {created_at}"
            ],
        ];

        $tplStmt = $pdo->prepare("INSERT INTO email_templates (template_key, template_name, subject, html_body, text_body, is_active) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name), subject=VALUES(subject), html_body=VALUES(html_body), text_body=VALUES(text_body)");
        foreach ($brandedTemplates as $bt) {
            $tplStmt->execute([$bt[0], $bt[1], $bt[2], $bt[3], $bt[4]]);
        }
        $successes[] = "Email templates: " . count($brandedTemplates) . " templates seeded/updated";
    } catch (Exception $e) {
        $errors[] = "Email templates seeding: " . $e->getMessage();
    }

    // =====================================================
    // 11. EMAIL LOGS TABLE
    // =====================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `feature_key` VARCHAR(100) DEFAULT 'manual',
            `email_account_id` INT DEFAULT NULL,
            `from_email` VARCHAR(255) NOT NULL DEFAULT '',
            `to_email` VARCHAR(255) NOT NULL DEFAULT '',
            `cc` VARCHAR(500) DEFAULT NULL,
            `bcc` VARCHAR(500) DEFAULT NULL,
            `subject` VARCHAR(500) NOT NULL DEFAULT '',
            `html_body` LONGTEXT DEFAULT NULL,
            `text_body` LONGTEXT DEFAULT NULL,
            `status` ENUM('queued','sent','failed') DEFAULT 'queued',
            `error_message` TEXT DEFAULT NULL,
            `provider_response` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_feature_key` (`feature_key`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_to_email` (`to_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $successes[] = "Email logs table created/verified";
    } catch (Exception $e) {
        $errors[] = "Email logs table: " . $e->getMessage();
    }

    // =====================================================
    // 12. DEFAULT SETTINGS
    // =====================================================
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->execute(['website_whatsapp_number', '+447490916612']);
        $stmt->execute(['exchange_rate_usd_to_pkr', '280']);
        $stmt->execute(['exchange_rate_usd_to_inr', '85']);
        $successes[] = "Default settings seeded";
    } catch (Exception $e) {
        // Ignore
    }

    // =====================================================
    // SAFE SCHEMA FIX — Add missing columns to existing tables
    // =====================================================
    $safeColumns = [
        // email_accounts
        ['email_accounts', 'label', "VARCHAR(100) NOT NULL DEFAULT 'General'"],
        ['email_accounts', 'from_name', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['email_accounts', 'reply_to', "VARCHAR(255) DEFAULT NULL"],
        ['email_accounts', 'smtp_timeout', "INT NOT NULL DEFAULT 30"],
        ['email_accounts', 'incoming_enabled', "TINYINT(1) DEFAULT 0"],
        ['email_accounts', 'incoming_type', "VARCHAR(10) DEFAULT 'imap'"],
        ['email_accounts', 'incoming_host', "VARCHAR(255) DEFAULT NULL"],
        ['email_accounts', 'incoming_port', "INT DEFAULT NULL"],
        ['email_accounts', 'incoming_encryption', "VARCHAR(10) DEFAULT 'tls'"],
        ['email_accounts', 'incoming_username', "VARCHAR(255) DEFAULT NULL"],
        ['email_accounts', 'incoming_password', "TEXT DEFAULT NULL"],
        ['email_accounts', 'imap_folder', "VARCHAR(100) DEFAULT 'INBOX'"],

        // email_routing_rules
        ['email_routing_rules', 'feature_label', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['email_routing_rules', 'email_account_id', "INT DEFAULT NULL"],
        ['email_routing_rules', 'is_enabled', "TINYINT(1) DEFAULT 1"],

        // email_templates
        ['email_templates', 'template_name', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['email_templates', 'is_active', "TINYINT(1) DEFAULT 1"],
        ['email_templates', 'text_body', "LONGTEXT DEFAULT NULL"],

        // email_logs
        ['email_logs', 'feature_key', "VARCHAR(100) DEFAULT 'manual'"],
        ['email_logs', 'email_account_id', "INT DEFAULT NULL"],
        ['email_logs', 'cc', "VARCHAR(500) DEFAULT NULL"],
        ['email_logs', 'bcc', "VARCHAR(500) DEFAULT NULL"],
        ['email_logs', 'error_message', "TEXT DEFAULT NULL"],
        ['email_logs', 'provider_response', "TEXT DEFAULT NULL"],
    ];

    $fixedCount = 0;
    foreach ($safeColumns as $sc) {
        try {
            $pdo->exec("ALTER TABLE `{$sc[0]}` ADD COLUMN `{$sc[1]}` {$sc[2]}");
            $fixedCount++;
        } catch (Exception $e) {
            // Column already exists — expected
        }
    }
    if ($fixedCount > 0) {
        $successes[] = "Schema fix: Added $fixedCount missing columns";
    } else {
        $successes[] = "Schema fix: All columns already present";
    }

    // =====================================================
    // OUTPUT RESULTS
    // =====================================================
    
    if (!empty($successes)) {
        echo "<div class='mb-6'>";
        foreach ($successes as $msg) {
            echo "<p class='text-green-500 mb-2'>✓ $msg</p>";
        }
        echo "</div>";
    }

    if (!empty($errors)) {
        echo "<div class='mb-6'>";
        foreach ($errors as $msg) {
            echo "<p class='text-red-500 mb-2'>✗ $msg</p>";
        }
        echo "</div>";
    }

    if (empty($errors)) {
        echo "<div class='mt-8 p-6 bg-green-500/20 border border-green-500/30 rounded-2xl'>
            <h2 class='text-xl font-bold text-green-400 mb-4'>✓ Migration Complete!</h2>
            <p class='mb-4'>All database tables and columns have been created/updated successfully.</p>
            <p class='text-sm opacity-70 mb-4'><strong>Important:</strong> Delete this migrate.php file for security!</p>
            <div class='flex gap-4'>
                <a href='/admin/' class='bg-orange-500 text-white px-6 py-2 rounded-lg font-bold hover:bg-orange-600'>Admin Panel</a>
            </div>
        </div>";
    } else {
        echo "<div class='mt-8 p-6 bg-yellow-500/20 border border-yellow-500/30 rounded-2xl'>
            <h2 class='text-xl font-bold text-yellow-400 mb-4'>⚠ Migration Completed with Warnings</h2>
            <p class='mb-4'>Some operations encountered issues. Please review the errors above.</p>
            <p class='text-sm opacity-70'>You may need to manually fix some database issues.</p>
        </div>";
    }

} catch (PDOException $e) {
    echo "<div class='p-6 bg-red-500/20 border border-red-500/30 rounded-2xl'>
        <h2 class='text-xl font-bold text-red-400 mb-4'>✗ Migration Failed</h2>
        <p class='text-red-300'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</div></body></html>";
?>
