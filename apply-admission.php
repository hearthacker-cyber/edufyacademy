<?php
session_start();
include "../config/db.php"; 
include "../config/razorpay.php"; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: mobile-login.php");
    exit();
}

// Check if course_id is provided
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header("Location: mobile-courses.php");
    exit();
}

$course_id = $_GET['course_id'];

// Fetch course details
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: mobile-courses.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching course: " . $e->getMessage());
}

// Fetch user details
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail
    }
}

// Initialize Razorpay
$razorpayConfig = new RazorpayConfig();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    
    // Basic validation
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (strlen($phone) < 10) $errors[] = "Phone number must be at least 10 digits";
    
    if (empty($errors)) {
        try {
            // Store enrollment data in session for Razorpay payment
            $_SESSION['enrollment_data'] = [
                'user_id' => $_SESSION['user_id'],
                'course_id' => $course_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip_code' => $zip_code,
                'country' => $country,
                'amount' => $course['price'] * 100, // Convert to paise
                'course_title' => $course['title'],
                'course_image' => $course['image']
            ];
            
            // Create Razorpay Order
            $api = $razorpayConfig->getApi();
            $orderData = [
                'receipt' => 'MOB-EDU' . time(),
                'amount' => $course['price'] * 100,
                'currency' => 'INR',
                'payment_capture' => 1,
                'notes' => [
                    'course_id' => $course_id,
                    'user_id' => $_SESSION['user_id'],
                    'course_title' => $course['title'],
                    'source' => 'mobile_app'
                ]
            ];
            
            $razorpayOrder = $api->order->create($orderData);
            $_SESSION['razorpay_order_id'] = $razorpayOrder['id'];
            
            // Redirect to mobile payment process page
            header("Location: mobile-payment-process.php");
            exit();
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            if (strpos($error_message, 'Authentication failed') !== false) {
                $errors[] = "Payment gateway authentication failed. Please check your API keys.";
            } else {
                $errors[] = "Error creating payment order: " . $error_message;
            }
            error_log("Razorpay Error (Mobile): " . $error_message);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Enroll - <?php echo htmlspecialchars($course['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 100%;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 22px;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .card h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #2E7D32;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #555;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background: #fff;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -8px;
        }
        .col-6, .col-4 {
            padding: 0 8px;
            flex: 0 0 50%;
            max-width: 50%;
        }
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .alert-danger {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-info {
            background: #e3f2fd;
            border: 1px solid #b3e5fc;
            color: #01579b;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-align: center;
            text-decoration: none;
        }
        .btn:hover {
            background: #388E3C;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .course-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .course-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        .price-summary {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e0e0e0;
        }
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .total {
            border-top: 1px solid #e0e0e0;
            padding-top: 10px;
            margin-top: 10px;
            font-weight: 600;
            color: #2E7D32;
            font-size: 16px;
        }
        .payment-methods {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        .badge {
            padding: 4px 8px;
            background: #f1f8e9;
            border: 1px solid #c5e1a5;
            border-radius: 4px;
            font-size: 12px;
            color: #33691e;
        }
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin: 15px 0;
        }
        .checkbox-group input {
            margin-right: 10px;
            margin-top: 3px;
        }
        .checkbox-group label {
            font-size: 14px;
            line-height: 1.4;
        }
        .security-note {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        .back-link {
            display: inline-block;
            color: #4CAF50;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            .col-6, .col-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 10px;
            }
            .header {
                padding: 15px;
            }
            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="mobile-courses.php" class="back-link">← Back to Courses</a>
        
        <div class="header">
            <h1>Enroll in <?php echo htmlspecialchars($course['title']); ?></h1>
            <p>Complete your enrollment and start learning</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-6">
                <div class="card">
                    <h3>Personal Information</h3>
                    <form method="POST" id="enrollmentForm">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="first_name">First Name*</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ($user ? htmlspecialchars($user['first_name']) : ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name*</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ($user ? htmlspecialchars($user['last_name']) : ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="email">Email*</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ($user ? htmlspecialchars($user['email']) : ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="phone">Phone*</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ($user ? htmlspecialchars($user['contact']) : ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address" 
                                value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                        value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                        value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label for="zip_code">ZIP Code</label>
                                    <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                        value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select class="form-control" id="country" name="country">
                                <option value="India" selected>India</option>
                                <option value="USA">United States</option>
                                <option value="UK">United Kingdom</option>
                            </select>
                        </div>
                        
                        <h3 style="margin-top: 25px; margin-bottom: 15px;">Payment Information</h3>
                        
                        <div class="alert alert-info">
                            <strong>Secure Payment:</strong> You'll be redirected to Razorpay's secure payment gateway.
                        </div>
                        
                        <div class="payment-methods">
                            <span class="badge">Credit Cards</span>
                            <span class="badge">Debit Cards</span>
                            <span class="badge">UPI</span>
                            <span class="badge">Net Banking</span>
                            <span class="badge">Wallet</span>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="termsAgreement" required>
                            <label for="termsAgreement">
                                I agree to the Terms & Conditions and Privacy Policy
                            </label>
                        </div>
                        
                        <button type="submit" class="btn">
                            Pay ₹<?php echo number_format($course['price'], 2); ?> & Continue
                        </button>
                        
                        <div class="security-note">
                            <span>🔒 Secure payment powered by Razorpay (RBI Approved)</span>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-6">
                <div class="card">
                    <h3>Order Summary</h3>
                    
                    <div class="course-summary">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if ($course['image']): ?>
                                <img src="../admin/<?php echo htmlspecialchars($course['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($course['title']); ?>" 
                                     class="course-image">
                            <?php endif; ?>
                            <div>
                                <h4 style="margin-bottom: 5px; font-size: 16px;"><?php echo htmlspecialchars($course['title']); ?></h4>
                                <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($course['level']); ?> Level</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="price-summary">
                        <div class="price-item">
                            <span>Course Price:</span>
                            <span>₹<?php echo number_format($course['price'], 2); ?></span>
                        </div>
                        <div class="price-item">
                            <span>Tax:</span>
                            <span>₹0.00</span>
                        </div>
                        <div class="price-item">
                            <span>Discount:</span>
                            <span style="color: #4CAF50;">₹0.00</span>
                        </div>
                        <div class="price-item total">
                            <span>Total Amount:</span>
                            <span>₹<?php echo number_format($course['price'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });
            
            // Check terms agreement
            const termsCheckbox = document.getElementById('termsAgreement');
            if (!termsCheckbox.checked) {
                isValid = false;
                alert('Please agree to the Terms & Conditions');
            }
            
            // Phone validation
            const phone = document.getElementById('phone').value.replace(/\D/g, '');
            if (phone.length < 10) {
                isValid = false;
                alert('Phone number must be at least 10 digits');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields correctly.');
            }
        });
        
        // Real-time validation
        document.querySelectorAll('input[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#ddd';
                }
            });
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length > 10) {
                phone = phone.substring(0, 10);
            }
            this.value = phone;
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>