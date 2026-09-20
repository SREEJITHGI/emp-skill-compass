<?php
$host = "localhost";
$user = "root";
$pass = "SREECARINO@2005";
$sqlFile = __DIR__ . "/skill_compass.sql";

echo "=== Skill Compass Database Setup ===\n";

try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        die("Connection error: " . $conn->connect_error . "\n");
    }
    echo "[OK] Connected to MySQL successfully.\n";

    // Read SQL file
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at $sqlFile\n");
    }
    $sqlContent = file_get_contents($sqlFile);

    // Execute multi_query
    if ($conn->multi_query($sqlContent)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        echo "[OK] skill_compass.sql imported successfully.\n";
    } else {
        echo "[WARN] multi_query error: " . $conn->error . "\n";
    }

    $conn->select_db("skill_compass");

    // Standardize default passwords so all accounts have known credentials:
    // admin: admin123
    // hrmanager: hr123
    // manager (rjohnson): manager123
    // employees (jdoe, jsmith, mwilson, lbrown): employee123
    $adminHash = password_hash("admin123", PASSWORD_DEFAULT);
    $hrHash = password_hash("hr123", PASSWORD_DEFAULT);
    $managerHash = password_hash("manager123", PASSWORD_DEFAULT);
    $employeeHash = password_hash("employee123", PASSWORD_DEFAULT);

    $conn->query("UPDATE users SET password = '$adminHash' WHERE username = 'admin'");
    $conn->query("UPDATE users SET password = '$hrHash' WHERE username = 'hrmanager'");
    $conn->query("UPDATE users SET password = '$managerHash' WHERE role = 'manager'");
    $conn->query("UPDATE users SET password = '$employeeHash' WHERE role = 'employee'");

    echo "[OK] Passwords configured:\n";
    echo "  - Admin:      admin / admin123\n";
    echo "  - HR:         hrmanager / hr123\n";
    echo "  - Manager:    rjohnson / manager123\n";
    echo "  - Employees:  jdoe, jsmith, mwilson, lbrown / employee123\n";

    // Verify table counts
    echo "\n=== Database Summary ===\n";
    $tables = ['users', 'skills', 'employee_skills', 'trainings', 'certifications'];
    foreach ($tables as $t) {
        $res = $conn->query("SELECT COUNT(*) as cnt FROM $t");
        $cnt = $res ? $res->fetch_assoc()['cnt'] : 0;
        echo "  - Table '$t': $cnt rows\n";
    }

    echo "\n[SUCCESS] Database initialization complete!\n";
    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
