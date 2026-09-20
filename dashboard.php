<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Check role
if ($_SESSION["role"] === "manager") {
    header("location: manager_dashboard.php");
    exit;
} elseif ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

// Get counts for dashboard stats
$totalEmployees = 0;
$totalSkills = 0;
$totalCertifications = 0;
$recentTrainings = [];
$pendingRequests = [];

// Count employees
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'employee'";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $totalEmployees = $row['count'];
    }
}

// Count unique skills
$sql = "SELECT COUNT(DISTINCT skill_id) as count FROM employee_skills";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $totalSkills = $row['count'];
    }
}

// Count certifications
$sql = "SELECT COUNT(*) as count FROM certifications";
if ($result = $conn->query($sql)) {
    if ($row = $result->fetch_assoc()) {
        $totalCertifications = $row['count'];
    }
}

// Get pending self-service requests
$reqSql = "SELECT r.*, u.first_name, u.last_name, u.department, u.job_title 
           FROM requests r 
           JOIN users u ON r.employee_id = u.id 
           WHERE r.status = 'pending' 
           ORDER BY r.created_at ASC";
if ($res = $conn->query($reqSql)) {
    while ($row = $res->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
}
$pendingApprovalsCount = count($pendingRequests);

// Get recent trainings
$sql = "SELECT t.title, t.start_date, t.status, u.first_name, u.last_name 
        FROM trainings t 
        JOIN users u ON t.employee_id = u.id 
        ORDER BY t.start_date DESC LIMIT 5";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $recentTrainings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Skill Compass</title>
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
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="#approvals-section">
                        <i class="fas fa-tasks"></i>
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
                <h1 class="page-title">Admin / HR Dashboard</h1>
                <a href="profile.php" class="user-menu" style="text-decoration: none; color: inherit;" title="Manage Profile & Password">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
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
            
            <!-- Stats Cards -->
            <div class="stats-container" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalEmployees; ?></h3>
                        <p>Total Employees</p>
                    </div>
                </div>
                <div class="stat-card" style="<?php echo $pendingApprovalsCount > 0 ? 'border: 2px solid #f59e0b; background: #fffbeb;' : ''; ?>">
                    <div class="stat-icon" style="background-color: rgba(245, 158, 11, 0.15); color: #b45309;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3 style="color: <?php echo $pendingApprovalsCount > 0 ? '#b45309' : 'inherit'; ?>;"><?php echo $pendingApprovalsCount; ?></h3>
                        <p>Pending Approvals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(38, 166, 154, 0.1); color: #26a69a;">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalSkills; ?></h3>
                        <p>Registered Skills</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: rgba(255, 179, 0, 0.1); color: #ffb300;">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalCertifications; ?></h3>
                        <p>Active Certifications</p>
                    </div>
                </div>
            </div>

            <!-- Approval Queue Section -->
            <?php if (!empty($pendingRequests)): ?>
            <div class="card" id="approvals-section" style="margin-bottom: 2rem; border-left: 4px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h2 style="font-size: 1.25rem; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-clipboard-list" style="color: #f59e0b;"></i> Organization Approval Queue
                        <span class="badge badge-warning" style="font-size: 0.85rem;"><?php echo $pendingApprovalsCount; ?> Pending</span>
                    </h2>
                    <span style="font-size: 0.85rem; color: #64748b;">
                        Admin / HR override: approve or reject self-service requests across all teams.
                    </span>
                </div>

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

                            <div class="approval-details-box">
                                <?php if ($req['type'] === 'skill'): ?>
                                    <div><strong>Skill:</strong> <?php echo htmlspecialchars($det['skill_name'] ?? 'Skill'); ?> &bull; <strong>Proficiency:</strong> <span style="color: #2563eb; font-weight: bold;"><?php echo htmlspecialchars($det['proficiency'] ?? '50'); ?>%</span></div>
                                <?php elseif ($req['type'] === 'certification'): ?>
                                    <div><strong>Certification:</strong> <?php echo htmlspecialchars($det['name'] ?? ''); ?> &bull; <strong>Issuer:</strong> <?php echo htmlspecialchars($det['issuing_body'] ?? ''); ?></div>
                                <?php elseif ($req['type'] === 'training'): ?>
                                    <div><strong>Training:</strong> <?php echo htmlspecialchars($det['training_title'] ?? $det['topic'] ?? 'Training Session'); ?></div>
                                <?php endif; ?>

                                <?php if (!empty($det['notes'])): ?>
                                    <div style="margin-top: 0.4rem; font-style: italic; color: #475569;">
                                        <i class="fas fa-comment-alt" style="color: #94a3b8;"></i> <?php echo htmlspecialchars($det['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form action="php/handle_request.php" method="POST" class="approval-actions-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="review_request">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <input type="hidden" name="redirect_back" value="../dashboard.php">

                                <input type="text" name="review_notes" class="approval-notes-input" placeholder="Feedback or reason for decision...">

                                <button type="submit" name="decision" value="approved" class="btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="submit" name="decision" value="rejected" class="btn-reject" onclick="return confirm('Are you sure you want to reject this request?');">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Trainings</h2>
                    <a href="trainings.php" class="btn btn-outline">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Training</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTrainings)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No recent trainings found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTrainings as $training): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($training['first_name'] . ' ' . $training['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($training['title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['start_date'])); ?></td>
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
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="add_employee.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Employee
                    </a>
                    <a href="add_skill.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Skill
                    </a>
                    <a href="create_training.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Schedule Training
                    </a>
                    <a href="certifications.php" class="btn btn-primary">
                        <i class="fas fa-certificate"></i> Manage Certifications
                    </a>
                    <a href="reports.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i> Generate Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
