<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Only admin, hr, or manager can edit trainings
if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr" && $_SESSION["role"] !== "manager") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

$trainingId = intval($_GET['id'] ?? 0);
if ($trainingId <= 0) {
    header("location: trainings.php");
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("edit_training.php?id=$trainingId");
    $title = trim($_POST['title'] ?? '');
    $employeeId = intval($_POST['employee_id'] ?? 0);
    $relatedSkillId = !empty($_POST['related_skill_id']) ? intval($_POST['related_skill_id']) : null;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status = trim($_POST['status'] ?? 'Planned');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || $employeeId <= 0 || empty($startDate)) {
        $message = "Title, employee, and start date are required.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("UPDATE trainings SET title = ?, employee_id = ?, related_skill_id = ?, start_date = ?, end_date = ?, status = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissssi", $title, $employeeId, $relatedSkillId, $startDate, $endDate, $status, $description, $trainingId);
        if ($stmt->execute()) {
            header("location: trainings.php?msg=Training updated successfully");
            exit;
        } else {
            $message = "Error updating training: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Fetch existing training
$stmt = $conn->prepare("SELECT * FROM trainings WHERE id = ?");
$stmt->bind_param("i", $trainingId);
$stmt->execute();
$res = $stmt->get_result();
$training = $res->fetch_assoc();
$stmt->close();

if (!$training) {
    header("location: trainings.php?error=Training not found");
    exit;
}

// Fetch employees list
$employees = [];
$empRes = $conn->query("SELECT id, first_name, last_name, department FROM users WHERE role = 'employee' ORDER BY first_name, last_name");
if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Fetch skills list
$skills = [];
$skRes = $conn->query("SELECT id, name, category FROM skills ORDER BY name");
if ($skRes) {
    while ($row = $skRes->fetch_assoc()) {
        $skills[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Training - Skill Compass</title>
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

        <!-- Content Area -->
        <div class="content-area">
            <div class="top-bar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="trainings.php" class="btn btn-outline" style="padding: 0.5rem 0.75rem;"><i class="fas fa-arrow-left"></i> Back to Trainings</a>
                    <h1 class="page-title">Edit Training Session</h1>
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
                <form method="POST" action="edit_training.php?id=<?php echo $trainingId; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="title"><i class="fas fa-graduation-cap"></i> Training Title *</label>
                        <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($training['title']); ?>">
                    </div>

                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="employee_id"><i class="fas fa-user"></i> Employee *</label>
                            <select id="employee_id" name="employee_id" class="search-input" style="width: 100%;" required>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo $training['employee_id'] == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['department'] ?? 'General') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="related_skill_id"><i class="fas fa-lightbulb"></i> Related Skill</label>
                            <select id="related_skill_id" name="related_skill_id" class="search-input" style="width: 100%;">
                                <option value="">None / General</option>
                                <?php foreach ($skills as $sk): ?>
                                    <option value="<?php echo $sk['id']; ?>" <?php echo $training['related_skill_id'] == $sk['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sk['name'] . ' (' . ($sk['category'] ?? 'General') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" style="display: flex; gap: 1.5rem;">
                        <div class="form-group" style="flex: 1;">
                            <label for="start_date"><i class="fas fa-calendar-plus"></i> Start Date *</label>
                            <input type="date" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($training['start_date']); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="end_date"><i class="fas fa-calendar-check"></i> End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($training['end_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="status"><i class="fas fa-tasks"></i> Status</label>
                            <select id="status" name="status" class="search-input" style="width: 100%;">
                                <option value="Planned" <?php echo $training['status'] === 'Planned' ? 'selected' : ''; ?>>Planned</option>
                                <option value="In Progress" <?php echo $training['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $training['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $training['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-medium); border-radius: var(--border-radius); font-family: inherit;"><?php echo htmlspecialchars($training['description'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                        <a href="trainings.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
