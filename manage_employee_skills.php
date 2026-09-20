<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Only admin, hr, or manager can manage employee skills
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

// Handle Add / Update Skill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_skill') {
    require_csrf("manage_employee_skills.php?id=$employeeId");
    $skillId = intval($_POST['skill_id'] ?? 0);
    $proficiency = intval($_POST['proficiency'] ?? 0);
    $proficiency = max(0, min(100, $proficiency));

    if ($skillId <= 0) {
        $message = "Please select a valid skill.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO employee_skills (employee_id, skill_id, proficiency) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE proficiency = VALUES(proficiency)");
        $stmt->bind_param("iii", $employeeId, $skillId, $proficiency);
        if ($stmt->execute()) {
            $message = "Skill proficiency successfully saved!";
            $messageType = "success";
        } else {
            $message = "Error saving skill: " . $conn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}

// Handle Remove Skill
if (isset($_GET['remove_skill_id'])) {
    $removeSkillId = intval($_GET['remove_skill_id']);
    $stmt = $conn->prepare("DELETE FROM employee_skills WHERE employee_id = ? AND skill_id = ?");
    $stmt->bind_param("ii", $employeeId, $removeSkillId);
    if ($stmt->execute()) {
        header("location: manage_employee_skills.php?id=" . $employeeId . "&msg=Skill removed successfully");
        exit;
    }
    $stmt->close();
}

// Fetch employee info
$stmt = $conn->prepare("SELECT id, first_name, last_name, department, job_title, email FROM users WHERE id = ?");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$empRes = $stmt->get_result();
$employee = $empRes->fetch_assoc();
$stmt->close();

if (!$employee) {
    header("location: employees.php?error=Employee not found");
    exit;
}

// Fetch current assigned skills
$assignedSkills = [];
$assignedSkillIds = [];
$stmt = $conn->prepare("SELECT es.proficiency, es.last_updated, s.id as skill_id, s.name as skill_name, s.category 
                        FROM employee_skills es 
                        JOIN skills s ON es.skill_id = s.id 
                        WHERE es.employee_id = ? 
                        ORDER BY s.name ASC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$sRes = $stmt->get_result();
while ($row = $sRes->fetch_assoc()) {
    $assignedSkills[] = $row;
    $assignedSkillIds[] = $row['skill_id'];
}
$stmt->close();

// Fetch all skills for dropdown
$allSkills = [];
$allRes = $conn->query("SELECT id, name, category FROM skills ORDER BY name ASC");
if ($allRes) {
    while ($row = $allRes->fetch_assoc()) {
        $allSkills[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Skills: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - Skill Compass</title>
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
                    <a href="view_employee.php?id=<?php echo $employeeId; ?>" class="btn btn-outline" style="padding: 0.5rem 0.75rem;"><i class="fas fa-arrow-left"></i> Employee Profile</a>
                    <h1 class="page-title">Manage Skills: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
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
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success" style="background-color: #e8f5e9; color: #2e7d32; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; border-left: 4px solid #43a047; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Add / Update Skill Form -->
            <div class="card" style="margin-bottom: 2rem;">
                <h2 style="font-size: 1.2rem; color: var(--gray-dark); margin-bottom: 1rem;">
                    <i class="fas fa-plus-circle"></i> Assign or Update Skill Proficiency
                </h2>
                <form method="POST" action="manage_employee_skills.php?id=<?php echo $employeeId; ?>" style="display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: flex-end;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_skill">
                    
                    <div class="form-group" style="flex: 2; min-width: 250px; margin-bottom: 0;">
                        <label for="skill_id">Select Skill *</label>
                        <select name="skill_id" id="skill_id" class="search-input" style="width: 100%;" required>
                            <option value="">-- Choose Skill --</option>
                            <?php foreach ($allSkills as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['name'] . ' (' . ($s['category'] ?? 'General') . ')'); ?>
                                    <?php echo in_array($s['id'], $assignedSkillIds) ? ' - [Already Assigned]' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                        <label for="proficiency">Proficiency: <span id="profVal">75</span>%</label>
                        <input type="range" id="proficiency" name="proficiency" min="0" max="100" value="75" oninput="document.getElementById('profVal').innerText = this.value" style="width: 100%; cursor: pointer;">
                    </div>

                    <div style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary" style="height: 42px;">
                            <i class="fas fa-save"></i> Save Skill
                        </button>
                    </div>
                </form>
            </div>

            <!-- Current Skills Table -->
            <div class="card">
                <h2 style="font-size: 1.2rem; color: var(--gray-dark); margin-bottom: 1.25rem;">
                    <i class="fas fa-list-check"></i> Currently Assigned Skills (<?php echo count($assignedSkills); ?>)
                </h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Skill Name</th>
                                <th>Category</th>
                                <th style="width: 35%;">Proficiency Level</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignedSkills)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray-medium); padding: 2rem;">
                                        No skills currently assigned to this employee.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignedSkills as $ask): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ask['skill_name']); ?></strong></td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars($ask['category'] ?? 'General'); ?></span></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div class="progress-bar" style="background-color: var(--gray-light); height: 8px; border-radius: 4px; overflow: hidden; flex: 1;">
                                                    <div class="progress" style="width: <?php echo $ask['proficiency']; ?>%; background-color: <?php echo $ask['proficiency'] >= 80 ? 'var(--success-color)' : ($ask['proficiency'] >= 50 ? 'var(--primary-color)' : 'var(--warning-color)'); ?>; height: 100%;"></div>
                                                </div>
                                                <span style="font-weight: 600; min-width: 40px;"><?php echo $ask['proficiency']; ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo $ask['last_updated'] ? date('M d, Y', strtotime($ask['last_updated'])) : 'N/A'; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="Edit" onclick="setEditSkill(<?php echo $ask['skill_id']; ?>, <?php echo $ask['proficiency']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="manage_employee_skills.php?id=<?php echo $employeeId; ?>&remove_skill_id=<?php echo $ask['skill_id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; color: var(--danger-color);" onclick="return confirm('Are you sure you want to remove this skill from <?php echo htmlspecialchars($employee['first_name']); ?>?');" title="Remove">
                                                <i class="fas fa-trash"></i>
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
        function setEditSkill(skillId, proficiency) {
            document.getElementById('skill_id').value = skillId;
            document.getElementById('proficiency').value = proficiency;
            document.getElementById('profVal').innerText = proficiency;
            document.getElementById('skill_id').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
