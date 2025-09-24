<?php
session_start();
require_once '../components/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get image path before deletion
    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // Delete image file
    if ($product && !empty($product['image'])) {
        $image_path = "../images/products/" . $product['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    header("Location: products.php?deleted=1");
    exit();
}

// Initialize variables
$error = $success = '';
$id = $name = $price = $image = $title = $category = $new_category = '';
$stock = 0;
$status = 'active';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price = isset($_POST['price']) ? trim($_POST['price']) : '';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $new_category = isset($_POST['new_category']) ? trim($_POST['new_category']) : '';
    $stock = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    
    // Handle new category
    if ($category === 'new' && !empty($new_category)) {
        $category = $new_category;
    }
    
    // Validate input
    if (empty($name) || empty($price)) {
        $error = "Product name and price are required";
    } else {
        // Handle image upload
        $uploaded_image = '';
        if (isset($_FILES['image_file']['name']) && $_FILES['image_file']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../images/products/";
            $file_name = basename($_FILES["image_file"]["name"]);
            $target_file = $target_dir . $file_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
            // Check if image file is actual image
            $check = getimagesize($_FILES["image_file"]["tmp_name"]);
            if ($check === false) {
                $error = "File is not an image.";
            } elseif ($_FILES["image_file"]["size"] > 2000000) { // 2MB limit
                $error = "Sorry, your file is too large (max 2MB).";
            } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            } else {
                // Generate unique filename
                $new_filename = uniqid() . '.' . $imageFileType;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
                    $uploaded_image = $new_filename;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }
        }
        
        if (empty($error)) {
            if ($id > 0 && empty($uploaded_image)) {
                $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();
                $image = $existing['image'];
            } else {
                $image = $uploaded_image;
            }
            
            // Save to database
            if ($id > 0) {
                // Update existing product
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, image=?, title=?, category=?, stock=?, status=? WHERE id=?");
                $stmt->bind_param("sssssisi", $name, $price, $image, $title, $category, $stock, $status, $id);
            } else {
                // Add new product
                $stmt = $conn->prepare("INSERT INTO products (name, price, image, title, category, stock, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiss", $name, $price, $image, $title, $category, $stock, $status);
            }
            
            if ($stmt->execute()) {
                $success = $id > 0 ? "Product updated successfully" : "Product added successfully";
            } else {
                $error = "Error saving product: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get product for editing
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_product = $result->fetch_assoc();
    $stmt->close();
}

// Handle filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get products
$sql = "SELECT * FROM products $where ORDER BY name";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct categories
$categories = [];
try {
    $cat_stmt = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    if ($cat_stmt) {
        $categories = $cat_stmt->fetch_all(MYSQLI_ASSOC);
        $cat_stmt->close();
    }
} catch (Exception $e) {
    $error = "Error fetching categories: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - Product Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/products.css">
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
                    <li><a href="products.php" class="active"><i class="fas fa-coffee"></i> <span>Products</span></a></li>
                    <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
                    <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
                    <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> <span>Admin Profile</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Product Management</h1>
            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-coffee me-2"></i> Products</h2>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus me-1"></i> Add New Product
                    </button>
                </div>
                
                <?php if (!empty($success)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> Product deleted successfully
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <h3><i class="fas fa-filter me-2"></i> Filter Products</h3>
                    <form method="get" action="products.php">
                        <div class="filter-row">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" placeholder="Search products..." 
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo ($category_filter === $cat['category']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="out-of-stock" <?php echo ($status_filter === 'out-of-stock') ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="archived" <?php echo ($status_filter === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="products.php" class="btn btn-cancel">Reset</a>
                        </div>
                    </form>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): 
                                $status = $product['status'] ?? 'active';
                                $category = $product['category'] ?? '';
                                $stock = $product['stock'] ?? 0;
                                
                                $status_class = '';
                                $status_text = '';
                                
                                if ($status === 'active') {
                                    $status_class = 'status-active';
                                    $status_text = 'Active';
                                } elseif ($status === 'out-of-stock') {
                                    $status_class = 'status-out-of-stock';
                                    $status_text = 'Out of Stock';
                                } elseif ($status === 'archived') {
                                    $status_class = 'status-archived';
                                    $status_text = 'Archived';
                                }
                            ?>
                            <tr>
                                <td>P<?= str_pad($product['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="popular-product">
                                        <img src="../images/products/<?= htmlspecialchars($product['image']) ?>" 
                                            alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='https://via.placeholder.com/50'">
                                        <div class="popular-product-info">
                                            <h4><?= htmlspecialchars($product['name']) ?></h4>
                                            <p><?= htmlspecialchars($product['title'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($category) ?></td>
                                <td>₹<?= htmlspecialchars($product['price']) ?></td>
                                <td><?= htmlspecialchars($stock) ?></td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="products.php?edit=<?= $product['id'] ?>" 
                                           class="btn btn-icon btn-edit btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="products.php?delete=<?= $product['id'] ?>" 
                                           class="btn btn-icon btn-delete btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this product?');">
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
    
    <!-- Add/Edit Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-coffee me-1"></i>
                    <?php echo isset($edit_product) ? 'Edit Product' : 'Add New Product'; ?>
                </h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="post" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $edit_product['id'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="price">Price (₹) *</label>
                            <input type="text" id="price" name="price" required
                                   value="<?php echo htmlspecialchars($edit_product['price'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Product Title/Description</label>
                        <input type="text" id="title" name="title" 
                               value="<?php echo htmlspecialchars($edit_product['title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category_select" name="category" required onchange="toggleNewCategory()">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php if (isset($edit_product) && $edit_product['category'] === $cat['category']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="new" <?php if (isset($edit_product) && !in_array($edit_product['category'], array_column($categories, 'category'))) echo 'selected'; ?>>-- Add New Category --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" id="stock" name="stock" min="0" 
                                   value="<?php echo $edit_product['stock'] ?? 0; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" id="new-category-group" style="<?php if (!isset($edit_product) || in_array($edit_product['category'], array_column($categories, 'category'))) echo 'display: none;'; ?>">
                        <label for="new_category">New Category Name *</label>
                        <input type="text" id="new_category" name="new_category" 
                               value="<?php if (isset($edit_product) && !in_array($edit_product['category'], array_column($categories, 'category'))) echo htmlspecialchars($edit_product['category']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="image_file">Product Image</label>
                        <?php if (isset($edit_product) && !empty($edit_product['image'])): ?>
                            <img src="../images/products/<?= htmlspecialchars($edit_product['image']) ?>" 
                                 alt="Current Image" class="image-preview">
                        <?php endif; ?>
                        <input type="file" id="image_file" name="image_file" accept="image/*">
                        <small>Leave blank to keep existing image</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo (($edit_product['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="out-of-stock" <?php echo (($edit_product['status'] ?? '') === 'out-of-stock') ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="archived" <?php echo (($edit_product['status'] ?? '') === 'archived') ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo isset($edit_product) ? 'Update Product' : 'Add Product'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal() {
            document.getElementById('productModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }
        
        // Toggle new category field
        function toggleNewCategory() {
            const categorySelect = document.getElementById('category_select');
            const newCategoryGroup = document.getElementById('new-category-group');
            
            if (categorySelect.value === 'new') {
                newCategoryGroup.style.display = 'block';
                document.getElementById('new_category').required = true;
            } else {
                newCategoryGroup.style.display = 'none';
                document.getElementById('new_category').required = false;
            }
        }
        
        // Open modal if we're editing a product
        <?php if (isset($_GET['edit'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                openModal();
                toggleNewCategory();
            });
        <?php endif; ?>
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
            }
        };
        
        // Initialize category field if editing
        <?php if (isset($edit_product)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const categorySelect = document.getElementById('category_select');
                const currentCategory = "<?= $edit_product['category'] ?? '' ?>";
                
                // Add current category if not in options
                if (currentCategory && !Array.from(categorySelect.options).some(opt => opt.value === currentCategory)) {
                    const newOption = document.createElement('option');
                    newOption.value = currentCategory;
                    newOption.textContent = currentCategory;
                    newOption.selected = true;
                    categorySelect.insertBefore(newOption, categorySelect.lastChild);
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>