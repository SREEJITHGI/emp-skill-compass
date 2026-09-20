<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Check role: managers, admin, and HR are allowed
if ($_SESSION["role"] !== "manager" && $_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

$userId = $_SESSION["id"];

// Get manager info to determine their department
$managerDepartment = '';
$sql = "SELECT department, first_name, last_name FROM users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $managerDepartment = $row['department'];
    }
    $stmt->close();
}

// If admin or HR viewing manager dashboard, allow department filter or default to manager's department
$selectedDepartment = isset($_GET['department']) ? trim($_GET['department']) : $managerDepartment;

// Get all departments for filter if admin/hr
$allDepartments = [];
if ($_SESSION["role"] === "admin" || $_SESSION["role"] === "hr") {
    $deptRes = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
    if ($deptRes) {
        while ($dRow = $deptRes->fetch_assoc()) {
            $allDepartments[] = $dRow['department'];
        }
    }
}

// Department Stats
$teamMemberCount = 0;
$teamSkillCount = 0;
$teamActiveTrainings = 0;
$teamCertCount = 0;

// Count team members in department
if (!empty($selectedDepartment)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE department = ? AND role = 'employee'");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $teamMemberCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    // Unique skills in department
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT es.skill_id) as cnt FROM employee_skills es JOIN users u ON es.employee_id = u.id WHERE u.department = ?");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $teamSkillCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    // Active trainings in department
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM trainings t JOIN users u ON t.employee_id = u.id WHERE u.department = ? AND t.status IN ('Planned', 'In Progress')");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $teamActiveTrainings = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();

    // Certifications in department
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM certifications c JOIN users u ON c.employee_id = u.id WHERE u.department = ?");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $teamCertCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
} else {
    // If no specific department assigned, aggregate all employees
    $res = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'employee'");
    $teamMemberCount = $res->fetch_assoc()['cnt'] ?? 0;
    $res = $conn->query("SELECT COUNT(DISTINCT skill_id) as cnt FROM employee_skills");
    $teamSkillCount = $res->fetch_assoc()['cnt'] ?? 0;
    $res = $conn->query("SELECT COUNT(*) as cnt FROM trainings WHERE status IN ('Planned', 'In Progress')");
    $teamActiveTrainings = $res->fetch_assoc()['cnt'] ?? 0;
    $res = $conn->query("SELECT COUNT(*) as cnt FROM certifications");
    $teamCertCount = $res->fetch_assoc()['cnt'] ?? 0;
}

// Fetch Pending Approvals for Team
$pendingRequests = [];
$reqSql = "SELECT r.*, u.first_name, u.last_name, u.email, u.job_title, u.department 
           FROM requests r 
           JOIN users u ON r.employee_id = u.id 
           WHERE r.status = 'pending'";
if (!empty($selectedDepartment)) {
    $reqSql .= " AND u.department = ?";
    $stmt = $conn->prepare($reqSql . " ORDER BY r.created_at ASC");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($reqSql . " ORDER BY r.created_at ASC");
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}
$pendingApprovalsCount = count($pendingRequests);

// Fetch Recently Reviewed Requests
$recentReviews = [];
$histSql = "SELECT r.*, u.first_name, u.last_name, u.department, rev.first_name as rev_first, rev.last_name as rev_last 
            FROM requests r 
            JOIN users u ON r.employee_id = u.id 
            LEFT JOIN users rev ON r.reviewed_by = rev.id 
            WHERE r.status != 'pending'";
if (!empty($selectedDepartment)) {
    $histSql .= " AND u.department = ?";
    $stmt = $conn->prepare($histSql . " ORDER BY r.reviewed_at DESC LIMIT 5");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($histSql . " ORDER BY r.reviewed_at DESC LIMIT 5");
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentReviews[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}

// Get team members list
$teamMembers = [];
$teamSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.job_title, u.department,
            (SELECT COUNT(*) FROM employee_skills WHERE employee_id = u.id) as skill_count,
            (SELECT COUNT(*) FROM trainings WHERE employee_id = u.id AND status = 'In Progress') as active_trainings
            FROM users u WHERE u.role = 'employee'";
if (!empty($selectedDepartment)) {
    $teamSql .= " AND u.department = ?";
    $stmt = $conn->prepare($teamSql . " ORDER BY u.last_name, u.first_name");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($teamSql . " ORDER BY u.last_name, u.first_name");
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $teamMembers[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}

// Get team upcoming/in-progress trainings
$teamTrainings = [];
$trainSql = "SELECT t.id, t.title, t.start_date, t.end_date, t.status, u.first_name, u.last_name, s.name as skill_name
             FROM trainings t
             JOIN users u ON t.employee_id = u.id
             LEFT JOIN skills s ON t.related_skill_id = s.id
             WHERE 1=1";
if (!empty($selectedDepartment)) {
    $trainSql .= " AND u.department = ?";
    $stmt = $conn->prepare($trainSql . " ORDER BY t.start_date DESC LIMIT 5");
    $stmt->bind_param("s", $selectedDepartment);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($trainSql . " ORDER BY t.start_date DESC LIMIT 5");
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $teamTrainings[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Skill Compass</title>
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
                    <a href="manager_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#approvals-section">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Approvals</span>
                        <?php if ($pendingApprovalsCount > 0): ?>
                            <span class="badge badge-warning" style="margin-left: auto; font-size: 0.75rem; padding: 0.2rem 0.5rem;"><?php echo $pendingApprovalsCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="employees.php">
                        <i class="fas fa-users"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="skills.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>Skills</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="trainings.php">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Trainings</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="certifications.php">
                        <i class="fas fa-certificate"></i>
                        <span>Certifications</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
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
                    <h1 class="page-title">Manager Dashboard</h1>
                    <span style="color: var(--gray-dark); font-size: 0.9rem;">
                        Department: <strong><?php echo htmlspecialchars($selectedDepartment ?: 'All Departments'); ?></strong>
                    </span>
                </div>
                <a href="profile.php" class="user-menu" style="text-decoration: none; color: inherit;" title="Manage Profile & Password">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION["name"] ?? 'M', 0, 1)); ?>
                    </div>
                    <div class="user-name">
                        <?php echo htmlspecialchars($_SESSION["name"] ?? 'Manager'); ?>
                        <span style="font-size: 0.8rem; color: var(--gray-dark); display: block;">
                            (<?php echo ucfirst($_SESSION["role"]); ?>)
                        </span>
                    </div>
                </a>
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

            <?php if (!empty($allDepartments)): ?>
            <div class="card" style="margin-bottom: 1.5rem;">
                <form method="GET" action="manager_dashboard.php" style="display: flex; gap: 1rem; align-items: center;">
                    <label for="department" style="font-weight: 500;">Filter Department:</label>
                    <select name="department" id="department" class="search-input" style="max-width: 250px;">
                        <option value="">All Departments</option>
                        <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $selectedDepartment === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="grid-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div class="stat-card card" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem;">
                    <div class="stat-icon" style="background-color: rgba(30, 136, 229, 0.1); color: var(--primary-color); width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.8rem; font-weight: 700;"><?php echo $teamMemberCount; ?></div>
                        <div class="stat-label" style="color: var(--gray-dark); font-size: 0.9rem;">Team Members</div>
                    </div>
                </div>
                
                <div class="stat-card card" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem; <?php echo $pendingApprovalsCount > 0 ? 'border: 2px solid #f59e0b; background: #fffbeb;' : ''; ?>">
                    <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.15); color: #b45309; width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.8rem; font-weight: 700; color: <?php echo $pendingApprovalsCount > 0 ? '#b45309' : 'inherit'; ?>;"><?php echo $pendingApprovalsCount; ?></div>
                        <div class="stat-label" style="color: var(--gray-dark); font-size: 0.9rem;">Pending Approvals</div>
                    </div>
                </div>

                <div class="stat-card card" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem;">
                    <div class="stat-icon" style="background-color: rgba(38, 166, 154, 0.1); color: var(--secondary-color); width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.8rem; font-weight: 700;"><?php echo $teamSkillCount; ?></div>
                        <div class="stat-label" style="color: var(--gray-dark); font-size: 0.9rem;">Department Skills</div>
                    </div>
                </div>
                
                <div class="stat-card card" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem;">
                    <div class="stat-icon" style="background-color: rgba(255, 179, 0, 0.1); color: var(--warning-color); width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.8rem; font-weight: 700;"><?php echo $teamActiveTrainings; ?></div>
                        <div class="stat-label" style="color: var(--gray-dark); font-size: 0.9rem;">Active Trainings</div>
                    </div>
                </div>
                
                <div class="stat-card card" style="display: flex; align-items: center; gap: 1.25rem; padding: 1.25rem;">
                    <div class="stat-icon" style="background-color: rgba(67, 160, 71, 0.1); color: var(--success-color); width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.8rem; font-weight: 700;"><?php echo $teamCertCount; ?></div>
                        <div class="stat-label" style="color: var(--gray-dark); font-size: 0.9rem;">Certifications</div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- PENDING APPROVALS QUEUE                                        -->
            <!-- ============================================================== -->
            <div class="card" id="approvals-section" style="margin-bottom: 2rem; border-left: 4px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h2 style="font-size: 1.25rem; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-tasks" style="color: #f59e0b;"></i> Team Approval Queue
                        <?php if ($pendingApprovalsCount > 0): ?>
                            <span class="badge badge-warning" style="font-size: 0.85rem;"><?php echo $pendingApprovalsCount; ?> Pending</span>
                        <?php endif; ?>
                    </h2>
                    <span style="font-size: 0.85rem; color: #64748b;">
                        Approving requests automatically updates employee skills, certifications, or training schedules.
                    </span>
                </div>

                <?php if (empty($pendingRequests)): ?>
                    <div style="text-align: center; padding: 2.5rem; color: #64748b;">
                        <i class="fas fa-check-circle" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.75rem; display: block;"></i>
                        <h4 style="margin: 0 0 0.25rem 0; color: #1e293b;">All caught up!</h4>
                        <p style="margin: 0; font-size: 0.9rem;">There are no pending self-service requests awaiting your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="approval-queue-list">
                        <?php foreach ($pendingRequests as $req): ?>
                            <?php $det = json_decode($req['details'], true) ?: []; ?>
                            <div class="approval-card">
                                <div class="approval-card-header">
                                    <div class="approval-emp-info">
                                        <div class="approval-avatar">
                                            <?php echo strtoupper(substr($req['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #0f172a; font-size: 1rem;">
                                                <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?>
                                                <span style="font-size: 0.8rem; font-weight: normal; color: #64748b;">(<?php echo htmlspecialchars($req['job_title'] ?: 'Employee'); ?> &bull; <?php echo htmlspecialchars($req['department']); ?>)</span>
                                            </div>
                                            <div class="approval-meta">
                                                Submitted on <?php echo date('M d, Y \a\t h:i A', strtotime($req['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge-type type-<?php echo htmlspecialchars($req['type']); ?>">
                                        <?php echo htmlspecialchars($req['type']); ?>
                                    </span>
                                </div>

                                <div class="approval-title">
                                    <?php echo htmlspecialchars($req['title']); ?>
                                </div>

                                <!-- Request Details Box -->
                                <div class="approval-details-box">
                                    <?php if ($req['type'] === 'skill'): ?>
                                        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                                            <div><strong>Skill:</strong> <?php echo htmlspecialchars($det['skill_name'] ?? 'Skill'); ?></div>
                                            <div><strong>Proposed Proficiency:</strong> <span style="color: #2563eb; font-weight: bold;"><?php echo htmlspecialchars($det['proficiency'] ?? '50'); ?>%</span></div>
                                            <?php if (!empty($det['category'])): ?>
                                                <div><strong>Category:</strong> <?php echo htmlspecialchars($det['category']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($req['type'] === 'certification'): ?>
                                        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                                            <div><strong>Certification:</strong> <?php echo htmlspecialchars($det['name'] ?? ''); ?></div>
                                            <div><strong>Issuer:</strong> <?php echo htmlspecialchars($det['issuing_body'] ?? ''); ?></div>
                                            <?php if (!empty($det['issue_date'])): ?>
                                                <div><strong>Issued:</strong> <?php echo date('M d, Y', strtotime($det['issue_date'])); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($det['expiry_date'])): ?>
                                                <div><strong>Expires:</strong> <?php echo date('M d, Y', strtotime($det['expiry_date'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($det['certificate_url'])): ?>
                                            <?php $isDoc = strpos($det['certificate_url'], 'uploads/') === 0; ?>
                                            <div style="margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                                <strong><?php echo $isDoc ? 'Attached Proof Document:' : 'Credential Link:'; ?></strong> 
                                                <a href="<?php echo htmlspecialchars($det['certificate_url']); ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.8rem; padding: 0.2rem 0.6rem;">
                                                    <i class="fas <?php echo $isDoc ? 'fa-file-pdf' : 'fa-external-link-alt'; ?>"></i> <?php echo $isDoc ? 'View Uploaded Document' : 'Open Verification Link'; ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($req['type'] === 'training'): ?>
                                        <div style="margin-bottom: 0.4rem;">
                                            <strong>Training Target:</strong> <?php echo htmlspecialchars($det['training_title'] ?? $det['topic'] ?? 'Training Session'); ?>
                                            <span style="font-size: 0.8rem; color: #64748b;">(<?php echo htmlspecialchars($det['mode'] ?? 'enrollment'); ?>)</span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($det['notes'])): ?>
                                        <div style="margin-top: 0.5rem; font-style: italic; color: #475569; background: #ffffff; padding: 0.5rem 0.75rem; border-radius: 4px; border-left: 3px solid #cbd5e1;">
                                            <i class="fas fa-quote-left" style="color: #94a3b8; margin-right: 0.35rem;"></i>
                                            <?php echo htmlspecialchars($det['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Form -->
                                <form action="php/handle_request.php" method="POST" class="approval-actions-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="review_request">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="redirect_back" value="../manager_dashboard.php">

                                    <input type="text" name="review_notes" class="approval-notes-input" placeholder="Feedback or remarks for <?php echo htmlspecialchars($req['first_name']); ?>...">

                                    <button type="submit" name="decision" value="approved" class="btn-approve">
                                        <i class="fas fa-check"></i> Approve & Apply
                                    </button>
                                    <button type="submit" name="decision" value="rejected" class="btn-reject" onclick="return confirm('Are you sure you want to reject this request?');">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recently Reviewed Requests History -->
            <?php if (!empty($recentReviews)): ?>
            <div class="card" style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem;">
                    <i class="fas fa-history" style="color: #64748b;"></i> Recently Processed Requests
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Request</th>
                                <th>Reviewed On</th>
                                <th>Outcome</th>
                                <th>Manager Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReviews as $rev): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']); ?></strong></td>
                                    <td><span class="badge-type type-<?php echo htmlspecialchars($rev['type']); ?>"><?php echo htmlspecialchars($rev['type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($rev['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($rev['reviewed_at'])); ?></td>
                                    <td>
                                        <?php if ($rev['status'] === 'approved'): ?>
                                            <span class="badge-status badge-approved"><i class="fas fa-check"></i> Approved</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-rejected"><i class="fas fa-times"></i> Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: #475569;"><?php echo htmlspecialchars($rev['review_notes'] ?: '-'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Team Members Table -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h2 style="font-size: 1.25rem; color: var(--gray-dark);">
                        <i class="fas fa-user-friends"></i> Team Members
                    </h2>
                    <a href="create_training.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Schedule Training
                    </a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role / Title</th>
                                <th>Email</th>
                                <th>Skills Tracked</th>
                                <th>Active Trainings</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teamMembers)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--gray-medium); padding: 2rem;">
                                        No team members found in this department.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teamMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['job_title'] ?: 'Team Member'); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $member['skill_count']; ?> Skills</span>
                                        </td>
                                        <td>
                                            <?php if ($member['active_trainings'] > 0): ?>
                                                <span class="badge badge-warning"><?php echo $member['active_trainings']; ?> In Progress</span>
                                            <?php else: ?>
                                                <span class="badge" style="background-color: var(--gray-light); color: var(--gray-dark);">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_employee.php?id=<?php echo $member['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="manage_employee_skills.php?id=<?php echo $member['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="Manage Skills">
                                                <i class="fas fa-lightbulb"></i>
                                            </a>
                                            <a href="create_training.php?employee_id=<?php echo $member['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="Assign Training">
                                                <i class="fas fa-graduation-cap"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Team Trainings -->
            <div class="card">
                <h2 style="font-size: 1.25rem; color: var(--gray-dark); margin-bottom: 1.25rem;">
                    <i class="fas fa-calendar-alt"></i> Recent & Upcoming Team Trainings
                </h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Training Title</th>
                                <th>Team Member</th>
                                <th>Related Skill</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teamTrainings)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--gray-medium); padding: 1.5rem;">
                                        No recent trainings scheduled for this team.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teamTrainings as $training): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($training['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($training['skill_name'] ?? 'General'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['start_date'])); ?></td>
                                        <td><?php echo $training['end_date'] ? date('M d, Y', strtotime($training['end_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $training['status'] === 'Completed' ? 'badge-success' : 
                                                    ($training['status'] === 'In Progress' ? 'badge-warning' : 
                                                     ($training['status'] === 'Cancelled' ? 'badge-danger' : 'badge-primary')); 
                                            ?>">
                                                <?php echo htmlspecialchars($training['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_training.php?id=<?php echo $training['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
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
</body>
</html>
