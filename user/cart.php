<?php
ob_start();
include_once '../components/session.php';
include_once '../components/connection.php';
include_once '../components/popups.php';
include 'header.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
   header('location:login.php?source=view_cart');
   exit;
}

if (isset($_POST['update_cart'])) {
   $update_qty = htmlspecialchars($_POST['cart_quantity']);
   $update_id = htmlspecialchars($_POST['cart_id']);
   mysqli_query($conn, "UPDATE `cart` SET qty = '$update_qty' WHERE id = '$update_id' AND user_id = '$user_id'") or die('query failed');
   $_SESSION['popup_message'] = "Cart updated successfully...";
        header("Location: cart.php");
        exit;
}

if (isset($_GET['remove'])) {
   $remove_id = htmlspecialchars($_GET['remove']);
   mysqli_query($conn, "DELETE FROM `cart` WHERE id = '$remove_id' AND user_id = '$user_id'") or die('query failed');
   $_SESSION['popup_message'] = "Item removed from cart!";
        header("Location: cart.php");
        exit;
}

if (isset($_GET['delete_all'])) {
   mysqli_query($conn, "DELETE FROM `cart` WHERE user_id = '$user_id'") or die('query failed');
   $_SESSION['popup_message'] = "Deleted all items from cart!";
        header("Location: cart.php");
        exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Shopping Cart</title>
   <link rel="stylesheet" href="../css/user-css/cart.css">
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
         <a href="products.php" class="back-btn"><i class='fas fa-chevron-left'></i></a><!--&larr for symbol;-->
      </div>

   <div class="shopping-cart">
      <h1 class="heading">Shopping Cart</h1>
      
      <div class="cart-list">
         <?php
         $cart_query = mysqli_query($conn, "
             SELECT cart.*, products.name, products.image 
             FROM cart 
             JOIN products ON cart.product_id = products.id 
             WHERE cart.user_id = '$user_id'
         ") or die('query failed');
         
         $grand_total = 0;
         if(mysqli_num_rows($cart_query) > 0) {
            while($fetch_cart = mysqli_fetch_assoc($cart_query)) {
               $sub_total = $fetch_cart['price'] * $fetch_cart['qty'];
               $grand_total += $sub_total;
         ?>
            <div class="cart-item">
               <img src="../images/products/<?= $fetch_cart['image'] ?>" class="cart-image" alt="">
               <div class="cart-name"><?= $fetch_cart['name'] ?></div>
               <div class="cart-price">Price: ₹ <?= $fetch_cart['price'] ?></div>
               
               <form class="cart-quantity-form" action="" method="post">
                  <input type="hidden" name="cart_id" value="<?= $fetch_cart['id'] ?>">
                  <input type="number" min="1" name="cart_quantity" value="<?= $fetch_cart['qty'] ?>">
                  <input type="submit" name="update_cart" value="Update" class="option-btn">
               </form>
               
               <div class="cart-total">Total: ₹ <?= $sub_total ?></div>
               
               <div class="cart-actions">
                  <a href="cart.php?remove=<?= $fetch_cart['id'] ?>" class="delete-btn">Remove</a>
               </div>
            </div>
         <?php
            }
         } else {
            echo '<div class="cart-item" style="grid-column: 1/-1; text-align: center; padding: 40px;">No items in cart</div>';
         }
         ?>
      </div>
      
      <div class="cart-summary">
         <div class="grand-total">
            <span>Grand Total:</span>
            <span>₹ <?= $grand_total ?></span>
         </div>
         
         <div class="cart-btn">  
            <?php if($grand_total > 0): ?>
               <a href="cart.php?delete_all" class="delete-btn">Delete All</a>
               <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
            <?php endif; ?>
         </div>
      </div>
   </div>
</div>

<?php include_once 'footer.php';?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="../js/script.js"></script>
</body>
</html>
