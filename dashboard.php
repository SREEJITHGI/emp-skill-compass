
<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

// Check if user is admin or HR
if ($_SESSION["role"] != "admin" && $_SESSION["role"] != "hr") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";

// Get counts for dashboard stats
$totalEmployees = 0;
$totalSkills = 0;
$totalCertifications = 0;
$recentTrainings = [];

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
        
        <!-- Main Content -->
        <div class="content-area">
            <div class="top-bar">
                <h1 class="page-title">Admin Dashboard</h1>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo substr($_SESSION["name"], 0, 1); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalEmployees; ?></h3>
                        <p>Total Employees</p>
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
                    <a href="reports.php" class="btn btn-outline">
                        <i class="fas fa-chart-bar"></i> Generate Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Show notification
            function showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Remove the notification after 5 seconds
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease-in forwards';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                }, 5000);
            }
            
            // Example usage (uncomment to test):
            // showNotification('Welcome to Skill Compass Dashboard!', 'success');
        });
    </script>
</body>
</html>
