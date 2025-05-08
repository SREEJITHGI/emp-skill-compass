
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

$message = '';
$messageType = '';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data and validate
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $employeeId = trim($_POST['employee_id']);
    $startDate = trim($_POST['start_date']);
    $endDate = trim($_POST['end_date']);
    $status = trim($_POST['status']);
    $relatedSkillId = !empty($_POST['related_skill_id']) ? trim($_POST['related_skill_id']) : null;
    $createdBy = $_SESSION["id"];
    
    // Validate required fields
    if (empty($title) || empty($employeeId) || empty($startDate) || empty($status)) {
        $message = "Please fill all required fields";
        $messageType = "error";
    } else {
        // Insert new training
        $sql = "INSERT INTO trainings (title, description, employee_id, start_date, end_date, status, related_skill_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssisssis", $title, $description, $employeeId, $startDate, $endDate, $status, $relatedSkillId, $createdBy);
            if ($stmt->execute()) {
                $message = "Training scheduled successfully!";
                $messageType = "success";
                
                // Redirect after successful addition
                header("Location: trainings.php?msg=Training scheduled successfully");
                exit;
            } else {
                $message = "Error: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Get employees for dropdown
$employees = [];
$sql = "SELECT id, first_name, last_name FROM users WHERE role = 'employee' ORDER BY first_name, last_name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Get skills for dropdown
$skills = [];
$sql = "SELECT id, name FROM skills ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Training - Skill Compass</title>
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
                <h1 class="page-title">Schedule New Training</h1>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo substr($_SESSION["name"], 0, 1); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                </div>
            </div>
            
            <!-- Form -->
            <div class="card">
                <?php if (!empty($message)): ?>
                    <div class="notification <?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Training Title*</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="employee_id">Employee*</label>
                            <select id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date*</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status*</label>
                            <select id="status" name="status" required>
                                <option value="Planned">Planned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="related_skill_id">Related Skill</label>
                            <select id="related_skill_id" name="related_skill_id">
                                <option value="">Select Skill (Optional)</option>
                                <?php foreach ($skills as $skill): ?>
                                    <option value="<?php echo $skill['id']; ?>">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Training
                        </button>
                        <a href="trainings.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default dates
            const today = new Date();
            document.getElementById('start_date').valueAsDate = today;
            
            const endDate = new Date();
            endDate.setDate(today.getDate() + 5); // Default to 5 days later
            document.getElementById('end_date').valueAsDate = endDate;
            
            // Make sure end date is not before start date
            document.getElementById('start_date').addEventListener('change', function() {
                const startDate = new Date(this.value);
                const endDateInput = document.getElementById('end_date');
                
                if (endDateInput.value && new Date(endDateInput.value) < startDate) {
                    endDateInput.valueAsDate = startDate;
                }
            });
        });
    </script>
</body>
</html>
