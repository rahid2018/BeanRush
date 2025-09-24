<?php
session_start();
require_once '../components/connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_data = [];
$profile_updated = false;
$password_updated = false;
$error_message = '';

// Default values if database fetch fails
$default_admin = [
    'id' => $admin_id,
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'phone' => '',
    'password' => '',
    'created_at' => date('Y-m-d H:i:s')
];

try {
    $stmt = $conn->prepare("SELECT id, name, email, phone, password, created_at FROM admin WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
    } else {
        $admin_data = $default_admin;
        $error_message = "Admin profile not found or inactive";
    }
    $stmt->close();
    
    // Split name into first and last name
    $name_parts = isset($admin_data['name']) ? explode(' ', $admin_data['name'], 2) : ['Admin', 'User'];
    $admin_data['first_name'] = $name_parts[0];
    $admin_data['last_name'] = isset($name_parts[1]) ? $name_parts[1] : '';
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $admin_data = $default_admin;
    $error_message = "Error loading profile data";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validate inputs
        if (empty($first_name) || empty($email)) {
            $error_message = "First name and email are required";
        } else {
            $name = $first_name . ' ' . $last_name;
            
            try {
                $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $phone, $admin_id);
                
                if ($stmt->execute()) {
                    $profile_updated = true;
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_email'] = $email;
                    
                    // Refresh admin data
                    $admin_data['name'] = $name;
                    $admin_data['email'] = $email;
                    $admin_data['phone'] = $phone;
                    $name_parts = explode(' ', $admin_data['name'], 2);
                    $admin_data['first_name'] = $name_parts[0];
                    $admin_data['last_name'] = isset($name_parts[1]) ? $name_parts[1] : '';
                } else {
                    $error_message = "Failed to update profile";
                }
                $stmt->close();
            } catch (Exception $e) {
                error_log("Update profile error: " . $e->getMessage());
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $error_message = "Current password is required";
        } elseif (empty($new_password)) {
            $error_message = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $stmt->bind_result($hashed_password);
                $stmt->fetch();
                $stmt->close();
                
                if ($hashed_password && password_verify($current_password, $hashed_password)) {
                    // Update password
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_hashed_password, $admin_id);
                    
                    if ($stmt->execute()) {
                        $password_updated = true;
                    } else {
                        $error_message = "Failed to update password";
                    }
                    $stmt->close();
                } else {
                    $error_message = "Current password is incorrect";
                }
            } catch (Exception $e) {
                error_log("Password change error: " . $e->getMessage());
                $error_message = "Error changing password";
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - Admin Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/profile.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="logo">
                <i class="fas fa-mug-hot"></i>
                <span>BeanRush Admin</span>
            </div>
            <nav>
                <ul>
                    <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="products.php"><i class="fas fa-coffee"></i> <span>Products</span></a></li>
                    <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
                    <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user-cog"></i> <span>Admin Profile</span></a></li>
                    <li><a href="?logout=true" class="logout-link-sidebar" onclick="return confirm('Logout from this account?')">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Admin Profile</h1>
                <div class="header-actions">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_data['name'] ?? 'Admin'); ?>&background=4b3621&color=fff" alt="Admin" class="avatar" width="40" height="40" style="border-radius: 50%;">
                    </div>
                </div>
            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-user-cog me-2"></i> Admin Profile</h2>
                </div>
                
                <?php if ($profile_updated): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Your profile has been updated successfully!</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($password_updated): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Your password has been changed successfully!</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="profile-container">
                    <div class="profile-sidebar">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_data['name'] ?? 'Admin'); ?>&background=4b3621&color=fff&size=150" alt="Admin Avatar" class="profile-avatar">
                        <h3 class="profile-name"><?php echo htmlspecialchars($admin_data['name'] ?? 'Admin User'); ?></h3>
                        <div class="profile-role">Administrator</div>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php 
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(DISTINCT group_id) FROM orders");
                                        $stmt->execute();
                                        $stmt->bind_result($order_count);
                                        $stmt->fetch();
                                        echo $order_count ?? 0;
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        echo "0";
                                        error_log("Order count error: " . $e->getMessage());
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Orders</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php 
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
                                        $stmt->execute();
                                        $stmt->bind_result($product_count);
                                        $stmt->fetch();
                                        echo $product_count ?? 0;
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        echo "0";
                                        error_log("Product count error: " . $e->getMessage());
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Products</div>
                            </div>
                        </div>
                        
                        <div class="profile-details">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="detail-info">
                                    <h4>Email</h4>
                                    <p><?php echo htmlspecialchars($admin_data['email'] ?? 'Not available'); ?></p>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="detail-info">
                                    <h4>Phone</h4>
                                    <p><?php echo !empty($admin_data['phone']) ? htmlspecialchars($admin_data['phone']) : 'Not provided'; ?></p>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="detail-info">
                                    <h4>Joined</h4>
                                    <p><?php 
                                    if (isset($admin_data['created_at']) && !empty($admin_data['created_at'])) {
                                        echo date('F j, Y', strtotime($admin_data['created_at']));
                                    } else {
                                        echo "Unknown";
                                    }
                                    ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-content">
                        <div class="form-container">
                            <h3><i class="fas fa-user me-2"></i> Profile Information</h3>
                            <form method="POST" action="profile.php">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first-name">First Name</label>
                                        <input type="text" id="first-name" name="first_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($admin_data['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="last-name">Last Name</label>
                                        <input type="text" id="last-name" name="last_name" class="form-control" 
                                            value="<?php echo htmlspecialchars($admin_data['last_name'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-control" 
                                            value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="text" id="phone" name="phone" class="form-control" 
                                            value="<?php echo htmlspecialchars($admin_data['phone'] ?? ''); ?>" 
                                            placeholder="Optional">
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Profile
                                </button>
                            </form>
                        </div>

                        <div class="form-container">
                            <h3><i class="fas fa-lock me-2"></i> Change Password</h3>
                            <form method="POST" action="profile.php">
                                <div class="form-row">
                                    <div class="form-group password-toggle">
                                        <label for="current-password">Current Password</label>
                                        <input type="password" id="current-password" name="current_password" class="form-control" required>
                                        <span class="toggle-icon" onclick="togglePassword('current-password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <div class="form-group password-toggle">
                                        <label for="new-password">New Password</label>
                                        <input type="password" id="new-password" name="new_password" class="form-control" required minlength="8">
                                        <span class="toggle-icon" onclick="togglePassword('new-password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <div class="form-group password-toggle">
                                        <label for="confirm-password">Confirm Password</label>
                                        <input type="password" id="confirm-password" name="confirm_password" class="form-control" required minlength="8">
                                        <span class="toggle-icon" onclick="togglePassword('confirm-password')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="password-strength">
                                    <div class="strength-meter">
                                        <div class="strength-bar"></div>
                                    </div>
                                    <div class="strength-text">Password strength: <span id="strength-text">None</span></div>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                        
                        <div class="form-container">
                            <h3><i class="fas fa-shield-alt me-2"></i> Security Settings</h3>
                            <div class="form-group">
                                <label for="two-factor">Two-Factor Authentication</label>
                                <div class="toggle-switch">
                                    <input type="checkbox" id="two-factor" class="toggle-input">
                                    <label for="two-factor" class="toggle-label"></label>
                                    <span class="toggle-text">Enable two-factor authentication</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.toggle-icon i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('new-password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-bar');
            const strengthText = document.getElementById('strength-text');
            let strength = 0;
            
            // Check password length
            if (password.length > 7) strength += 1;
            
            // Check for lowercase letters
            if (password.match(/[a-z]+/)) strength += 1;
            
            // Check for uppercase letters
            if (password.match(/[A-Z]+/)) strength += 1;
            
            // Check for numbers
            if (password.match(/[0-9]+/)) strength += 1;
            
            // Check for special characters
            if (password.match(/[$@#&!]+/)) strength += 1;
            
            // Update the strength bar
            strengthBar.style.width = (strength * 20) + '%';
            
            // Update strength text
            if (strength === 0) {
                strengthText.textContent = 'None';
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 3) {
                strengthText.textContent = 'Weak';
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 5) {
                strengthText.textContent = 'Medium';
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthText.textContent = 'Strong';
                strengthBar.style.backgroundColor = '#28a745';
            }
        });
        
        // Toggle switch for two-factor authentication
        document.getElementById('two-factor').addEventListener('change', function() {
            if (this.checked) {
                alert('Two-factor authentication has been enabled. You will need to set it up on your next login.');
            } else {
                alert('Two-factor authentication has been disabled.');
            }
        });


    </script>
</body>
</html>