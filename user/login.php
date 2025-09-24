<?php
ob_start();
include_once '../components/session.php';
include_once '../components/connection.php';
include '../components/popups.php';

// preserve incoming source
$login_source = $_GET['source'] ?? 'home';

// helper to map source -> redirect
function map_redirect(string $src): string {
    switch ($src) {
        case 'home':        return 'index.php';
        case 'add_to_cart': return 'products.php';
        case 'view_cart':   return 'cart.php';
        default:            return 'index.php';
    }
}

$redirect = map_redirect($login_source);

// handle login
if (isset($_POST['submit'])) {
    $post_source = $_POST['source'] ?? $_GET['source'] ?? 'home';
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass  = md5($_POST['pass']); // or use password_hash()/password_verify for better security

    // check if email exists
    $check_email = mysqli_query($conn, "SELECT * FROM `users` WHERE email = '$email'") or die('query failed');

    if (mysqli_num_rows($check_email) > 0) {
        // email exists, now check password
        $row = mysqli_fetch_assoc($check_email);
        if ($row['password'] === $pass) {
            // successful login
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['popup_message'] = "Logged in successfully!";
            header("Location: " . map_redirect($post_source));
            exit;
        } else {
            // email correct, password wrong
            $_SESSION['popup_message'] = "Incorrect password!";
            header("Location: login.php?source=" . urlencode($post_source));
            exit;
        }
    } else {
        // email does not exist, redirect to registration
        $_SESSION['popup_message'] = "Email not found! Please register first.";
        header("Location: register.php?source=" . urlencode($post_source));
        exit;
    }
}
?>


<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>BeanRush - Login</title>
    <!-- <link rel="stylesheet" href="style.css" />unwated i think -->
    <link rel="stylesheet" href="../css/user-css/style-register.css" />
</head>
<body>

<section class="register-section">
    <!-- <img src="login.png" alt="BeanRush Logo" class="register-logo" /> -->
    <h2 class="register-title">User Login</h2>
    <p class="register-subtitle">Welcome back! Let’s brew something amazing together.</p>

    <form action="login.php" method="post" class="register-form">
        <input type="hidden" name="source" value="<?= htmlspecialchars($login_source) ?>">

        <div class="menu-details">
            <p class="text">Your Email<sup>*</sup></p>
            <input type="email" name="email" required placeholder="Here..." maxlength="50"
                oninput="this.value=this.value.replace(/\s/g, '')" />
        </div>

        <div class="menu-details">
            <p class="text">Your Password<sup>*</sup></p>
            <input type="password" name="pass" required placeholder="Here..." maxlength="50"
                oninput="this.value=this.value.replace(/\s/g, '')" />
        </div>

        <input type="submit" name="submit" value="Login" />
        <p class="text">Don't have an account? <a href="register.php?source=<?= urlencode($login_source) ?>">Sign Up</a></p>
            <p style="font-size: 12px; width:100%;margin-top:10px;text-align: center;">Secure user access to BeanRush</p>
    </form>
</section>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="../script.js"></script>
</body>
</html>
