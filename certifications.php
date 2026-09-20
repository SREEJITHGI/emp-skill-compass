<?php
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

require_once "php/config.php";
require_once "php/csrf.php";
require_once "php/upload_helper.php";

$message = '';
$messageType = '';

// Handle Add Certification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    require_csrf('certifications.php');
    // Only admin, hr, manager can add certifications
    if ($_SESSION["role"] === "employee") {
        $message = "You do not have permission to add certifications.";
        $messageType = "error";
    } else {
        $employeeId = intval($_POST['employee_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $issuingBody = trim($_POST['issuing_body'] ?? '');
        $issueDate = !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $description = trim($_POST['description'] ?? '');
        $certificateUrl = trim($_POST['certificate_url'] ?? '');

        // Handle direct file upload if present
        if (!empty($_FILES['certificate_file']['name'])) {
            $upResult = handle_certificate_upload($_FILES['certificate_file']);
            if (!$upResult['success']) {
                $message = $upResult['error'];
                $messageType = "error";
            } else {
                $certificateUrl = $upResult['path'];
            }
        }

        if (empty($message)) {
            if ($employeeId <= 0 || empty($name)) {
                $message = "Employee and certification name are required.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO certifications (employee_id, name, issuing_body, issue_date, expiry_date, description, certificate_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $employeeId, $name, $issuingBody, $issueDate, $expiryDate, $description, $certificateUrl);
                if ($stmt->execute()) {
                    $message = "Certification added successfully!";
                    $messageType = "success";
                } else {
                    $message = "Database error: " . $conn->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
        }
    }
}


// Handle Delete Certification
if (isset($_GET['delete_id']) && ($_SESSION["role"] === 'admin' || $_SESSION["role"] === 'hr')) {
    $deleteId = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM certifications WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        header("Location: certifications.php?msg=Certification deleted successfully");
        exit;
    }
    $stmt->close();
}

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Get certifications
$certifications = [];
$sql = "SELECT c.*, u.first_name, u.last_name, u.department, u.job_title 
        FROM certifications c 
        JOIN users u ON c.employee_id = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($_SESSION["role"] === "employee") {
    $sql .= " AND c.employee_id = ?";
    $params[] = $_SESSION["id"];
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.issuing_body LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $sParam = "%" . $search . "%";
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $types .= "ssss";
}

if ($statusFilter === 'active') {
    $sql .= " AND (c.expiry_date IS NULL OR c.expiry_date >= CURDATE())";
} elseif ($statusFilter === 'expired') {
    $sql .= " AND (c.expiry_date IS NOT NULL AND c.expiry_date < CURDATE())";
}

$sql .= " ORDER BY c.issue_date DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $certifications[] = $row;
    }
    if (isset($stmt)) $stmt->close();
}

// Get employees list for modal dropdown
$employees = [];
if ($_SESSION["role"] !== "employee") {
    $empRes = $conn->query("SELECT id, first_name, last_name, department FROM users WHERE role = 'employee' ORDER BY first_name, last_name");
    if ($empRes) {
        while ($eRow = $empRes->fetch_assoc()) {
            $employees[] = $eRow;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications - Skill Compass</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
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
                <li class="menu-item">
                    <a href="trainings.php">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Trainings</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="menu-item active">
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

        <!-- Main Content -->
        <div class="content-area">
            <div class="top-bar">
                <h1 class="page-title">Certifications</h1>
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

            <!-- Search and Controls -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <form method="GET" action="certifications.php">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="Search certifications or employee..." class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" class="search-input" style="max-width: 180px;">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                        <a href="php/export.php?type=certifications" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <?php if ($_SESSION['role'] !== 'employee'): ?>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
                            <i class="fas fa-plus"></i> Add Certification
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Certifications Table -->
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Certification</th>
                                <th>Employee</th>
                                <th>Issuing Body</th>
                                <th>Issue Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($certifications)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--gray-medium); padding: 2rem;">No certifications found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($certifications as $cert): 
                                    $isExpired = $cert['expiry_date'] && strtotime($cert['expiry_date']) < time();
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cert['name']); ?></strong>
                                            <?php if (!empty($cert['description'])): ?>
                                                <div style="font-size: 0.8rem; color: var(--gray-dark);"><?php echo htmlspecialchars($cert['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?>
                                            <div style="font-size: 0.8rem; color: var(--gray-dark);"><?php echo htmlspecialchars($cert['department'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cert['issuing_body'] ?: 'N/A'); ?></td>
                                        <td><?php echo $cert['issue_date'] ? date('M d, Y', strtotime($cert['issue_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $cert['expiry_date'] ? date('M d, Y', strtotime($cert['expiry_date'])) : 'No Expiration'; ?></td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge badge-danger">Expired</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($cert['certificate_url'])): ?>
                                                <?php $isUploaded = strpos($cert['certificate_url'], 'uploads/') === 0; ?>
                                                <a href="<?php echo htmlspecialchars($cert['certificate_url']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.25rem 0.5rem;" title="<?php echo $isUploaded ? 'View Document' : 'Open Link'; ?>">
                                                    <i class="fas <?php echo $isUploaded ? 'fa-file-pdf' : 'fa-external-link-alt'; ?>"></i> <?php echo $isUploaded ? 'File' : 'Link'; ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'hr'): ?>
                                                <a href="certifications.php?delete_id=<?php echo $cert['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; color: var(--danger-color);" onclick="return confirm('Delete this certification record?');" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
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

    <!-- Add Certification Modal -->
    <?php if ($_SESSION['role'] !== 'employee'): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.3rem;"><i class="fas fa-certificate"></i> Add Certification</h2>
                <button type="button" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
            </div>
            <form method="POST" action="certifications.php" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="employee_id">Employee *</label>
                    <select name="employee_id" id="employee_id" class="search-input" style="width: 100%;" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['department'] ?? 'General') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="name">Certification Name *</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. AWS Certified Solutions Architect">
                </div>

                <div class="form-group">
                    <label for="issuing_body">Issuing Organization</label>
                    <input type="text" id="issuing_body" name="issuing_body" placeholder="e.g. Amazon Web Services, Cisco, PMI">
                </div>

                <div class="form-row" style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label for="issue_date">Issue Date</label>
                        <input type="date" id="issue_date" name="issue_date">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" id="expiry_date" name="expiry_date">
                    </div>
                </div>

                <div class="form-group">
                    <label for="certificate_file"><i class="fas fa-file-upload"></i> Upload Certificate Document (.pdf, .png, .jpg, max 5MB)</label>
                    <input type="file" id="certificate_file" name="certificate_file" accept=".pdf,.png,.jpg,.jpeg">
                </div>

                <div class="form-group">
                    <label for="certificate_url">Or Verification URL (Optional)</label>
                    <input type="url" id="certificate_url" name="certificate_url" placeholder="https://...">
                </div>

                <div class="form-group">
                    <label for="description">Description / Notes</label>
                    <textarea id="description" name="description" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-medium); border-radius: var(--border-radius);"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Certification</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
