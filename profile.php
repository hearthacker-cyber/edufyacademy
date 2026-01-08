<?php
include('includes/nav.php');
include('includes/menu.php');
session_start();

header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: sign-in.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$user = [];
$errors = [];
$success = false;
$show_profile_form = false;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: sign-in.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors['database'] = "Error fetching profile data. Please try again.";
}

// Handle mobile number submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_mobile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("Invalid CSRF token");
    }

    $contact = trim(filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING));

    if (empty($contact)) {
        $errors['contact'] = 'Contact number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $contact)) {
        $errors['contact'] = 'Contact must be 10-15 digits';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE contact = ? AND id != ?");
            $stmt->execute([$contact, $user_id]);
            if ($stmt->rowCount() > 0) {
                $errors['contact'] = 'Contact number already registered';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET contact = ? WHERE id = ?");
                $stmt->execute([$contact, $user_id]);
                $_SESSION['toast_message'] = 'Contact number updated successfully!';
                $_SESSION['toast_type'] = 'success';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['database'] = "Error updating contact number. Please try again.";
        }
    }
}

// Handle profile update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("Invalid CSRF token");
    }

    // Sanitize inputs
    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $contact = trim(filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING));
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($first_name)) {
        $errors['first_name'] = 'First name is required';
    } elseif (!preg_match('/^[a-zA-Z\s]{2,50}$/', $first_name)) {
        $errors['first_name'] = 'First name must be 2-50 letters';
    }

    if (empty($last_name)) {
        $errors['last_name'] = 'Last name is required';
    } elseif (!preg_match('/^[a-zA-Z\s]{1,50}$/', $last_name)) {
        $errors['last_name'] = 'Last name must be 1-50 letters';
    }

    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($contact)) {
        $errors['contact'] = 'Contact number is required';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $contact)) {
        $errors['contact'] = 'Contact must be 10-15 digits';
    }

    // Handle avatar upload
    $avatar_path = $user['avatar'] ?? null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $file_name = 'avatar_' . $user_id . '_' . time() . '.' . strtolower($file_ext);
        $allowed_types = ['jpg', 'jpeg', 'png'];
        
        if (in_array(strtolower($file_ext), $allowed_types)) {
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
                // Delete old avatar if it exists
                if ($avatar_path && file_exists($avatar_path)) {
                    unlink($avatar_path);
                }
                $avatar_path = $target_path;
            } else {
                $errors['avatar'] = 'Failed to upload avatar';
            }
        } else {
            $errors['avatar'] = 'Only JPG, JPEG, PNG files are allowed';
        }
    }

    // Check if password is being changed
    $password_changed = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect';
        }

        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters';
        }

        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        if (empty($errors)) {
            $password_changed = true;
        }
    }

    // Update profile if no errors
    if (empty($errors)) {
        try {
            // Check if email is being changed to an existing one
            if ($email !== $user['email']) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->rowCount() > 0) {
                    $errors['email'] = 'Email already registered';
                }
            }

            // Check if contact is being changed to an existing one
            if ($contact !== $user['contact']) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE contact = ? AND id != ?");
                $stmt->execute([$contact, $user_id]);
                if ($stmt->rowCount() > 0) {
                    $errors['contact'] = 'Contact number already registered';
                }
            }

            if (empty($errors)) {
                // Prepare update query
                $update_fields = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'contact' => $contact
                ];
                
                if ($password_changed) {
                    $update_fields['password'] = password_hash($new_password, PASSWORD_BCRYPT);
                }
                
                if ($avatar_path) {
                    $update_fields['avatar'] = $avatar_path;
                }

                $set_clause = implode(', ', array_map(function($field) {
                    return "$field = :$field";
                }, array_keys($update_fields)));
                
                $stmt = $pdo->prepare("UPDATE users SET $set_clause WHERE id = :id");
                $update_fields['id'] = $user_id;
                $stmt->execute($update_fields);

                // Update session data
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                
                $success = true;
                $_SESSION['toast_message'] = 'Profile updated successfully!';
                $_SESSION['toast_type'] = 'success';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $show_profile_form = false;
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errors['database'] = "Error updating profile. Please try again.";
        }
    }
}

// Fetch purchased courses
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.price, e.enrollment_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ? AND e.payment_status = 'completed'
        ORDER BY e.enrollment_date DESC
    ");
    $stmt->execute([$user_id]);
    $purchased_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors['database'] = "Error fetching purchased courses. Please try again.";
}

// Fetch quiz attempts
try {
    $stmt = $pdo->prepare("
        SELECT qa.id, qa.course_id, c.title, qa.score, qa.created_at, qa.correct_answers, qa.wrong_answers, qa.unanswered, qa.total_questions
        FROM quiz_attempts qa
        JOIN courses c ON qa.course_id = c.id
        WHERE qa.user_id = ?
        ORDER BY qa.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $quiz_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors['database'] = "Error fetching quiz attempts. Please try again.";
}

// Check if profile form should be shown
if (isset($_GET['edit']) && $_GET['edit'] === 'true') {
    $show_profile_form = true;
}

// Display toast if message exists
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Learning Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet"/>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-large: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        body {
            background: var(--bg-light);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .dashboard-container {
            padding: 15px;
            max-width: 600px;
            margin: 0 auto;
        }

        .dashboard-header {
            background: var(--primary-gradient);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            text-align: center;
        }

        .dashboard-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .stat-icon.courses {
            background: linear-gradient(135deg, #667eea20, #764ba220);
            color: var(--primary);
        }

        .stat-icon.quizzes {
            background: linear-gradient(135deg, #f093fb20, #f5576c20);
            color: #f5576c;
        }

        .stat-icon.achievements {
            background: linear-gradient(135deg, #43e97b20, #38f9d720);
            color: #38f9d7;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .content-card {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1rem;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h3 {
            margin: 0 0 0.25rem 0;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .profile-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .profile-details {
            display: grid;
            gap: 0.75rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--bg-light);
            border-radius: 6px;
        }

        .detail-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .course-item, .quiz-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            background: var(--bg-light);
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .course-item:hover, .quiz-item:hover {
            background: white;
            box-shadow: var(--shadow-light);
        }

        .course-icon, .quiz-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
        }

        .course-icon {
            background: var(--primary-gradient);
        }

        .quiz-icon {
            background: var(--secondary-gradient);
        }

        .item-content {
            flex: 1;
        }

        .item-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .quiz-score {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .quiz-score.pass {
            background: #10b98120;
            color: #10b981;
        }

        .quiz-score.fail {
            background: #ef444420;
            color: #ef4444;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 0.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }

        .mobile-contact-form {
            background: var(--warning-gradient);
            border-radius: 12px;
            padding: 1.5rem;
            color: white;
            text-align: center;
        }

        .mobile-contact-form h2 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .mobile-contact-form p {
            opacity: 0.9;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
        }

        .invalid-feedback {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .edit-form {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-light);
        }

        .avatar-upload {
            position: relative;
            max-width: 150px;
            margin: 0 auto 1rem;
        }

        .avatar-edit {
            position: absolute;
            right: 5px;
            bottom: 5px;
            z-index: 1;
        }

        .avatar-edit input {
            display: none;
        }

        .avatar-edit label {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
            box-shadow: var(--shadow-light);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid var(--border-light);
            box-shadow: var(--shadow-light);
            margin: 0 auto;
            overflow: hidden;
        }

        .avatar-preview > div {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
        }

        .password-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        .password-section h5 {
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: var(--success-gradient);
            color: white;
            border-radius: 8px;
            padding: 10px 15px;
            box-shadow: var(--shadow-medium);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .toast.error {
            background: var(--secondary-gradient);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php if (!empty($errors['database'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($errors['database']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($user['contact'])): ?>
            <!-- Mobile Number Input Form -->
            <div class="mobile-contact-form">
                <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                <h2>Complete Your Profile</h2>
                <p>Please provide your contact number to access your full learning dashboard</p>
                
                <form action="profile.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="submit_mobile" value="1">
                    
                    <div class="form-group">
                        <label for="contact" class="form-label" style="color: white;">Contact Number</label>
                        <input type="tel"
                            class="form-control <?php echo !empty($errors['contact']) ? 'is-invalid' : ''; ?>"
                            id="contact"
                            name="contact"
                            placeholder="Enter your contact number">
                        <?php if (!empty($errors['contact'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['contact']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>
                        Complete Profile
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h1>
                <p>Continue your learning journey and track your progress</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon courses">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo count($purchased_courses); ?></div>
                    <div class="stat-label">Courses Enrolled</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon quizzes">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo count($quiz_attempts); ?></div>
                    <div class="stat-label">Quizzes Attempted</div>
                </div>
                
                
            </div>

            <?php if (!$show_profile_form): ?>
                <!-- Main Content -->
                <div>
                    <!-- My Courses -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-graduation-cap"></i>
                                My Courses
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($purchased_courses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-book-open"></i>
                                    <h4>No courses yet</h4>
                                    <p>Start your learning journey by enrolling in a course</p>
                                    <a href="courses.php" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Browse Courses
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($purchased_courses as $course): ?>
                                    <div class="course-item">
                                        <div class="course-icon">
                                            <i class="fas fa-play"></i>
                                        </div>
                                        <div class="item-content">
                                            <div class="item-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                            <div class="item-meta">
                                                Enrolled on <?php echo date('M j, Y', strtotime($course['enrollment_date'])); ?>
                                            </div>
                                        </div>
                                        <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-arrow-right"></i>
                                            Continue
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quiz Attempts -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-question-circle"></i>
                                Recent Quiz Attempts
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($quiz_attempts)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-question"></i>
                                    <h4>No quiz attempts yet</h4>
                                    <p>Take a quiz to test your knowledge</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($quiz_attempts, 0, 3) as $attempt): ?>
                                    <div class="quiz-item">
                                        <div class="quiz-icon">
                                            <i class="fas fa-question"></i>
                                        </div>
                                        <div class="item-content">
                                            <div class="item-title"><?php echo htmlspecialchars($attempt['title']); ?></div>
                                            <div class="item-meta">
                                                Attempted on <?php echo date('M j, Y', strtotime($attempt['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="quiz-score <?php echo $attempt['score'] >= 70 ? 'pass' : 'fail'; ?>">
                                            <?php echo number_format($attempt['score'], 1); ?>%
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($quiz_attempts) > 3): ?>
                                    <div class="text-center mt-2">
                                        <a href="quiz-results.php" class="btn btn-outline">
                                            <i class="fas fa-list"></i>
                                            View All Attempts
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Card -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user"></i>
                                My Profile
                            </h3>
                            <a href="profile.php?edit=true" class="btn btn-outline">
                                <i class="fas fa-pencil-alt"></i>
                                Edit
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="profile-section">
                                <div class="profile-avatar">
    <?php if (!empty($user['avatar'])): ?>
        <?php
        // Check if the avatar is an absolute URL (starts with http:// or https://)
        $avatarUrl = (preg_match('/^https?:\/\//', $user['avatar']))
            ? htmlspecialchars($user['avatar']) // Use absolute URL as-is (e.g., Google profile picture)
            : '../' . htmlspecialchars($user['avatar']); // Prepend ../ for local paths
        ?>
        <img src="<?php echo $avatarUrl; ?>" alt="Profile">
    <?php else: ?>
        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
    <?php endif; ?>
</div>
                                <div class="profile-info">
                                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                            <div class="profile-details">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="detail-content">
                                        <div class="detail-label">Contact Number</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($user['contact']); ?></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="detail-content">
                                        <div class="detail-label">Member Since</div>
                                        <div class="detail-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <a href="auth/logout.php" class="btn btn-outline text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Profile Edit Form -->
                <div class="edit-form">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="card-title">
                            <i class="fas fa-user-edit"></i>
                            Edit Profile
                        </h3>
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>

                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="submit_profile" value="1">
                        
                        <!-- Avatar Upload -->
                        <div class="avatar-upload">
                            <div class="avatar-edit">
                                <input type="file" id="avatarUpload" name="avatar" accept=".png, .jpg, .jpeg">
                                <label for="avatarUpload"><i class="fas fa-camera"></i></label>
                            </div>
                            <div class="avatar-preview">
                                <div id="avatarPreview" style="background-image: url('<?php 
                                    echo !empty('../'.$user['avatar']) ? htmlspecialchars('../'.$user['avatar']) : 
                                    'https://ui-avatars.com/api/?name='.urlencode($user['first_name'].'+'.$user['last_name']).'&size=150'; 
                                ?>');"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text"
                                class="form-control <?php echo !empty($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                id="first_name"
                                name="first_name"
                                value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            <?php if (!empty($errors['first_name'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['first_name']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text"
                                class="form-control <?php echo !empty($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                id="last_name"
                                name="last_name"
                                value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            <?php if (!empty($errors['last_name'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['last_name']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email"
                                class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>"
                                id="email"
                                name="email"
                                value="<?php echo htmlspecialchars($user['email']); ?>">
                            <?php if (!empty($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact" class="form-label">Contact Number</label>
                            <input type="tel"
                                class="form-control <?php echo !empty($errors['contact']) ? 'is-invalid' : ''; ?>"
                                id="contact"
                                name="contact"
                                value="<?php echo htmlspecialchars($user['contact']); ?>">
                            <?php if (!empty($errors['contact'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['contact']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="password-section">
                            <h5>Change Password</h5>
                            
                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div style="position: relative;">
                                    <input type="password"
                                        class="form-control <?php echo !empty($errors['current_password']) ? 'is-invalid' : ''; ?>"
                                        id="current_password"
                                        name="current_password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!empty($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['current_password']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <div style="position: relative;">
                                    <input type="password"
                                        class="form-control <?php echo !empty($errors['new_password']) ? 'is-invalid' : ''; ?>"
                                        id="new_password"
                                        name="new_password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!empty($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['new_password']); ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div style="position: relative;">
                                    <input type="password"
                                        class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                        id="confirm_password"
                                        name="confirm_password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (!empty($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i>
                            Save Changes
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Toast Notification -->
    <?php if (isset($toast_message)): ?>
        <div class="toast-container">
            <div class="toast <?php echo $toast_type === 'error' ? 'error' : ''; ?>">
                <span><?php echo htmlspecialchars($toast_message); ?></span>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'" style="color: white; background: none; border: none; margin-left: 10px;"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php include('includes/footer.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Avatar preview
        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').style.backgroundImage = 'url('+e.target.result+')';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        document.getElementById('avatarUpload').addEventListener('change', function() {
            readURL(this);
        });

        // Password toggle
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-hide toast after 5 seconds
        <?php if (isset($toast_message)): ?>
            setTimeout(() => {
                const toast = document.querySelector('.toast-container');
                if (toast) toast.style.display = 'none';
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>