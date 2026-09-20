<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// If user is admin or HR, redirect to admin dashboard
if ($_SESSION["role"] == "admin" || $_SESSION["role"] == "hr") {
    header("location: dashboard.php");
    exit;
} elseif ($_SESSION["role"] == "manager") {
    header("location: manager_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

$employeeId = $_SESSION["id"];
$employeeSkills = [];
$employeeCertifications = [];
$upcomingTrainings = [];
$myRequests = [];
$allCatalogSkills = [];
$availableTrainings = [];

// Get employee skills
$sql = "SELECT es.proficiency, es.last_updated, s.name as skill_name, s.category 
        FROM employee_skills es 
        JOIN skills s ON es.skill_id = s.id 
        WHERE es.employee_id = ? 
        ORDER BY es.proficiency DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $employeeSkills[] = $row;
    }
    $stmt->close();
}

// Get employee certifications
$sql = "SELECT c.id, c.name, c.issuing_body, c.issue_date, c.expiry_date, c.certificate_url 
        FROM certifications c 
        WHERE c.employee_id = ? 
        ORDER BY c.issue_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $employeeCertifications[] = $row;
    }
    $stmt->close();
}

// Get upcoming trainings for employee
$sql = "SELECT id, title, description, start_date, end_date, status 
        FROM trainings 
        WHERE employee_id = ? 
        ORDER BY start_date ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcomingTrainings[] = $row;
    }
    $stmt->close();
}

// Get employee requests
$sql = "SELECT r.*, u.first_name as reviewer_first, u.last_name as reviewer_last 
        FROM requests r 
        LEFT JOIN users u ON r.reviewed_by = u.id 
        WHERE r.employee_id = ? 
        ORDER BY r.created_at DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $myRequests[] = $row;
    }
    $stmt->close();
}

// Count pending requests
$pendingRequestsCount = 0;
foreach ($myRequests as $req) {
    if ($req['status'] === 'pending') {
        $pendingRequestsCount++;
    }
}

// Fetch all catalog skills for dropdown
$skillRes = $conn->query("SELECT id, name, category FROM skills ORDER BY name ASC");
if ($skillRes) {
    while ($row = $skillRes->fetch_assoc()) {
        $allCatalogSkills[] = $row;
    }
}

// Fetch general available/planned trainings for enrollment dropdown
$trainRes = $conn->query("SELECT MIN(id) as id, title, MIN(start_date) as start_date, MIN(end_date) as end_date FROM trainings WHERE status IN ('Planned', 'In Progress') GROUP BY title ORDER BY start_date ASC");
if ($trainRes) {
    while ($row = $trainRes->fetch_assoc()) {
        $availableTrainings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Skill Compass</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-compass"></i> <span>Skill Compass</span></h2>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item active">
                    <a href="employee_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#skills-section">
                        <i class="fas fa-lightbulb"></i>
                        <span>My Skills</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#requests-section">
                        <i class="fas fa-paper-plane"></i>
                        <span>My Requests</span>
                        <?php if ($pendingRequestsCount > 0): ?>
                            <span class="badge badge-warning" style="margin-left: auto; font-size: 0.75rem; padding: 0.2rem 0.5rem;"><?php echo $pendingRequestsCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#trainings-section">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Trainings</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#certifications-section">
                        <i class="fas fa-certificate"></i>
                        <span>Certifications</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="profile.php">
                        <i class="fas fa-user-shield"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="php/auth.php?action=logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="content-area">
            <div class="top-bar">
                <div>
                    <h1 class="page-title">Employee Portal</h1>
                    <p style="color: #64748b; margin: 0; font-size: 0.9rem;">Track skills, submit certifications, and manage training requests</p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button type="button" class="btn btn-primary" onclick="openRequestModal()" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-plus-circle"></i> Submit New Request
                    </button>
                    <a href="profile.php" class="user-menu" style="text-decoration: none; color: inherit;" title="Manage Profile & Password">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                    </a>
                </div>
            </div>


            <!-- Alerts -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success" style="background-color: #e8f5e9; color: #2e7d32; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #43a047; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="background-color: #ffebee; color: #c62828; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #e53935; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($employeeSkills); ?></h3>
                        <p>Verified Skills</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(38, 166, 154, 0.1); color: #26a69a;">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($employeeCertifications); ?></h3>
                        <p>Certifications</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(255, 179, 0, 0.1); color: #ffb300;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($upcomingTrainings); ?></h3>
                        <p>Trainings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pendingRequestsCount; ?></h3>
                        <p>Pending Approvals</p>
                    </div>
                </div>
            </div>

            <!-- My Requests & Approvals Section -->
            <div class="card" id="requests-section">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-history" style="color: #6366f1; margin-right: 0.5rem;"></i> My Requests & Approval Status</h2>
                    <button type="button" class="btn btn-outline" onclick="openRequestModal()">
                        <i class="fas fa-plus"></i> New Request
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Request Details</th>
                                <th>Submitted On</th>
                                <th>Status</th>
                                <th>Manager Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myRequests)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #64748b; padding: 2rem;">
                                        <i class="fas fa-paper-plane" style="font-size: 2rem; margin-bottom: 0.5rem; color: #cbd5e1; display: block;"></i>
                                        You have not submitted any self-service requests yet. Click <strong>"Submit New Request"</strong> to submit skills, certifications, or training enrollments.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myRequests as $req): ?>
                                    <tr>
                                        <td>
                                            <span class="badge-type type-<?php echo htmlspecialchars($req['type']); ?>">
                                                <?php echo htmlspecialchars($req['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($req['title']); ?></strong>
                                            <?php 
                                                $reqDetails = json_decode($req['details'], true);
                                                if (!empty($reqDetails['notes'])): 
                                            ?>
                                                <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">
                                                    <i class="fas fa-comment-alt"></i> <?php echo htmlspecialchars($reqDetails['notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                        <td>
                                            <?php if ($req['status'] === 'approved'): ?>
                                                <span class="badge-status badge-approved">
                                                    <i class="fas fa-check-circle"></i> Approved
                                                </span>
                                            <?php elseif ($req['status'] === 'rejected'): ?>
                                                <span class="badge-status badge-rejected">
                                                    <i class="fas fa-times-circle"></i> Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-status badge-pending">
                                                    <i class="fas fa-clock"></i> Pending Review
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($req['review_notes'])): ?>
                                                <span style="font-size: 0.85rem; color: #334155;">
                                                    <?php echo htmlspecialchars($req['review_notes']); ?>
                                                </span>
                                                <?php if (!empty($req['reviewer_first'])): ?>
                                                    <div style="font-size: 0.75rem; color: #94a3b8;">
                                                        By <?php echo htmlspecialchars($req['reviewer_first'] . ' ' . $req['reviewer_last']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif ($req['status'] === 'pending'): ?>
                                                <span style="color: #94a3b8; font-size: 0.85rem;">Awaiting manager review</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.85rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- My Skills Section -->
            <div class="card" id="skills-section">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-lightbulb" style="color: #f59e0b; margin-right: 0.5rem;"></i> My Verified Skills</h2>
                    <button type="button" class="btn btn-outline" onclick="openRequestModal('skill')">
                        <i class="fas fa-plus"></i> Request Skill Addition
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Category</th>
                                <th>Proficiency</th>
                                <th>Last Verified</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employeeSkills)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #64748b; padding: 2rem;">No skills verified yet. Submit a skill endorsement request above!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employeeSkills as $skill): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong></td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($skill['category'] ?? 'General'); ?></span></td>
                                        <td style="width: 35%;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="progress-container" style="flex: 1; height: 8px; margin: 0; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                                    <div class="progress-bar" style="width: <?php echo $skill['proficiency']; ?>%; height: 100%; background: #3b82f6;"></div>
                                                </div>
                                                <span style="font-weight: 600; min-width: 40px; font-size: 0.85rem;"><?php echo $skill['proficiency']; ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($skill['last_updated'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Upcoming Trainings Section -->
            <div class="card" id="trainings-section">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-graduation-cap" style="color: #0284c7; margin-right: 0.5rem;"></i> My Trainings</h2>
                    <button type="button" class="btn btn-outline" onclick="openRequestModal('training')">
                        <i class="fas fa-plus"></i> Request Training Enrollment
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Training</th>
                                <th>Description</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingTrainings)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #64748b; padding: 2rem;">No training sessions scheduled. Submit an enrollment request!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcomingTrainings as $training): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($training['title']); ?></strong></td>
                                        <td style="color: #64748b;"><?php echo htmlspecialchars($training['description'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['start_date'])); ?></td>
                                        <td><?php echo !empty($training['end_date']) ? date('M d, Y', strtotime($training['end_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $training['status'] === 'Completed' ? 'badge-success' : 
                                                    ($training['status'] === 'In Progress' ? 'badge-warning' : 'badge-primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($training['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Certifications Section -->
            <div class="card" id="certifications-section">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-certificate" style="color: #10b981; margin-right: 0.5rem;"></i> My Certifications</h2>
                    <button type="button" class="btn btn-outline" onclick="openRequestModal('certification')">
                        <i class="fas fa-plus"></i> Submit Certification
                    </button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Certification</th>
                                <th>Issuing Body</th>
                                <th>Issue Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employeeCertifications)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No verified certifications yet. Submit your certificates to add them to your record!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employeeCertifications as $cert): ?>
                                    <?php 
                                        $isExpired = !empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < time();
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cert['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cert['issuing_body'] ?? '-'); ?></td>
                                        <td><?php echo !empty($cert['issue_date']) ? date('M d, Y', strtotime($cert['issue_date'])) : '-'; ?></td>
                                        <td><?php echo !empty($cert['expiry_date']) ? date('M d, Y', strtotime($cert['expiry_date'])) : 'No Expiry'; ?></td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge badge-danger">Expired</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($cert['certificate_url'])): ?>
                                                <?php $isUploaded = strpos($cert['certificate_url'], 'uploads/') === 0; ?>
                                                <a href="<?php echo htmlspecialchars($cert['certificate_url']); ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.75rem;">
                                                    <i class="fas <?php echo $isUploaded ? 'fa-file-pdf' : 'fa-external-link-alt'; ?>"></i> <?php echo $isUploaded ? 'Document' : 'View'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.85rem;">Verified</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================================================== -->
    <!-- REQUEST MODAL                                                  -->
    <!-- ============================================================== -->
    <div class="modal-overlay" id="requestModal" onclick="handleBackdropClick(event)">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3><i class="fas fa-paper-plane" style="color: #2563eb;"></i> Submit Request to Manager</h3>
                <button type="button" class="modal-close" onclick="closeRequestModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Tabs -->
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" id="tabBtn-skill" onclick="switchRequestTab('skill')">
                        <i class="fas fa-lightbulb"></i> Skill Endorsement
                    </button>
                    <button type="button" class="tab-btn" id="tabBtn-certification" onclick="switchRequestTab('certification')">
                        <i class="fas fa-certificate"></i> Add Certification
                    </button>
                    <button type="button" class="tab-btn" id="tabBtn-training" onclick="switchRequestTab('training')">
                        <i class="fas fa-graduation-cap"></i> Training Enrollment
                    </button>
                </div>

                <!-- FORM: Skill Endorsement -->
                <div class="tab-panel active" id="tabPanel-skill">
                    <form action="php/handle_request.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="request_type" value="skill">
                        
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="skill_select" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Select Skill</label>
                            <select id="skill_select" name="skill_id" required style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;" onchange="handleSkillSelectChange(this)">
                                <option value="">-- Choose from Skill Catalog --</option>
                                <?php foreach ($allCatalogSkills as $cs): ?>
                                    <option value="<?php echo $cs['id']; ?>"><?php echo htmlspecialchars($cs['name'] . ' (' . ($cs['category'] ?? 'General') . ')'); ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Suggest a New Skill</option>
                            </select>
                        </div>

                        <!-- New skill fields (conditional) -->
                        <div id="newSkillFields" style="display: none; background: #f8fafc; padding: 1rem; border-radius: 6px; border: 1px dashed #cbd5e1; margin-bottom: 1.25rem;">
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <label for="new_skill_name" style="font-weight: 500; font-size: 0.9rem;">New Skill Name</label>
                                <input type="text" id="new_skill_name" name="new_skill_name" placeholder="e.g. Next.js, Rust, Kubernetes" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label for="new_skill_category" style="font-weight: 500; font-size: 0.9rem;">Category</label>
                                <input type="text" id="new_skill_category" name="new_skill_category" placeholder="e.g. Programming, Cloud, Design" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                                <label for="proficiencySlider" style="font-weight: 500;">Proficiency Level</label>
                                <span id="profValue" style="font-weight: bold; color: #2563eb; background: #eff6ff; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.9rem;">75%</span>
                            </div>
                            <input type="range" id="proficiencySlider" name="proficiency" min="10" max="100" value="75" step="5" style="width: 100%;" oninput="updateProfDisplay(this.value)">
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                <span>Beginner (10-40%)</span>
                                <span>Intermediate (45-70%)</span>
                                <span>Advanced (75-90%)</span>
                                <span>Expert (95-100%)</span>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="skill_notes" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Evidence / Justification</label>
                            <textarea id="skill_notes" name="notes" rows="3" placeholder="Describe your experience, projects delivered, or coursework completed to justify this rating..." style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit to Manager</button>
                        </div>
                    </form>
                </div>

                <!-- FORM: Add Certification -->
                <div class="tab-panel" id="tabPanel-certification">
                    <form action="php/handle_request.php" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="request_type" value="certification">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="cert_name" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Certification Title *</label>
                            <input type="text" id="cert_name" name="cert_name" required placeholder="e.g. AWS Certified Solutions Architect - Associate" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="issuing_body" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Issuing Organization *</label>
                            <input type="text" id="issuing_body" name="issuing_body" required placeholder="e.g. Amazon Web Services, Google, Microsoft, Cisco" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="issue_date" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Issue Date</label>
                                <input type="date" id="issue_date" name="issue_date" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label for="expiry_date" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Expiration Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="emp_cert_file" style="font-weight: 500; display: block; margin-bottom: 0.4rem;"><i class="fas fa-file-upload"></i> Upload Certificate Proof (.pdf, .png, .jpg, max 5MB)</label>
                            <input type="file" id="emp_cert_file" name="certificate_file" accept=".pdf,.png,.jpg,.jpeg" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="certificate_url" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Or Credential URL / Verification ID</label>
                            <input type="text" id="certificate_url" name="certificate_url" placeholder="https://www.credly.com/badges/... or license code" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="cert_notes" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Notes / Remarks</label>
                            <textarea id="cert_notes" name="notes" rows="2" placeholder="Any extra information for your manager..." style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Certification</button>
                        </div>
                    </form>
                </div>

                <!-- FORM: Training Enrollment -->
                <div class="tab-panel" id="tabPanel-training">
                    <form action="php/handle_request.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="submit_request">
                        <input type="hidden" name="request_type" value="training">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="font-weight: 500; display: block; margin-bottom: 0.5rem;">Enrollment Mode</label>
                            <div style="display: flex; gap: 1.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                                    <input type="radio" name="training_mode" value="existing" checked onchange="handleTrainingModeChange(this.value)"> Enroll in Scheduled Training
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                                    <input type="radio" name="training_mode" value="custom" onchange="handleTrainingModeChange(this.value)"> Request New Training Topic
                                </label>
                            </div>
                        </div>

                        <!-- Existing training select -->
                        <div id="existingTrainingGroup" class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="training_id" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Choose Scheduled Training</label>
                            <select id="training_id" name="training_id" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                                <option value="">-- Select Planned Training Session --</option>
                                <?php foreach ($availableTrainings as $at): ?>
                                    <option value="<?php echo $at['id']; ?>">
                                        <?php echo htmlspecialchars($at['title'] . ' (Starts: ' . date('M d, Y', strtotime($at['start_date'])) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom topic input (hidden by default) -->
                        <div id="customTrainingGroup" style="display: none; background: #f8fafc; padding: 1rem; border-radius: 6px; border: 1px dashed #cbd5e1; margin-bottom: 1.25rem;">
                            <div class="form-group" style="margin-bottom: 0.75rem;">
                                <label for="custom_topic" style="font-weight: 500; font-size: 0.9rem;">Desired Training Topic / Course</label>
                                <input type="text" id="custom_topic" name="custom_topic" placeholder="e.g. Advanced TypeScript Architecture" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label for="related_skill_id" style="font-weight: 500; font-size: 0.9rem;">Related Skill</label>
                                <select id="related_skill_id" name="related_skill_id" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                                    <option value="">-- Select Related Skill (Optional) --</option>
                                    <?php foreach ($allCatalogSkills as $cs): ?>
                                        <option value="<?php echo $cs['id']; ?>"><?php echo htmlspecialchars($cs['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label for="training_notes" style="font-weight: 500; display: block; margin-bottom: 0.4rem;">Learning Goals / Justification</label>
                            <textarea id="training_notes" name="notes" rows="3" placeholder="How will this training help your role or team projects?" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" class="btn btn-outline" onclick="closeRequestModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Training Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openRequestModal(preferredTab = 'skill') {
            const modal = document.getElementById('requestModal');
            if (modal) {
                modal.classList.add('active');
                switchRequestTab(preferredTab);
            }
        }

        function closeRequestModal() {
            const modal = document.getElementById('requestModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function handleBackdropClick(event) {
            if (event.target.id === 'requestModal') {
                closeRequestModal();
            }
        }

        function switchRequestTab(tabName) {
            const tabs = ['skill', 'certification', 'training'];
            tabs.forEach(t => {
                const btn = document.getElementById('tabBtn-' + t);
                const panel = document.getElementById('tabPanel-' + t);
                if (btn && panel) {
                    if (t === tabName) {
                        btn.classList.add('active');
                        panel.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                        panel.classList.remove('active');
                    }
                }
            });
        }

        function updateProfDisplay(val) {
            const label = document.getElementById('profValue');
            if (label) {
                label.textContent = val + '%';
            }
        }

        function handleSkillSelectChange(sel) {
            const newFields = document.getElementById('newSkillFields');
            if (newFields) {
                newFields.style.display = (sel.value === 'new') ? 'block' : 'none';
            }
        }

        function handleTrainingModeChange(mode) {
            const existGroup = document.getElementById('existingTrainingGroup');
            const customGroup = document.getElementById('customTrainingGroup');
            if (mode === 'existing') {
                if (existGroup) existGroup.style.display = 'block';
                if (customGroup) customGroup.style.display = 'none';
            } else {
                if (existGroup) existGroup.style.display = 'none';
                if (customGroup) customGroup.style.display = 'block';
            }
        }
    </script>
</body>
</html>
