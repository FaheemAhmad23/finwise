<?php
/**
 * API endpoint to list all media files for the media picker
 * Returns JSON array of media items
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

try {
    $pdo = getDB();
    $media = $pdo->query("SELECT id, filename, original_filename, path, size, width, height FROM media ORDER BY created_at DESC")->fetchAll();
    echo json_encode($media);
} catch (Exception $e) {
    echo json_encode([]);
}
exit;
