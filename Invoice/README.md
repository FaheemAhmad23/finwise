# MFA Tools - PHP/MySQL Website

A complete PHP/MySQL website for providing free Canva Pro (Canva Education) access to students. Features a beautiful dark theme with glassmorphism design, admin panel, and blog system.

## Features

### Frontend
- **Homepage** - Hero section, about preview, teams section, channels, blog preview
- **About Page** - Full story of MFA Tools
- **Teams Page** - Join Canva Education teams (3 teams)
- **Channels Page** - WhatsApp and Telegram channel links
- **Contact Page** - Email and Telegram support
- **Blog** - Dynamic blog with posts from database
- **SEO Optimized** - Meta tags, sitemap, robots.txt

### Admin Panel
- **Dashboard** - Overview with stats
- **Team Links** - Manage 3 Canva team invite links
- **Channel Links** - Manage WhatsApp and Telegram links
- **Contact Links** - Manage email and Telegram support
- **Blog Posts** - Create, edit, delete posts with thumbnails
- **Site Settings** - Customize hero section and site info

## Requirements

- PHP 7.4 or higher (PHP 8.x recommended)
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled

## Installation on Hostinger

### Step 1: Upload Files

1. Log in to your Hostinger hPanel
2. Go to **File Manager** or use **FTP**
3. Navigate to `public_html` folder
4. Upload all files from this package (except `README.md`)

### Step 2: Create Database

1. In hPanel, go to **Databases** → **MySQL Databases**
2. Create a new database (e.g., `mfatools_db`)
3. Create a database user with a strong password
4. Add the user to the database with **All Privileges**
5. Note down:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

### Step 3: Configure Database

1. Open `includes/config.php` in File Manager
2. Update these values:

```php
define('DB_HOST', 'localhost');        // Usually localhost on Hostinger
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

3. Update the site URL:

```php
define('SITE_URL', 'https://yourdomain.com');
```

4. **IMPORTANT**: Change the admin credentials:

```php
define('ADMIN_USER', 'your_admin_username');
define('ADMIN_PASS', 'your_secure_password');
```

### Step 4: Run Installation

1. Visit `https://yourdomain.com/install.php` in your browser
2. The script will create all necessary tables and sample data
3. **Delete `install.php` after successful installation!**

### Step 5: Configure SSL (Recommended)

1. In hPanel, go to **SSL** → **Install SSL**
2. Enable free Let's Encrypt SSL
3. Uncomment HTTPS redirect in `.htaccess`:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Admin Panel Access

- URL: `https://yourdomain.com/admin/`
- Default credentials (change these!):
  - Username: `admin`
  - Password: `mfatools2026`

## File Structure

```
mfatools/
├── admin/                  # Admin panel
│   ├── includes/
│   │   ├── admin_header.php
│   │   └── admin_footer.php
│   ├── index.php          # Dashboard
│   ├── login.php          # Login page
│   ├── logout.php         # Logout handler
│   ├── teams.php          # Team links management
│   ├── channels.php       # Channel links management
│   ├── contact.php        # Contact links management
│   ├── posts.php          # Blog posts list
│   ├── post-edit.php      # Post editor
│   └── settings.php       # Site settings
├── includes/
│   ├── config.php         # Database & site configuration
│   ├── header.php         # Frontend header
│   └── footer.php         # Frontend footer
├── index.php              # Homepage
├── about.php              # About page
├── teams.php              # Teams page
├── channels.php           # Channels page
├── contact.php            # Contact page
├── blog.php               # Blog listing
├── post.php               # Single post view
├── install.php            # Database installer (DELETE AFTER USE)
├── 404.php                # Error page
├── 500.php                # Server error page
├── .htaccess              # Apache configuration
├── robots.txt             # SEO robots file
└── sitemap.xml.php        # Dynamic sitemap
```

## Customization

### Changing Colors

The main accent color is orange (`#ff5f1f`). To change it, search and replace in all PHP files:
- `#ff5f1f` - Primary orange
- `orange-500` - Tailwind orange class
- `from-orange-500` - Gradient classes

### Adding New Pages

1. Create a new PHP file in the root directory
2. Include header and footer:

```php
<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Your Page Title';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Your content here -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### Adding New Settings

1. Add the setting in `admin/settings.php`
2. Use `getSetting('key', 'default')` to retrieve it
3. Use `updateSetting('key', 'value')` to save it

## Security Notes

1. **Change admin credentials** immediately after installation
2. **Delete `install.php`** after setup
3. Keep PHP and MySQL updated
4. Use HTTPS (SSL certificate)
5. Regular backups recommended

## Support

For issues or questions:
- Email: support@mfatools.one
- Telegram: @mfatools

## License

This project is created for MFA Tools. All rights reserved.

---

**Version:** 1.0.0  
**Last Updated:** January 2026
