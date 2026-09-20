<?php
/**
 * Automated Verification: File Uploads & CSV Exports (Option C)
 * Skill Compass - Employee Skill Tracking System
 */

require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/upload_helper.php';

echo "=== STARTING OPTION C TEST SUITE: UPLOADS & CSV EXPORTS ===\n\n";

$passCount = 0;
$failCount = 0;

function assert_true($condition, $testName) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $testName\n";
        $passCount++;
    } else {
        echo "[FAIL] $testName\n";
        $failCount++;
    }
}

// =========================================================================
// PART 1: CERTIFICATE UPLOAD VALIDATION TESTS
// =========================================================================
echo "--- 1. Testing Certificate Upload Helper ---\n";

// 1.1 Empty file upload (optional file)
$noFile = [
    'name' => '',
    'type' => '',
    'tmp_name' => '',
    'error' => UPLOAD_ERR_NO_FILE,
    'size' => 0
];
$res = handle_certificate_upload($noFile);
assert_true($res['success'] === true && $res['path'] === '', "No-file upload returns success with empty path");

// 1.2 Disallowed extension (.php or .exe)
$fakePhp = tempnam(sys_get_temp_dir(), 'test_php');
file_put_contents($fakePhp, "<?php echo 'malicious'; ?>");
$invalidExtFile = [
    'name' => 'malicious.php',
    'type' => 'text/php',
    'tmp_name' => $fakePhp,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($fakePhp)
];
$res = handle_certificate_upload($invalidExtFile);
assert_true($res['success'] === false && strpos($res['error'], 'allowed') !== false, "Disallowed file extension is blocked");
unlink($fakePhp);

// 1.3 File exceeds size limit
$largeFile = tempnam(sys_get_temp_dir(), 'test_large');
$fp = fopen($largeFile, 'w');
fseek($fp, 6 * 1024 * 1024); // 6MB
fwrite($fp, '0');
fclose($fp);
$oversizeFile = [
    'name' => 'oversized.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $largeFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($largeFile)
];
$res = handle_certificate_upload($oversizeFile, 5 * 1024 * 1024);
assert_true($res['success'] === false && strpos($res['error'], 'maximum') !== false, "Oversized file (>5MB) is blocked");
unlink($largeFile);

// 1.4 Mime type spoofing (extension .pdf but content is plain text)
$fakePdf = tempnam(sys_get_temp_dir(), 'test_fake');
file_put_contents($fakePdf, "This is not a real PDF document header.");
$spoofedFile = [
    'name' => 'fake.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $fakePdf,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($fakePdf)
];
$res = handle_certificate_upload($spoofedFile);
assert_true($res['success'] === false && strpos($res['error'], 'content does not match') !== false, "Spoofed MIME type file is blocked");
unlink($fakePdf);

// 1.5 Valid PNG upload (1x1 pixel PNG)
$validPng = tempnam(sys_get_temp_dir(), 'test_png');
$pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($validPng, $pngContent);
$realPngFile = [
    'name' => 'aws_cert.png',
    'type' => 'image/png',
    'tmp_name' => $validPng,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pngContent)
];
$res = handle_certificate_upload($realPngFile);
assert_true($res['success'] === true && strpos($res['path'], 'uploads/certificates/cert_') !== false, "Valid PNG upload succeeds and generates safe path");
if ($res['success'] && !empty($res['path'])) {
    $createdFile = __DIR__ . '/../' . $res['path'];
    assert_true(file_exists($createdFile), "Uploaded PNG file exists on server disk");
    @unlink($createdFile); // Clean up test artifact
}
unlink($validPng);

// 1.6 Valid PDF upload
$validPdf = tempnam(sys_get_temp_dir(), 'test_pdf');
$pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 3 3]>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000101 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n147\n%%EOF\n";
file_put_contents($validPdf, $pdfContent);
$realPdfFile = [
    'name' => 'gcp_architect.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $validPdf,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($pdfContent)
];
$res = handle_certificate_upload($realPdfFile);
assert_true($res['success'] === true && strpos($res['path'], '.pdf') !== false, "Valid PDF upload succeeds");
if ($res['success'] && !empty($res['path'])) {
    $createdFile = __DIR__ . '/../' . $res['path'];
    assert_true(file_exists($createdFile), "Uploaded PDF file exists on server disk");
    @unlink($createdFile); // Clean up test artifact
}
unlink($validPdf);


// =========================================================================
// PART 2: CSV EXPORTS VIA HTTP REQUESTS
// =========================================================================
echo "\n--- 2. Testing Universal CSV Exports via Local Server ---\n";

$baseUrl = 'http://localhost:8080';

// Helper to perform HTTP GET with session cookie
function http_get_with_cookie($url, $cookie = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (!empty($cookie)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $headers, 'body' => $body];
}

// 2.1 Unauthenticated access to export.php
$unauth = http_get_with_cookie("$baseUrl/php/export.php?type=employees");
assert_true($unauth['code'] === 302, "Unauthenticated request to export.php redirects (HTTP 302)");

// 2.2 Authenticate with CSRF token and grab session cookie
// First, fetch index.php to obtain session cookie and CSRF token
$indexRes = http_get_with_cookie("$baseUrl/index.php");
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $indexRes['headers'], $cookieMatches);
$initialCookie = !empty($cookieMatches[1]) ? implode('; ', $cookieMatches[1]) : '';

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $indexRes['body'], $tokenMatches);
$csrfToken = $tokenMatches[1] ?? '';
assert_true(!empty($csrfToken), "Extracted valid CSRF token from login form");

// Now authenticate as admin using the cookie and CSRF token
$ch = curl_init("$baseUrl/php/auth.php");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $csrfToken
]));
curl_setopt($ch, CURLOPT_COOKIE, $initialCookie);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $res, $matches);
$sessionCookie = !empty($matches[1]) ? implode('; ', $matches[1]) : $initialCookie;
curl_close($ch);
assert_true(strpos($res, 'dashboard.php') !== false, "Admin session authenticated successfully");

// 2.3 Employees CSV Export
$empExport = http_get_with_cookie("$baseUrl/php/export.php?type=employees", $sessionCookie);
assert_true($empExport['code'] === 200, "Employees CSV export returns HTTP 200");
assert_true(strpos($empExport['headers'], 'Content-Type: text/csv') !== false, "Employees export returns text/csv Content-Type");
assert_true(str_starts_with($empExport['body'], "\xEF\xBB\xBF"), "Employees CSV starts with UTF-8 BOM for Excel compatibility");
assert_true(strpos($empExport['body'], 'Employee ID') !== false && strpos($empExport['body'], 'Email') !== false, "Employees CSV has correct header columns");

// 2.4 Skills CSV Export
$skillsExport = http_get_with_cookie("$baseUrl/php/export.php?type=skills", $sessionCookie);
assert_true($skillsExport['code'] === 200, "Skills CSV export returns HTTP 200");
assert_true(strpos($skillsExport['body'], 'Skill ID') !== false && strpos($skillsExport['body'], 'Skill Name') !== false, "Skills CSV has correct header columns");

// 2.5 Certifications CSV Export
$certsExport = http_get_with_cookie("$baseUrl/php/export.php?type=certifications", $sessionCookie);
assert_true($certsExport['code'] === 200, "Certifications CSV export returns HTTP 200");
assert_true(strpos($certsExport['body'], 'Cert ID') !== false && strpos($certsExport['body'], 'Certification Title') !== false, "Certifications CSV has correct header columns");

// 2.6 Trainings CSV Export
$trainingsExport = http_get_with_cookie("$baseUrl/php/export.php?type=trainings", $sessionCookie);
assert_true($trainingsExport['code'] === 200, "Trainings CSV export returns HTTP 200");
assert_true(strpos($trainingsExport['body'], 'Training ID') !== false && strpos($trainingsExport['body'], 'Training Title') !== false, "Trainings CSV has correct header columns");

// 2.7 Reports (Comprehensive Audit) CSV Export
$reportsExport = http_get_with_cookie("$baseUrl/php/export.php?type=reports", $sessionCookie);
assert_true($reportsExport['code'] === 200, "Audit Reports CSV export returns HTTP 200");
assert_true(strpos($reportsExport['body'], 'ORGANIZATIONAL COMPREHENSIVE AUDIT REPORT') !== false && strpos($reportsExport['body'], 'DEPARTMENT BENCHMARKS') !== false, "Reports CSV has correct comprehensive sections and headers");

// 2.8 Single Employee Skills CSV Export
$empSkillsExport = http_get_with_cookie("$baseUrl/php/export.php?type=employee_skills&id=3", $sessionCookie);
assert_true($empSkillsExport['code'] === 200, "Employee Skills CSV export returns HTTP 200");
assert_true(strpos($empSkillsExport['body'], 'Skill Name') !== false && strpos($empSkillsExport['body'], 'Proficiency (%)') !== false, "Employee Skills CSV has correct header columns");


// =========================================================================
// SUMMARY
// =========================================================================
echo "\n==========================================\n";
echo "OPTION C TEST RESULTS:\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";
echo "==========================================\n";

if ($failCount === 0) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "TEST FAILURES DETECTED.\n";
    exit(1);
}
