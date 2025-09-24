<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();

}

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
