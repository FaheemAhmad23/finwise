<?php
/**
 * API endpoint to upload a media file via AJAX
 * Returns JSON with success status and file path
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['media_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['media_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error. Please try again.']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 10 * 1024 * 1024; // 10MB

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 10MB.']);
    exit;
}

// Ensure upload directory exists
$uploadDir = __DIR__ . '/../../uploads/media/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$convertToWebp = isset($_POST['convert_webp']) && $_POST['convert_webp'] === '1';

if ($convertToWebp && in_array($mimeType, ['image/jpeg', 'image/png']) && function_exists('imagewebp')) {
    $newFilename = uniqid('media_') . '.webp';
    $uploadPath = $uploadDir . $newFilename;
    
    if ($mimeType === 'image/jpeg') {
        $image = imagecreatefromjpeg($file['tmp_name']);
    } else {
        $image = imagecreatefrompng($file['tmp_name']);
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }
    
    if ($image && imagewebp($image, $uploadPath, 85)) {
        imagedestroy($image);
        $mimeType = 'image/webp';
        $fileSize = filesize($uploadPath);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to convert image to WebP.']);
        exit;
    }
} else {
    $newFilename = uniqid('media_') . '.' . strtolower($ext);
    $uploadPath = $uploadDir . $newFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to upload file.']);
        exit;
    }
    $fileSize = $file['size'];
}

// Get image dimensions
$imageInfo = getimagesize($uploadPath);
$width = $imageInfo[0] ?? null;
$height = $imageInfo[1] ?? null;

// Save to database
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO media (filename, original_filename, path, size, mime_type, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $newFilename,
        $file['name'],
        '/uploads/media/' . $newFilename,
        $fileSize,
        $mimeType,
        $width,
        $height,
        $_SESSION['user_id']
    ]);
    
    logAuditAction('media_upload', 'media', $pdo->lastInsertId(), 'Uploaded via picker: ' . $file['name']);
    
    echo json_encode([
        'success' => true,
        'path' => '/uploads/media/' . $newFilename,
        'filename' => $newFilename,
        'id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    @unlink($uploadPath);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
