<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/csrf.php';

$userId = $_SESSION["id"];
$userRole = $_SESSION["role"];

$message = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch current user details
$stmt = $conn->prepare("SELECT id, username, password, first_name, last_name, email, role, department, job_title, hire_date, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: index.php?error=User not found");
    exit;
}

// -------------------------------------------------------------
// Handle Form Submissions
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('profile.php');
    $formAction = $_POST['action'] ?? '';

    // Action 1: Update Profile Details
    if ($formAction === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            header("Location: profile.php?error=" . urlencode("First name, last name, and email are required."));
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: profile.php?error=" . urlencode("Please enter a valid email address."));
            exit;
        }

        // Check if email already exists for another user
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $email, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            header("Location: profile.php?error=" . urlencode("This email address is already in use by another account."));
            exit;
        }
        $checkStmt->close();

        // Update database
        $upStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $upStmt->bind_param("sssi", $firstName, $lastName, $email, $userId);
        if ($upStmt->execute()) {
            $_SESSION["name"] = $firstName . ' ' . $lastName;
            $upStmt->close();
            header("Location: profile.php?msg=" . urlencode("Profile information updated successfully."));
            exit;
        } else {
            $err = $conn->error;
            $upStmt->close();
            header("Location: profile.php?error=" . urlencode("Database error: " . $err));
            exit;
        }
    }

    // Action 2: Change Password
    if ($formAction === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            header("Location: profile.php?error=" . urlencode("Please fill in all password fields."));
            exit;
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            header("Location: profile.php?error=" . urlencode("Current password does not match our records."));
            exit;
        }

        // Validate new password length
        if (strlen($newPassword) < 6) {
            header("Location: profile.php?error=" . urlencode("New password must be at least 6 characters long."));
            exit;
        }

        // Check confirmation
        if ($newPassword !== $confirmPassword) {
            header("Location: profile.php?error=" . urlencode("New password and confirmation do not match."));
            exit;
        }

        // Hash and update
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pwdStmt->bind_param("si", $newHash, $userId);
        if ($pwdStmt->execute()) {
            $pwdStmt->close();
            header("Location: profile.php?msg=" . urlencode("Your password has been changed successfully."));
            exit;
        } else {
            $err = $conn->error;
            $pwdStmt->close();
            header("Location: profile.php?error=" . urlencode("Database error: " . $err));
            exit;
        }
    }
}

// Determine return dashboard URL based on role
$dashboardUrl = 'dashboard.php';
if ($userRole === 'manager') {
    $dashboardUrl = 'manager_dashboard.php';
} elseif ($userRole === 'employee') {
    $dashboardUrl = 'employee_dashboard.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Security - Skill Compass</title>
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
                    <a href="<?php echo $dashboardUrl; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($userRole === 'admin' || $userRole === 'hr'): ?>
                    <li class="menu-item"><a href="employees.php"><i class="fas fa-users"></i><span>Employees</span></a></li>
                    <li class="menu-item"><a href="skills.php"><i class="fas fa-lightbulb"></i><span>Skills</span></a></li>
                    <li class="menu-item"><a href="trainings.php"><i class="fas fa-graduation-cap"></i><span>Trainings</span></a></li>
                    <li class="menu-item"><a href="certifications.php"><i class="fas fa-certificate"></i><span>Certifications</span></a></li>
                    <li class="menu-item"><a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
                <?php elseif ($userRole === 'manager'): ?>
                    <li class="menu-item"><a href="manager_dashboard.php#approvals-section"><i class="fas fa-clipboard-check"></i><span>Approvals</span></a></li>
                    <li class="menu-item"><a href="employees.php"><i class="fas fa-users"></i><span>Team</span></a></li>
                    <li class="menu-item"><a href="skills.php"><i class="fas fa-lightbulb"></i><span>Skills</span></a></li>
                    <li class="menu-item"><a href="trainings.php"><i class="fas fa-graduation-cap"></i><span>Trainings</span></a></li>
                    <li class="menu-item"><a href="certifications.php"><i class="fas fa-certificate"></i><span>Certifications</span></a></li>
                    <li class="menu-item"><a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
                <?php else: ?>
                    <li class="menu-item"><a href="employee_dashboard.php#skills-section"><i class="fas fa-lightbulb"></i><span>My Skills</span></a></li>
                    <li class="menu-item"><a href="employee_dashboard.php#requests-section"><i class="fas fa-paper-plane"></i><span>My Requests</span></a></li>
                    <li class="menu-item"><a href="employee_dashboard.php#trainings-section"><i class="fas fa-graduation-cap"></i><span>Trainings</span></a></li>
                    <li class="menu-item"><a href="employee_dashboard.php#certifications-section"><i class="fas fa-certificate"></i><span>Certifications</span></a></li>
                <?php endif; ?>
                <li class="menu-item active">
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
                    <h1 class="page-title">Profile & Security Settings</h1>
                    <span style="color: #64748b; font-size: 0.9rem;">Manage your personal account details and credentials</span>
                </div>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                </div>
            </div>

            <!-- Flash Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success" style="background-color: #e8f5e9; color: #2e7d32; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #43a047; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="background-color: #ffebee; color: #c62828; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #e53935; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Profile Summary Hero Card -->
            <div class="card" style="margin-bottom: 2rem; background: linear-gradient(135deg, #ffffff, #f8fafc); border-left: 5px solid #2563eb;">
                <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #38bdf8); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                            <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </h2>
                            <span class="badge badge-primary" style="text-transform: uppercase; font-size: 0.75rem;">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </div>
                        <div style="color: #64748b; font-size: 0.95rem; margin-top: 0.25rem;">
                            <span><i class="fas fa-user-tag" style="margin-right: 0.35rem;"></i> @<?php echo htmlspecialchars($user['username']); ?></span> &bull; 
                            <span><i class="fas fa-briefcase" style="margin-right: 0.35rem;"></i> <?php echo htmlspecialchars($user['job_title'] ?: 'Team Member'); ?></span> &bull; 
                            <span><i class="fas fa-building" style="margin-right: 0.35rem;"></i> <?php echo htmlspecialchars($user['department'] ?: 'Unassigned'); ?></span>
                        </div>
                    </div>
                    <div style="text-align: right; color: #94a3b8; font-size: 0.85rem;">
                        <div>Joined: <strong><?php echo !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : date('M d, Y', strtotime($user['created_at'])); ?></strong></div>
                        <div style="margin-top: 0.25rem;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> CSRF Protected</div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Grid: Profile Info & Change Password -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem;">
                <!-- Column 1: Update Profile Details -->
                <div class="card">
                    <div class="card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.25rem;">
                        <h2 class="card-title" style="margin: 0; font-size: 1.15rem; color: #1e293b;">
                            <i class="fas fa-user-edit" style="color: #2563eb; margin-right: 0.5rem;"></i> Personal Information
                        </h2>
                    </div>
                    <form action="profile.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="first_name" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user['first_name']); ?>" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                            <div class="form-group">
                                <label for="last_name" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user['last_name']); ?>" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="email" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">Email Address *</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="username_disp" style="font-weight: 500; display: block; margin-bottom: 0.35rem; color: #64748b;">Username (Read-Only)</label>
                            <input type="text" id="username_disp" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; background: #f1f5f9; color: #64748b; border-radius: 6px; cursor: not-allowed;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="font-weight: 500; display: block; margin-bottom: 0.35rem; color: #64748b;">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?>" disabled style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; background: #f1f5f9; color: #64748b; border-radius: 6px; cursor: not-allowed;">
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 500; display: block; margin-bottom: 0.35rem; color: #64748b;">Job Title</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['job_title'] ?: 'N/A'); ?>" disabled style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; background: #f1f5f9; color: #64748b; border-radius: 6px; cursor: not-allowed;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-save"></i> Save Profile Details
                        </button>
                    </form>
                </div>

                <!-- Column 2: Change Password -->
                <div class="card">
                    <div class="card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.25rem;">
                        <h2 class="card-title" style="margin: 0; font-size: 1.15rem; color: #1e293b;">
                            <i class="fas fa-key" style="color: #f59e0b; margin-right: 0.5rem;"></i> Change Password
                        </h2>
                    </div>
                    <form action="profile.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="current_password" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter current password" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label for="new_password" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="At least 6 characters" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="confirm_password" style="font-weight: 500; display: block; margin-bottom: 0.35rem;">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Repeat new password" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                        </div>

                        <div style="background: #f8fafc; border-left: 3px solid #f59e0b; padding: 0.75rem; border-radius: 4px; font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">
                            <i class="fas fa-info-circle" style="color: #f59e0b;"></i> Password must be at least 6 characters long. Keep your credentials confidential.
                        </div>

                        <button type="submit" class="btn btn-primary" style="background-color: #f59e0b; border-color: #f59e0b; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-lock"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
