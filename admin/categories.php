<?php
session_start();
require_once '../components/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch categories data from products table
$categories = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            category as name,
            COUNT(*) as product_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
        FROM products 
        WHERE category IS NOT NULL AND category != ''
        GROUP BY category
        ORDER BY product_count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Determine overall status based on product counts
        $status = ($row['active_count'] > 0) ? 'active' : 'inactive';
        $categories[] = [
            'name' => $row['name'],
            'product_count' => $row['product_count'],
            'active_count' => $row['active_count'],
            'inactive_count' => $row['inactive_count'],
            'status' => $status
        ];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Categories error: " . $e->getMessage());
}

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        
        try {
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count == 0) {
                $_SESSION['message'] = "Category '$name' can be used for new products.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Category '$name' already exists in products.";
                $_SESSION['message_type'] = 'info';
            }
            header("Location: categories.php");
            exit();
        } catch (Exception $e) {
            error_log("Add category error: " . $e->getMessage());
            $_SESSION['message'] = "Error processing category.";
            $_SESSION['message_type'] = 'error';
        }
    }
    
    if (isset($_POST['update_category'])) {
        $old_name = $_POST['old_name'];
        $new_name = trim($_POST['new_name']);
        
        try {
            // Update category name in all products that have this category
            $stmt = $conn->prepare("UPDATE products SET category = ? WHERE category = ?");
            $stmt->bind_param("ss", $new_name, $old_name);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Category updated successfully!";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "No products found with this category.";
                $_SESSION['message_type'] = 'info';
            }
            $stmt->close();
            header("Location: categories.php");
            exit();
        } catch (Exception $e) {
            error_log("Update category error: " . $e->getMessage());
            $_SESSION['message'] = "Error updating category.";
            $_SESSION['message_type'] = 'error';
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $name = $_POST['name'];
        
        try {
            // Remove category from all products
            $stmt = $conn->prepare("UPDATE products SET category = NULL WHERE category = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Category removed from products successfully!";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "No products found with this category.";
                $_SESSION['message_type'] = 'info';
            }
            $stmt->close();
            header("Location: categories.php");
            exit();
        } catch (Exception $e) {
            error_log("Delete category error: " . $e->getMessage());
            $_SESSION['message'] = "Error removing category.";
            $_SESSION['message_type'] = 'error';
        }
    }
    
    if (isset($_POST['update_category_status'])) {
        $name = $_POST['name'];
        $status = $_POST['status'];
        
        try {
            // Update status of all products in this category
            $stmt = $conn->prepare("UPDATE products SET status = ? WHERE category = ?");
            $stmt->bind_param("ss", $status, $name);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['message'] = "Category status updated successfully!";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "No products found with this category.";
                $_SESSION['message_type'] = 'info';
            }
            $stmt->close();
            header("Location: categories.php");
            exit();
        } catch (Exception $e) {
            error_log("Update category status error: " . $e->getMessage());
            $_SESSION['message'] = "Error updating category status.";
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Display messages
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Haven - Categories</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="../css/admin-css/categories-reports.css">
    <!--had added to css-->
    <!-- <style>
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
        .product-stats {
            font-size: 12px;
            color: #666;
        }
    </style> -->
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
                    <li><a href="categories.php" class="active"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> <span>Admin Profile</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Categories</h1>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="section-title">
                    <h2><i class="fas fa-tags me-2"></i> Product Categories</h2>
                    <button class="btn btn-primary" onclick="openAddCategoryModal()"><i class="fas fa-plus me-1"></i> Add New Category</button>
                </div>

                <!-- Category Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-tags"></i></div>
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">Total Categories</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-coffee"></i></div>
                        <div class="stat-value"><?php echo array_sum(array_column($categories, 'product_count')); ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?php echo count(array_filter($categories, function($cat) { return $cat['status'] === 'active'; })); ?></div>
                        <div class="stat-label">Active Categories</div>
                    </div>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Products</th>
                            <th>Active Products</th>
                            <th>Inactive Products</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No categories found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td><?php echo $category['product_count']; ?></td>
                                    <td><?php echo $category['active_count']; ?></td>
                                    <td><?php echo $category['inactive_count']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $category['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-icon btn-edit btn-sm" onclick="openEditCategoryModal('<?php echo htmlspecialchars($category['name']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-icon btn-status btn-sm" onclick="openStatusCategoryModal('<?php echo htmlspecialchars($category['name']); ?>', '<?php echo $category['status']; ?>')">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                            <button class="btn btn-icon btn-delete btn-sm" onclick="openDeleteCategoryModal('<?php echo htmlspecialchars($category['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Category Modal -->
    <div class="modal" id="addCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus me-2"></i> Add New Category</h3>
                <button class="close-modal" onclick="closeModal('addCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="categories.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category-name">Category Name</label>
                        <input type="text" id="category-name" name="name" class="form-control" placeholder="Enter category name" required>
                        <small class="form-text">This category can be assigned to new products.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal" id="editCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit me-2"></i> Edit Category</h3>
                <button class="close-modal" onclick="closeModal('editCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="categories.php">
                <input type="hidden" id="edit-old-name" name="old_name">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-new-name">New Category Name</label>
                        <input type="text" id="edit-new-name" name="new_name" class="form-control" required>
                        <small class="form-text">This will update the category name for all products that currently have this category.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Category Modal -->
    <div class="modal" id="statusCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-power-off me-2"></i> Update Category Status</h3>
                <button class="close-modal" onclick="closeModal('statusCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="categories.php">
                <input type="hidden" id="status-category-name" name="name">
                <div class="modal-body">
                    <p>Update status for all products in category: <strong id="status-category-display"></strong></p>
                    <div class="form-group">
                        <label for="status-select">Status</label>
                        <select id="status-select" name="status" class="select-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusCategoryModal')">Cancel</button>
                    <button type="submit" name="update_category_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal" id="deleteCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-trash me-2"></i> Remove Category</h3>
                <button class="close-modal" onclick="closeModal('deleteCategoryModal')">&times;</button>
            </div>
            <form method="POST" action="categories.php">
                <input type="hidden" id="delete-category-name" name="name">
                <div class="modal-body">
                    <p>Are you sure you want to remove the category "<span id="delete-category-display"></span>" from all products?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i> This will remove the category from all products that currently have it. The products will remain but will have no category.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteCategoryModal')">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-danger">Remove Category</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
            document.getElementById('category-name').value = '';
        }

        function openEditCategoryModal(name) {
            document.getElementById('edit-old-name').value = name;
            document.getElementById('edit-new-name').value = name;
            document.getElementById('editCategoryModal').style.display = 'flex';
        }

        function openStatusCategoryModal(name, currentStatus) {
            document.getElementById('status-category-name').value = name;
            document.getElementById('status-category-display').textContent = name;
            document.getElementById('status-select').value = currentStatus;
            document.getElementById('statusCategoryModal').style.display = 'flex';
        }

        function openDeleteCategoryModal(name) {
            document.getElementById('delete-category-name').value = name;
            document.getElementById('delete-category-display').textContent = name;
            document.getElementById('deleteCategoryModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (const modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>