<?php
ob_start();
include_once '../components/connection.php';
include_once '../components/popups.php';
include_once '../components/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" />
  <link rel="stylesheet" href="../css/user-css/styles.css" />
  <link rel="stylesheet" href="../css/user-css/user.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <title>BeanRush</title>
</head>
<body>
<header>
    <nav class="navbar section-content">
        <a href="#" class="nav-logo">
            <h2 class="logo-text"><i class="fas fa-mug-hot"></i> BeanRush</h2>
        </a>

        <button id="menu-open-button" class="fas fa-bars"></button>

        <ul class="nav-menu">
            <button id="menu-close-button" class="fas fa-times"></button>
            <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
            <li class="nav-item"><a href="#gallery" class="nav-link">Gallery</a></li>
            <li class="nav-item"><a href="products.php" class="nav-link">Products</a></li>
            <li class="nav-item"><a href="#about" class="nav-link">About</a></li>
            <li class="nav-item"><a href="orders.php" class="nav-link">Orders</a></li>
            <?php if ($user_id): 
                // Fetch user details
                $select_user = mysqli_query($conn, "SELECT * FROM `users` WHERE id = '$user_id'");
                $fetch_user = mysqli_fetch_assoc($select_user);
            ?>
                <li class="nav-item account-dropdown">
                    <a href="#" class="nav-link account-btn">Profile</a>
                    <div class="dropdown-content">
                        <div class="user-details">
                            <p>User: <span><?= $fetch_user['name'] ?></span></p>
                            <p>Email: <span><?= $fetch_user['email'] ?></span></p>
                        </div>
                        <a href="?logout=true" class="logout-link" onclick="return confirm('Logout from this account?');">Logout</a>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item"><a href="login.php" class="nav-link">Account</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>