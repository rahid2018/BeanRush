<?php
session_start();
require_once '../components/connection.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error_message = "Both email and password are required";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, name, email, password FROM admin WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_email'] = $admin['email'];
                    header("Location: admin.php");
                    exit();
                } else {
                    $error_message = "Invalid email or password";
                }
            } else {
                $error_message = "Invalid email or password";
            }
            $stmt->close();
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Error during login. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush Admin - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/login.css">
</head>
<body>
    <div class="login-container">
        <i class="fas fa-coffee coffee-bean bean-1"></i>
        <i class="fas fa-coffee coffee-bean bean-2"></i>
        
        <div class="logo">
            <i class="fas fa-mug-hot"></i>
            <h1>BeanRush Admin</h1>
        </div>
        
        <h2>Login to Your Account</h2>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="admin@example.com">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="login-footer">
            <p>Secure admin access to BeanRush management system</p>
        </div>
    </div>
</body>
</html>