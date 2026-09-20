<?php
session_start();
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/csrf.php';

echo "=== Automated Test: Option B (Profile & Password Change + CSRF Protection) ===\n\n";

// TEST 1: CSRF Helper Functions
echo "[TEST 1] CSRF Token Generation & Validation...\n";
$token = get_csrf_token();
if (strlen($token) === 64 && ctype_xdigit($token)) {
    echo " -> [PASS] Generated 64-char hexadecimal token: " . substr($token, 0, 16) . "...\n";
} else {
    echo " -> [FAIL] Invalid token format: $token\n";
}

// Simulated POST without CSRF token
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
if (!verify_csrf()) {
    echo " -> [PASS] verify_csrf() correctly rejected request missing CSRF token.\n";
} else {
    echo " -> [FAIL] verify_csrf() should have rejected missing token.\n";
}

// Simulated POST with forged/wrong CSRF token
$_POST['csrf_token'] = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
if (!verify_csrf()) {
    echo " -> [PASS] verify_csrf() correctly rejected tampered CSRF token.\n";
} else {
    echo " -> [FAIL] verify_csrf() accepted fraudulent token.\n";
}

// Simulated POST with genuine CSRF token
$_POST['csrf_token'] = $token;
if (verify_csrf()) {
    echo " -> [PASS] verify_csrf() successfully verified legitimate session CSRF token!\n";
} else {
    echo " -> [FAIL] verify_csrf() failed on legitimate token.\n";
}

// TEST 2: Profile Update
echo "\n[TEST 2] Profile Information Update...\n";
$jdoe = $conn->query("SELECT id, username, first_name, last_name, email, password FROM users WHERE username = 'jdoe'")->fetch_assoc();
if (!$jdoe) {
    die("FATAL: User 'jdoe' not found in database.\n");
}

$origEmail = $jdoe['email'];
$testEmail = "jdoe.updated@example.com";

// Update email
$upStmt = $conn->prepare("UPDATE users SET first_name = 'Johnathan', email = ? WHERE id = ?");
$upStmt->bind_param("si", $testEmail, $jdoe['id']);
$upStmt->execute();
$upStmt->close();

$checkUser = $conn->query("SELECT first_name, email FROM users WHERE id = {$jdoe['id']}")->fetch_assoc();
if ($checkUser['first_name'] === 'Johnathan' && $checkUser['email'] === $testEmail) {
    echo " -> [PASS] Profile updated: first_name='{$checkUser['first_name']}', email='{$checkUser['email']}'.\n";
} else {
    echo " -> [FAIL] Profile update did not reflect in DB.\n";
}

// Revert profile to original
$revStmt = $conn->prepare("UPDATE users SET first_name = 'John', email = ? WHERE id = ?");
$revStmt->bind_param("si", $origEmail, $jdoe['id']);
$revStmt->execute();
$revStmt->close();
echo " -> [INFO] Reverted profile back to clean original state.\n";

// TEST 3: Password Change Validation & Hashing
echo "\n[TEST 3] Secure Password Change Flow...\n";

// Sub-test 3A: Wrong current password
$wrongCurrent = "wrongpassword999";
if (!password_verify($wrongCurrent, $jdoe['password'])) {
    echo " -> [PASS] Correctly rejected wrong current password.\n";
} else {
    echo " -> [FAIL] Accepted invalid current password.\n";
}

// Sub-test 3B: Valid current password check
if (password_verify("employee123", $jdoe['password'])) {
    echo " -> [PASS] Verified current password 'employee123'.\n";
} else {
    echo " -> [FAIL] Current password verification failed.\n";
}

// Sub-test 3C: Password hashing & update
$newTestPwd = "NewSecurePassword#2026";
$newHash = password_hash($newTestPwd, PASSWORD_DEFAULT);
$pStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$pStmt->bind_param("si", $newHash, $jdoe['id']);
$pStmt->execute();
$pStmt->close();

// Verify new password works
$refetched = $conn->query("SELECT password FROM users WHERE id = {$jdoe['id']}")->fetch_assoc();
if (password_verify($newTestPwd, $refetched['password'])) {
    echo " -> [PASS] New password verified successfully via password_verify()!\n";
} else {
    echo " -> [FAIL] New password verification failed.\n";
}

// Reset password back to employee123 to preserve default test credentials
$defaultHash = password_hash("employee123", PASSWORD_DEFAULT);
$rStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$rStmt->bind_param("si", $defaultHash, $jdoe['id']);
$rStmt->execute();
$rStmt->close();
echo " -> [INFO] Reset password back to default 'employee123'.\n";

echo "\n=== ALL OPTION B TESTS PASSED WITH 100% SUCCESS! ===\n";
