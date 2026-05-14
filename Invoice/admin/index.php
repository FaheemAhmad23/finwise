<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$pdo = getDB();

// ─── Resilient stat queries (each independent) ───
$clientCount = 0;
$invoiceCount = 0;
$recentClients = [];

try { $clientCount = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(); } catch (Exception $e) {}
try { $invoiceCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(); } catch (Exception $e) {}

// Recent clients
try {
    $recentClients = $pdo->query("SELECT id, name, email, whatsapp, payment_status, created_at FROM clients ORDER BY created_at DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {}
?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-black tracking-tight mb-1 uppercase">Dashboard</h1>
        <p class="text-[10px] font-bold uppercase tracking-widest text-white/30">Invoicing & Client Management</p>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4 mb-8">
        <div class="bg-white/5 border border-white/5 rounded-3xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Total Clients</p>
            <p class="text-xl font-black"><?php echo $clientCount; ?></p>
        </div>

        <div class="bg-white/5 border border-white/5 rounded-3xl p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Total Invoices</p>
            <p class="text-xl font-black"><?php echo $invoiceCount; ?></p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Recent Clients -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white/5 border border-white/5 rounded-[32px] p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-sm font-black uppercase tracking-widest">Recent Clients</h2>
                    <a href="/admin/clients.php" class="text-[10px] font-bold uppercase tracking-widest text-orange-500 hover:text-orange-400 transition">View All →</a>
                </div>
                
                <?php if (empty($recentClients)): ?>
                    <p class="text-xs text-white/30 text-center py-8 italic">No clients yet.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="text-[9px] font-bold uppercase tracking-widest text-white/30 pb-3">Name</th>
                                <th class="text-[9px] font-bold uppercase tracking-widest text-white/30 pb-3">Email</th>
                                <th class="text-[9px] font-bold uppercase tracking-widest text-white/30 pb-3">WhatsApp</th>
                                <th class="text-[9px] font-bold uppercase tracking-widest text-white/30 pb-3 text-center">Status</th>
                                <th class="text-[9px] font-bold uppercase tracking-widest text-white/30 pb-3 text-right">Since</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClients as $c): ?>
                            <tr class="border-b border-white/[0.03] hover:bg-white/[0.02] transition">
                                <td class="py-3 pr-3">
                                    <span class="text-[11px] font-bold text-white/80"><?php echo sanitize($c['name']); ?></span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="text-[11px] text-white/50"><?php echo sanitize($c['email'] ?? '-'); ?></span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="text-[11px] text-white/50"><?php echo sanitize($c['whatsapp'] ?? '-'); ?></span>
                                </td>
                                <td class="py-3 text-center">
                                    <?php 
                                        $ps = $c['payment_status'] ?? 'unknown';
                                        $colors = ['paid' => 'text-green-400 bg-green-500/10', 'pending' => 'text-yellow-400 bg-yellow-500/10', 'overdue' => 'text-red-400 bg-red-500/10'];
                                        $cl = $colors[$ps] ?? 'text-white/40 bg-white/5';
                                    ?>
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?php echo $cl; ?>"><?php echo ucfirst($ps); ?></span>
                                </td>
                                <td class="py-3 text-right">
                                    <span class="text-[10px] text-white/30"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="space-y-6">
            <div class="bg-white/5 border border-white/5 rounded-[32px] p-8">
                <h2 class="text-sm font-black uppercase tracking-widest mb-6">Quick Actions</h2>
                <div class="space-y-3">
                    <a href="/admin/clients.php" class="flex items-center gap-3 p-4 bg-white/5 rounded-2xl hover:bg-orange-500/10 hover:border-orange-500/20 border border-transparent transition group">
                        <i class="fa-solid fa-user-plus text-xs text-orange-500"></i>
                        <span class="text-xs font-bold uppercase tracking-widest group-hover:text-orange-500 transition">Manage Clients</span>
                    </a>
                    <a href="/admin/invoice.php" class="flex items-center gap-3 p-4 bg-white/5 rounded-2xl hover:bg-white/10 border border-transparent transition">
                        <i class="fa-solid fa-file-invoice text-xs text-white/30"></i>
                        <span class="text-xs font-bold uppercase tracking-widest">View Invoice</span>
                    </a>
                    <a href="/admin/email-compose.php" class="flex items-center gap-3 p-4 bg-white/5 rounded-2xl hover:bg-white/10 border border-transparent transition">
                        <i class="fa-solid fa-envelope text-xs text-white/30"></i>
                        <span class="text-xs font-bold uppercase tracking-widest">Compose Email</span>
                    </a>
                    <a href="/admin/settings.php" class="flex items-center gap-3 p-4 bg-white/5 rounded-2xl hover:bg-white/10 border border-transparent transition">
                        <i class="fa-solid fa-cog text-xs text-white/30"></i>
                        <span class="text-xs font-bold uppercase tracking-widest">Settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
