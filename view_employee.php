<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "php/config.php";

$employeeId = intval($_GET['id'] ?? 0);
if ($employeeId <= 0) {
    header("location: employees.php");
    exit;
}

// Fetch employee details
$stmt = $conn->prepare("SELECT id, username, first_name, last_name, email, role, department, job_title, hire_date, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$empRes = $stmt->get_result();
$employee = $empRes->fetch_assoc();
$stmt->close();

if (!$employee) {
    header("location: employees.php?error=Employee not found");
    exit;
}

// Fetch employee skills
$skills = [];
$stmt = $conn->prepare("SELECT es.proficiency, es.last_updated, s.id as skill_id, s.name as skill_name, s.category, s.description 
                        FROM employee_skills es 
                        JOIN skills s ON es.skill_id = s.id 
                        WHERE es.employee_id = ? 
                        ORDER BY es.proficiency DESC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$sRes = $stmt->get_result();
while ($row = $sRes->fetch_assoc()) {
    $skills[] = $row;
}
$stmt->close();

// Fetch employee trainings
$trainings = [];
$stmt = $conn->prepare("SELECT t.*, s.name as skill_name 
                        FROM trainings t 
                        LEFT JOIN skills s ON t.related_skill_id = s.id 
                        WHERE t.employee_id = ? 
                        ORDER BY t.start_date DESC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$tRes = $stmt->get_result();
while ($row = $tRes->fetch_assoc()) {
    $trainings[] = $row;
}
$stmt->close();

// Fetch employee certifications
$certifications = [];
$stmt = $conn->prepare("SELECT * FROM certifications WHERE employee_id = ? ORDER BY issue_date DESC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$cRes = $stmt->get_result();
while ($row = $cRes->fetch_assoc()) {
    $certifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - Profile</title>
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
                <li class="menu-item">
                    <a href="<?php echo $_SESSION['role'] === 'employee' ? 'employee_dashboard.php' : ($_SESSION['role'] === 'manager' ? 'manager_dashboard.php' : 'dashboard.php'); ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item active">
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
                    <a href="php/auth.php?action=logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="top-bar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="employees.php" class="btn btn-outline" style="padding: 0.5rem 0.75rem;"><i class="fas fa-arrow-left"></i> Back</a>
                    <h1 class="page-title"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><?php echo substr($_SESSION["name"] ?? 'U', 0, 1); ?></div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"] ?? 'User'); ?></div>
                </div>
            </div>

            <!-- Employee Info Card -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700;">
                            <?php echo substr($employee['first_name'], 0, 1); ?>
                        </div>
                        <div>
                            <h2 style="font-size: 1.5rem; margin-bottom: 0.25rem;">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </h2>
                            <p style="color: var(--gray-dark); margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($employee['job_title'] ?: 'Employee'); ?> &bull; 
                                <strong><?php echo htmlspecialchars($employee['department'] ?: 'No Department'); ?></strong>
                            </p>
                            <div style="display: flex; gap: 1.5rem; font-size: 0.9rem; color: var(--gray-dark);">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($employee['email']); ?></span>
                                <span><i class="fas fa-user-tag"></i> Username: <?php echo htmlspecialchars($employee['username']); ?></span>
                                <?php if ($employee['hire_date']): ?>
                                    <span><i class="fas fa-calendar-check"></i> Hired: <?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($_SESSION['role'] !== 'employee'): ?>
                    <div style="display: flex; gap: 0.75rem;">
                        <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Details
                        </a>
                        <a href="manage_employee_skills.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-lightbulb"></i> Manage Skills
                        </a>
                        <a href="create_training.php?employee_id=<?php echo $employee['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-plus"></i> Assign Training
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.25rem; color: var(--gray-dark);"><i class="fas fa-lightbulb"></i> Skills & Proficiency</h2>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <a href="php/export.php?type=employee_skills&id=<?php echo $employee['id']; ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-file-csv"></i> Export Skills CSV
                        </a>
                        <?php if ($_SESSION['role'] !== 'employee'): ?>
                        <a href="manage_employee_skills.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-cog"></i> Update Skills
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (empty($skills)): ?>
                    <p style="text-align: center; color: var(--gray-medium); padding: 1.5rem;">No skills assigned to this employee yet.</p>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($skills as $sk): ?>
                            <div style="background: var(--background-light); padding: 1rem; border-radius: 6px; border: 1px solid var(--gray-light);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <strong><?php echo htmlspecialchars($sk['skill_name']); ?></strong>
                                    <span class="badge badge-primary" style="font-size: 0.75rem;"><?php echo htmlspecialchars($sk['category'] ?? 'General'); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--gray-dark); margin-bottom: 0.25rem;">
                                    <span>Proficiency</span>
                                    <span><strong><?php echo $sk['proficiency']; ?>%</strong></span>
                                </div>
                                <div class="progress-bar" style="background-color: var(--gray-light); height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div class="progress" style="width: <?php echo $sk['proficiency']; ?>%; background-color: <?php echo $sk['proficiency'] >= 80 ? 'var(--success-color)' : ($sk['proficiency'] >= 50 ? 'var(--primary-color)' : 'var(--warning-color)'); ?>; height: 100%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Trainings Section -->
            <div class="card" style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; color: var(--gray-dark); margin-bottom: 1.5rem;"><i class="fas fa-graduation-cap"></i> Trainings History</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Training Title</th>
                                <th>Related Skill</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trainings)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--gray-medium); padding: 1.5rem;">No training records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trainings as $tr): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($tr['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($tr['skill_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($tr['start_date'])); ?></td>
                                        <td><?php echo $tr['end_date'] ? date('M d, Y', strtotime($tr['end_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $tr['status'] === 'Completed' ? 'badge-success' : 
                                                    ($tr['status'] === 'In Progress' ? 'badge-warning' : 
                                                     ($tr['status'] === 'Cancelled' ? 'badge-danger' : 'badge-primary')); 
                                            ?>">
                                                <?php echo htmlspecialchars($tr['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_training.php?id=<?php echo $tr['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="View Training">
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

            <!-- Certifications Section -->
            <div class="card">
                <h2 style="font-size: 1.25rem; color: var(--gray-dark); margin-bottom: 1.5rem;"><i class="fas fa-certificate"></i> Certifications</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Certification Name</th>
                                <th>Issuing Body</th>
                                <th>Issue Date</th>
                                <th>Expiry Date</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($certifications)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray-medium); padding: 1.5rem;">No certifications recorded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($certifications as $cr): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cr['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cr['issuing_body'] ?: 'N/A'); ?></td>
                                        <td><?php echo $cr['issue_date'] ? date('M d, Y', strtotime($cr['issue_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $cr['expiry_date'] ? date('M d, Y', strtotime($cr['expiry_date'])) : 'No Expiry'; ?></td>
                                        <td>
                                            <?php if (!empty($cr['certificate_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($cr['certificate_url']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                    <i class="<?php echo str_starts_with($cr['certificate_url'], 'uploads/') ? 'fas fa-file-download' : 'fas fa-external-link-alt'; ?>"></i> 
                                                    <?php echo str_starts_with($cr['certificate_url'], 'uploads/') ? 'View Document' : 'View Link'; ?>
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--gray-medium);">N/A</span>
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
</body>
</html>
