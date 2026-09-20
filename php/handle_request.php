<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload_helper.php';

$userId = $_SESSION["id"];
$userRole = $_SESSION["role"];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ==========================================
// 1. SUBMIT REQUEST (Employee)
// ==========================================
if ($action === 'submit_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('../employee_dashboard.php');
    $requestType = trim($_POST['request_type'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($requestType, ['skill', 'certification', 'training'])) {
        header("Location: ../employee_dashboard.php?error=" . urlencode("Invalid request type."));
        exit;
    }

    $targetId = null;
    $title = '';
    $details = [];

    if ($requestType === 'skill') {
        $skillId = $_POST['skill_id'] ?? '';
        $proficiency = max(1, min(100, intval($_POST['proficiency'] ?? 50)));

        if ($skillId === 'new') {
            $newSkillName = trim($_POST['new_skill_name'] ?? '');
            $newCategory = trim($_POST['new_skill_category'] ?? 'General');
            if (empty($newSkillName)) {
                header("Location: ../employee_dashboard.php?error=" . urlencode("Please enter the new skill name."));
                exit;
            }
            $title = "New Skill: " . $newSkillName . " (" . $proficiency . "%)";
            $details = [
                'is_new_skill' => true,
                'skill_name' => $newSkillName,
                'category' => $newCategory,
                'proficiency' => $proficiency,
                'notes' => $notes
            ];
        } else {
            $skillId = intval($skillId);
            if ($skillId <= 0) {
                header("Location: ../employee_dashboard.php?error=" . urlencode("Please select a valid skill."));
                exit;
            }
            // Fetch skill name
            $stmt = $conn->prepare("SELECT name FROM skills WHERE id = ?");
            $stmt->bind_param("i", $skillId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $skillName = $res['name'] ?? 'Skill #' . $skillId;
            $stmt->close();

            $targetId = $skillId;
            $title = "Skill Endorsement: " . $skillName . " (" . $proficiency . "%)";
            $details = [
                'is_new_skill' => false,
                'skill_id' => $skillId,
                'skill_name' => $skillName,
                'proficiency' => $proficiency,
                'notes' => $notes
            ];
        }
    } elseif ($requestType === 'certification') {
        $certName = trim($_POST['cert_name'] ?? '');
        $issuingBody = trim($_POST['issuing_body'] ?? '');
        $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $certUrl = trim($_POST['certificate_url'] ?? '');

        // Handle uploaded certificate document if present
        if (!empty($_FILES['certificate_file']['name'])) {
            $upResult = handle_certificate_upload($_FILES['certificate_file']);
            if (!$upResult['success']) {
                header("Location: ../employee_dashboard.php?error=" . urlencode($upResult['error']));
                exit;
            }
            $certUrl = $upResult['path'];
        }

        if (empty($certName) || empty($issuingBody)) {
            header("Location: ../employee_dashboard.php?error=" . urlencode("Certification name and issuing body are required."));
            exit;
        }

        $title = "Certification: " . $certName . " (" . $issuingBody . ")";
        $details = [
            'name' => $certName,
            'issuing_body' => $issuingBody,
            'issue_date' => $issueDate,
            'expiry_date' => $expiryDate,
            'certificate_url' => $certUrl,
            'notes' => $notes
        ];
    } elseif ($requestType === 'training') {
        $trainingMode = $_POST['training_mode'] ?? 'existing';

        if ($trainingMode === 'existing') {
            $trainingId = intval($_POST['training_id'] ?? 0);
            if ($trainingId <= 0) {
                header("Location: ../employee_dashboard.php?error=" . urlencode("Please select a training to enroll in."));
                exit;
            }
            $stmt = $conn->prepare("SELECT title FROM trainings WHERE id = ?");
            $stmt->bind_param("i", $trainingId);
            $stmt->execute();
            $tRow = $stmt->get_result()->fetch_assoc();
            $trainingTitle = $tRow['title'] ?? 'Training #' . $trainingId;
            $stmt->close();

            $targetId = $trainingId;
            $title = "Enrollment: " . $trainingTitle;
            $details = [
                'mode' => 'existing',
                'training_id' => $trainingId,
                'training_title' => $trainingTitle,
                'notes' => $notes
            ];
        } else {
            $topic = trim($_POST['custom_topic'] ?? '');
            $skillId = !empty($_POST['related_skill_id']) ? intval($_POST['related_skill_id']) : null;
            if (empty($topic)) {
                header("Location: ../employee_dashboard.php?error=" . urlencode("Please specify the training topic."));
                exit;
            }
            $title = "New Training Request: " . $topic;
            $details = [
                'mode' => 'custom',
                'topic' => $topic,
                'related_skill_id' => $skillId,
                'notes' => $notes
            ];
        }
    }

    $detailsJson = json_encode($details);

    $stmt = $conn->prepare("INSERT INTO requests (employee_id, type, target_id, title, details, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isisis", $userId, $requestType, $targetId, $title, $detailsJson);
    // Note: mysqli bind_param types: i=int, s=string. targetId may be null.
    // Let's bind properly:
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO requests (employee_id, type, target_id, title, details, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isiss", $userId, $requestType, $targetId, $title, $detailsJson);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: ../employee_dashboard.php?msg=" . urlencode("Your request has been submitted to your manager for review."));
        exit;
    } else {
        $err = $conn->error;
        $stmt->close();
        header("Location: ../employee_dashboard.php?error=" . urlencode("Error saving request: " . $err));
        exit;
    }
}

// ==========================================
// 2. REVIEW REQUEST (Manager / HR / Admin)
// ==========================================
if ($action === 'review_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectBack = $_POST['redirect_back'] ?? '../manager_dashboard.php';
    require_csrf($redirectBack);

    if ($userRole !== 'manager' && $userRole !== 'admin' && $userRole !== 'hr') {
        header("Location: ../index.php?error=" . urlencode("Unauthorized access."));
        exit;
    }

    $requestId = intval($_POST['request_id'] ?? 0);
    $statusDecision = trim($_POST['decision'] ?? ''); // 'approved' or 'rejected'
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if ($requestId <= 0 || !in_array($statusDecision, ['approved', 'rejected'])) {
        header("Location: $redirectBack?error=" . urlencode("Invalid decision or request ID."));
        exit;
    }

    // Fetch the request
    $stmt = $conn->prepare("SELECT r.*, u.department, u.first_name, u.last_name FROM requests r JOIN users u ON r.employee_id = u.id WHERE r.id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$req) {
        header("Location: $redirectBack?error=" . urlencode("Request not found."));
        exit;
    }

    if ($req['status'] !== 'pending') {
        header("Location: $redirectBack?error=" . urlencode("This request has already been reviewed."));
        exit;
    }

    // Process Approval Fulfillment
    if ($statusDecision === 'approved') {
        $details = json_decode($req['details'], true) ?: [];
        $employeeId = $req['employee_id'];

        if ($req['type'] === 'skill') {
            $proficiency = intval($details['proficiency'] ?? 50);

            if (!empty($details['is_new_skill'])) {
                // Check if skill already exists by name
                $checkSkill = $conn->prepare("SELECT id FROM skills WHERE name = ?");
                $checkSkill->bind_param("s", $details['skill_name']);
                $checkSkill->execute();
                $sRow = $checkSkill->get_result()->fetch_assoc();
                $checkSkill->close();

                if ($sRow) {
                    $skillId = $sRow['id'];
                } else {
                    $newSkillStmt = $conn->prepare("INSERT INTO skills (name, category, description) VALUES (?, ?, ?)");
                    $desc = "Added via employee self-service request";
                    $newSkillStmt->bind_param("sss", $details['skill_name'], $details['category'], $desc);
                    $newSkillStmt->execute();
                    $skillId = $conn->insert_id;
                    $newSkillStmt->close();
                }
            } else {
                $skillId = intval($details['skill_id'] ?? 0);
            }

            if ($skillId > 0) {
                // Insert or Update employee_skills
                $checkEs = $conn->prepare("SELECT id FROM employee_skills WHERE employee_id = ? AND skill_id = ?");
                $checkEs->bind_param("ii", $employeeId, $skillId);
                $checkEs->execute();
                $esRow = $checkEs->get_result()->fetch_assoc();
                $checkEs->close();

                if ($esRow) {
                    $updateEs = $conn->prepare("UPDATE employee_skills SET proficiency = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateEs->bind_param("ii", $proficiency, $esRow['id']);
                    $updateEs->execute();
                    $updateEs->close();
                } else {
                    $insertEs = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_id, proficiency) VALUES (?, ?, ?)");
                    $insertEs->bind_param("iii", $employeeId, $skillId, $proficiency);
                    $insertEs->execute();
                    $insertEs->close();
                }
            }
        } elseif ($req['type'] === 'certification') {
            $certName = $details['name'] ?? 'Certification';
            $issuingBody = $details['issuing_body'] ?? '';
            $issueDate = !empty($details['issue_date']) ? $details['issue_date'] : null;
            $expiryDate = !empty($details['expiry_date']) ? $details['expiry_date'] : null;
            $certUrl = $details['certificate_url'] ?? '';
            $desc = "Approved via self-service verification";

            $insCert = $conn->prepare("INSERT INTO certifications (employee_id, name, description, issuing_body, issue_date, expiry_date, certificate_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insCert->bind_param("issssss", $employeeId, $certName, $desc, $issuingBody, $issueDate, $expiryDate, $certUrl);
            $insCert->execute();
            $insCert->close();
        } elseif ($req['type'] === 'training') {
            if (($details['mode'] ?? '') === 'existing') {
                $trainingId = intval($details['training_id'] ?? 0);
                if ($trainingId > 0) {
                    // Update training assignment or duplicate for employee if different
                    $tCheck = $conn->prepare("SELECT title, description, related_skill_id, start_date, end_date FROM trainings WHERE id = ?");
                    $tCheck->bind_param("i", $trainingId);
                    $tCheck->execute();
                    $tData = $tCheck->get_result()->fetch_assoc();
                    $tCheck->close();

                    if ($tData) {
                        $insT = $conn->prepare("INSERT INTO trainings (title, description, employee_id, start_date, end_date, status, related_skill_id, created_by) VALUES (?, ?, ?, ?, ?, 'Planned', ?, ?)");
                        $insT->bind_param("ssissii", $tData['title'], $tData['description'], $employeeId, $tData['start_date'], $tData['end_date'], $tData['related_skill_id'], $userId);
                        $insT->execute();
                        $insT->close();
                    }
                }
            } else {
                $topic = $details['topic'] ?? 'Requested Training';
                $skillId = !empty($details['related_skill_id']) ? intval($details['related_skill_id']) : null;
                $today = date('Y-m-d');
                $nextMonth = date('Y-m-d', strtotime('+30 days'));
                $desc = "Custom training request approved by manager";

                $insT = $conn->prepare("INSERT INTO trainings (title, description, employee_id, start_date, end_date, status, related_skill_id, created_by) VALUES (?, ?, ?, ?, ?, 'Planned', ?, ?)");
                $insT->bind_param("ssissii", $topic, $desc, $employeeId, $today, $nextMonth, $skillId, $userId);
                $insT->execute();
                $insT->close();
            }
        }
    }

    // Update the request record
    $updateReq = $conn->prepare("UPDATE requests SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $updateReq->bind_param("ssii", $statusDecision, $reviewNotes, $userId, $requestId);
    $updateReq->execute();
    $updateReq->close();

    $actionWord = $statusDecision === 'approved' ? 'approved and fulfilled' : 'rejected';
    header("Location: $redirectBack?msg=" . urlencode("Request #$requestId has been $actionWord."));
    exit;
}

header("Location: ../dashboard.php");
exit;
