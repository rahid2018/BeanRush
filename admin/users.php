<?php
session_start();
require_once '../components/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: users.php?deleted=1");
        exit();
    } else {
        $error = "Error deleting user: " . $conn->error;
    }
    $stmt->close();
}

// Handle form submission (add/edit user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    // Check if email exists (for new users or when changing email)
    if ($id == 0 || ($id > 0 && $email !== $_POST['original_email'])) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Email already exists";
        }
        $stmt->close();
    }
    
    // Password validation for new users or when changing password
    if ($id == 0 && empty($password)) {
        $errors[] = "Password is required for new users";
    }
    
    if (!empty($password) && $password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        if ($id > 0) {
            // Update existing user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, status=?, password=? WHERE id=?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $status, $hashed_password, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, status=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $phone, $status, $id);
            }
        } else {
            // Add new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, status, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $phone, $status, $hashed_password);
        }
        
        if ($stmt->execute()) {
            $success = $id > 0 ? "User updated successfully" : "User added successfully";
            // Clear edit mode after successful submission
            if ($id > 0) {
                header("Location: users.php?success=" . urlencode($success));
                exit();
            }
        } else {
            $errors[] = "Error saving user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_user = $result->fetch_assoc();
    $stmt->close();
}

// Handle filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$email_filter = $_GET['email'] ?? '';
$phone_filter = $_GET['phone'] ?? '';

// Build query with filters
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($email_filter)) {
    $conditions[] = "email LIKE ?";
    $params[] = "%$email_filter%";
    $types .= 's';
}

if (!empty($phone_filter)) {
    $conditions[] = "phone LIKE ?";
    $params[] = "%$phone_filter%";
    $types .= 's';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get users
$sql = "SELECT * FROM users $where ORDER BY name";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/users.css">
    
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
                    <li><a href="users.php" class="active"><i class="fas fa-users"></i> <span>Users</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
                    <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> <span>Admin Profile</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>User Management</h1>
            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-users me-2"></i> Users</h2>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </button>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> 
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> User deleted successfully
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <h3><i class="fas fa-filter me-2"></i> Filter Users</h3>
                    <form method="get" action="users.php">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" placeholder="Search users..." 
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="Email address..." 
                                    value="<?php echo htmlspecialchars($email_filter); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" placeholder="Phone number..." 
                                    value="<?php echo htmlspecialchars($phone_filter); ?>">
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="users.php" class="btn btn-cancel">Reset</a>
                        </div>
                    </form>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): 
                                $status = $user['status'] ?? 'active';
                                
                                $status_class = '';
                                $status_text = '';
                                
                                if ($status === 'active') {
                                    $status_class = 'status-active';
                                    $status_text = 'Active';
                                } elseif ($status === 'inactive') {
                                    $status_class = 'status-inactive';
                                    $status_text = 'Inactive';
                                }
                                
                                $avatar_name = str_replace(' ', '+', $user['name']);
                            ?>
                            <tr>
                                <td>#<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="user-avatar">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo $avatar_name; ?>&background=4b3621&color=fff" alt="<?php echo htmlspecialchars($user['name']); ?>">
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-icon btn-view btn-sm" 
                                            onclick="viewUserDetails(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="users.php?edit=<?php echo $user['id']; ?>" 
                                           class="btn btn-icon btn-edit btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="users.php?delete=<?php echo $user['id']; ?>" 
                                           class="btn btn-icon btn-delete btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this user?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit User Modal -->
    <div class="modal" id="userModal" <?php echo isset($_GET['edit']) ? 'style="display: flex;"' : ''; ?>>
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-user me-1"></i>
                    <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
                </h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="post" action="users.php">
                <input type="hidden" name="id" value="<?php echo $edit_user['id'] ?? ''; ?>">
                <input type="hidden" name="original_email" value="<?php echo $edit_user['email'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo htmlspecialchars($edit_user['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo (($edit_user['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($edit_user['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password">
                            <small><?php echo $edit_user ? 'Leave blank to keep current password' : 'Required for new users'; ?></small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- User Details Modal -->
    <div class="modal" id="userDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-user-circle me-1"></i>
                    User Details
                </h3>
                <button class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="userDetailsContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal() {
            document.getElementById('userModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
            // Redirect to clear edit parameters
            window.location.href = 'users.php';
        }
        
        function closeDetailsModal() {
            document.getElementById('userDetailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                if (event.target.id === 'userModal') {
                    window.location.href = 'users.php';
                }
            }
        };
        
        // View user details
        function viewUserDetails(userId) {
            // Show loading indicator
            document.getElementById('userDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading user details...</p>
                </div>
            `;
            
            document.getElementById('userDetailsModal').style.display = 'flex';
            
            // Fetch user details via AJAX
            fetch(`user_details.php?user_id=${userId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('userDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('userDetailsContent').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i> Error loading user details
                        </div>
                    `;
                });
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeDetailsModal();
            }
        });
    </script>
</body>
</html>