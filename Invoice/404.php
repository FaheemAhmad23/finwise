<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Page Not Found';
$pageDescription = 'The page you are looking for does not exist.';

http_response_code(404);
require_once __DIR__ . '/includes/header.php';
?>

    <section class="relative pt-32 md:pt-40 pb-20 px-6 max-w-4xl mx-auto text-center min-h-[60vh] flex flex-col items-center justify-center">
        <div class="text-9xl font-black text-white/10 mb-8">404</div>
        <h1 class="text-4xl md:text-6xl font-extrabold tracking-tighter mb-6">
            Page Not <span class="text-gradient-vibrant">Found</span>
        </h1>
        <p class="text-xl opacity-50 max-w-xl mx-auto mb-12">
            Oops! The page you're looking for doesn't exist or has been moved.
        </p>
        <div class="flex flex-col sm:flex-row gap-4">
            <a href="/" class="cta-button px-8 py-3 rounded-full font-bold">Go Home</a>
            <a href="/teams.php" class="px-8 py-3 rounded-full font-bold bg-white/10 hover:bg-white/20 transition">Join Teams</a>
        </div>
    </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
