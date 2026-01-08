<?php
session_start();
include "../config/db.php";
include "../config/razorpay.php";

// Check if payment ID exists
if (!isset($_GET['razorpay_payment_id'])) {
    header("Location: mobile-courses.php");
    exit();
}

$payment_id = $_GET['razorpay_payment_id'];
$order_id = $_GET['razorpay_order_id'] ?? null;
$signature = $_GET['razorpay_signature'] ?? null;

// Try to recover enrollment data from session first
$enrollment_data = $_SESSION['enrollment_data'] ?? null;

// If session data is missing, try to recover from payment_logs
if (!$enrollment_data && $order_id) {
    try {
        // Look up payment log to get course_id and user_id
        $stmt = $pdo->prepare("SELECT user_id, course_id FROM payment_logs WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$order_id]);
        $payment_log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment_log) {
            // Fetch user details
            $stmt = $pdo->prepare("SELECT first_name, last_name, email, contact FROM users WHERE id = ?");
            $stmt->execute([$payment_log['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Fetch course details
            $stmt = $pdo->prepare("SELECT title, price, image FROM courses WHERE id = ?");
            $stmt->execute([$payment_log['course_id']]);
            $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $course_info) {
                // Reconstruct enrollment data
                $enrollment_data = [
                    'user_id' => $payment_log['user_id'],
                    'course_id' => $payment_log['course_id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'phone' => $user['contact'] ?? '',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'zip_code' => '',
                    'country' => 'India',
                    'amount' => $course_info['price'] * 100,
                    'course_title' => $course_info['title'],
                    'course_image' => $course_info['image']
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Payment recovery error: " . $e->getMessage());
    }
}

// If still no enrollment data, redirect (will show recovery message in JS)
if (!$enrollment_data) {
    // Store payment info for JavaScript recovery attempt
    $payment_info_for_js = [
        'payment_id' => $payment_id,
        'order_id' => $order_id
    ];
} else {
    $payment_info_for_js = null;
}

$razorpayConfig = new RazorpayConfig();
$api = $razorpayConfig->getApi();

// Verify payment signature (only if signature is provided)
$signature_valid = false;
if ($order_id && $signature) {
    $generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $razorpayConfig->getKeySecret());
    $signature_valid = ($generated_signature == $signature);
} else {
    // If signature missing, verify via API call
    try {
        $payment = $api->payment->fetch($payment_id);
        $signature_valid = ($payment && $payment->status == 'captured');
    } catch (Exception $e) {
        error_log("Payment verification error: " . $e->getMessage());
        $signature_valid = false;
    }
}

$success = false;
$message = '';
$enrollment_id = null;

if ($signature_valid && $enrollment_data) {
    try {
        // Payment is successful and verified
        $payment = $api->payment->fetch($payment_id);
        
        if ($payment->status == 'captured') {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert enrollment record (same as web)
            $stmt = $pdo->prepare("
                INSERT INTO enrollments (
                    user_id, course_id, first_name, last_name, email, phone, 
                    address, city, state, zip_code, country, payment_method, 
                    payment_status, amount_paid, transaction_id, razorpay_payment_id,
                    razorpay_order_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $enrollment_data['user_id'],
                $enrollment_data['course_id'],
                $enrollment_data['first_name'],
                $enrollment_data['last_name'],
                $enrollment_data['email'],
                $enrollment_data['phone'],
                $enrollment_data['address'],
                $enrollment_data['city'],
                $enrollment_data['state'],
                $enrollment_data['zip_code'],
                $enrollment_data['country'],
                'razorpay',
                'completed',
                $enrollment_data['amount'] / 100,
                $payment_id,
                $payment_id,
                $order_id
            ]);
            
            $enrollment_id = $pdo->lastInsertId();
            $pdo->commit();
            
            $success = true;
            $message = "Payment successful! You have been enrolled in the course.";
            
            // Clear session data
            unset($_SESSION['enrollment_data']);
            unset($_SESSION['razorpay_order_id']);
            
            // Clear localStorage backup on success
            echo '<script>try { localStorage.removeItem("payment_enrollment_backup"); localStorage.removeItem("payment_order_id"); } catch(e) {}</script>';
            
        } else {
            $message = "Payment verification failed. Status: " . $payment->status;
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error processing enrollment: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
} elseif (!$enrollment_data) {
    $message = "Session expired. Attempting to recover payment data...";
    // Will be handled by JavaScript recovery
} else {
    $message = "Payment verification failed. Invalid signature or missing data.";
}

// Fetch course details for display
$course = null;
if ($enrollment_data) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$enrollment_data['course_id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Course fetch error: " . $e->getMessage());
    }
} elseif ($payment_id) {
    // Try to get course from payment log
    try {
        $stmt = $pdo->prepare("
            SELECT c.* FROM courses c
            INNER JOIN payment_logs pl ON pl.course_id = c.id
            WHERE pl.payment_id = ? OR pl.order_id = ?
            ORDER BY pl.created_at DESC LIMIT 1
        ");
        $stmt->execute([$payment_id, $order_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Course recovery error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Payment Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verification-container {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .success-icon { color: #4CAF50; }
        .error-icon { color: #f44336; }
        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .details-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-weight: 500;
            color: #555;
        }
        .detail-value {
            color: #333;
            font-weight: 600;
        }
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 25px;
        }
        .btn {
            display: block;
            padding: 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background: #388E3C;
        }
        .btn-outline {
            background: transparent;
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        .btn-outline:hover {
            background: #f1f8e9;
        }
        .btn-danger {
            background: #f44336;
            color: white;
        }
        .btn-danger:hover {
            background: #d32f2f;
        }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        @media (max-width: 480px) {
            .verification-container {
                padding: 20px;
            }
            h1 {
                font-size: 22px;
            }
            .icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($success): ?>
            <div class="icon success-icon">🎉</div>
            <h1 class="success">Payment Successful!</h1>
            <p>Congratulations! You're now enrolled in the course.</p>
            
            <div class="details-box">
                <div class="detail-item">
                    <span class="detail-label">Course:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($course['title']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Enrollment ID:</span>
                    <span class="detail-value">#EDU-<?php echo str_pad($enrollment_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value"><?php echo $payment_id; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Amount Paid:</span>
                    <span class="detail-value">₹<?php echo number_format($enrollment_data['amount'] / 100, 2); ?></span>
                </div>
            </div>
            
            <div class="button-group">
                <a href="my-courses.php" class="btn btn-primary">
                    Start Learning Now
                </a>
                <a href="mobile-courses.php" class="btn btn-outline">
                    Browse More Courses
                </a>
            </div>
            
        <?php else: ?>
            <div class="icon error-icon">❌</div>
            <h1 class="error">Payment Failed</h1>
            <p><?php echo $message; ?></p>
            
            <div class="details-box">
                <p><strong>Error Details:</strong></p>
                <p style="color: #f44336; font-size: 14px;"><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="button-group">
                <?php if ($enrollment_data): ?>
                    <a href="mobile-enroll.php?course_id=<?php echo $enrollment_data['course_id']; ?>" class="btn btn-danger">
                        Try Payment Again
                    </a>
                <?php elseif ($course): ?>
                    <a href="mobile-enroll.php?course_id=<?php echo $course['id']; ?>" class="btn btn-danger">
                        Try Payment Again
                    </a>
                <?php else: ?>
                    <a href="mobile-courses.php" class="btn btn-danger">
                        Back to Courses
                    </a>
                <?php endif; ?>
                <a href="mobile-courses.php" class="btn btn-outline">
                    Browse Courses
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // JavaScript recovery for session loss in WebView
        <?php if (!$enrollment_data && $payment_info_for_js): ?>
        (function() {
            try {
                var backup = localStorage.getItem('payment_enrollment_backup');
                if (backup) {
                    var enrollmentData = JSON.parse(backup);
                    var paymentId = '<?php echo htmlspecialchars($payment_id, ENT_QUOTES); ?>';
                    var orderId = '<?php echo htmlspecialchars($order_id ?? '', ENT_QUOTES); ?>';
                    var signature = '<?php echo htmlspecialchars($signature ?? '', ENT_QUOTES); ?>';
                    
                    // Show recovery message
                    console.log('Attempting payment recovery from localStorage...');
                    
                    // The recovery will be handled server-side via payment_logs lookup
                    // This is just for logging
                }
            } catch(e) {
                console.error('Recovery script error:', e);
            }
        })();
        <?php endif; ?>
        
        // Clear old backup data (older than 1 hour)
        try {
            var backup = localStorage.getItem('payment_enrollment_backup');
            if (backup) {
                var data = JSON.parse(backup);
                var oneHour = 60 * 60 * 1000;
                if (Date.now() - data.timestamp > oneHour) {
                    localStorage.removeItem('payment_enrollment_backup');
                    localStorage.removeItem('payment_order_id');
                }
            }
        } catch(e) {
            // Ignore errors
        }
    </script>
</body>
</html>