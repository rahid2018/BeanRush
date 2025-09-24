<?php
include_once 'components/connection.php';

$sql  = "INSERT INTO payments (order_id, method, amount, confirmed, created_at, details)
SELECT 
    id,
    method,
    SUM(price * qty) AS amount,  -- Total amount for order
    MIN(payment_confirmed),       -- Use MIN() to handle multiple items
    MIN(date),                    -- Earliest timestamp
    MAX(payment_details)          -- Preserve payment details
FROM orders
GROUP BY id, method;";

if (mysqli_multi_query($conn, $sql)) {
    echo "✅ All data Inserted successfully!";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
