<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Only admin, hr, or manager can view skill employee breakdown
if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr" && $_SESSION["role"] !== "manager") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";

$skillId = intval($_GET['id'] ?? 0);
if ($skillId <= 0) {
    header("location: skills.php");
    exit;
}

// Fetch skill details
$stmt = $conn->prepare("SELECT * FROM skills WHERE id = ?");
$stmt->bind_param("i", $skillId);
$stmt->execute();
$res = $stmt->get_result();
$skill = $res->fetch_assoc();
$stmt->close();

if (!$skill) {
    header("location: skills.php?error=Skill not found");
    exit;
}

// Fetch employees holding this skill
$employees = [];
$stmt = $conn->prepare("SELECT u.id as employee_id, u.first_name, u.last_name, u.department, u.job_title, u.email,
                               es.proficiency, es.last_updated
                        FROM employee_skills es
                        JOIN users u ON es.employee_id = u.id
                        WHERE es.skill_id = ?
                        ORDER BY es.proficiency DESC, u.last_name ASC");
$stmt->bind_param("i", $skillId);
$stmt->execute();
$res = $stmt->get_result();
$totalProf = 0;
while ($row = $res->fetch_assoc()) {
    $employees[] = $row;
    $totalProf += $row['proficiency'];
}
$stmt->close();

$employeeCount = count($employees);
$avgProf = $employeeCount > 0 ? round($totalProf / $employeeCount) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees with <?php echo htmlspecialchars($skill['name']); ?> - Skill Compass</title>
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
                    <a href="<?php echo $_SESSION['role'] === 'manager' ? 'manager_dashboard.php' : 'dashboard.php'; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="employees.php">
                        <i class="fas fa-users"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="menu-item active">
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
                    <a href="skills.php" class="btn btn-outline" style="padding: 0.5rem 0.75rem;"><i class="fas fa-arrow-left"></i> Back to Skills</a>
                    <h1 class="page-title">Employees with: <?php echo htmlspecialchars($skill['name']); ?></h1>
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><?php echo substr($_SESSION["name"] ?? 'U', 0, 1); ?></div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"] ?? 'User'); ?></div>
                </div>
            </div>

            <!-- Skill Overview Header -->
            <div class="card" style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                            <h2 style="font-size: 1.5rem;"><?php echo htmlspecialchars($skill['name']); ?></h2>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($skill['category'] ?? 'General'); ?></span>
                        </div>
                        <p style="color: var(--gray-dark); max-width: 650px;">
                            <?php echo htmlspecialchars($skill['description'] ?: 'No description provided.'); ?>
                        </p>
                    </div>
                    <div style="display: flex; gap: 1.5rem;">
                        <div style="text-align: center; background: var(--background-light); padding: 0.75rem 1.25rem; border-radius: 6px; border: 1px solid var(--gray-light);">
                            <div style="font-size: 1.75rem; font-weight: 700; color: var(--primary-color);"><?php echo $employeeCount; ?></div>
                            <div style="font-size: 0.85rem; color: var(--gray-dark);">Employees</div>
                        </div>
                        <div style="text-align: center; background: var(--background-light); padding: 0.75rem 1.25rem; border-radius: 6px; border: 1px solid var(--gray-light);">
                            <div style="font-size: 1.75rem; font-weight: 700; color: var(--secondary-color);"><?php echo $avgProf; ?>%</div>
                            <div style="font-size: 0.85rem; color: var(--gray-dark);">Avg Proficiency</div>
                        </div>
                        <a href="edit_skill.php?id=<?php echo $skill['id']; ?>" class="btn btn-outline" style="align-self: center;">
                            <i class="fas fa-edit"></i> Edit Skill
                        </a>
                    </div>
                </div>
            </div>

            <!-- Employees List Table -->
            <div class="card">
                <h3 style="font-size: 1.2rem; color: var(--gray-dark); margin-bottom: 1.25rem;">
                    <i class="fas fa-users"></i> Ranked Talent Matrix
                </h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Job Title</th>
                                <th style="width: 35%;">Proficiency</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--gray-medium); padding: 2rem;">
                                        No employees currently have this skill assigned.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                            <div style="font-size: 0.8rem; color: var(--gray-dark);"><?php echo htmlspecialchars($emp['email']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['department'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['job_title'] ?: 'N/A'); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div class="progress-bar" style="background-color: var(--gray-light); height: 8px; border-radius: 4px; overflow: hidden; flex: 1;">
                                                    <div class="progress" style="width: <?php echo $emp['proficiency']; ?>%; background-color: <?php echo $emp['proficiency'] >= 80 ? 'var(--success-color)' : ($emp['proficiency'] >= 50 ? 'var(--primary-color)' : 'var(--warning-color)'); ?>; height: 100%;"></div>
                                                </div>
                                                <span style="font-weight: 600; min-width: 40px;"><?php echo $emp['proficiency']; ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo $emp['last_updated'] ? date('M d, Y', strtotime($emp['last_updated'])) : 'N/A'; ?></td>
                                        <td>
                                            <a href="view_employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="manage_employee_skills.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="Update Skill">
                                                <i class="fas fa-cog"></i>
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
