<?php
ob_start();
include_once '../components/session.php';
include_once '../components/connection.php';
include '../user/header.php';
include '../components/popups.php';

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_GET['logout'])) {
   unset($user_id);
   session_destroy();
   header('location:index.php');
   exit;
}

if (isset($_POST['add_to_cart'])) {
   if (!$user_id) {
      header('Location: login.php?source=add_to_cart');
      exit;
   }
   $product_id = htmlspecialchars($_POST['product_id']);
   $product_price = htmlspecialchars($_POST['product_price']);
   $product_qty = htmlspecialchars($_POST['product_quantity']);

   $check_query = mysqli_query($conn, "SELECT * FROM `cart` WHERE product_id = '$product_id' AND user_id = '$user_id'") or die('query failed');

   if (mysqli_num_rows($check_query) > 0) {
      $_SESSION['popup_message'] = "Product Already in cart!";
        header("Location: products.php");
        exit;
   } else {
      mysqli_query($conn, "INSERT INTO `cart` (user_id, product_id, price, qty) 
         VALUES ('$user_id', '$product_id', '$product_price', '$product_qty')") or die('query failed');
      $_SESSION['popup_message'] = "Product Added to cart.";
        header("Location: products.php");
        exit;
   }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
// $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build product query with filters
$product_query = "SELECT * FROM `products` WHERE 1";
$conditions = [];

if (!empty($search)) {
    $conditions[] = "`title` LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
}

if (!empty($category_filter)) {
    $conditions[] = "`category` = '" . mysqli_real_escape_string($conn, $category_filter) . "'";
}

// if (!empty($status_filter)) {
//     $conditions[] = "`status` = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
// }

if (!empty($conditions)) {
    $product_query .= " AND " . implode(" AND ", $conditions);
}

// Get distinct categories for filter dropdown
$category_query = "SELECT DISTINCT category FROM `products` WHERE category IS NOT NULL AND category != ''";
$category_result = mysqli_query($conn, $category_query);
$categories = [];
if ($category_result) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row['category'];
    }
}

$select_product = mysqli_query($conn, $product_query) or die('query failed: ' . mysqli_error($conn));
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Products</title>
   <link rel="stylesheet" href="../css/user-css/cart.css">
   <style>
   
   </style>
</head>
<body>
<div class="container">
   <div class="user-profile">
      <?php
         $select_user = mysqli_query($conn, "SELECT * FROM `users` WHERE id = '$user_id'") or die('query failed');
         if(mysqli_num_rows($select_user) > 0) {
            $fetch_user = mysqli_fetch_assoc($select_user);
         }
      ?>
      <!-- <p>Username: <span><?= $fetch_user['name'] ?></span></p>
      <p>Email: <span><?= $fetch_user['email'] ?></span></p> -->
   </div>
   <div class="flex">
         <a href="menu.php" class="back-btn"><i class='fas fa-chevron-left'></i></a>
   </div>

   <!-- Filter Section -->
   <div class="filter-section">
      <h2 style="margin-top: 0; margin-bottom: 20px; color: #ddd;">Filter Products</h2>
      <form method="GET" action="products.php" class="filter-container">
         
         <div class="filter-group">
            <!-- <label for="search">Search</label> -->
            <input type="text" id="search" name="search" placeholder="Search products..." 
                   value="<?= htmlspecialchars($search) ?>">
         </div>
         
         <div class="filter-group">
            <!-- <label for="category">Category</label> -->
            <select id="category" name="category">
               <option value="">All Categories</option>
               <?php foreach ($categories as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>" 
                     <?= ($category_filter == $cat) ? 'selected' : '' ?>>
                     <?= htmlspecialchars($cat) ?>
                  </option>
               <?php endforeach; ?>
            </select>
         </div>
         
         <!-- <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status">
               <option value="">All Status</option>
               <option value="active" <?= ($status_filter == 'active') ? 'selected' : '' ?>>Active</option>
               <option value="out-of-stock" <?= ($status_filter == 'out-of-stock') ? 'selected' : '' ?>>Out of Stock</option>
               <option value="archived" <?= ($status_filter == 'archived') ? 'selected' : '' ?>>Archived</option>
            </select>
         </div> -->
         
         <div class="filter-actions">
            <button type="submit" class="filter-btn">
               <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="products.php" class="reset-btn">
               <i class="fas fa-sync-alt"></i> Reset
            </a>
         </div>
      </form>
   </div>

   <div class="products">
      <h1 class="heading"><?= empty($search) ? 'Our Products' : 'Search Results' ?></h1>
      <div class="box-container">
         <?php if(mysqli_num_rows($select_product) > 0): ?>
            <?php while($fetch_product = mysqli_fetch_assoc($select_product)): ?>
               <form method="post" class="box" action="">
                  <img src="../images/products/<?= htmlspecialchars($fetch_product['image']) ?>" alt="">
                  <div class="name"><?= htmlspecialchars($fetch_product['name']) ?></div>
                  <div class="price">₹ <?= htmlspecialchars($fetch_product['price']) ?></div>
                  <input type="number" min="1" name="product_quantity" value="1" required>
                  <input type="hidden" name="product_id" value="<?= htmlspecialchars($fetch_product['id']) ?>">
                  <input type="hidden" name="product_price" value="<?= htmlspecialchars($fetch_product['price']) ?>">
                  <input type="submit" value="Add to Cart" name="add_to_cart" class="btn">
                  <a href="cart.php"><i class="view-cart-btn">View 🛒</i></a>
               </form>
            <?php endwhile; ?>
         <?php else: ?>
            <div class="no-products" style="grid-column: 1/-1; text-align: center; padding: 40px; color: white;">
               <?= empty($search) ? 'No products available' : 'No products found matching "' .htmlspecialchars($search).'"' ?>
            </div>
         <?php endif; ?>
      </div>
   </div>
</div>

<?php include_once 'footer.php';?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script src="../js/script.js"></script>
</body>
</html>