<?php
session_start();
include "../config/db.php";
include "../config/razorpay.php";

// Check if enrollment data exists
if (!isset($_SESSION['enrollment_data']) || !isset($_SESSION['razorpay_order_id'])) {
    header("Location: mobile-courses.php");
    exit();
}

$enrollment_data = $_SESSION['enrollment_data'];
$razorpayConfig = new RazorpayConfig();
$keyId = $razorpayConfig->getKeyId();

// Fetch course details
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$enrollment_data['course_id']]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching course: " . $e->getMessage());
}

// Log payment attempt (same as web version)
try {
    $stmt = $pdo->prepare("INSERT INTO payment_logs (user_id, course_id, order_id, amount, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $enrollment_data['user_id'],
        $enrollment_data['course_id'],
        $_SESSION['razorpay_order_id'],
        $enrollment_data['amount'],
        'initiated'
    ]);
} catch (Exception $e) {
    error_log("Payment log error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Complete Payment - <?php echo htmlspecialchars($course['title']); ?></title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 22px;
            color: #333;
            margin-bottom: 8px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .course-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .course-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .price-summary {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
        }
        .total {
            border-top: 2px solid #e0e0e0;
            padding-top: 12px;
            margin-top: 12px;
            font-weight: 600;
            color: #333;
            font-size: 18px;
        }
        .payment-loading {
            text-align: center;
            padding: 30px 0;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #rzp-button {
            width: 100%;
            padding: 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.3s;
        }
        #rzp-button:hover {
            background: #388E3C;
        }
        .security-info {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        .security-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 10px 0;
        }
        .security-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .icon-lock { color: #4CAF50; }
        .icon-shield { color: #2196F3; }
        .icon-bank { color: #9C27B0; }
        .security-text {
            font-size: 11px;
            color: #666;
        }
        .footer-text {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 15px;
            line-height: 1.4;
        }
        .footer-text a {
            color: #4CAF50;
            text-decoration: none;
        }
        .processing {
            background: #4CAF50 !important;
            cursor: not-allowed;
        }
        @media (max-width: 480px) {
            .payment-container {
                padding: 20px;
            }
            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="header">
            <h1>Complete Your Payment</h1>
            <p>You're enrolling in: <strong><?php echo htmlspecialchars($course['title']); ?></strong></p>
        </div>
        
        <div class="course-info">
            <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
            <div style="font-size: 12px; color: #666;">Enrollment</div>
        </div>
        
        <div class="price-summary">
            <div class="price-item">
                <span>Course Price:</span>
                <span>₹<?php echo number_format($enrollment_data['amount']/100, 2); ?></span>
            </div>
            <div class="price-item">
                <span>Tax (GST):</span>
                <span>₹0.00</span>
            </div>
            <div class="price-item">
                <span>Platform Fee:</span>
                <span>₹0.00</span>
            </div>
            <div class="price-item total">
                <span>Total Amount:</span>
                <span>₹<?php echo number_format($enrollment_data['amount']/100, 2); ?></span>
            </div>
        </div>
        
        <div class="payment-loading" id="paymentLoading">
            <div class="spinner"></div>
            <p>Preparing secure payment gateway...</p>
        </div>
        
        <button id="rzp-button">
            <span>Pay ₹<?php echo number_format($enrollment_data['amount']/100, 2); ?></span>
        </button>
        
        <div class="security-info">
            <div style="font-weight: 600; color: #2E7D32; margin-bottom: 10px;">Payment Security</div>
            <div class="security-icons">
                <div class="security-icon">
                    <div class="icon icon-lock">🔒</div>
                    <div class="security-text">256-bit SSL</div>
                </div>
                <div class="security-icon">
                    <div class="icon icon-shield">🛡️</div>
                    <div class="security-text">PCI DSS</div>
                </div>
                <div class="security-icon">
                    <div class="icon icon-bank">🏦</div>
                    <div class="security-text">RBI Approved</div>
                </div>
            </div>
        </div>
        
        <div class="footer-text">
            <p>Secure payment powered by Razorpay (RBI Approved)</p>
            <p>By continuing, you agree to our <a href="../terms.php">Terms</a> and <a href="../privacy.php">Privacy Policy</a></p>
        </div>
    </div>

    <script>
        var options = {
            "key": "<?php echo $keyId; ?>",
            "amount": "<?php echo $enrollment_data['amount']; ?>",
            "currency": "INR",
            "name": "Edufy Academy",
            "description": "Course: <?php echo htmlspecialchars($course['title']); ?>",
            "image": "https://cdn.razorpay.com/logos/GhRQcyean79PqE_medium.png",
            "order_id": "<?php echo $_SESSION['razorpay_order_id']; ?>",
            "handler": function (response) {
                // Show processing state
                document.getElementById('rzp-button').innerHTML = '<span>Processing Payment...</span>';
                document.getElementById('rzp-button').classList.add('processing');
                
                // Backup enrollment data to localStorage (for WebView session recovery)
                try {
                    var enrollmentBackup = {
                        user_id: <?php echo $enrollment_data['user_id']; ?>,
                        course_id: <?php echo $enrollment_data['course_id']; ?>,
                        first_name: "<?php echo addslashes($enrollment_data['first_name']); ?>",
                        last_name: "<?php echo addslashes($enrollment_data['last_name']); ?>",
                        email: "<?php echo addslashes($enrollment_data['email']); ?>",
                        phone: "<?php echo addslashes($enrollment_data['phone']); ?>",
                        address: "<?php echo addslashes($enrollment_data['address']); ?>",
                        city: "<?php echo addslashes($enrollment_data['city']); ?>",
                        state: "<?php echo addslashes($enrollment_data['state']); ?>",
                        zip_code: "<?php echo addslashes($enrollment_data['zip_code']); ?>",
                        country: "<?php echo addslashes($enrollment_data['country']); ?>",
                        amount: <?php echo $enrollment_data['amount']; ?>,
                        course_title: "<?php echo addslashes($enrollment_data['course_title']); ?>",
                        course_image: "<?php echo addslashes($enrollment_data['course_image']); ?>",
                        order_id: "<?php echo $_SESSION['razorpay_order_id']; ?>",
                        timestamp: Date.now()
                    };
                    localStorage.setItem('payment_enrollment_backup', JSON.stringify(enrollmentBackup));
                    localStorage.setItem('payment_order_id', "<?php echo $_SESSION['razorpay_order_id']; ?>");
                } catch(e) {
                    console.error('Failed to backup enrollment data:', e);
                }
                
                // Update payment log (same as web)
                fetch('../config/update-payment-log.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: "<?php echo $_SESSION['razorpay_order_id']; ?>",
                        payment_id: response.razorpay_payment_id,
                        status: 'processing',
                        source: 'mobile_app'
                    })
                }).catch(function(error) {
                    console.error('Payment log update failed:', error);
                });
                
                // Redirect to verification page
                setTimeout(function() {
                    window.location.href = "mobile-verify-payment.php?razorpay_payment_id=" + encodeURIComponent(response.razorpay_payment_id) + 
                        "&razorpay_order_id=" + encodeURIComponent(response.razorpay_order_id) + 
                        "&razorpay_signature=" + encodeURIComponent(response.razorpay_signature);
                }, 1500);
            },
            "prefill": {
                "name": "<?php echo $enrollment_data['first_name'] . ' ' . $enrollment_data['last_name']; ?>",
                "email": "<?php echo $enrollment_data['email']; ?>",
                "contact": "<?php echo $enrollment_data['phone']; ?>"
            },
            "notes": {
                "course_id": "<?php echo $enrollment_data['course_id']; ?>",
                "user_id": "<?php echo $enrollment_data['user_id']; ?>",
                "course_title": "<?php echo $enrollment_data['course_title']; ?>"
            },
            "theme": {
                "color": "#4CAF50"
            },
            "modal": {
                "ondismiss": function() {
                    if(confirm('Cancel payment? You will be redirected back.')) {
                        fetch('../update-payment-log.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                order_id: "<?php echo $_SESSION['razorpay_order_id']; ?>",
                                status: 'cancelled'
                            })
                        });
                        window.location.href = "mobile-enroll.php?course_id=<?php echo $enrollment_data['course_id']; ?>";
                    }
                }
            }
        };

        var rzp1 = new Razorpay(options);

        // Auto open payment modal after short delay
        setTimeout(function() {
            document.getElementById('paymentLoading').style.display = 'none';
            document.getElementById('rzp-button').style.display = 'flex';
            rzp1.open();
        }, 1000);

        document.getElementById('rzp-button').onclick = function(e) {
            rzp1.open();
            e.preventDefault();
        }

        // Handle payment errors
        rzp1.on('payment.failed', function(response) {
            console.error('Payment failed:', response.error);
            
            // Clear localStorage backup on failure
            try {
                localStorage.removeItem('payment_enrollment_backup');
                localStorage.removeItem('payment_order_id');
            } catch(e) {
                console.error('Failed to clear backup:', e);
            }
            
            fetch('../config/update-payment-log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: "<?php echo $_SESSION['razorpay_order_id']; ?>",
                    payment_id: response.error.metadata ? response.error.metadata.payment_id : null,
                    status: 'failed',
                    error: response.error.description,
                    source: 'mobile_app'
                })
            }).catch(function(error) {
                console.error('Payment log update failed:', error);
            });
            
            alert('Payment failed: ' + (response.error.description || 'Unknown error'));
            window.location.href = "mobile-enroll.php?course_id=<?php echo $enrollment_data['course_id']; ?>&error=payment_failed";
        });
        
        // Handle modal dismissal
        rzp1.on('modal.closed', function() {
            // Clear backup if user closes without paying
            try {
                var backup = localStorage.getItem('payment_enrollment_backup');
                if (backup) {
                    var backupData = JSON.parse(backup);
                    // Only clear if payment wasn't completed (check timestamp - if less than 5 seconds, likely cancelled)
                    var timeDiff = Date.now() - backupData.timestamp;
                    if (timeDiff < 5000) {
                        localStorage.removeItem('payment_enrollment_backup');
                        localStorage.removeItem('payment_order_id');
                    }
                }
            } catch(e) {
                console.error('Error handling modal close:', e);
            }
        });
    </script>
</body>
</html>