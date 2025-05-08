
<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

// If user is admin or HR, redirect to admin dashboard
if ($_SESSION["role"] == "admin" || $_SESSION["role"] == "hr") {
    header("location: dashboard.php");
    exit;
}

require_once "php/config.php";

// Get employee details
$employeeId = $_SESSION["id"];
$employeeSkills = [];
$employeeCertifications = [];
$upcomingTrainings = [];

// Get employee skills
$sql = "SELECT es.proficiency, es.last_updated, s.name as skill_name 
        FROM employee_skills es 
        JOIN skills s ON es.skill_id = s.id 
        WHERE es.employee_id = ?";
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
$sql = "SELECT c.name, c.issuing_body, c.issue_date, c.expiry_date 
        FROM certifications c 
        WHERE c.employee_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $employeeCertifications[] = $row;
    }
    $stmt->close();
}

// Get upcoming trainings
$sql = "SELECT title, description, start_date, end_date, status 
        FROM trainings 
        WHERE employee_id = ? AND status != 'Completed' 
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
                    <a href="my_skills.php">
                        <i class="fas fa-lightbulb"></i>
                        <span>My Skills</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="my_trainings.php">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Trainings</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="my_certifications.php">
                        <i class="fas fa-certificate"></i>
                        <span>Certifications</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="my_profile.php">
                        <i class="fas fa-user"></i>
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
                <h1 class="page-title">My Dashboard</h1>
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
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($employeeSkills); ?></h3>
                        <p>Skills</p>
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
                        <p>Upcoming Trainings</p>
                    </div>
                </div>
            </div>
            
            <!-- My Skills -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Skills</h2>
                    <a href="my_skills.php" class="btn btn-outline">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Proficiency</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employeeSkills)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No skills added yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($employeeSkills, 0, 5) as $skill): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-bar" style="width: <?php echo $skill['proficiency']; ?>%"></div>
                                            </div>
                                            <?php echo $skill['proficiency']; ?>%
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($skill['last_updated'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Upcoming Trainings -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upcoming Trainings</h2>
                    <a href="my_trainings.php" class="btn btn-outline">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Training</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingTrainings)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No upcoming trainings</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcomingTrainings as $training): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($training['title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['end_date'])); ?></td>
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
        </div>
    </div>

    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html>
