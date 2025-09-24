<?php
ob_start();
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../components/session.php';
include_once '../components/connection.php';
include_once '../components/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('location:login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_SESSION['order_id'] ?? null;

if (!$order_id) {
    header('location:index.php');
    exit();
}

// Fetch order - FIXED to include created_at
$stmt = $conn->prepare("
    SELECT o.*, p.amount, p.method AS payment_method, p.status AS payment_status 
    FROM orders o
    JOIN payments p ON o.id = p.order_id
    WHERE o.id = ?
");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name AS product_name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$order || count($order_items) === 0) {
    header('location:index.php');
    exit();
}

$total_amount = 0;
foreach ($order_items as $item) {
    $total_amount += $item['price'] * $item['qty'];
}

// Clear the success message after displaying
if (isset($_SESSION['order_success'])) {
    $success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
} else {
    if (count($order_items) === 0) {
        header('location:index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/user-css/confirmation.css" />
</head>
<body>
    <div class="confirmation-container">
        <div class="header">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1 class="confirmation-title">Order Confirmed!</h1>
            
            <p class="confirmation-message">
                Thank you for your order! Your purchase is being processed and you'll receive a confirmation email shortly.
            </p>
        </div>
        
        <div class="content">
            <div class="summary-card">
                <div class="summary-title">
                    <i class="fas fa-receipt"></i>
                    <h2>Order Summary</h2>
                </div>
                
                <?php if ($order): ?>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Order Number:</span>
                            <span class="detail-value">#<?= substr($order['id'], 0, 8) ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Order Date:</span>
                            <span class="detail-value">
                                <?php 
                                    if (!empty($order['created_at'])) {
                                        echo date('F j, Y', strtotime($order['created_at']));
                                    } else {
                                        echo date('F j, Y');
                                    }
                                ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Customer Name:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['name']) ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value"><?= htmlspecialchars($order['payment_method']) ?></span>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h3 class="items-title">Order Items</h3>
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <img src="../images/products/<?= $item['image'] ?>" alt="<?= $item['product_name'] ?>" class="order-item-image">
                                <div class="order-item-details">
                                    <div class="order-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="order-item-info">
                                        <span>Qty: <?= $item['qty'] ?></span>
                                        <span>₹<?= number_format($item['price'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-total">
                        <span>Total Amount:</span>
                        <span>₹<?= number_format($total_amount, 2) ?></span>
                    </div>
                    
                    <div class="estimated-delivery">
                        <i class="fas fa-truck"></i>
                        <span>
                            Estimated Delivery: 
                            <?php 
                                if (!empty($order['created_at'])) {
                                    echo date('F j, Y', strtotime($order['created_at'] . ' +3 days'));
                                } else {
                                    echo date('F j, Y', strtotime('+3 days'));
                                }
                            ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p class="confirmation-message">No order details found. Please check your order history.</p>
                <?php endif; ?>
            </div>
            
            <div class="order-details">
                <h2 class="detail-title">Delivery Information</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Delivery Address:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['address']) ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Order Status:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['status']) ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value"><?= htmlspecialchars($order['payment_status']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Continue Shopping
                </a>
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> View Order History
                </a>
            </div>
        </div>
    </div>

    <script>
        // Confetti animation for celebration - FIXED variable name
        document.addEventListener('DOMContentLoaded', function() {
            const confettiCount = 50;
            const container = document.querySelector('.header');
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = `hsl(${Math.random() * 360}, 70%, 60%)`;
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                container.appendChild(confetti);
            }
        });
    </script>
</body>
</html>