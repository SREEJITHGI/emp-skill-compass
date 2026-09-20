<?php
/**
 * Data Export Controller (CSV Streaming)
 * Skill Compass - Employee Skill Tracking System
 */

session_start();

// Authentication guard
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/config.php';

$type = trim($_GET['type'] ?? 'employees');
$timestamp = date('Ymd_His');

// Clean any previous output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Set stream headers
header('Content-Type: text/csv; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: 0');

// Open php output stream
$out = fopen('php://output', 'w');

// Write UTF-8 BOM for Microsoft Excel compatibility
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

switch ($type) {
    // -------------------------------------------------------------
    // 1. EMPLOYEES EXPORT
    // -------------------------------------------------------------
    case 'employees':
        header("Content-Disposition: attachment; filename=\"employees_export_{$timestamp}.csv\"");

        // Header row
        fputcsv($out, [
            'Employee ID',
            'First Name',
            'Last Name',
            'Email',
            'Role',
            'Department',
            'Job Title',
            'Hire Date',
            'Verified Skills Count',
            'Certifications Count',
            'Active Trainings'
        ]);

        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.department, u.job_title, u.hire_date,
                (SELECT COUNT(*) FROM employee_skills WHERE employee_id = u.id) as skill_count,
                (SELECT COUNT(*) FROM certifications WHERE employee_id = u.id) as cert_count,
                (SELECT COUNT(*) FROM trainings WHERE employee_id = u.id AND status IN ('Planned', 'In Progress')) as active_trainings
                FROM users u 
                WHERE u.role = 'employee'
                ORDER BY u.department, u.last_name, u.first_name";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    ucfirst($row['role']),
                    $row['department'] ?: 'Unassigned',
                    $row['job_title'] ?: 'Employee',
                    $row['hire_date'] ?: 'N/A',
                    $row['skill_count'],
                    $row['cert_count'],
                    $row['active_trainings']
                ]);
            }
        }
        break;

    // -------------------------------------------------------------
    // 2. SKILLS CATALOG EXPORT
    // -------------------------------------------------------------
    case 'skills':
        header("Content-Disposition: attachment; filename=\"skills_catalog_{$timestamp}.csv\"");

        fputcsv($out, [
            'Skill ID',
            'Skill Name',
            'Category',
            'Description',
            'Employees With Skill',
            'Average Proficiency (%)'
        ]);

        $sql = "SELECT s.id, s.name, s.category, s.description, 
                COUNT(es.employee_id) as emp_count,
                AVG(es.proficiency) as avg_prof
                FROM skills s 
                LEFT JOIN employee_skills es ON s.id = es.skill_id
                GROUP BY s.id 
                ORDER BY s.category, s.name";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['name'],
                    $row['category'] ?: 'General',
                    $row['description'] ?: '-',
                    $row['emp_count'],
                    $row['avg_prof'] !== null ? round($row['avg_prof'], 1) : 0
                ]);
            }
        }
        break;

    // -------------------------------------------------------------
    // 3. CERTIFICATIONS COMPLIANCE AUDIT EXPORT
    // -------------------------------------------------------------
    case 'certifications':
        header("Content-Disposition: attachment; filename=\"certifications_audit_{$timestamp}.csv\"");

        fputcsv($out, [
            'Cert ID',
            'Employee Name',
            'Department',
            'Certification Title',
            'Issuing Body',
            'Issue Date',
            'Expiry Date',
            'Status',
            'Document / Verification URL'
        ]);

        $sql = "SELECT c.id, c.name, c.issuing_body, c.issue_date, c.expiry_date, c.certificate_url,
                u.first_name, u.last_name, u.department
                FROM certifications c
                JOIN users u ON c.employee_id = u.id
                ORDER BY c.issue_date DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $isExpired = !empty($row['expiry_date']) && strtotime($row['expiry_date']) < time();
                $status = $isExpired ? 'Expired' : 'Active';
                fputcsv($out, [
                    $row['id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['department'] ?: 'Unassigned',
                    $row['name'],
                    $row['issuing_body'] ?: '-',
                    $row['issue_date'] ?: 'N/A',
                    $row['expiry_date'] ?: 'No Expiry',
                    $status,
                    $row['certificate_url'] ?: 'Verified in System'
                ]);
            }
        }
        break;

    // -------------------------------------------------------------
    // 4. TRAININGS SCHEDULE EXPORT
    // -------------------------------------------------------------
    case 'trainings':
        header("Content-Disposition: attachment; filename=\"trainings_schedule_{$timestamp}.csv\"");

        fputcsv($out, [
            'Training ID',
            'Training Title',
            'Participant Name',
            'Department',
            'Related Skill',
            'Start Date',
            'End Date',
            'Status',
            'Description'
        ]);

        $sql = "SELECT t.id, t.title, t.description, t.start_date, t.end_date, t.status,
                u.first_name, u.last_name, u.department,
                s.name as skill_name
                FROM trainings t
                JOIN users u ON t.employee_id = u.id
                LEFT JOIN skills s ON t.related_skill_id = s.id
                ORDER BY t.start_date DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['title'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['department'] ?: 'Unassigned',
                    $row['skill_name'] ?: 'General',
                    $row['start_date'],
                    $row['end_date'] ?: 'N/A',
                    $row['status'],
                    $row['description'] ?: '-'
                ]);
            }
        }
        break;

    // -------------------------------------------------------------
    // 5. FULL ORGANIZATIONAL AUDIT REPORT
    // -------------------------------------------------------------
    case 'reports':
        header("Content-Disposition: attachment; filename=\"organizational_audit_report_{$timestamp}.csv\"");

        fputcsv($out, ['=== SKILL COMPASS - ORGANIZATIONAL COMPREHENSIVE AUDIT REPORT ===']);
        fputcsv($out, ['Generated On: ' . date('Y-m-d H:i:s'), 'Generated By: ' . $_SESSION['name']]);
        fputcsv($out, []); // Blank line

        // Section 1: Department Summary
        fputcsv($out, ['--- DEPARTMENT BENCHMARKS ---']);
        fputcsv($out, ['Department', 'Employee Count', 'Total Skills Assigned', 'Average Skill Proficiency (%)']);

        $deptSql = "SELECT u.department, 
                    COUNT(DISTINCT u.id) as emp_count,
                    COUNT(es.id) as skill_entries,
                    AVG(es.proficiency) as avg_prof
                    FROM users u
                    LEFT JOIN employee_skills es ON u.id = es.employee_id
                    WHERE u.role = 'employee'
                    GROUP BY u.department
                    ORDER BY u.department";
        $dRes = $conn->query($deptSql);
        if ($dRes) {
            while ($d = $dRes->fetch_assoc()) {
                fputcsv($out, [
                    $d['department'] ?: 'Unassigned',
                    $d['emp_count'],
                    $d['skill_entries'],
                    $d['avg_prof'] !== null ? round($d['avg_prof'], 1) : 0
                ]);
            }
        }

        fputcsv($out, []); // Blank line

        // Section 2: Complete Employee Skill Matrix
        fputcsv($out, ['--- COMPLETE EMPLOYEE SKILLS MATRIX ---']);
        fputcsv($out, ['Employee Name', 'Department', 'Job Title', 'Skill Name', 'Category', 'Proficiency (%)', 'Last Verified Date']);

        $matrixSql = "SELECT u.first_name, u.last_name, u.department, u.job_title,
                      s.name as skill_name, s.category, es.proficiency, es.last_updated
                      FROM employee_skills es
                      JOIN users u ON es.employee_id = u.id
                      JOIN skills s ON es.skill_id = s.id
                      ORDER BY u.department, u.last_name, s.name";
        $mRes = $conn->query($matrixSql);
        if ($mRes) {
            while ($m = $mRes->fetch_assoc()) {
                fputcsv($out, [
                    $m['first_name'] . ' ' . $m['last_name'],
                    $m['department'] ?: 'Unassigned',
                    $m['job_title'] ?: 'Employee',
                    $m['skill_name'],
                    $m['category'] ?: 'General',
                    $m['proficiency'] . '%',
                    date('Y-m-d', strtotime($m['last_updated']))
                ]);
            }
        }
        break;

    // -------------------------------------------------------------
    // 6. SINGLE EMPLOYEE SKILLS MATRIX EXPORT
    // -------------------------------------------------------------
    case 'employee_skills':
        $empId = intval($_GET['id'] ?? 0);
        $emp = $conn->query("SELECT first_name, last_name, department FROM users WHERE id = $empId")->fetch_assoc();
        $safeName = $emp ? preg_replace('/[^a-zA-Z0-9]/', '_', $emp['first_name'] . '_' . $emp['last_name']) : 'employee';
        header("Content-Disposition: attachment; filename=\"skills_{$safeName}_{$timestamp}.csv\"");

        fputcsv($out, ['Skill Name', 'Category', 'Proficiency (%)', 'Last Updated']);

        $stmt = $conn->prepare("SELECT s.name, s.category, es.proficiency, es.last_updated 
                                FROM employee_skills es 
                                JOIN skills s ON es.skill_id = s.id 
                                WHERE es.employee_id = ? 
                                ORDER BY es.proficiency DESC");
        $stmt->bind_param("i", $empId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['name'],
                $row['category'] ?: 'General',
                $row['proficiency'] . '%',
                date('Y-m-d', strtotime($row['last_updated']))
            ]);
        }
        $stmt->close();
        break;

    default:
        fputcsv($out, ['Error', 'Invalid export type requested.']);
        break;
}

fclose($out);
exit;
