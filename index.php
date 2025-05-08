
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Skill Compass - Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form-container">
            <div class="login-header">
                <h1><i class="fas fa-compass"></i> Skill Compass</h1>
                <p>Employee Skill Tracking System</p>
            </div>
            <form action="php/auth.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-login">Login</button>
                </div>
                <?php if(isset($_GET['error'])): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                <div class="login-info">
                    <p><i class="fas fa-info-circle"></i> Employees: Login to view your skills and training details.</p>
                    <p><i class="fas fa-info-circle"></i> Managers: Login to manage your team's skill development.</p>
                    <p><i class="fas fa-info-circle"></i> HR/Admin: Login to manage all employee skills and training.</p>
                </div>
            </form>
            <div class="login-footer">
                <p>&copy; 2023 Skill Compass - All rights reserved</p>
            </div>
        </div>
        <div class="login-image">
            <div class="overlay"></div>
            <div class="welcome-text">
                <h2>Welcome to Skill Compass</h2>
                <p>Track, manage, and develop your professional skills</p>
                <div class="welcome-info">
                    <p>Employees can view and track their personal skills development</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
