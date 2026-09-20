<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "php/config.php";

$trainingId = intval($_GET['id'] ?? 0);
if ($trainingId <= 0) {
    header("location: trainings.php");
    exit;
}

// Quick status update action
if (isset($_POST['update_status']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'hr' || $_SESSION['role'] === 'manager')) {
    $newStatus = trim($_POST['status'] ?? '');
    if (in_array($newStatus, ['Planned', 'In Progress', 'Completed', 'Cancelled'])) {
        $stmt = $conn->prepare("UPDATE trainings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $trainingId);
        $stmt->execute();
        $stmt->close();
        header("location: view_training.php?id=" . $trainingId . "&msg=Status updated successfully");
        exit;
    }
}

// Fetch training details
$sql = "SELECT t.*, 
               u.first_name as emp_first_name, u.last_name as emp_last_name, u.email as emp_email, u.department as emp_dept, u.job_title as emp_title,
               s.name as skill_name, s.category as skill_category,
               c.first_name as creator_first, c.last_name as creator_last
        FROM trainings t
        JOIN users u ON t.employee_id = u.id
        LEFT JOIN skills s ON t.related_skill_id = s.id
        LEFT JOIN users c ON t.created_by = c.id
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trainingId);
$stmt->execute();
$res = $stmt->get_result();
$training = $res->fetch_assoc();
$stmt->close();

if (!$training) {
    header("location: trainings.php?error=Training not found");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($training['title']); ?> - Training Details</title>
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
                <?php if ($_SESSION['role'] !== 'employee'): ?>
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
                <?php endif; ?>
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
                <?php if ($_SESSION['role'] !== 'employee'): ?>
                <li class="menu-item">
                    <a href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <?php endif; ?>
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
                    <a href="trainings.php" class="btn btn-outline" style="padding: 0.5rem 0.75rem;"><i class="fas fa-arrow-left"></i> Back to Trainings</a>
                    <h1 class="page-title">Training Details</h1>
                </div>
                <div class="user-menu">
                    <div class="user-avatar"><?php echo substr($_SESSION["name"] ?? 'U', 0, 1); ?></div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"] ?? 'User'); ?></div>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success" style="background-color: #e8f5e9; color: #2e7d32; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #43a047; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 850px; margin: 0 auto 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.6rem; color: var(--primary-dark); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($training['title']); ?>
                        </h2>
                        <span class="badge <?php 
                            echo $training['status'] === 'Completed' ? 'badge-success' : 
                                ($training['status'] === 'In Progress' ? 'badge-warning' : 
                                 ($training['status'] === 'Cancelled' ? 'badge-danger' : 'badge-primary')); 
                        ?>" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">
                            <i class="fas fa-info-circle"></i> Status: <?php echo htmlspecialchars($training['status']); ?>
                        </span>
                    </div>
                    <?php if ($_SESSION['role'] !== 'employee'): ?>
                    <div style="display: flex; gap: 0.75rem;">
                        <a href="edit_training.php?id=<?php echo $training['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Training
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; background: var(--background-light); padding: 1.5rem; border-radius: 8px;">
                    <div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem; margin-bottom: 0.25rem;">Enrolled Employee</div>
                        <div style="font-weight: 600; font-size: 1.05rem;">
                            <?php echo htmlspecialchars($training['emp_first_name'] . ' ' . $training['emp_last_name']); ?>
                        </div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem;">
                            <?php echo htmlspecialchars($training['emp_title'] ?: 'Employee'); ?> (<?php echo htmlspecialchars($training['emp_dept'] ?: 'General'); ?>)
                        </div>
                    </div>

                    <div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem; margin-bottom: 0.25rem;">Target Skill</div>
                        <div style="font-weight: 600; font-size: 1.05rem;">
                            <?php echo htmlspecialchars($training['skill_name'] ?? 'General Development'); ?>
                        </div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem;">
                            <?php echo htmlspecialchars($training['skill_category'] ?? ''); ?>
                        </div>
                    </div>

                    <div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem; margin-bottom: 0.25rem;">Schedule Duration</div>
                        <div style="font-weight: 600; font-size: 1rem;">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($training['start_date'])); ?>
                            <?php if ($training['end_date']): ?>
                                &rarr; <?php echo date('M d, Y', strtotime($training['end_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem; margin-bottom: 0.25rem;">Created By</div>
                        <div style="font-weight: 600; font-size: 1rem;">
                            <?php echo htmlspecialchars(($training['creator_first'] ?? '') . ' ' . ($training['creator_last'] ?? 'System')); ?>
                        </div>
                        <div style="color: var(--gray-dark); font-size: 0.85rem;">
                            <?php echo date('M d, Y', strtotime($training['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.15rem; color: var(--gray-dark); margin-bottom: 0.75rem;">Description & Objectives</h3>
                    <div style="line-height: 1.7; color: var(--text-color); background: #fff; border: 1px solid var(--gray-light); padding: 1.25rem; border-radius: 6px;">
                        <?php echo nl2br(htmlspecialchars($training['description'] ?: 'No detailed syllabus or description provided for this training.')); ?>
                    </div>
                </div>

                <?php if ($_SESSION['role'] !== 'employee'): ?>
                <!-- Quick Status Change Bar -->
                <div style="border-top: 1px solid var(--gray-light); padding-top: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <span style="font-weight: 500; color: var(--gray-dark);">Quick Status Update:</span>
                    <form method="POST" action="view_training.php?id=<?php echo $trainingId; ?>" style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="hidden" name="update_status" value="1">
                        <select name="status" class="search-input" style="width: auto;">
                            <option value="Planned" <?php echo $training['status'] === 'Planned' ? 'selected' : ''; ?>>Planned</option>
                            <option value="In Progress" <?php echo $training['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $training['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?php echo $training['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
