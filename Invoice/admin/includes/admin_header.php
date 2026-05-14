<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

// Check session expiry (10 hours)
if (!checkSessionExpiry(36000)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: /admin/login.php?error=" . urlencode("Session expired"));
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize($pageTitle) . ' | ' : ''; ?>Admin - Envoicing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0a0a0a;
            color: #ffffff;
        }
        
        .sidebar {
            background: #050505;
            border-right: 1px solid rgba(255,255,255,0.03);
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
            color: rgba(255,255,255,0.3);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .sidebar-link:hover {
            background: rgba(255,255,255,0.03);
            color: #fff;
        }
        
        .sidebar-link.active {
            background: rgba(255,95,31,0.08);
            color: #ff5f1f;
        }
        
        .sidebar-section {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: rgba(255,255,255,0.15);
            padding: 24px 16px 8px;
        }
        
        .card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 24px;
        }
        
        .sidebar-scroll {
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        
        .sidebar-scroll::-webkit-scrollbar { width: 2px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); }
        
        .input-field {
            width: 100%;
            background: #111111;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 16px;
            color: #ffffff;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #ff5f1f;
            background: #151515;
            box-shadow: 0 0 0 4px rgba(255, 95, 31, 0.1);
        }

        /* Fix autofill background */
        .input-field:-webkit-autofill,
        .input-field:-webkit-autofill:hover, 
        .input-field:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0px 1000px #111111 inset;
            transition: background-color 5000s ease-in-out 0s;
        }
        
        .btn-primary {
            background: #ff5f1f;
            color: #fff;
            padding: 10px 24px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary:hover {
            background: #e54e10;
            transform: translateY(-1px);
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #22c55e;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
        }
    </style>
</head>
<body class="min-h-screen">
    
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar w-60 fixed h-full p-6 hidden lg:flex flex-col">
            <!-- Logo -->
            <a href="/admin/" class="flex items-center gap-3 mb-8 px-2">
                <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-black text-xs">E</div>
                <span class="text-sm font-black tracking-tighter uppercase">Admin Panel</span>
            </a>
            
            <!-- Navigation -->
            <nav class="sidebar-scroll flex-1">
                <div class="sidebar-section">Overview</div>
                <a href="/admin/" class="sidebar-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-grid-2 w-4"></i>
                    <span>Dashboard</span>
                </a>

                <div class="sidebar-section">Invoicing</div>
                <a href="/admin/clients.php" class="sidebar-link <?php echo $currentPage === 'clients' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-group w-4"></i>
                    <span>Clients</span>
                </a>
                <a href="/admin/invoice.php" class="sidebar-link <?php echo $currentPage === 'invoice' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice w-4"></i>
                    <span>Invoice</span>
                </a>
                
                <div class="sidebar-section">Email Center</div>
                <a href="/admin/email-accounts.php" class="sidebar-link <?php echo $currentPage === 'email-accounts' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-at w-4"></i>
                    <span>Accounts</span>
                </a>
                <a href="/admin/email-routing.php" class="sidebar-link <?php echo $currentPage === 'email-routing' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-route w-4"></i>
                    <span>Routing</span>
                </a>
                <a href="/admin/email-templates.php" class="sidebar-link <?php echo $currentPage === 'email-templates' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-code w-4"></i>
                    <span>Templates</span>
                </a>
                <a href="/admin/email-compose.php" class="sidebar-link <?php echo $currentPage === 'email-compose' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-pen-to-square w-4"></i>
                    <span>Compose</span>
                </a>
                <a href="/admin/email-logs.php" class="sidebar-link <?php echo $currentPage === 'email-logs' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left w-4"></i>
                    <span>Logs</span>
                </a>
                
                <div class="sidebar-section">System</div>
                <a href="/admin/settings.php" class="sidebar-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cog w-4"></i>
                    <span>Settings</span>
                </a>
                <a href="/admin/security.php" class="sidebar-link <?php echo $currentPage === 'security' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-shield-halved w-4"></i>
                    <span>Security</span>
                </a>
                <a href="/admin/audit-logs.php" class="sidebar-link <?php echo $currentPage === 'audit-logs' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-list-check w-4"></i>
                    <span>Audit Logs</span>
                </a>
                <a href="/admin/users.php" class="sidebar-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-gear w-4"></i>
                    <span>Users</span>
                </a>
                <a href="/migrate.php" class="sidebar-link" target="_blank">
                    <i class="fa-solid fa-database w-4"></i>
                    <span>Migration</span>
                </a>
            </nav>
            
            <!-- User -->
            <div class="mt-auto pt-6 border-t border-white/5">
                <div class="flex items-center gap-3 px-2 mb-4">
                    <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-[10px] font-bold text-white/40">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="overflow-hidden">
                        <div class="text-[10px] font-black text-white truncate"><?php echo sanitize($_SESSION['user_name'] ?? 'Admin'); ?></div>
                        <div class="text-[8px] font-bold text-white/20 uppercase tracking-widest">Administrator</div>
                    </div>
                </div>
                <a href="logout.php" class="sidebar-link text-red-500/50 hover:text-red-500 hover:bg-red-500/5">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 lg:ml-60 p-6 md:p-10">
            <!-- Mobile Header -->
            <div class="lg:hidden flex items-center justify-between mb-8">
                <a href="/admin/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-black text-xs">E</div>
                    <span class="text-sm font-black tracking-tighter uppercase">Admin</span>
                </a>
                <button id="mobileMenuBtn" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>

            <!-- Mobile Navigation -->
            <div id="mobileMenu" class="hidden lg:hidden fixed inset-0 z-50 bg-[#0a0a0a] p-6 pt-20">
                <nav class="space-y-2">
                    <a href="/admin/" class="sidebar-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">Dashboard</a>
                    <a href="/admin/clients.php" class="sidebar-link <?php echo $currentPage === 'clients' ? 'active' : ''; ?>">Clients</a>
                    <a href="/admin/invoice.php" class="sidebar-link <?php echo $currentPage === 'invoice' ? 'active' : ''; ?>">Invoice</a>
                    <a href="/admin/email-accounts.php" class="sidebar-link <?php echo $currentPage === 'email-accounts' ? 'active' : ''; ?>">Email Accounts</a>
                    <a href="/admin/email-compose.php" class="sidebar-link <?php echo $currentPage === 'email-compose' ? 'active' : ''; ?>">Compose Email</a>
                    <a href="/admin/settings.php" class="sidebar-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">Settings</a>
                    <a href="/admin/security.php" class="sidebar-link <?php echo $currentPage === 'security' ? 'active' : ''; ?>">Security</a>
                    <a href="logout.php" class="sidebar-link text-red-500">Sign Out</a>
                </nav>
            </div>

