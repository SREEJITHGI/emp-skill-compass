
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

// Initialize report data
$departmentSkills = [];
$topSkills = [];
$skillGaps = [];
$trainingCompletionRate = 0;
$certifications = [];

// Get department skill distribution
$sql = "SELECT d.department, s.name as skill_name, COUNT(*) as employee_count
        FROM (
            SELECT DISTINCT department FROM users WHERE role = 'employee' AND department IS NOT NULL
        ) d
        CROSS JOIN skills s
        LEFT JOIN employee_skills es ON es.skill_id = s.id
        LEFT JOIN users u ON es.employee_id = u.id AND u.department = d.department
        GROUP BY d.department, s.name
        ORDER BY d.department, employee_count DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departmentSkills[] = $row;
    }
}

// Get top skills
$sql = "SELECT s.name as skill_name, COUNT(es.employee_id) as employee_count
        FROM skills s
        JOIN employee_skills es ON s.id = es.skill_id
        GROUP BY s.id
        ORDER BY employee_count DESC
        LIMIT 5";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $topSkills[] = $row;
    }
}

// Get skill gaps (skills with low adoption)
$sql = "SELECT s.name as skill_name, COUNT(es.employee_id) as employee_count,
        (SELECT COUNT(*) FROM users WHERE role = 'employee') as total_employees
        FROM skills s
        LEFT JOIN employee_skills es ON s.id = es.skill_id
        GROUP BY s.id
        HAVING employee_count < (total_employees * 0.3) -- Less than 30% of employees have this skill
        ORDER BY employee_count ASC
        LIMIT 5";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $skillGaps[] = $row;
    }
}

// Get training completion rate
$sql = "SELECT 
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed,
        COUNT(*) as total
        FROM trainings
        WHERE status != 'Cancelled'";

$result = $conn->query($sql);
if ($result) {
    if ($row = $result->fetch_assoc()) {
        $completedTrainings = $row['completed'];
        $totalTrainings = $row['total'];
        $trainingCompletionRate = $totalTrainings > 0 ? ($completedTrainings / $totalTrainings) * 100 : 0;
    }
}

// Get certification distribution
$sql = "SELECT c.issuing_body, COUNT(*) as cert_count
        FROM certifications c
        GROUP BY c.issuing_body
        ORDER BY cert_count DESC
        LIMIT 5";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $certifications[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Skill Compass</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li class="menu-item active">
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
                <h1 class="page-title">Reports & Analytics</h1>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="php/export.php?type=reports" class="btn btn-primary">
                        <i class="fas fa-file-csv"></i> Export Full Audit Report (CSV)
                    </a>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php echo substr($_SESSION["name"], 0, 1); ?>
                        </div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION["name"]); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Training Completion Rate -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Training Completion Rate</h2>
                </div>
                <div style="display: flex; align-items: center; padding: 2rem;">
                    <div style="width: 150px; height: 150px; position: relative;">
                        <canvas id="trainingCompletionChart"></canvas>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                            <h3 style="margin: 0; font-size: 1.8rem;"><?php echo round($trainingCompletionRate); ?>%</h3>
                            <p style="margin: 0; font-size: 0.8rem; color: var(--gray-dark);">Completion</p>
                        </div>
                    </div>
                    <div style="margin-left: 2rem;">
                        <p><strong>Training Status Overview:</strong></p>
                        <p>- Completed trainings help employees develop their skills</p>
                        <p>- Training completion rate is a key indicator of employee development</p>
                        <p>- Higher completion rates often correlate with better skill growth</p>
                    </div>
                </div>
            </div>
            
            <!-- Top Skills and Skill Gaps -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Top Skills -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Top Skills</h2>
                    </div>
                    <div style="padding: 1rem;">
                        <canvas id="topSkillsChart"></canvas>
                    </div>
                </div>
                
                <!-- Skill Gaps -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Skill Gaps</h2>
                    </div>
                    <div style="padding: 1rem;">
                        <canvas id="skillGapsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Certifications -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Certification Distribution</h2>
                </div>
                <div style="padding: 1rem;">
                    <canvas id="certificationsChart"></canvas>
                </div>
            </div>
            
            <!-- Report Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Report Actions</h2>
                </div>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; padding: 1rem;">
                    <button class="btn btn-primary" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn btn-primary" onclick="exportCSV()">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                    <button class="btn btn-outline" onclick="generateDetailedReport()">
                        <i class="fas fa-chart-line"></i> Generate Detailed Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Training Completion Rate Doughnut Chart
            const trainingCompletionCtx = document.getElementById('trainingCompletionChart').getContext('2d');
            const trainingCompletionRate = <?php echo json_encode($trainingCompletionRate); ?>;
            
            new Chart(trainingCompletionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Incomplete'],
                    datasets: [{
                        data: [trainingCompletionRate, (100 - trainingCompletionRate)],
                        backgroundColor: ['#43a047', '#eceff1'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '75%',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Top Skills Bar Chart
            const topSkillsCtx = document.getElementById('topSkillsChart').getContext('2d');
            const topSkills = <?php echo json_encode($topSkills); ?>;
            
            new Chart(topSkillsCtx, {
                type: 'bar',
                data: {
                    labels: topSkills.map(skill => skill.skill_name),
                    datasets: [{
                        label: 'Number of Employees',
                        data: topSkills.map(skill => skill.employee_count),
                        backgroundColor: '#1e88e5',
                        borderColor: '#1565c0',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Skill Gaps Bar Chart
            const skillGapsCtx = document.getElementById('skillGapsChart').getContext('2d');
            const skillGaps = <?php echo json_encode($skillGaps); ?>;
            
            new Chart(skillGapsCtx, {
                type: 'bar',
                data: {
                    labels: skillGaps.map(skill => skill.skill_name),
                    datasets: [{
                        label: 'Number of Employees',
                        data: skillGaps.map(skill => skill.employee_count),
                        backgroundColor: '#ffb300',
                        borderColor: '#fb8c00',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
            
            // Certifications Pie Chart
            const certificationsCtx = document.getElementById('certificationsChart').getContext('2d');
            const certifications = <?php echo json_encode($certifications); ?>;
            
            new Chart(certificationsCtx, {
                type: 'pie',
                data: {
                    labels: certifications.map(cert => cert.issuing_body),
                    datasets: [{
                        data: certifications.map(cert => cert.cert_count),
                        backgroundColor: [
                            '#1e88e5',
                            '#26a69a',
                            '#ffb300',
                            '#7e57c2',
                            '#ef5350'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true
                }
            });
        });
        
        // Print report function
        function printReport() {
            window.print();
        }
        
        // Export CSV function (simplified for demonstration)
        function exportCSV() {
            alert('CSV report is being generated and will download shortly.');
            // In a real application, this would call a PHP endpoint to generate a CSV file
        }
        
        // Generate detailed report function
        function generateDetailedReport() {
            window.location.href = 'detailed_report.php';
        }
    </script>
</body>
</html>
