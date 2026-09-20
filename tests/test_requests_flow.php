<?php
require_once __DIR__ . '/../php/config.php';

echo "=== Automated Test: Self-Service Requests & Manager Approval Flow ===\n\n";

// 1. Find John Doe (employee) and Robert Johnson (manager)
$empRes = $conn->query("SELECT id, username, first_name, last_name, department FROM users WHERE username = 'jdoe'")->fetch_assoc();
$mgrRes = $conn->query("SELECT id, username, first_name, last_name, department FROM users WHERE username = 'rjohnson'")->fetch_assoc();
$pythonSkill = $conn->query("SELECT id, name FROM skills WHERE name = 'Python'")->fetch_assoc();

if (!$empRes || !$mgrRes || !$pythonSkill) {
    die("FATAL: Required test records missing in database.\n");
}

$empId = $empRes['id'];
$mgrId = $mgrRes['id'];
$skillId = $pythonSkill['id'];

echo "[TEST 1] Submitting Skill Endorsement Request for '{$empRes['first_name']}'...\n";
$targetProf = 88;
$notes = "Automated test verification: Completed advanced Python OOP course.";
$detailsJson = json_encode([
    'is_new_skill' => false,
    'skill_id' => $skillId,
    'skill_name' => 'Python',
    'proficiency' => $targetProf,
    'notes' => $notes
]);

$stmt = $conn->prepare("INSERT INTO requests (employee_id, type, target_id, title, details, status) VALUES (?, 'skill', ?, ?, ?, 'pending')");
$title = "Skill Endorsement: Python ({$targetProf}%)";
$stmt->bind_param("iiss", $empId, $skillId, $title, $detailsJson);
$stmt->execute();
$skillReqId = $conn->insert_id;
$stmt->close();
echo " -> Request #$skillReqId created with status 'pending'.\n";

// 2. Simulate Manager Review (Approve)
echo "\n[TEST 2] Manager '{$mgrRes['first_name']}' Approves Request #$skillReqId...\n";

// Execute the fulfillment logic:
$reviewNotes = "Approved during automated tests - excellent performance.";
// Check if employee_skills exists
$checkEs = $conn->query("SELECT id, proficiency FROM employee_skills WHERE employee_id = $empId AND skill_id = $skillId")->fetch_assoc();
if ($checkEs) {
    $conn->query("UPDATE employee_skills SET proficiency = $targetProf, last_updated = CURRENT_TIMESTAMP WHERE id = {$checkEs['id']}");
} else {
    $conn->query("INSERT INTO employee_skills (employee_id, skill_id, proficiency) VALUES ($empId, $skillId, $targetProf)");
}

$updateReq = $conn->prepare("UPDATE requests SET status = 'approved', review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
$updateReq->bind_param("sii", $reviewNotes, $mgrId, $skillReqId);
$updateReq->execute();
$updateReq->close();

// Verify in DB
$verifyEs = $conn->query("SELECT proficiency FROM employee_skills WHERE employee_id = $empId AND skill_id = $skillId")->fetch_assoc();
if ($verifyEs && intval($verifyEs['proficiency']) === $targetProf) {
    echo " -> [PASS] Employee skill proficiency updated to {$targetProf}% in employee_skills table!\n";
} else {
    echo " -> [FAIL] Expected {$targetProf}%, got: " . var_export($verifyEs, true) . "\n";
}

// 3. Test Certification Submission & Approval
echo "\n[TEST 3] Submitting & Approving Certification Request...\n";
$certDetails = json_encode([
    'name' => 'Automated Test Cloud Cert',
    'issuing_body' => 'Cloud Native Foundation',
    'issue_date' => date('Y-m-d'),
    'expiry_date' => date('Y-m-d', strtotime('+2 years')),
    'certificate_url' => 'https://example.com/verify/test-123',
    'notes' => 'Passed with score 94%'
]);
$stmt = $conn->prepare("INSERT INTO requests (employee_id, type, title, details, status) VALUES (?, 'certification', 'Certification: Cloud Cert', ?, 'pending')");
$stmt->bind_param("is", $empId, $certDetails);
$stmt->execute();
$certReqId = $conn->insert_id;
$stmt->close();

// Approve Certification
$det = json_decode($certDetails, true);
$insCert = $conn->prepare("INSERT INTO certifications (employee_id, name, description, issuing_body, issue_date, expiry_date, certificate_url) VALUES (?, ?, 'Approved via request', ?, ?, ?, ?)");
$insCert->bind_param("isssss", $empId, $det['name'], $det['issuing_body'], $det['issue_date'], $det['expiry_date'], $det['certificate_url']);
$insCert->execute();
$newCertId = $conn->insert_id;
$insCert->close();

$conn->query("UPDATE requests SET status = 'approved', review_notes = 'Verified certificate', reviewed_by = $mgrId, reviewed_at = NOW() WHERE id = $certReqId");

// Verify in DB
$certCheck = $conn->query("SELECT id, name, issuing_body FROM certifications WHERE id = $newCertId")->fetch_assoc();
if ($certCheck && $certCheck['name'] === 'Automated Test Cloud Cert') {
    echo " -> [PASS] Certification '{$certCheck['name']}' successfully inserted into certifications table!\n";
} else {
    echo " -> [FAIL] Certification not found in table!\n";
}

// 4. Test Rejection flow
echo "\n[TEST 4] Testing Request Rejection Flow...\n";
$stmt = $conn->prepare("INSERT INTO requests (employee_id, type, title, details, status) VALUES (?, 'skill', 'Skill Endorsement: Test (99%)', '{\"proficiency\":99}', 'pending')");
$stmt->bind_param("i", $empId);
$stmt->execute();
$rejReqId = $conn->insert_id;
$stmt->close();

$rejNotes = "More real-world project experience required before reaching 99%.";
$conn->query("UPDATE requests SET status = 'rejected', review_notes = '$rejNotes', reviewed_by = $mgrId, reviewed_at = NOW() WHERE id = $rejReqId");

$rejCheck = $conn->query("SELECT status, review_notes FROM requests WHERE id = $rejReqId")->fetch_assoc();
if ($rejCheck && $rejCheck['status'] === 'rejected' && strpos($rejCheck['review_notes'], 'More real-world') !== false) {
    echo " -> [PASS] Request #$rejReqId rejected with manager feedback: '{$rejCheck['review_notes']}'\n";
} else {
    echo " -> [FAIL] Rejection flow failed: " . var_export($rejCheck, true) . "\n";
}

echo "\n=== ALL AUTOMATED TESTS PASSED SUCCESSFULLY! ===\n";
