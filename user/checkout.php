<?php
ob_start();
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../components/session.php';
include_once '../components/connection.php';
include_once '../components/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('location:login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Get user details
$select_user = mysqli_query($conn, "SELECT * FROM `users` WHERE id = '$user_id'");
$fetch_user = mysqli_fetch_assoc($select_user);

// Place Order
if (isset($_POST['place_order'])) {
    $name         = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $number       = htmlspecialchars(trim($_POST['number']), ENT_QUOTES, 'UTF-8');
    $email        = htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8');
    $flat         = htmlspecialchars(trim($_POST['flat']), ENT_QUOTES, 'UTF-8');
    $street       = htmlspecialchars(trim($_POST['street']), ENT_QUOTES, 'UTF-8');
    $city         = htmlspecialchars(trim($_POST['city']), ENT_QUOTES, 'UTF-8');
    $country      = htmlspecialchars(trim($_POST['country']), ENT_QUOTES, 'UTF-8');
    $pin_code     = htmlspecialchars(trim($_POST['pin_code']), ENT_QUOTES, 'UTF-8');
    $address_type = htmlspecialchars(trim($_POST['address_type']), ENT_QUOTES, 'UTF-8');
    $method       = htmlspecialchars(trim($_POST['method']), ENT_QUOTES, 'UTF-8');

    $address = "$flat, $street, $city, $country - $pin_code";

    // Payment details
    $payment_confirmed = 0;
    $transaction_id = null;
    $payment_details = '';

    if ($method === 'credit or debit card') {
        $card_number = htmlspecialchars(trim($_POST['card_number']), ENT_QUOTES, 'UTF-8');
        $expiry_date = htmlspecialchars(trim($_POST['expiry_date']), ENT_QUOTES, 'UTF-8');
        $cvv = htmlspecialchars(trim($_POST['cvv']), ENT_QUOTES, 'UTF-8');
        $card_name = htmlspecialchars(trim($_POST['card_name']), ENT_QUOTES, 'UTF-8');
        $transaction_id = $card_number;
        $payment_details = "Card ending in: " . substr($card_number, -4);
        $payment_confirmed = isset($_POST['payment_confirmed']) ? 1 : 0;
    } elseif ($method === 'net banking') {
        $bank = htmlspecialchars(trim($_POST['bank']), ENT_QUOTES, 'UTF-8');
        $payment_details = "Bank: $bank";
        $payment_confirmed = isset($_POST['payment_confirmed']) ? 1 : 0;
    } elseif ($method === 'UPI') {
        $payment_details = "UPI payment completed";
        $payment_confirmed = isset($_POST['payment_confirmed']) ? 1 : 0;
    } elseif ($method === 'cash on delivery') {
        $payment_details = "Cash on Delivery";
        $payment_confirmed = 1;
    }

    try {
        $conn->begin_transaction();

        // Create order IDs (varchar(100) for order_items)
        $order_id = substr(create_unique_id(), 0, 100); // Ensure it fits in varchar(100)
        $group_id = substr(create_unique_id(), 0, 50); // Ensure it fits in varchar(50)
        
        // Insert into orders table
        $insert_order = $conn->prepare("
            INSERT INTO orders 
            (id, group_id, user_id, name, number, email, address, address_type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in progress')
        ");
        $insert_order->bind_param(
            "ssssssss", 
            $order_id,
            $group_id,
            $user_id, 
            $name, 
            $number, 
            $email, 
            $address, 
            $address_type
        );
        
        if (!$insert_order->execute()) {
            throw new Exception("Order insertion failed: " . $insert_order->error);
        }

        // Process products
$grand_total = 0;

if (isset($_GET['get_id'])) {
    // Single product order
    $product_id = (int)$_GET['get_id'];
    $select_product = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $select_product->bind_param("i", $product_id);
    $select_product->execute();
    $product = $select_product->get_result()->fetch_assoc();
    
    if (!$product) {
        throw new Exception("Product not found");
    }
    
    $product_price = $product['price']; // Create variable
    $qty = 1;
    
    $insert_item = $conn->prepare("
        INSERT INTO order_items
        (order_id, product_id, price, qty) 
        VALUES (?, ?, ?, ?)
    ");
    $insert_item->bind_param(
        "sidi", 
        $order_id, 
        $product_id, 
        $product_price, // Use variable
        $qty
    );
    
    if (!$insert_item->execute()) {
        throw new Exception("Order item insertion failed: " . $insert_item->error);
    }
    
    $grand_total = (float)$product_price;
} else {
    // Cart-based order
    $select_cart = $conn->prepare("
        SELECT c.*, p.price 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $select_cart->bind_param("i", $user_id);
    $select_cart->execute();
    $cart_items = $select_cart->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($cart_items as $item) {
        $item_product_id = (int)$item['product_id'];
        $item_price = (float)$item['price'];
        $item_qty = (int)$item['qty'];
        
        $insert_item = $conn->prepare("
            INSERT INTO order_items
            (order_id, product_id, price, qty) 
            VALUES (?, ?, ?, ?)
        ");
        $insert_item->bind_param(
            "sidi",
            $order_id,
            $item_product_id,
            $item_price,
            $item_qty
        );
        
        if (!$insert_item->execute()) {
            throw new Exception("Order item insertion failed: " . $insert_item->error);
        }
        
        $grand_total += $item_price * $item_qty;
    }
    
    // Clear cart
    $delete_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $delete_cart->bind_param("i", $user_id);
    if (!$delete_cart->execute()) {
        throw new Exception("Cart deletion failed: " . $delete_cart->error);
    }
}

        // Insert payment record - CORRECTED FOR YOUR payments TABLE
        $payment_id = substr(create_unique_id(), 0, 100); // varchar(100)
        $payment_status = $payment_confirmed ? 'completed' : 'pending';
        
        $insert_payment = $conn->prepare("
            INSERT INTO payments 
            (id, order_id, method, amount, transaction_id, status, confirmed, details) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_payment->bind_param(
            "sssdssis", // types: string, string, string, double, string, string, integer, string
            $payment_id, 
            $order_id, 
            $method, 
            $grand_total, 
            $transaction_id, 
            $payment_status, 
            $payment_confirmed,
            $payment_details
        );
        
        if (!$insert_payment->execute()) {
            throw new Exception("Payment insertion failed: " . $insert_payment->error);
        }

        $conn->commit();

        $_SESSION['order_id'] = $order_id;
        $_SESSION['order_success'] = "Your order has been placed successfully!";
        header('Location: confirmation.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        error_log("CHECKOUT ERROR: " . $e->getMessage());
        header('Location: checkout.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="../css/user-css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-popup">
            <?= $_SESSION['error'] ?>
            <button onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <section class="checkout-section">
        <h1 class="heading">Checkout Summary</h1>

        <div class="checkout-container">
            <form action="checkout.php" method="POST" class="checkout-form">
                <h3 class="form-title">Billing Details</h3>
                
                <!-- Payment Status Indicator -->
                <div id="paymentStatusIndicator" class="payment-status pending">
                    <strong>Payment Status:</strong> Pending confirmation
                </div>
                
                <!-- Hidden field to store payment confirmation status -->
                <input type="hidden" name="payment_confirmed" id="paymentConfirmedInput" value="0">
                
                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Your Name <span>*</span></label>
                            <input type="text" name="name" required maxlength="50" placeholder="Enter your name"
                                class="form-input" value="<?= $fetch_user['name'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Your Number <span>*</span></label>
                            <input type="text" name="number" required maxlength="10" placeholder="Enter your number"
                                class="form-input" value="<?= $fetch_user['phone'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label>Your Email <span>*</span></label>
                            <input type="email" name="email" readonly value="<?= $fetch_user['email'] ?? '' ?>"
                                class="form-input">
                        </div>

                        <div class="form-group">
                            <label>Payment Method <span>*</span></label>
                            <select name="method" class="form-input" required id="paymentMethod">
                                <option value="">Choose Here...</option>
                                <option value="cash on delivery">Cash on Delivery</option>
                                <option value="credit or debit card">Credit or Debit Card</option>
                                <option value="net banking">Net Banking</option>
                                <option value="UPI">UPI or RuPay</option>
                            </select>
                        </div>
                        
                        <!-- Payment Confirmation Modal -->
                        <div id="paymentModal" class="payment-modal">
                            <div class="modal-content">
                                <span class="close-modal">&times;</span>
                                <h3>Confirm Payment Details</h3>
                                <div id="modalPaymentFields">
                                    <!-- Card Fields -->
                                    <div class="payment-extra" id="cardFields">
                                        <h4><i class="fas fa-credit-card"></i> Card Details</h4>
                                        <div class="card-logos">
                                            <img src="../images/card-logo/visa.png" alt="Visa">
                                            <img src="../images/card-logo/mastercard.png" alt="Mastercard">
                                            <img src="../images/card-logo/rupay.png" alt="RuPay">
                                        </div>
                                        <div class="card">
                                            <div class="form-group">
                                                <input type="text" name="card_number" maxlength="19"
                                                    placeholder="Card Number..." class="form-input" id="cardNumber">
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <input type="text" name="expiry_date" placeholder="Expiry... MM/YY"
                                                        class="form-input" id="expiryDate">
                                                </div>
                                                <div class="form-group">
                                                    <input type="text" name="cvv" maxlength="3" placeholder="CVV..."
                                                        class="form-input" id="cvv">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <input type="text" name="card_name" placeholder="Cardholder Name..."
                                                        class="form-input" id="cardName">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- UPI Fields -->
                                    <div class="payment-extra" id="upiFields">
                                        <h4><i class="fas fa-qrcode"></i> UPI Payment</h4>
                                        <div class="upi-container">
                                            <p>Scan the QR code below using any UPI app</p>
                                            <div class="qrcode-placeholder">
                                                <!-- QR code would go here -->
                                                <span style="color: #666;">QR Code Placeholder</span>
                                            </div>
                                            <div class="upi-id">beanrush@ybl</div>
                                            <p class="upi-note">After payment, please click the button below</p>
                                            <button type="button" class="upi-confirm-btn">
                                                <i class="fas fa-check-circle"></i> I have paid via UPI
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Net Banking Fields -->
                                    <div class="payment-extra" id="netBankingFields">
                                        <h4><i class="fas fa-university"></i> Net Banking</h4>
                                        <div class="form-group">
                                            <label>Select Bank <span>*</span></label>
                                            <select name="bank" class="form-input">
                                                <option value="">Select your bank</option>
                                                <option value="sbi">State Bank of India</option>
                                                <option value="hdfc">HDFC Bank</option>
                                                <option value="icici">ICICI Bank</option>
                                                <option value="axis">Axis Bank</option>
                                                <option value="kotak">Kotak Mahindra Bank</option>
                                            </select>
                                        </div>
                                        <div class="bank-icons">
                                            <div class="bank-icon">
                                                <div style="width: 50px; height: 50px; background-color: #ddd; border-radius: 4px; margin-bottom: 5px;"></div>
                                                <span>SBI</span>
                                            </div>
                                            <div class="bank-icon">
                                                <div style="width: 50px; height: 50px; background-color: #ddd; border-radius: 4px; margin-bottom: 5px;"></div>
                                                <span>HDFC</span>
                                            </div>
                                            <div class="bank-icon">
                                                <div style="width: 50px; height: 50px; background-color: #ddd; border-radius: 4px; margin-bottom: 5px;"></div>
                                                <span>ICICI</span>
                                            </div>
                                            <div class="bank-icon">
                                                <div style="width: 50px; height: 50px; background-color: #ddd; border-radius: 4px; margin-bottom: 5px;"></div>
                                                <span>Axis</span>
                                            </div>
                                            <div class="bank-icon">
                                                <div style="width: 50px; height: 50px; background-color: #ddd; border-radius: 4px; margin-bottom: 5px;"></div>
                                                <span>Kotak</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="confirmPayment" class="payment-confirm-btn">
                                    Confirm Payment
                                </button>
                                <div id="paymentStatus"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address Type <span>*</span></label>
                            <select name="address_type" class="form-input" required>
                                <option value="">Choose Here...</option>
                                <option value="home">Home</option>
                                <option value="office">Office</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-column">
                        <div class="form-group">
                            <label>Address Line 01 <span>*</span></label>
                            <input type="text" name="flat" required maxlength="50" placeholder="Flat & building number"
                                class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Address Line 02 <span>*</span></label>
                            <input type="text" name="street" required maxlength="50"
                                placeholder="Street name & locality" class="form-input">
                        </div>
                        <div class="form-group">
                            <label>City Name <span>*</span></label>
                            <input type="text" name="city" required maxlength="50" placeholder="Enter your city name"
                                class="form-input">
                        </div>
                        <div class="form-group">
                            <label>Country Name <span>*</span></label>
                            <select name="country" class="form-input" required>
                                <option value="">Choose Here...</option>
                                <option value="India">India</option>
                                <option value="China">China</option>
                                <option value="England">England</option>
                                <option value="Norway">Norway</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pin Code <span>*</span></label>
                            <input type="text" name="pin_code" required maxlength="6" placeholder="e.g. 123456"
                                class="form-input">
                        </div>
                    </div>
                </div>

                <button type="submit" name="place_order" class="submit-btn">Place Order</button>
            </form>

            <div class="order-summary">
                <h3 class="summary-title">Cart Items</h3>
                <div class="cart-items-container">
                    <?php
                    $grand_total = 0;

                    if (isset($_GET['get_id'])) {
                        $id = (int)$_GET['get_id'];
                        $select_get = $conn->prepare("SELECT * FROM products WHERE id = ?");
                        $select_get->bind_param("i", $id);
                        $select_get->execute();
                        $result = $select_get->get_result();

                        if ($fetch_get = $result->fetch_assoc()) {
                            $grand_total = $fetch_get['price'];
                            ?>
                            <div class="cart-item">
                                <img src="../images/products/<?= htmlspecialchars($fetch_get['image']); ?>" class="cart-item-image"
                                    alt="">
                                <div class="cart-item-details">
                                    <h4 class="cart-item-name">
                                        <?= htmlspecialchars($fetch_get['name']); ?>
                                    </h4>
                                    <p class="cart-item-price">₹
                                        <?= $fetch_get['price']; ?> x 1
                                    </p>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        $select_cart = $conn->prepare("
                            SELECT c.*, p.name, p.image, p.price 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.user_id = ?
                        ");
                        $select_cart->bind_param("i", $user_id);
                        $select_cart->execute();
                        $result = $select_cart->get_result();

                        if ($result->num_rows > 0) {
                            while ($fetch_cart = $result->fetch_assoc()) {
                                $sub_total = $fetch_cart['price'] * $fetch_cart['qty'];
                                $grand_total += $sub_total;
                                ?>
                                <div class="cart-item">
                                    <img src="../images/products/<?= htmlspecialchars($fetch_cart['image']); ?>"
                                        class="cart-item-image" alt="">
                                    <div class="cart-item-details">
                                        <h4 class="cart-item-name">
                                            <?= htmlspecialchars($fetch_cart['name']); ?>
                                        </h4>
                                        <p>Price: ₹
                                            <?= $fetch_cart['price']; ?>
                                        </p>
                                        <p>Qty:
                                            <?= $fetch_cart['qty']; ?>
                                        </p>
                                        <p>Subtotal: ₹
                                            <?= $sub_total; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<p class="empty-cart">Your cart is empty</p>';
                        }
                    }
                    ?>
                </div>
                <div class="cart-total">
                    <span class="total-label">Grand Total:</span>
                    <span class="total-amount">₹
                        <?= $grand_total; ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Payment confirmation state
        let paymentConfirmed = false;
        const paymentConfirmedInput = document.getElementById('paymentConfirmedInput');
        const paymentStatusIndicator = document.getElementById('paymentStatusIndicator');

        // Show/hide payment fields based on selection
        document.getElementById('paymentMethod').addEventListener('change', function () {
            const method = this.value;

            // Update payment status indicator
            if (method === 'cash on delivery') {
                paymentStatusIndicator.className = 'payment-status confirmed';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Confirmed (Cash on Delivery)';
                paymentConfirmed = true;
                paymentConfirmedInput.value = '1';
            } else {
                paymentStatusIndicator.className = 'payment-status pending';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Pending confirmation';
                paymentConfirmed = false;
                paymentConfirmedInput.value = '0';
            }

            // Hide all extra fields
            document.querySelectorAll('.payment-extra').forEach(function (el) {
                el.style.display = 'none';
            });

            // For non-COD methods, show the payment modal
            if (method !== 'cash on delivery') {
                document.getElementById('paymentModal').style.display = 'block';

                // Show specific payment form in modal
                if (method === 'credit or debit card') {
                    document.getElementById('cardFields').style.display = 'block';
                } else if (method === 'UPI') {
                    document.getElementById('upiFields').style.display = 'block';
                } else if (method === 'net banking') {
                    document.getElementById('netBankingFields').style.display = 'block';
                }
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Hide all payment fields initially
            document.querySelectorAll('.payment-extra').forEach(function (el) {
                el.style.display = 'none';
            });

            // Hide payment modal
            document.getElementById('paymentModal').style.display = 'none';

            // Set COD as default confirmed
            if (document.getElementById('paymentMethod').value === 'cash on delivery') {
                paymentStatusIndicator.className = 'payment-status confirmed';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Confirmed (Cash on Delivery)';
                paymentConfirmed = true;
                paymentConfirmedInput.value = '1';
            } else {
                paymentStatusIndicator.className = 'payment-status pending';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Pending confirmation';
            }
        });

        // Close payment modal
        document.querySelector('.close-modal').addEventListener('click', function () {
            document.getElementById('paymentModal').style.display = 'none';

            // Reset to COD if user closes without confirming
            if (!paymentConfirmed) {
                document.getElementById('paymentMethod').value = 'cash on delivery';
                paymentStatusIndicator.className = 'payment-status confirmed';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Confirmed (Cash on Delivery)';
                paymentConfirmed = true;
                paymentConfirmedInput.value = '1';
            }
        });

        // Confirm payment details
        document.getElementById('confirmPayment').addEventListener('click', function () {
            const method = document.getElementById('paymentMethod').value;
            let valid = true;

            // Validate card details
            if (method === 'credit or debit card') {
                const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
                const expiry = document.getElementById('expiryDate').value;
                const cvv = document.getElementById('cvv').value;
                const cardName = document.getElementById('cardName').value;

                if (!cardNumber || !expiry || !cvv || !cardName) {
                    valid = false;
                    alert('Please fill all card details');
                } else if (!/^\d{16}$/.test(cardNumber)) {
                    valid = false;
                    alert('Please enter a valid 16-digit card number');
                } else if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                    valid = false;
                    alert('Please enter expiry date in MM/YY format');
                } else if (!/^\d{3}$/.test(cvv)) {
                    valid = false;
                    alert('Please enter a valid 3-digit CVV');
                }
            }

            // Validate net banking
            if (method === 'net banking') {
                const bank = document.querySelector('select[name="bank"]').value;
                if (!bank) {
                    valid = false;
                    alert('Please select your bank');
                }
            }

            // UPI doesn't need validation beyond clicking the button
            if (valid) {
                const statusEl = document.getElementById('paymentStatus');
                statusEl.textContent = '✓ Payment details confirmed';
                statusEl.style.color = 'green';
                statusEl.style.padding = '10px';
                statusEl.style.textAlign = 'center';
                statusEl.style.fontWeight = 'bold';
                
                // Update payment status
                paymentConfirmed = true;
                paymentConfirmedInput.value = '1';
                
                // Update the status indicator
                paymentStatusIndicator.className = 'payment-status confirmed';
                paymentStatusIndicator.innerHTML = '<strong>Payment Status:</strong> Confirmed';
                
                // Disable confirm button after success
                this.disabled = true;
                
                setTimeout(() => {
                    document.getElementById('paymentModal').style.display = 'none';
                    this.disabled = false;
                }, 1500);
            }
        });

        // Main form submission validation
        document.querySelector('form').addEventListener('submit', function (e) {
            const method = document.getElementById('paymentMethod').value;

            // For non-COD methods, ensure payment is confirmed
            if (method !== 'cash on delivery' && !paymentConfirmed) {
                alert('Please complete payment confirmation first');
                e.preventDefault();
                return;
            }
        });

        // Format card number input
        document.getElementById('cardNumber').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            e.target.value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
        });

        // Format expiry date input
        document.getElementById('expiryDate').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);
            if (value.length >= 2) {
                value = value.replace(/(\d{2})(\d+)/, '$1/$2');
            }
            e.target.value = value;
        });

        // UPI confirmation button
        document.querySelector('.upi-confirm-btn').addEventListener('click', function () {
            alert('Thank you for confirming your UPI payment! Please click "Confirm Payment" to continue.');
        });

        // Form validation
        document.querySelector('.checkout-form').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            const paymentMethod = document.getElementById('paymentMethod').value;
            if (paymentMethod === '') {
                isValid = false;
                alert('Please select a payment method');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
            }
        });
    </script>

    <?php include 'footer.php';?>
    <script src="../js/script.js"></script>
</body>
</html>