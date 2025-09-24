<?php
include_once 'components/connection.php';

// Assuming $conn is your mysqli connection
$sql = "CREATE TABLE `payments` (
  `id` VARCHAR(10) PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_id` VARCHAR(100),
  `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `confirmed` BOOLEAN DEFAULT FALSE,
  `details` TEXT,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
);";

if ($conn->query($sql) === TRUE) {
    echo " created successfully";
} else {
    echo "Error: " . $conn->error;
}
?>
