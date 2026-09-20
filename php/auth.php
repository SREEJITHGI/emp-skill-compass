
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'csrf.php';

// Login functionality
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_csrf('../index.php');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        header("Location: ../index.php?error=Please fill all required fields");
        exit;
    }
    
    // Prepare a select statement
    $sql = "SELECT id, username, password, role, first_name, last_name FROM users WHERE username = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("s", $username);
        
        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Store result
            $stmt->store_result();
            
            // Check if username exists, if yes then verify password
            if ($stmt->num_rows == 1) {
                // Bind result variables
                $stmt->bind_result($id, $username, $hashed_password, $role, $first_name, $last_name);
                if ($stmt->fetch()) {
                    if (password_verify($password, $hashed_password)) {
                        // Password is correct, store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["role"] = $role;
                        $_SESSION["name"] = $first_name . " " . $last_name;
                        
                        // Redirect user to dashboard based on role
                        switch($role) {
                            case 'admin':
                            case 'hr':
                                header("location: ../dashboard.php");
                                break;
                            case 'manager':
                                header("location: ../manager_dashboard.php");
                                break;
                            case 'employee':
                                header("location: ../employee_dashboard.php");
                                break;
                            default:
                                header("location: ../dashboard.php");
                        }
                        exit;
                    } else {
                        // Password is not valid
                        header("Location: ../index.php?error=Invalid username or password");
                        exit;
                    }
                }
            } else {
                // Username doesn't exist
                header("Location: ../index.php?error=Invalid username or password");
                exit;
            }
        } else {
            header("Location: ../index.php?error=Oops! Something went wrong. Please try again later.");
            exit;
        }

        // Close statement
        $stmt->close();
    }
}

// Logout functionality
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("location: ../index.php");
    exit;
}
?>
