
<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Check if user is admin, HR, or manager
if ($_SESSION["role"] != "admin" && $_SESSION["role"] != "hr" && $_SESSION["role"] != "manager") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";

// Initialize search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$departmentFilter = isset($_GET['department']) ? trim($_GET['department']) : '';

// Get all employees
$employees = [];
$departments = [];

// Get unique departments
$sql = "SELECT DISTINCT department FROM users WHERE role = 'employee' AND department IS NOT NULL AND department != '' ORDER BY department";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Build query with search and filters using prepared statement
$sql = "SELECT id, first_name, last_name, email, department, job_title FROM users WHERE role = 'employee'";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}
if (!empty($departmentFilter)) {
    $sql .= " AND department = ?";
    $params[] = $departmentFilter;
    $types .= "s";
}
$sql .= " ORDER BY last_name, first_name";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    if (isset($stmt)) {
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Skill Compass</title>
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
        
        <!-- Main Content -->
        <div class="content-area">
            <div class="top-bar">
                <h1 class="page-title">Employees</h1>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo substr($_SESSION["name"], 0, 1); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success" style="background-color: #e8f5e9; color: #2e7d32; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #43a047; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="background-color: #ffebee; color: #c62828; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #e53935; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Search and Filters -->
            <div class="card">
                <form method="GET" action="employees.php">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search employees..." class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="department" class="search-input" style="max-width: 200px;">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $departmentFilter === $department ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="php/export.php?type=employees" class="btn btn-outline">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <a href="add_employee.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Employee
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Employees List -->
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Job Title</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No employees found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['job_title']); ?></td>
                                        <td>
                                            <a href="view_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_employee_skills.php?id=<?php echo $employee['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-lightbulb"></i>
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
