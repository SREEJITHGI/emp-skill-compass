<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Only admin, hr, or manager can edit skills
if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "hr" && $_SESSION["role"] !== "manager") {
    header("location: employee_dashboard.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";

$skillId = intval($_GET['id'] ?? 0);
if ($skillId <= 0) {
    header("location: skills.php");
    exit;
}

$message = '';
$messageType = '';

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("edit_skill.php?id=$skillId");
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $message = "Skill name is required.";
        $messageType = "error";
    } else {
        // Check uniqueness excluding current skill
        $stmt = $conn->prepare("SELECT id FROM skills WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $skillId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Another skill with this name already exists.";
            $messageType = "error";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE skills SET name = ?, category = ?, description = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $category, $description, $skillId);
            if ($stmt->execute()) {
                header("location: skills.php?msg=Skill updated successfully");
                exit;
            } else {
                $message = "Database error: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch existing skill details
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

// Get categories for autocomplete
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM skills WHERE category IS NOT NULL AND category != '' ORDER BY category");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categories[] = $r['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Skill - Skill Compass</title>
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
                    <h1 class="page-title">Edit Skill: <?php echo htmlspecialchars($skill['name']); ?></h1>
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

            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <form method="POST" action="edit_skill.php?id=<?php echo $skillId; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="name"><i class="fas fa-lightbulb"></i> Skill Name *</label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($skill['name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="category"><i class="fas fa-tags"></i> Category</label>
                        <input type="text" id="category" name="category" list="category_list" value="<?php echo htmlspecialchars($skill['category'] ?? ''); ?>" placeholder="e.g. Programming, Cloud, DevOps">
                        <datalist id="category_list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-medium); border-radius: var(--border-radius); font-family: inherit;"><?php echo htmlspecialchars($skill['description'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                        <a href="skills.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
