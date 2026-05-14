<?php
$pageTitle = 'Site Settings';
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_name' => isset($_POST['site_name']) ? trim($_POST['site_name']) : '',
        'site_tagline' => isset($_POST['site_tagline']) ? trim($_POST['site_tagline']) : '',
        'hero_badge' => isset($_POST['hero_badge']) ? trim($_POST['hero_badge']) : '',
        'hero_title' => isset($_POST['hero_title']) ? trim($_POST['hero_title']) : '',
        'hero_subtitle' => isset($_POST['hero_subtitle']) ? trim($_POST['hero_subtitle']) : '',
        'hero_description' => isset($_POST['hero_description']) ? trim($_POST['hero_description']) : '',
        'google_verification_meta' => isset($_POST['google_verification_meta']) ? trim($_POST['google_verification_meta']) : '',
        'website_whatsapp_number' => isset($_POST['website_whatsapp_number']) ? trim($_POST['website_whatsapp_number']) : '+447490916612'
    ];
    
    // Validate Google verification meta tag if provided
    if (!empty($settings['google_verification_meta'])) {
        if (!preg_match('/<meta\s+name=["\']google-site-verification["\']\s+content=["\'][^"\']+["\']\s*\/?>/i', $settings['google_verification_meta'])) {
            $error = 'Invalid Google verification meta tag format. Please paste the complete meta tag from Google Search Console.';
        }
    }
    
    if (empty($error)) {
        $allSuccess = true;
        foreach ($settings as $key => $value) {
            if (!updateSetting($key, $value)) {
                $allSuccess = false;
            }
        }
        
        if ($allSuccess) {
            logAuditAction('settings_update', 'settings', null, 'Updated site settings');
            $success = 'Settings updated successfully!';
        } else {
            $error = 'Some settings failed to update. Please try again.';
        }
    }
}

// Get current values
$siteName = getSetting('site_name', 'MFA Tools');
$siteTagline = getSetting('site_tagline', 'Free Kanwa Pro for Students');
$heroBadge = getSetting('hero_badge', 'Empowering 1 Million+ Creators Globally');
$heroTitle = getSetting('hero_title', 'Free Kanwa Pro');
$heroSubtitle = getSetting('hero_subtitle', 'For Students.');
$heroDescription = getSetting('hero_description', '');
$googleVerificationMeta = getSetting('google_verification_meta', '');
$websiteWhatsApp = getSetting('website_whatsapp_number', '+447490916612');
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-10">
        <h1 class="text-3xl font-bold mb-2">Site Settings</h1>
        <p class="text-white/50">Customize your website's appearance and content.</p>
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
    
    <form method="POST">
        <!-- General Settings -->
        <div class="card p-8 mb-6">
            <h2 class="text-xl font-bold mb-6">General Settings</h2>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Site Name</label>
                    <input type="text" name="site_name" value="<?php echo sanitize($siteName); ?>" 
                           class="input-field" placeholder="MFA Tools">
                </div>
                
                <div>
                    <label class="block text-white/50 text-sm mb-2">Site Tagline</label>
                    <input type="text" name="site_tagline" value="<?php echo sanitize($siteTagline); ?>" 
                           class="input-field" placeholder="Free Kanwa Pro for Students">
                </div>
            </div>
        </div>
        
        <!-- WhatsApp Settings -->
        <div class="card p-8 mb-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-green-500/20 rounded-xl flex items-center justify-center">
                    <i class="fa-brands fa-whatsapp text-green-500"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold">WhatsApp Settings</h2>
                    <p class="text-white/50 text-sm">Global WhatsApp number used across the site</p>
                </div>
            </div>
            <div>
                <label class="block text-white/50 text-sm mb-2">WhatsApp Number</label>
                <input type="text" name="website_whatsapp_number" value="<?php echo sanitize($websiteWhatsApp); ?>" 
                       class="input-field" placeholder="+447490916612">
                <p class="text-white/30 text-xs mt-2">Include country code, e.g. +447490916612. Used on contact page, checkout thank-you page, and footer.</p>
            </div>
        </div>
        
        <!-- Hero Section Settings -->
        <div class="card p-8 mb-6">
            <h2 class="text-xl font-bold mb-6">Homepage Hero Section</h2>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Badge Text</label>
                    <p class="text-white/30 text-xs mb-2">The small text above the main title</p>
                    <input type="text" name="hero_badge" value="<?php echo sanitize($heroBadge); ?>" 
                           class="input-field" placeholder="Empowering 1 Million+ Creators Globally">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-white/50 text-sm mb-2">Hero Title (Line 1)</label>
                        <input type="text" name="hero_title" value="<?php echo sanitize($heroTitle); ?>" 
                               class="input-field" placeholder="Free Kanwa Pro">
                    </div>
                    
                    <div>
                        <label class="block text-white/50 text-sm mb-2">Hero Title (Line 2 - Gradient)</label>
                        <input type="text" name="hero_subtitle" value="<?php echo sanitize($heroSubtitle); ?>" 
                               class="input-field" placeholder="For Students.">
                    </div>
                </div>
                
                <div>
                    <label class="block text-white/50 text-sm mb-2">Hero Description</label>
                    <textarea name="hero_description" class="input-field min-h-[100px]" 
                              placeholder="MFA Tools helps students, creators, and learners..."><?php echo sanitize($heroDescription); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Google Search Console -->
        <div class="card p-8 mb-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center">
                    <i class="fa-brands fa-google text-blue-500"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold">Google Search Console</h2>
                    <p class="text-white/50 text-sm">Verify your site ownership with Google</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-white/50 text-sm mb-2">Verification Meta Tag</label>
                    <textarea name="google_verification_meta" class="input-field font-mono text-sm" rows="3"
                              placeholder='<meta name="google-site-verification" content="your-verification-code" />'><?php echo sanitize($googleVerificationMeta); ?></textarea>
                    <p class="text-white/30 text-xs mt-2">
                        Paste the complete meta tag from Google Search Console. It will be automatically added to your site's &lt;head&gt; section.
                    </p>
                </div>
                
                <?php if (!empty($googleVerificationMeta)): ?>
                <div class="p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
                    <p class="text-green-500 text-sm">
                        <i class="fa-solid fa-check-circle mr-2"></i>
                        Verification meta tag is configured and active.
                    </p>
                </div>
                <?php else: ?>
                <div class="p-4 bg-white/5 border border-white/10 rounded-xl">
                    <p class="text-white/50 text-sm">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        No verification tag configured. Add one from Google Search Console to verify your site.
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <p class="text-blue-400 text-sm font-bold mb-2">How to get your verification tag:</p>
                    <ol class="text-white/50 text-sm space-y-1 list-decimal list-inside">
                        <li>Go to <a href="https://search.google.com/search-console" target="_blank" class="text-blue-400 hover:underline">Google Search Console</a></li>
                        <li>Add your property (URL prefix method)</li>
                        <li>Choose "HTML tag" verification method</li>
                        <li>Copy the entire meta tag and paste it above</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <!-- Preview -->
        <div class="card p-8 mb-6 bg-black">
            <h2 class="text-xl font-bold mb-6">Preview</h2>
            <div class="text-center py-8">
                <div class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-white/5 border border-white/10 text-[10px] font-bold tracking-wider uppercase mb-6">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    <?php echo sanitize($heroBadge); ?>
                </div>
                <h3 class="text-4xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-4">
                    <?php echo sanitize($heroTitle); ?><br>
                    <span class="bg-gradient-to-r from-white via-orange-400 to-orange-500 bg-clip-text text-transparent"><?php echo sanitize($heroSubtitle); ?></span>
                </h3>
                <p class="text-white/50 max-w-xl mx-auto"><?php echo sanitize($heroDescription); ?></p>
            </div>
        </div>
        
        <!-- Submit -->
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-save mr-2"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
