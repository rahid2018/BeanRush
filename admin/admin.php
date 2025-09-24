<?php
session_start();
require_once '../components/connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch stats from database
$total_sales_today = 0;
$today_orders = 0;
$pending_orders = 0;
$total_products = 0;
$registered_customers = 0;
$most_popular_product = "N/A";
$popular_product_sold_today = 0;
$recent_orders = [];

try {
    // Total sales today
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p
        WHERE DATE(p.created_at) = CURDATE() AND p.status = 'completed'
    ");
    $stmt->execute();
    $stmt->bind_result($total_sales_today);
    $stmt->fetch();
    $stmt->close();

    // Today's orders
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT group_id) 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $stmt->bind_result($today_orders);
    $stmt->fetch();
    $stmt->close();

    // Pending orders
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT group_id) 
        FROM orders 
        WHERE status = 'in progress'
    ");
    $stmt->execute();
    $stmt->bind_result($pending_orders);
    $stmt->fetch();
    $stmt->close();

    // Total products
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $stmt->execute();
    $stmt->bind_result($total_products);
    $stmt->fetch();
    $stmt->close();

    // Registered customers
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stmt->execute();
    $stmt->bind_result($registered_customers);
    $stmt->fetch();
    $stmt->close();

    // Most popular product today
    $stmt = $conn->prepare("
        SELECT p.name, SUM(oi.qty) as sold_today 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) = CURDATE() 
        GROUP BY oi.product_id 
        ORDER BY sold_today DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $stmt->bind_result($mp_product, $mp_sold);
    if ($stmt->fetch()) {
        $most_popular_product = $mp_product;
        $popular_product_sold_today = $mp_sold;
    }
    $stmt->close();

    // Recent orders 
    $stmt = $conn->prepare("
        SELECT 
            o.group_id, 
            o.name AS customer_name, 
            o.status,
            COALESCE(SUM(oi.price * oi.qty), 0) AS total_amount
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.group_id, o.name, o.status
        ORDER BY MAX(o.created_at) DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = [
            "#GO" . substr($row['group_id'], -6),
            $row['customer_name'],
            $row['total_amount'],
            $row['status']
        ];
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Monthly sales data
$monthly_sales = array_fill_keys(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], 0);
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(p.created_at, '%b') AS month,
            COALESCE(SUM(p.amount), 0) AS total
        FROM payments p
        WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) 
            AND p.status = 'completed'
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m'), DATE_FORMAT(p.created_at, '%b')
        ORDER BY MIN(p.created_at)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $monthly_sales[$row['month']] = (float)$row['total'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Sales chart error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="logo">
                <i class="fas fa-mug-hot"></i>
                <span>BeanRush Admin</span>
            </div>
            <nav>
                <ul>
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="products.php"><i class="fas fa-coffee"></i> <span>Products</span></a></li>
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
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-chart-pie me-2"></i> Business Overview</h2>
                    <div class="quick-actions">
                        <button class="btn btn-primary btn-sm" onclick="location.href='products.php?action=add'">
                            <i class="fas fa-plus me-1"></i> Add Product
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="location.href='orders.php'">
                            <i class="fas fa-eye me-1"></i> View Orders
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="location.href='users.php'">
                            <i class="fas fa-users me-1"></i> Manage Users
                        </button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                        <div class="stat-value">₹<?php echo number_format($total_sales_today, 2); ?></div>
                        <div class="stat-label">Total Sales Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="stat-value"><?php echo $today_orders; ?></div>
                        <div class="stat-label">Today's Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-value"><?php echo $pending_orders; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                        <div class="stat-value"><?php echo $total_products; ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value"><?php echo number_format($registered_customers); ?></div>
                        <div class="stat-label">Registered Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-value"><?php echo $most_popular_product; ?></div>
                        <div class="stat-label">Most Popular Product</div>
                        <div class="popular-product">
                            <img src="../images/products/<?php 
                                echo 'default-product.jpg'; 
                            ?>" alt="Popular Product" onerror="this.src='https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?crop=entropy&cs=tinysrgb&fit=crop&fm=jpg&h=100&w=100'">
                            <div class="popular-product-info">
                                <h4><?php echo $most_popular_product; ?></h4>
                                <p><i class="fas fa-shopping-bag me-1"></i> <?php echo $popular_product_sold_today; ?> sold today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart and Orders -->
                <div class="dashboard-row">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-line me-2"></i> Monthly Sales Trend</h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    <div class="recent-orders">
                        <h3><i class="fas fa-history me-2"></i> Recent Orders</h3>
                        <?php if (!empty($recent_orders)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order[0]); ?></td>
                                            <td><?php echo htmlspecialchars($order[1]); ?></td>
                                            <td>₹<?php echo number_format($order[2], 2); ?></td>
                                            <td>
                                                <?php
                                                    $status_class = strtolower(str_replace(' ', '-', $order[3]));
                                                    echo "<span class='status-badge status-$status_class'>{$order[3]}</span>";
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-orders">No recent orders found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Monthly Sales (₹)',
                        data: <?php echo json_encode(array_values($monthly_sales)); ?>,
                        borderColor: '#4b3621',
                        backgroundColor: 'rgba(139, 94, 46, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#d4a55f',
                        pointBorderColor: '#fff',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(44, 26, 13, 0.9)',
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 14 },
                            callbacks: {
                                label: function(context) {
                                    return '₹' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { 
                                color: 'rgba(0, 0, 0, 0.05)',
                                drawBorder: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                },
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: { 
                            grid: { 
                                display: false 
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    }
                }
            });
        });
    </script>
</body>
</html>