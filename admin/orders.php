<?php
session_start();
require_once '../components/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle order deletion
if (isset($_GET['delete'])) {
    $group_id = $_GET['delete'];
    
    // Start transaction to delete from all related tables
    $conn->begin_transaction();
    
    try {
        // Delete from payments table first
        $stmt1 = $conn->prepare("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE group_id = ?)");
        $stmt1->bind_param("s", $group_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Delete from order_items table
        $stmt2 = $conn->prepare("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE group_id = ?)");
        $stmt2->bind_param("s", $group_id);
        $stmt2->execute();
        $stmt2->close();
        
        // Finally delete from orders table
        $stmt3 = $conn->prepare("DELETE FROM orders WHERE group_id = ?");
        $stmt3->bind_param("s", $group_id);
        $stmt3->execute();
        $stmt3->close();
        
        $conn->commit();
        header("Location: orders.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error deleting order: " . $e->getMessage();
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $group_id = $_POST['group_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE group_id = ?");
    $stmt->bind_param("ss", $new_status, $group_id);
    if ($stmt->execute()) {
        $success = "Order status updated successfully";
    } else {
        $error = "Error updating order status: " . $conn->error;
    }
    $stmt->close();
}

// Handle filters
$search = $_GET['search'] ?? '';
$customer = $_GET['customer'] ?? '';
$status_filter = $_GET['status'] ?? '';
$order_date = $_GET['order_date'] ?? '';
$order_id = $_GET['order_id'] ?? '';

// Build query with filters
$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(o.name LIKE ? OR o.email LIKE ? OR o.number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($customer)) {
    $conditions[] = "o.name LIKE ?";
    $params[] = "%$customer%";
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($order_date)) {
    $conditions[] = "DATE(oi.created_at) = ?";
    $params[] = $order_date;
    $types .= 's';
}

if (!empty($order_id)) {
    $conditions[] = "o.group_id LIKE ?";
    $params[] = "%$order_id%";
    $types .= 's';
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT 
            o.group_id,
            o.name AS customer_name,
            o.email,
            o.number,
            o.address,
            o.address_type,
            o.status,
            p.method AS payment_method,
            COALESCE(p.confirmed, 0) AS payment_confirmed,
            COALESCE(SUM(oi.price * oi.qty), 0) AS total_amount,
            MAX(oi.created_at) AS order_date
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN payments p ON o.id = p.order_id
        $where
        GROUP BY o.group_id, o.name, o.email, o.number, o.address, o.address_type, o.status, p.method, p.confirmed
        ORDER BY order_date DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct statuses for filter dropdown
$statuses = [
    ['status' => 'in progress'],
    ['status' => 'completed'],
    ['status' => 'cancelled'],
    ['status' => 'shipped'],
    ['status' => 'delivered']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - Order Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/orders.css">
    
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
                    <li><a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> <span>Orders</span></a></li>
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
                <h1>Order Management</h1>

            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-shopping-cart me-2"></i> Orders</h2>
                    <!-- <button class="btn btn-primary"> -->
                        <!-- <i class="fas fa-file-export me-1"></i> Export Orders -->
                    </button>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> Order deleted successfully
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <h3><i class="fas fa-filter me-2"></i> Filter Orders</h3>
                    <form method="get" action="orders.php">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" id="search" name="search" placeholder="Search orders..." 
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-group">
                                <label for="customer">Customer</label>
                                <input type="text" id="customer" name="customer" placeholder="Customer name..." 
                                    value="<?php echo htmlspecialchars($customer); ?>">
                            </div>
                            <div class="form-group">
                                <label for="status">Order Status</label>
                                <select id="status" name="status">
                                    <option value="">All Status</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status['status']); ?>" 
                                            <?php echo ($status_filter === $status['status']) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status['status']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="order_date">Order Date</label>
                                <input type="date" id="order_date" name="order_date" 
                                    value="<?php echo htmlspecialchars($order_date); ?>">
                            </div>
                            <div class="form-group">
                                <label for="order_id">Order ID</label>
                                <input type="text" id="order_id" name="order_id" placeholder="Order ID..." 
                                    value="<?php echo htmlspecialchars($order_id); ?>">
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="orders.php" class="btn btn-cancel">Reset</a>
                        </div>
                    </form>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $status = $order['status'] ?? 'in progress';
                                $status_class = '';
                                $status_text = '';
                                
                                if ($status === 'in progress') {
                                    $status_class = 'status-in-progress';
                                    $status_text = 'In Progress';
                                } elseif ($status === 'completed') {
                                    $status_class = 'status-completed';
                                    $status_text = 'Completed';
                                } elseif ($status === 'cancelled') {
                                    $status_class = 'status-cancelled';
                                    $status_text = 'Cancelled';
                                } elseif ($status === 'shipped') {
                                    $status_class = 'status-shipped';
                                    $status_text = 'Shipped';
                                } elseif ($status === 'delivered') {
                                    $status_class = 'status-delivered';
                                    $status_text = 'Delivered';
                                }
                                
                                $payment_status = $order['payment_confirmed'] ? 'Paid' : 'Pending';
                                $payment_class = $order['payment_confirmed'] ? 'status-completed' : 'status-pending';
                                
                                $order_date = $order['order_date'] ? date('Y-m-d', strtotime($order['order_date'])) : 'N/A';
                                
                                $total_amount = $order['total_amount'] ?? 0;
                            ?>
                            <tr>
                                <td>#<?php echo substr($order['group_id'], 0, 8); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo $order_date; ?></td>
                                <td>₹<?php echo number_format($total_amount, 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $payment_class; ?>">
                                        <?php echo $payment_status; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-icon btn-view btn-sm" 
                                            onclick="viewOrderDetails('<?php echo $order['group_id']; ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-icon btn-edit btn-sm" 
                                            onclick="editOrderStatus('<?php echo $order['group_id']; ?>', '<?php echo $status; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="orders.php?delete=<?php echo $order['group_id']; ?>" 
                                           class="btn btn-icon btn-delete btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this order?');">
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
    
    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-file-invoice me-1"></i>
                    Order Details
                </h3>
                <button class="modal-close" onclick="closeModal('orderDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeModal('orderDetailsModal')">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Status Modal -->
    <div class="modal" id="editStatusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-edit me-1"></i>
                    Update Order Status
                </h3>
                <button class="modal-close" onclick="closeModal('editStatusModal')">&times;</button>
            </div>
            <form method="post" action="orders.php">
                <input type="hidden" name="group_id" id="editGroupId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newStatus">Order Status</label>
                        <select id="newStatus" name="status" class="form-control">
                            <option value="in progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('editStatusModal')">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
        
        // View order details
        function viewOrderDetails(groupId) {
            // Show loading indicator
            document.getElementById('orderDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading order details...</p>
                </div>
            `;
            
            openModal('orderDetailsModal');
            
            // Fetch order details via AJAX
            fetch(`order_details.php?group_id=${groupId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="message error">
                            <i class="fas fa-exclamation-circle"></i> Error loading order details
                        </div>
                    `;
                });
        }
        
        // Edit order status
        function editOrderStatus(groupId, currentStatus) {
            document.getElementById('editGroupId').value = groupId;
            document.getElementById('newStatus').value = currentStatus;
            openModal('editStatusModal');
        }
    </script>
</body>
</html>