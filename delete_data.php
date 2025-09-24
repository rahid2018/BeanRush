<?php
include_once 'components/connection.php';

$sql  = "ALTER TABLE orders
DROP COLUMN product_id,
DROP COLUMN price,
DROP COLUMN qty,
DROP COLUMN method,
DROP COLUMN payment_details,
DROP COLUMN payment_confirmed,
DROP COLUMN date;";

if (mysqli_multi_query($conn, $sql)) {
    echo "✅ All columns deleted successfully!";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
