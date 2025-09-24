<?php
session_start();
require_once '../components/connection.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch reports data
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$total_sales = 0;
$total_orders = 0;
$top_categories = [];
$top_products = [];
$sales_by_day = [];

try {
    // Total sales
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(oi.price * oi.qty), 0) 
        FROM order_items oi 
        INNER JOIN orders o ON oi.order_id = o.id 
        WHERE DATE(oi.created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stmt->bind_result($total_sales);
    $stmt->fetch();
    $stmt->close();

    // Total orders
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT id) 
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stmt->bind_result($total_orders);
    $stmt->fetch();
    $stmt->close();

    // Top categories
    $stmt = $conn->prepare("
        SELECT p.category, SUM(oi.price * oi.qty) as sales 
        FROM order_items oi 
        INNER JOIN products p ON oi.product_id = p.id 
        INNER JOIN orders o ON oi.order_id = o.id 
        WHERE DATE(oi.created_at) BETWEEN ? AND ?
        GROUP BY p.category 
        ORDER BY sales DESC 
        LIMIT 5
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $top_categories[] = $row;
    }
    $stmt->close();

    // Top products
    $stmt = $conn->prepare("
        SELECT p.name, SUM(oi.price * oi.qty) as sales, COUNT(DISTINCT oi.order_id) as orders 
        FROM order_items oi 
        INNER JOIN products p ON oi.product_id = p.id 
        INNER JOIN orders o ON oi.order_id = o.id 
        WHERE DATE(oi.created_at) BETWEEN ? AND ?
        GROUP BY oi.product_id 
        ORDER BY sales DESC 
        LIMIT 5
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $top_products[] = $row;
    }
    $stmt->close();

    // Sales by day
    $stmt = $conn->prepare("
        SELECT 
            DATE(oi.created_at) as day,
            COALESCE(SUM(oi.price * oi.qty), 0) as sales
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE DATE(oi.created_at) BETWEEN ? AND ?
        GROUP BY DATE(oi.created_at)
        ORDER BY DATE(oi.created_at)
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sales_by_day[$row['day']] = (float)$row['sales'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeanRush - Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="../css/admin-css/categories-reports.css">
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
                    <li><a href="reports.php" class="active"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>
                    <li><a href="categories.php"><i class="fas fa-tags"></i> <span>Categories</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user-cog"></i> <span>Admin Profile</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1>Reports</h1>
            </header>

            <div class="admin-content">
                <div class="section-title">
                    <h2><i class="fas fa-chart-line me-2"></i> Sales Reports</h2>
                    <!-- <button class="btn btn-primary"><i class="fas fa-file-pdf me-1"></i> Export PDF</button> -->
                </div>

                <!-- Date Range Filter -->
                <div class="form-container">
                    <h3><i class="fas fa-calendar-alt me-2"></i> Filter Reports</h3>
                    <form action="reports.php" method="GET">
                        <div class="date-range-filter">
                            <div class="form-group">
                                <label for="start-date">Start Date</label>
                                <input type="date" id="start-date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="form-group">
                                <label for="end-date">End Date</label>
                                <input type="date" id="end-date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Reports Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                        <div class="stat-value">₹<?php echo number_format($total_sales, 2); ?></div>
                        <div class="stat-label">Total Sales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="stat-value"><?php echo $total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-percent"></i></div>
                        <div class="stat-value">₹<?php echo $total_orders > 0 ? number_format($total_sales / $total_orders, 2) : '0.00'; ?></div>
                        <div class="stat-label">Average Order Value</div>
                    </div>
                </div>

                <!-- Sales Chart and Top Categories -->
                <div class="dashboard-row">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-line me-2"></i> Daily Sales Trend</h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>
                    <div class="recent-orders">
                        <h3><i class="fas fa-tags me-2"></i> Top Categories</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Sales</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category']); ?></td>
                                        <td>₹<?php echo number_format($category['sales'], 2); ?></td>
                                        <td><?php echo $total_sales > 0 ? number_format(($category['sales'] / $total_sales) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-star me-2"></i> Top Products</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="topProductsChart"></canvas>
                    </div>
                </div>

                <!-- Top Products Table -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-list me-2"></i> Top Products Details</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Total Sales</th>
                                <th>Number of Orders</th>
                                <th>Average Sale per Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>₹<?php echo number_format($product['sales'], 2); ?></td>
                                    <td><?php echo $product['orders']; ?></td>
                                    <td>₹<?php echo number_format($product['sales'] / $product['orders'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate daily sales chart
            const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
            const dailySalesChart = new Chart(dailySalesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($sales_by_day)); ?>,
                    datasets: [{
                        label: 'Daily Sales (₹)',
                        data: <?php echo json_encode(array_values($sales_by_day)); ?>,
                        backgroundColor: 'rgba(75, 54, 33, 0.7)',
                        borderColor: '#4b3621',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // Generate top products chart
            const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
            const topProductsChart = new Chart(topProductsCtx, {
                type: 'horizontalBar',
                data: {
                    labels: <?php echo json_encode(array_column($top_products, 'name')); ?>,
                    datasets: [{
                        label: 'Sales (₹)',
                        data: <?php echo json_encode(array_column($top_products, 'sales')); ?>,
                        backgroundColor: [
                            'rgba(62, 92, 51, 0.7)',
                            'rgba(140, 109, 70, 0.7)',
                            'rgba(75, 54, 33, 0.7)',
                            'rgba(139, 105, 65, 0.7)',
                            'rgba(97, 73, 45, 0.7)'
                        ],
                        borderColor: [
                            '#3e5c33',
                            '#8c6d46',
                            '#4b3621',
                            '#8b6941',
                            '#61492d'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>