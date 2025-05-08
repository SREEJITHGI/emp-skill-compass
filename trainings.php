
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

// Initialize search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Get all trainings
$trainings = [];

// Build query with search and filters
$sql = "SELECT t.id, t.title, t.start_date, t.end_date, t.status, 
               u1.first_name as emp_first_name, u1.last_name as emp_last_name,
               s.name as skill_name
        FROM trainings t 
        JOIN users u1 ON t.employee_id = u1.id
        LEFT JOIN skills s ON t.related_skill_id = s.id
        WHERE 1=1";
        
if (!empty($search)) {
    $sql .= " AND (t.title LIKE '%$search%' OR u1.first_name LIKE '%$search%' OR u1.last_name LIKE '%$search%')";
}

if (!empty($statusFilter)) {
    $sql .= " AND t.status = '$statusFilter'";
}

$sql .= " ORDER BY t.start_date DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trainings[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainings - Skill Compass</title>
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
                <li class="menu-item active">
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
                <h1 class="page-title">Trainings</h1>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo substr($_SESSION["name"], 0, 1); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="card">
                <form method="GET" action="trainings.php">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search trainings..." class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" class="search-input" style="max-width: 200px;">
                            <option value="">All Statuses</option>
                            <option value="Planned" <?php echo $statusFilter === 'Planned' ? 'selected' : ''; ?>>Planned</option>
                            <option value="In Progress" <?php echo $statusFilter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="create_training.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Schedule Training
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Trainings List -->
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Training</th>
                                <th>Employee</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Related Skill</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trainings)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No trainings found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trainings as $training): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($training['title']); ?></td>
                                        <td><?php echo htmlspecialchars($training['emp_first_name'] . ' ' . $training['emp_last_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($training['start_date'])); ?></td>
                                        <td>
                                            <?php echo $training['end_date'] ? date('M d, Y', strtotime($training['end_date'])) : 'N/A'; ?>
                                        </td>
                                        <td><?php echo $training['skill_name'] ?? 'N/A'; ?></td>
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
                                            <a href="edit_training.php?id=<?php echo $training['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-edit"></i>
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

    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html>
