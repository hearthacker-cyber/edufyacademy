
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

// Fetch user data
$user_id = $_SESSION['user_id'];
$user = [];
$errors = [];

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
    $errors['database'] = "Error fetching user data. Please try again.";
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

// Handle toast messages
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? null;
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results</title>
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

        .quiz-results-container {
            padding: 15px;
            max-width: 600px;
            margin: 0 auto;
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

        .quiz-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            background: var(--bg-light);
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }

        .quiz-item:hover {
            background: white;
            box-shadow: var(--shadow-light);
        }

        .quiz-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
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
            text-decoration: none;
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
    <?php include('includes/nav.php'); ?>
    <?php include('includes/menu.php'); ?>

    <div class="quiz-results-container">
        <?php if (!empty($errors['database'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($errors['database']); ?>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-question-circle"></i>
                    Quiz Results
                </h3>
                <a href="profile.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Profile
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($quiz_attempts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-question"></i>
                        <h4>No Quiz Attempts</h4>
                        <p>You haven't attempted any quizzes yet.</p>
                        <a href="courses.php" class="btn btn-outline">
                            <i class="fas fa-book"></i>
                            Browse Courses
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($quiz_attempts as $attempt): ?>
                        <div class="quiz-item">
                            <div class="quiz-icon">
                                <i class="fas fa-question"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title"><?php echo htmlspecialchars($attempt['title']); ?></div>
                                <div class="item-meta">
                                    Attempted on <?php echo date('M j, Y', strtotime($attempt['created_at'])); ?>
                                    <br>
                                    Correct: <?php echo $attempt['correct_answers']; ?> |
                                    Wrong: <?php echo $attempt['wrong_answers']; ?> |
                                    Unanswered: <?php echo $attempt['unanswered']; ?> |
                                    Total: <?php echo $attempt['total_questions']; ?>
                                </div>
                            </div>
                            <div class="quiz-score <?php echo $attempt['score'] >= 70 ? 'pass' : 'fail'; ?>">
                                <?php echo number_format($attempt['score'], 1); ?>%
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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
