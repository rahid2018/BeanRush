<?php
session_start();
include_once '../components/connection.php';
include_once '../components/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('location:login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $group_id = $_POST['group_id'];
    
    // Update order status to cancelled
    $update = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE group_id = ? AND user_id = ?");
    $update->bind_param("si", $group_id, $user_id);
    $update->execute();
    
    // Set success message
    $_SESSION['cancel_success'] = "Order #" . substr($group_id, 0, 8) . " has been cancelled successfully!";
    
    // Refresh the page to show updated status
    header("Location: orders.php");
    exit();
}

// Get all orders for this user grouped by order group - FIXED QUERY
$stmt = $conn->prepare("
    SELECT 
        o.group_id,
        MAX(oi.created_at) AS order_date,  -- Using order_items.created_at
        o.status,
        o.address_type,
        o.name,
        o.email,
        o.address,
        p.method AS payment_method,  -- From payments table
        p.status AS payment_status,  -- From payments table
        SUM(oi.price * oi.qty) AS total_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN payments p ON o.id = p.order_id
    WHERE o.user_id = ?
    GROUP BY o.group_id, o.status, o.address_type, o.name, o.email, o.address, p.method, p.status
    ORDER BY order_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get order items for each group
$order_items = [];
foreach ($orders as $order) {
    $stmt = $conn->prepare("
        SELECT oi.*, p.name AS product_name, p.image 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.group_id = ?
    ");
    $stmt->bind_param("s", $order['group_id']);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $order_items[$order['group_id']] = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Check for cancellation success message
$cancel_success = '';
if (isset($_SESSION['cancel_success'])) {
    $cancel_success = $_SESSION['cancel_success'];
    unset($_SESSION['cancel_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Bean Rush</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/user-css/orders.css">

</head>
<body>
    <div class="container">
        <!-- Display cancellation success message -->
        <?php if (!empty($cancel_success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?= $cancel_success ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Shop
            </a>
        </div>
        
        <!-- Orders Container -->
        <div class="orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?= substr($order['group_id'], 0, 8) ?></div>
                                <div class="order-date"><?= date('M d, Y', strtotime($order['order_date'])) ?></div>
                            </div>
                            <div>
                                <?php 
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch ($order['status']) {
                                        case 'in progress':
                                            $status_class = 'status-in-progress';
                                            $status_text = 'In Progress';
                                            break;
                                        case 'shipped':
                                            $status_class = 'status-shipped';
                                            $status_text = 'Shipped';
                                            break;
                                        case 'delivered':
                                            $status_class = 'status-delivered';
                                            $status_text = 'Delivered';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'status-cancelled';
                                            $status_text = 'Cancelled';
                                            break;
                                        default:
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                    }
                                ?>
                                <span class="order-status <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <div class="customer-info">
                                <h3 class="info-title"><i class="fas fa-user"></i> Customer Information</h3>
                                <div class="info-item">
                                    <div class="info-label">Name</div>
                                    <div class="info-value"><?= htmlspecialchars($order['name']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?= htmlspecialchars($order['email']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?= htmlspecialchars($order['address']) ?></div>
                                </div>
                            </div>
                            
                            <div class="shipping-info">
                                <h3 class="info-title"><i class="fas fa-truck"></i> Order Information</h3>
                                <div class="info-item">
                                    <div class="info-label">Address Type</div>
                                    <div class="info-value">
                                        <?= ucfirst(htmlspecialchars($order['address_type'])) ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?= ucfirst($order['payment_method']) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Payment Status</div>
                                    <div class="info-value">
                                        <?php 
                                            $payment_class = '';
                                            $payment_text = ucfirst($order['payment_status']);
                                            
                                            switch ($order['payment_status']) {
                                                case 'completed':
                                                    $payment_class = 'payment-paid';
                                                    break;
                                                case 'pending':
                                                    $payment_class = 'payment-pending';
                                                    break;
                                                case 'failed':
                                                    $payment_class = 'payment-failed';
                                                    break;
                                                case 'refunded':
                                                    $payment_class = 'payment-refunded';
                                                    break;
                                                default:
                                                    $payment_class = 'payment-pending';
                                            }
                                            
                                            // Special case for COD
                                            if ($order['payment_method'] === 'cash on delivery') {
                                                $payment_class = 'payment-cod';
                                            }
                                        ?>
                                        <span class="payment-status <?= $payment_class ?>">
                                            <?= $payment_text ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h3 class="order-items-title"><i class="fas fa-box"></i> Order Items</h3>
                        
                        <?php if (isset($order_items[$order['group_id']])): ?>
                            <?php foreach ($order_items[$order['group_id']] as $item): ?>
                                <div class="order-item">
                                    <img src="../images/products/<?= htmlspecialchars($item['image']) ?>" 
                                         alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                         class="item-image">
                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="item-price">₹<?= number_format($item['price'], 2) ?></div>
                                        <div class="item-qty">Quantity: <?= $item['qty'] ?></div>
                                    </div>
                                    <div class="item-subtotal">
                                        ₹<?= number_format($item['price'] * $item['qty'], 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-items">No items found for this order</p>
                        <?php endif; ?>
                        
                        <div class="order-total">
                            <span class="total-label">Total Amount:</span>
                            <span class="total-amount">₹<?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                        
                        <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                            <div class="order-actions">
                                <button class="cancel-btn" onclick="openCancelModal('<?= $order['group_id'] ?>')">
                                    <i class="fas fa-times-circle"></i> Cancel Order
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3 class="empty-message">You haven't placed any orders yet</h3>
                    <a href="products.php" class="back-btn">
                        <i class="fas fa-shopping-bag"></i> Browse Products
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Cancel Order</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p class="modal-text">Are you sure you want to cancel this order? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="cancelForm">
                    <input type="hidden" name="group_id" id="cancelGroupId">
                    <button type="submit" name="cancel_order" class="confirm-btn confirm-cancel">
                        <i class="fas fa-times-circle"></i> Confirm Cancel
                    </button>
                    <button type="button" class="confirm-btn confirm-close" onclick="closeModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Function to open the cancel modal
        function openCancelModal(groupId) {
            document.getElementById('cancelGroupId').value = groupId;
            document.getElementById('confirmationModal').classList.add('active');
        }
        
        // Function to close the modal
        function closeModal() {
            document.getElementById('confirmationModal').classList.remove('active');
        }
        
        // Close modal when clicking outside the content
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('confirmationModal');
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>