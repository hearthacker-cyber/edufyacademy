<?php
session_start();
require_once 'includes/db.php';
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Please log in to take the quiz.',
            'error_details' => 'Redirecting to login page.'
        ]);
        exit();
    }
    header("Location: index.php");
    exit();
}

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Verify user enrollment
try {
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $is_enrolled = $stmt->fetch();
} catch (PDOException $e) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: Unable to verify enrollment.',
            'error_details' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ]);
        exit();
    }
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (!$is_enrolled) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: You are not enrolled in this course.',
            'error_details' => 'Redirecting to course details page.'
        ]);
        exit();
    }
    header("Location: course-details.php?course_id=$course_id");
    exit();
}

// Get course details
try {
    $stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Course not found.',
                'error_details' => 'Please check the course ID.'
            ]);
            exit();
        }
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: Unable to fetch course details.',
            'error_details' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ]);
        exit();
    }
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$page_title = "Quiz - " . htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8');
$breadcrumb = [
    ["label" => "Home", "url" => "index.php", "icon" => "ph-house"],
    ["label" => htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'), "url" => "course-details.php?course_id=$course_id"],
    ["label" => "Quiz", "url" => ""]
];

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    header('Content-Type: application/json');

    try {
        $start_time = $_POST['start_time'] ?? date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s');
        $time_taken = strtotime($end_time) - strtotime($start_time);

        // Get all questions for this course
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_questions = count($questions);
        $correct_answers = 0;
        $wrong_answers = 0;
        $unanswered = 0;

        // Record the attempt
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, course_id, start_time, end_time, time_taken, total_questions) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $course_id, $start_time, $end_time, $time_taken, $total_questions]);
        $attempt_id = $pdo->lastInsertId();

        // Process each question
        $responses = [];
        foreach ($questions as $question) {
            $question_id = $question['id'];
            $selected_option = $_POST['question_' . $question_id] ?? null;

            if ($selected_option === null) {
                $unanswered++;
                $is_correct = null;
            } else {
                $is_correct = ($selected_option == $question['correct_option']) ? 1 : 0;
                if ($is_correct) {
                    $correct_answers++;
                } else {
                    $wrong_answers++;
                }
            }

            // Record the response
            $stmt = $pdo->prepare("INSERT INTO quiz_responses (attempt_id, question_id, selected_option, is_correct) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$attempt_id, $question_id, $selected_option, $is_correct]);

            // Store response details for results
            $responses[] = [
                'question_id' => $question_id,
                'question_text' => $question['question'],
                'selected_option' => $selected_option,
                'correct_option' => $question['correct_option'],
                'options' => [
                    'a' => $question['option_a'],
                    'b' => $question['option_b'],
                    'c' => $question['option_c'],
                    'd' => $question['option_d']
                ],
                'is_correct' => $is_correct
            ];
        }

        // Calculate score and update attempt
        $score = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 2) : 0;
        $stmt = $pdo->prepare("UPDATE quiz_attempts 
                              SET score = ?, correct_answers = ?, wrong_answers = ?, unanswered = ?
                              WHERE id = ?");
        $stmt->execute([$score, $correct_answers, $wrong_answers, $unanswered, $attempt_id]);

        // Return JSON response for AJAX
        if (isset($_POST['ajax'])) {
            echo json_encode([
                'success' => true,
                'attempt_id' => $attempt_id,
                'score' => $score,
                'correct_answers' => $correct_answers,
                'wrong_answers' => $wrong_answers,
                'unanswered' => $unanswered,
                'total_questions' => $total_questions,
                'time_taken' => gmdate("H:i:s", $time_taken),
                'pass_status' => $score >= 70 ? 'passed' : 'failed',
                'pass_threshold' => 70,
                'responses' => $responses
            ]);
            exit();
        }

        // Fallback redirect for non-AJAX
        header("Location: course-quiz.php?course_id=$course_id&result=$attempt_id");
        exit();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: Unable to process quiz submission.',
            'error_details' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ]);
        exit();
    }
}

// Get questions for display
try {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE course_id = ? ORDER BY RAND()");
    $stmt->execute([$course_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: Unable to fetch questions.',
            'error_details' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ]);
        exit();
    }
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Get previous attempts
try {
    $stmt = $pdo->prepare("SELECT score, created_at FROM quiz_attempts 
                          WHERE user_id = ? AND course_id = ? 
                          ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $previous_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: Unable to fetch previous attempts.',
            'error_details' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        ]);
        exit();
    }
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.0.3/font/css/phosphor.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet"/>
   
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --success: #10b981;
            --success-dark: #059669;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        body {
            background-color: #f8f9fa;
            color: var(--gray-800);
        }

        .quiz-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .quiz-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            text-align: center;
            border: 1px solid var(--gray-200);
        }

        .quiz-header h1 {
            color: var(--gray-900);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .quiz-header .lead {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .quiz-meta {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .meta-item i {
            color: var(--primary);
        }

        .question-card {
            background: white;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .question-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 32px;
            
            color: blue;
            border-radius: 50%;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 1rem;
        }

        .question-text {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .options-container {
            display: grid;
            gap: 0.75rem;
        }

        .option-wrapper {
            position: relative;
        }

        .option-input {
            position: absolute;
            opacity: 0;
            height: 0;
            width: 0;
        }

        .option-label {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .option-label:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .option-input:checked + .option-label {
            background: rgba(79, 70, 229, 0.05);
            border-color: var(--primary);
            color: var(--primary);
        }

        .option-marker {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-300);
            border-radius: 50%;
            margin-right: 1rem;
            position: relative;
            transition: all 0.2s ease;
        }

        .option-input:checked + .option-label .option-marker {
            border-color: var(--primary);
            background-color: var(--primary);
        }

        .option-input:checked + .option-label .option-marker::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        .quiz-progress {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 5rem;
            z-index: 10;
            border: 1px solid var(--gray-200);
        }

        .progress-bar-custom {
            height: 6px;
            background: var(--gray-200);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
        }

        .timer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .submit-section {
            background: white;
            border-radius: 12px;
            padding: 1.75rem;
            text-align: center;
            margin-top: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .btn-submit {
            background: var(--primary);
            border: none;
            color: white;
            padding: 0.875rem 2.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 16px;
            overflow: hidden;
            border: none;
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .result-score {
            text-align: center;
            margin-bottom: 2rem;
        }

        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .score-pass {
            background: var(--success);
        }

        .score-fail {
            background: var(--danger);
        }

        .result-status {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .result-time {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .stat-correct { color: var(--success); }
        .stat-wrong { color: var(--danger); }
        .stat-unanswered { color: var(--warning); }
        .stat-total { color: var(--primary); }

        .no-questions {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .no-questions i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .no-questions h3 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .no-questions p {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        .attempt-history {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .attempt-history h4 {
            color: var(--gray-800);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .attempt-list {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .attempt-item {
            min-width: 120px;
            padding: 0.75rem;
            border-radius: 8px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            text-align: center;
        }

        .attempt-score {
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .attempt-date {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        /* Correct Answers Section */
        .correct-answers-section {
            margin-top: 2rem;
        }

        .correct-answers-section h4 {
            color: var(--gray-800);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .answer-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .answer-card.correct {
            border-left: 4px solid var(--success);
        }

        .answer-card.incorrect {
            border-left: 4px solid var(--danger);
        }

        .answer-card.unanswered {
            border-left: 4px solid var(--warning);
        }

        .answer-question {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .answer-details {
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .answer-details span {
            display: block;
            margin-bottom: 0.25rem;
        }

        .answer-correct {
            color: var(--success);
            font-weight: 500;
        }

        .answer-incorrect {
            color: var(--danger);
            font-weight: 500;
        }

        .answer-unanswered {
            color: var(--warning);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .quiz-header {
                padding: 1.5rem;
            }
            
            .quiz-header h1 {
                font-size: 1.75rem;
            }
            
            .quiz-meta {
                gap: 1rem;
            }
            
            .question-card {
                padding: 1.5rem;
            }
            
            .option-label {
                padding: 0.875rem 1rem;
            }
            
            .modal-body {
                padding: 1.5rem;
            }

            .answer-card {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
     <?php include('includes/nav.php'); ?>
    <?php include('includes/menu.php'); ?>

    <div class="quiz-container">
        <!-- Quiz Header -->
        <div class="quiz-header">
            <h1><i class="fas fa-graduation-cap me-2"></i><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?> Quiz</h1>
            <p class="lead">Test your knowledge and track your progress</p>
            
            <div class="quiz-meta">
                <div class="meta-item">
                    <i class="fas fa-question-circle"></i>
                    <span><?= count($questions) ?> Questions</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span id="quiz-timer" class="timer">00:00:00</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-trophy"></i>
                    <span>70% to Pass</span>
                </div>
            </div>
        </div>

        <?php if (!empty($previous_attempts)): ?>
        <div class="attempt-history">
            <h4><i class="fas fa-history me-2"></i>Your Previous Attempts</h4>
            <div class="attempt-list">
                <?php foreach ($previous_attempts as $attempt): ?>
                    <div class="attempt-item">
                        <div class="attempt-score <?= $attempt['score'] >= 70 ? 'text-success' : 'text-danger' ?>">
                            <?= round($attempt['score']) ?>%
                        </div>
                        <div class="attempt-date">
                            <?= date('M j, Y', strtotime($attempt['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($questions)): ?>
            <div class="no-questions">
                <i class="fas fa-clipboard-question"></i>
                <h3>No Questions Available</h3>
                <p>This quiz doesn't have any questions yet. Please check back later.</p>
                <a href="course-details.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Course
                </a>
            </div>
        <?php else: ?>
            <!-- Progress Bar -->
            <div class="quiz-progress">
                <div class="progress-bar-custom">
                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">
                    <span>Progress: <span id="progress-text">0 of <?= count($questions) ?></span></span>
                    <span class="timer">
                        <i class="fas fa-clock"></i>
                        <span id="progress-timer">00:00:00</span>
                    </span>
                </div>
            </div>

            <!-- Quiz Form -->
            <form id="quiz-form" method="POST">
                <input type="hidden" name="start_time" id="start_time" value="<?= date('Y-m-d H:i:s') ?>">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="submit_quiz" value="1">

                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card" data-question="<?= $index + 1 ?>">
                        <div class="d-flex align-items-start mb-3">
                            <span class="question-number"><?= $index + 1 ?></span>
                            <div class="question-text"><?= htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        
                        <div class="options-container">
                            <?php 
                            $options = [
                                'a' => $question['option_a'],
                                'b' => $question['option_b'],
                                'c' => $question['option_c'],
                                'd' => $question['option_d']
                            ];
                            
                            foreach ($options as $key => $option): ?>
                                <div class="option-wrapper">
                                    <input type="radio" 
                                           class="option-input" 
                                           name="question_<?= $question['id'] ?>" 
                                           value="<?= $key ?>" 
                                           id="q<?= $question['id'] ?>_<?= $key ?>">
                                    <label class="option-label" for="q<?= $question['id'] ?>_<?= $key ?>">
                                        <div class="option-marker"></div>
                                        <span><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Submit Section -->
                <div class="submit-section">
                    <h4 class="mb-3">Ready to Submit?</h4>
                    <p class="text-muted mb-4">Review your answers and submit when you're ready. You can submit even with unanswered questions.</p>
                    <button type="submit" name="submit_quiz" class="btn-submit" id="submit-btn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Quiz
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Results Modal -->
    <div class="modal fade" id="resultsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-pie me-2"></i>Quiz Results
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="result-score">
                        <div class="score-circle" id="score-circle">
                            <span id="score-percentage">0%</span>
                        </div>
                        <div class="result-status" id="result-status"></div>
                        <div class="result-time">
                            <i class="fas fa-clock me-2"></i>
                            <span id="time-taken">00:00:00</span>
                        </div>
                    </div>

                    <div class="result-stats">
                        <div class="stat-card">
                            <span class="stat-value stat-correct" id="correct-count">0</span>
                            <span class="stat-label">Correct</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value stat-wrong" id="wrong-count">0</span>
                            <span class="stat-label">Wrong</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value stat-unanswered" id="unanswered-count">0</span>
                            <span class="stat-label">Unanswered</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-value stat-total" id="total-count">0</span>
                            <span class="stat-label">Total</span>
                        </div>
                    </div>

                    <div class="correct-answers-section">
                        <h4><i class="fas fa-check-circle me-2"></i>Answer Review</h4>
                        <div id="correct-answers-list"></div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <a href="course-details.php?id=<?= $course_id ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Course
                        </a>
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Submission Error</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="error-message">An error occurred while submitting your quiz.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-redo me-2"></i>Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>

   <?php include('includes/footer.php'); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Timer functionality
        let startTime = new Date();
        let timerInterval;

        function updateTimer() {
            const now = new Date();
            const elapsed = Math.floor((now - startTime) / 1000);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            
            const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            document.getElementById('quiz-timer').textContent = timeString;
            document.getElementById('progress-timer').textContent = timeString;
        }

        // Start timer
        timerInterval = setInterval(updateTimer, 1000);

        // Progress tracking
        function updateProgress() {
            const totalQuestions = <?= count($questions) ?>;
            const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
            const progress = (answeredQuestions / totalQuestions) * 100;
            
            document.getElementById('progress-fill').style.width = progress + '%';
            document.getElementById('progress-text').textContent = `${answeredQuestions} of ${totalQuestions}`;
        }

        // Add event listeners to radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', updateProgress);
        });

        // Form submission
        document.getElementById('quiz-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Server did not return JSON. Response: ${text.substring(0, 100)}...`);
                }

                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Submission failed');
                }
                
                // Stop timer
                clearInterval(timerInterval);
                
                showResults(data);
                
            } catch (error) {
                console.error('Submission error:', error);
                
                // Show user-friendly error message
                let errorMessage = error.message.includes('JSON parse error') 
                    ? 'Invalid server response. Please try again.'
                    : error.message;
                
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                document.getElementById('error-message').textContent = errorMessage;
                errorModal.show();
                
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

        function showResults(data) {
            // Update modal content
            document.getElementById('score-percentage').textContent = Math.round(data.score) + '%';
            document.getElementById('time-taken').textContent = data.time_taken;
            document.getElementById('correct-count').textContent = data.correct_answers;
            document.getElementById('wrong-count').textContent = data.wrong_answers;
            document.getElementById('unanswered-count').textContent = data.unanswered;
            document.getElementById('total-count').textContent = data.total_questions;
            
            const scoreCircle = document.getElementById('score-circle');
            const resultStatus = document.getElementById('result-status');
            
            if (data.pass_status === 'passed') {
                scoreCircle.className = 'score-circle score-pass';
                resultStatus.textContent = 'Congratulations! You Passed!';
                resultStatus.className = 'result-status text-success';
            } else {
                scoreCircle.className = 'score-circle score-fail';
                resultStatus.textContent = `Keep Learning! (${data.pass_threshold}% needed to pass)`;
                resultStatus.className = 'result-status text-danger';
            }

            // Display correct answers
            const answersList = document.getElementById('correct-answers-list');
            answersList.innerHTML = '';
            data.responses.forEach((response, index) => {
                const statusClass = response.is_correct === 1 ? 'correct' : 
                                  response.is_correct === 0 ? 'incorrect' : 'unanswered';
                const statusText = response.is_correct === 1 ? 'Correct' : 
                                 response.is_correct === 0 ? 'Incorrect' : 'Unanswered';
                const userAnswer = response.selected_option ? 
                    `${response.selected_option.toUpperCase()}. ${response.options[response.selected_option]}` : 
                    'No answer selected';
                const correctAnswer = `${response.correct_option.toUpperCase()}. ${response.options[response.correct_option]}`;

                const answerCard = document.createElement('div');
                answerCard.className = `answer-card ${statusClass}`;
                answerCard.innerHTML = `
                    <div class="answer-question">Question ${index + 1}: ${response.question_text}</div>
                    <div class="answer-details">
                        <span class="${statusClass}">Status: ${statusText}</span>
                        <span>Your Answer: ${userAnswer}</span>
                        <span class="answer-correct">Correct Answer: ${correctAnswer}</span>
                    </div>
                `;
                answersList.appendChild(answerCard);
            });
            
            // Show modal
            const resultsModal = new bootstrap.Modal(document.getElementById('resultsModal'));
            resultsModal.show();
        }

        // Initialize progress
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
        });
    </script>
</body>
</html>