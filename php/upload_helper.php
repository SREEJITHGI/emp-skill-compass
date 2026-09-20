<?php
/**
 * Secure File Upload Helper
 * Skill Compass - Employee Skill Tracking System
 */

/**
 * Handle certificate document upload (PDF, PNG, JPG, JPEG)
 *
 * @param array $file The $_FILES['field_name'] array
 * @param int $maxSizeBytes Maximum size in bytes (default 5MB)
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function handle_certificate_upload(array $file, int $maxSizeBytes = 5242880): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid upload parameters.'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => '']; // No file uploaded is not an error if optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // Size limit check
    if ($file['size'] > $maxSizeBytes) {
        $mb = round($maxSizeBytes / 1048576);
        return ['success' => false, 'error' => "File exceeds maximum permitted size of {$mb}MB."];
    }

    // Extension check
    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'png', 'jpg', 'jpeg'];

    if (!in_array($ext, $allowedExts, true)) {
        return ['success' => false, 'error' => 'Only PDF, PNG, JPG, or JPEG files are allowed.'];
    }

    // Mime type check
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/pjpeg'
    ];

    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'error' => 'File content does not match allowed PDF or image types.'];
    }

    // Target directory
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'certificates';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate collision-free safe filename
    $safeName = 'cert_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

    $isMoved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        $isMoved = move_uploaded_file($file['tmp_name'], $targetPath);
    } elseif (php_sapi_name() === 'cli' && file_exists($file['tmp_name'])) {
        $isMoved = copy($file['tmp_name'], $targetPath);
    }

    if (!$isMoved) {
        return ['success' => false, 'error' => 'Failed to save uploaded document to server.'];
    }

    // Return web-accessible path
    return [
        'success' => true,
        'path' => 'uploads/certificates/' . $safeName,
        'original_name' => $origName
    ];
}
