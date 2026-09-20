<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Only admin, hr, or manager can edit employees
if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr" && $_SESSION["role"] !== "manager") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

$employeeId = intval($_GET['id'] ?? 0);
if ($employeeId <= 0) {
    header("location: employees.php");
    exit;
}

$message = '';
$messageType = '';

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("edit_employee.php?id=$employeeId");
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');
    $hireDate = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $role = trim($_POST['role'] ?? 'employee');
    $newPassword = $_POST['new_password'] ?? '';

    // Validate
    if (empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
        $message = "First name, last name, email, and username are required.";
        $messageType = "error";
    } else {
        // Check uniqueness for username and email excluding current employee
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->bind_param("ssi", $username, $email, $employeeId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Username or email is already taken by another user.";
            $messageType = "error";
            $stmt->close();
        } else {
            $stmt->close();
            
            // If new password provided, update password hash as well
            if (!empty($newPassword)) {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, username=?, password=?, department=?, job_title=?, hire_date=?, role=? WHERE id=?");
                $stmt->bind_param("sssssssssi", $firstName, $lastName, $email, $username, $hashed, $department, $jobTitle, $hireDate, $role, $employeeId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, username=?, department=?, job_title=?, hire_date=?, role=? WHERE id=?");
                $stmt->bind_param("ssssssssi", $firstName, $lastName, $email, $username, $department, $jobTitle, $hireDate, $role, $employeeId);
            }

            if ($stmt->execute()) {
                header("location: employees.php?msg=Employee updated successfully");
                exit;
            } else {
                $message = "Error updating employee: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch existing employee data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$empRes = $stmt->get_result();
$emp = $empRes->fetch_assoc();
$stmt->close();

if (!$emp) {
    header("location: employees.php?error=Employee not found");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Skill Compass</title>
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
                    <h1 class="page-title">Edit Employee: <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></h1>
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><?php echo substr($_SESSION["name"] ?? 'U', 0, 1); ?></div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"] ?? 'User'); ?></div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>" style="background-color: <?php echo $messageType === 'success' ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $messageType === 'success' ? '#2e7d32' : '#c62828'; ?>; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid <?php echo $messageType === 'success' ? '#43a047' : '#e53935'; ?>; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 800px; margin: 0 auto;">
                <form method="POST" action="edit_employee.php?id=<?php echo $employeeId; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="first_name"><i class="fas fa-user"></i> First Name *</label>
                            <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($emp['first_name']); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="last_name"><i class="fas fa-user"></i> Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($emp['last_name']); ?>">
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($emp['email']); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="username"><i class="fas fa-id-badge"></i> Username *</label>
                            <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($emp['username']); ?>">
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="department"><i class="fas fa-building"></i> Department</label>
                            <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($emp['department'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="job_title"><i class="fas fa-briefcase"></i> Job Title</label>
                            <input type="text" id="job_title" name="job_title" value="<?php echo htmlspecialchars($emp['job_title'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="hire_date"><i class="fas fa-calendar-alt"></i> Hire Date</label>
                            <input type="date" id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($emp['hire_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="role"><i class="fas fa-user-shield"></i> User Role</label>
                            <select id="role" name="role" class="search-input" style="width: 100%;">
                                <option value="employee" <?php echo $emp['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                <option value="manager" <?php echo $emp['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="hr" <?php echo $emp['role'] === 'hr' ? 'selected' : ''; ?>>HR</option>
                                <option value="admin" <?php echo $emp['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-key"></i> New Password (leave blank to keep current password)</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password to reset">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <a href="employees.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
