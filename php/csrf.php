<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection Helper
 * Skill Compass - Employee Skill Tracking System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or retrieve the existing CSRF token for the session
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return an HTML hidden input containing the CSRF token
 */
function csrf_field(): string {
    $token = htmlspecialchars(get_csrf_token());
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verify whether the submitted CSRF token matches the session token
 */
function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($submittedToken) || empty($sessionToken)) {
        return false;
    }
    
    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Require a valid CSRF token on POST requests; redirects on failure
 */
function require_csrf(string $redirectUrl = ''): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        $dest = $redirectUrl ?: ($_SERVER['HTTP_REFERER'] ?? 'index.php');
        $sep = (strpos($dest, '?') !== false) ? '&' : '?';
        header("Location: " . $dest . $sep . "error=" . urlencode("Security validation failed (Invalid or missing CSRF token). Please try again."));
        exit;
    }
}
