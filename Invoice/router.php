<?php
/**
 * Envoicing - PHP Router for Clean URLs
 * 
 * This router handles clean URLs when running with PHP's built-in server.
 * On production (Apache/Nginx), the .htaccess file handles URL rewriting.
 * 
 * Usage: php -S localhost:8080 router.php
 */

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading slash
$uri = ltrim($uri, '/');

// If empty, redirect to admin
if (empty($uri) || $uri === '/') {
    header('Location: /admin/');
    return true;
}

// Check if it's a direct file request (with extension)
if (preg_match('/\.(?:php|html|css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot|map|json)$/i', $uri)) {
    // Check if file exists
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file)) {
        // For PHP files, include them
        if (preg_match('/\.php$/i', $uri)) {
            require $file;
            return true;
        }
        // For other files, let PHP's built-in server handle them
        return false;
    }
}

// Clean URL routing map
$routes = [
    // Admin pages
    'admin' => 'admin/index.php',
    'admin/login' => 'admin/login.php',
    'admin/logout' => 'admin/logout.php',
    'admin/settings' => 'admin/settings.php',
    'admin/users' => 'admin/users.php',
    'admin/verify-2fa' => 'admin/verify-2fa.php',
    'admin/clients' => 'admin/clients.php',
    'admin/invoice' => 'admin/invoice.php',
    'admin/audit-logs' => 'admin/audit-logs.php',
    'admin/security' => 'admin/security.php',

    // Email Center
    'admin/email-accounts' => 'admin/email-accounts.php',
    'admin/email-routing' => 'admin/email-routing.php',
    'admin/email-templates' => 'admin/email-templates.php',
    'admin/email-compose' => 'admin/email-compose.php',
    'admin/email-logs' => 'admin/email-logs.php',
];

// Check for exact route match
if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    return true;
}

// Check if the URI without trailing slash matches a PHP file
$phpFile = __DIR__ . '/' . $uri . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

// Check if it's a directory with index.php
$indexFile = __DIR__ . '/' . $uri . '/index.php';
if (file_exists($indexFile)) {
    require $indexFile;
    return true;
}

// Check if it's a static file
$staticFile = __DIR__ . '/' . $uri;
if (file_exists($staticFile) && is_file($staticFile)) {
    return false; // Let PHP's built-in server handle static files
}

// 404 - Page not found
http_response_code(404);
if (file_exists(__DIR__ . '/404.php')) {
    require __DIR__ . '/404.php';
} else {
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Page Not Found</h1><p>The requested page could not be found.</p><a href="/admin/">Go to Admin</a></body></html>';
}
return true;
