<?php
include_once '../components/session.php';
include_once '../components/connection.php';
include_once '../components/popups.php';

if (isset($_POST['submit'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = mysqli_real_escape_string($conn, md5($_POST['pass']));
    $cpass = mysqli_real_escape_string($conn, md5($_POST['cpass']));

    $select = mysqli_query($conn, "SELECT * FROM `users` WHERE email = '$email'") or die('query failed');

    if (mysqli_num_rows($select) > 0) {
        $_SESSION['popup_message'] = "User Already exists!";
        header("Location: login.php");
        exit;
    } elseif ($pass != $cpass) {
        $_SESSION['popup_message'] = "Incorrect Password";
        header("Location: login.php");
        exit;
    } else {
        mysqli_query($conn, "INSERT INTO `users` (name, email, password) VALUES ('$name', '$email', '$pass')") or die('query failed');

        // Preserve source from GET for redirect
        $src = $_GET['source'] ?? 'home';
        header('Location: login.php?source=' . urlencode($src));
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
    <title>BeanRush - Register</title>
    <!-- <link rel="stylesheet" href="../css/user-css/style.css" /> -->
    <link rel="stylesheet" href="../css/user-css/style-register.css" />
</head>
<body>

<section class="register-section">
    <!-- <img src="register.png" alt="BeanRush Logo" class="register-logo" /> -->
    <h2 class="register-title">Sign Up</h2>
    <p class="register-subtitle">Join us for the perfect brew experience.</p>

    <form action="register.php?source=<?= urlencode($_GET['source'] ?? 'home') ?>" method="post" class="register-form">
        <div class="menu-details">
            <p class="text">Your Name<sup>*</sup></p>
            <input type="text" name="name" required placeholder="#name" maxlength="50" />
        </div>

        <div class="menu-details">
            <p class="text">Your Email<sup>*</sup></p>
            <input type="email" name="email" required placeholder="#abc123@gmail.com" maxlength="50"
                oninput="this.value=this.value.replace(/\s/g, '')" />
        </div>

        <div class="menu-details">
            <p class="text">Your Password<sup>*</sup></p>
            <input type="password" name="pass" required placeholder="#12345" maxlength="50"
                oninput="this.value=this.value.replace(/\s/g, '')" />
        </div>

        <div class="menu-details">
            <p class="text">Confirm Password<sup>*</sup></p>
            <input type="password" name="cpass" required placeholder="#12345" maxlength="50"
                oninput="this.value=this.value.replace(/\s/g, '')" />
        </div>

        <input type="submit" name="submit" value="Register" />
        <p class="text">Already have an account? 
            <a href="login.php?source=<?= urlencode($_GET['source'] ?? 'home') ?>">Sign In</a>
        </p>
    </form>
</section>



<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="../js/script.js"></script>
</body>
</html>
