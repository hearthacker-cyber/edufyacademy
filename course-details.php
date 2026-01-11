<?php
session_start();
require_once 'includes/db.php';

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

// Get the sort parameter from URL
$sort = $_GET['sort'] ?? 'newest';

try {
    // Get user details
    $userStmt = $pdo->prepare("SELECT first_name, last_name, email, contact FROM users WHERE id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Check if lead already exists
        $leadStmt = $pdo->prepare("SELECT id FROM course_leads WHERE user_id = ? AND course_id = ?");
        $leadStmt->execute([$_SESSION['user_id'], $course_id]);
        $existingLead = $leadStmt->fetch();
        
        if ($existingLead) {
            // Update visit count for existing lead
            $updateStmt = $pdo->prepare("UPDATE course_leads 
                                        SET visit_count = visit_count + 1, 
                                            last_visit = NOW() 
                                        WHERE id = ?");
            $updateStmt->execute([$existingLead['id']]);
        } else {
            // Create new lead record
            $insertStmt = $pdo->prepare("INSERT INTO course_leads 
                                       (user_id, course_id, first_name, last_name, email, contact) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([
                $_SESSION['user_id'],
                $course_id,
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['contact']
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Lead tracking error: " . $e->getMessage());
    $_SESSION['toast_message'] = "Error tracking course visit. Please try again.";
    $_SESSION['toast_type'] = 'error';
}

// Define the order by clause based on sort parameter
$order_by = '';
switch ($sort) {
    case 'highest':
        $order_by = 'r.rating DESC, r.created_at DESC';
        break;
    case 'lowest':
        $order_by = 'r.rating ASC, r.created_at DESC';
        break;
    default: // newest
        $order_by = 'r.created_at DESC';
        break;
}

try {
    // Fetch course details
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
    $stmt->execute([':id' => $course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header("Location: courses.php");
        exit();
    }

    // Check if user is enrolled
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = :user_id AND course_id = :course_id");
    $stmt->execute([':user_id' => $_SESSION['user_id'], ':course_id' => $course_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    $isEnrolled = !empty($enrollment);

    // Fetch course sections
    $stmt = $pdo->prepare("SELECT * FROM courses_section WHERE course_id = :course_id ORDER BY order_no ASC");
    $stmt->execute([':course_id' => $course_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine free sections (first 3) and paid sections
    $freeSections = array_slice($sections, 0, 3);
    $paidSections = array_slice($sections, 3);

    // Fetch total lessons count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_lessons FROM course_lessons WHERE course_id = :course_id");
    $stmt->execute([':course_id' => $course_id]);
    $lessonsData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalLessons = $lessonsData['total_lessons'];

    // Format price
    $price = number_format($course['price'], 2);

    // Fetch all reviews with user info
    $stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name 
                         FROM reviews r
                         JOIN users u ON r.user_id = u.id
                         WHERE r.course_id = ?
                         ORDER BY $order_by");
    $stmt->execute([$course_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch review statistics
    $stmt = $pdo->prepare("SELECT 
                          AVG(rating) as avg_rating, 
                          COUNT(*) as total_reviews,
                          SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                          SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                          SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                          SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                          SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                          FROM reviews WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $avg_rating = round($stats['avg_rating'], 1);
    $total_reviews = $stats['total_reviews'];
    
    // Calculate percentages for each star rating
    $percentages = [
        5 => $total_reviews > 0 ? round(($stats['five_star'] / $total_reviews) * 100) : 0,
        4 => $total_reviews > 0 ? round(($stats['four_star'] / $total_reviews) * 100) : 0,
        3 => $total_reviews > 0 ? round(($stats['three_star'] / $total_reviews) * 100) : 0,
        2 => $total_reviews > 0 ? round(($stats['two_star'] / $total_reviews) * 100) : 0,
        1 => $total_reviews > 0 ? round(($stats['one_star'] / $total_reviews) * 100) : 0,
    ];

    // Fetch instructor details
    $stmt = $pdo->prepare("SELECT i.id, i.name, i.role, i.bio, i.image_path, i.rating, i.learners 
                          FROM instructor i 
                          JOIN courses c ON c.instructor_id = i.id 
                          WHERE c.id = ?");
    $stmt->execute([$course_id]);
    $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch all lessons for auto-play logic
    $allLessons = [];
    foreach ($sections as $index => $section) {
        $stmt = $pdo->prepare("SELECT * FROM course_lessons WHERE section_id = :section_id ORDER BY order_no ASC");
        $stmt->execute([':section_id' => $section['id']]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lessons as $lesson) {
            $isFreeLesson = $index < 3 && count(array_filter($allLessons, fn($l) => $l['section_id'] == $section['id'])) < 5;
            $allLessons[] = [
                'id' => $lesson['id'],
                'section_id' => $section['id'],
                'title' => $lesson['title'],
                'video_url' => $lesson['video_url'],
                'is_preview' => $lesson['is_preview'] == 1,
                'is_free' => $isFreeLesson,
                'can_access' => $isEnrolled || $isFreeLesson || $lesson['is_preview'] == 1
            ];
        }
    }

} catch (PDOException $e) {
    error_log("Error loading course: " . $e->getMessage());
    $_SESSION['toast_message'] = "Error loading course details. Please try again.";
    $_SESSION['toast_type'] = 'error';
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
    <title><?= htmlspecialchars($course['title']) ?> | ELearn Mobile</title>
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

        .course-container {
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

        .course-header {
            background: var(--primary-gradient);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: white;
            text-align: center;
        }

        .course-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .course-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .video-container {
            width: 100%;
            height: 250px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
        }

        .video-container iframe,
        .video-container video {
            width: 100%;
            height: 100%;
            border: none;
        }

        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 1rem;
        }

        .lock-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
        }

        .progress-card {
            background: var(--success-gradient);
            border-radius: 12px;
            padding: 1rem;
            color: white;
            margin-bottom: 1rem;
        }

        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: white;
            border-radius: 8px;
            transition: width 0.3s ease;
        }

        .section-accordion {
            margin-bottom: 0.75rem;
        }

        .section-toggle {
            background: var(--bg-light);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            width: 100%;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .section-toggle.active {
            background: var(--primary);
            color: white;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
        }

        .section-toggle.active .toggle-icon {
            transform: rotate(180deg);
        }

        .lessons-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--bg-card);
            border-radius: 8px;
            border: 1px solid var(--border-light);
            margin-top: 0.5rem;
        }

        .lessons-container.show {
            max-height: 1000px;
        }

        .lesson-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .lesson-item:hover {
            background-color: var(--bg-light);
        }

        .lesson-item.current-lesson {
            background-color: #e3f2fd;
            border-left: 4px solid var(--primary);
        }

        .lesson-item.completed-lesson .lesson-icon {
            background: var(--success);
        }

        .lesson-item:last-child {
            border-bottom: none;
        }

        .lesson-item.locked-lesson {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .lesson-item.locked-lesson:hover {
            background-color: transparent;
        }

        .lesson-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            background: var(--primary-gradient);
            color: white;
        }

        .lesson-item.locked-lesson .lesson-icon {
            background: var(--text-secondary);
        }

        .lesson-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .lesson-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-preview {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-free {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-premium {
            background: #ede9fe;
            color: #6b21a8;
        }

        .instructor-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .instructor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .instructor-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .instructor-info p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .rating-summary {
            background: var(--primary-gradient);
            border-radius: 12px;
            padding: 1rem;
            color: white;
            text-align: center;
            margin-bottom: 1rem;
        }

        .rating-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .rating-breakdown {
            margin-bottom: 1rem;
        }

        .rating-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .rating-bar {
            flex: 1;
            height: 6px;
            background: var(--border-light);
            border-radius: 6px;
            overflow: hidden;
        }

        .rating-fill {
            height: 100%;
            background: var(--warning);
            border-radius: 6px;
        }

        .review-item {
            padding: 0.75rem;
            border-radius: 8px;
            background: var(--bg-light);
            margin-bottom: 0.75rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .review-author {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .review-date {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .review-text {
            font-size: 0.9rem;
            color: var(--text-primary);
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
            width: 100%;
        }

        .btn-success {
            background: var(--success-gradient);
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
            width: 100%;
        }

        .btn-info {
            background: var(--secondary-gradient);
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
            width: 100%;
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

        .form-group {
            margin-bottom: 1rem;
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
        }

        .rating-input {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            font-size: 1.5rem;
            color: #e5e7eb;
            cursor: pointer;
        }

        .rating-input input:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: var(--warning);
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

        .video-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding: 10px;
            background: var(--bg-light);
            border-radius: 8px;
        }

        .current-lesson-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include('includes/nav.php'); ?>
    <?php include('includes/menu.php'); ?>

    <div class="course-container">
        <?php if (!empty($errors['database'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($errors['database']); ?>
            </div>
        <?php endif; ?>

        <!-- Course Header -->
        <div class="course-header">
            <h1><?php echo htmlspecialchars($course['title']); ?></h1>
            <div class="course-meta">
                <div class="meta-item">
                    <i class="fas fa-chart-pie"></i>
                    <?php echo ucfirst($course['level']); ?>
                </div>
                <div class="meta-item">
                    <i class="fas fa-video"></i>
                    <?php echo $totalLessons; ?> lessons
                </div>
                <div class="meta-item">
                    <i class="fas fa-globe"></i>
                    <?php echo htmlspecialchars($course['language']); ?>
                </div>
            </div>
        </div>

        <!-- Video Player -->
        <div class="content-card">
            <div class="video-container" id="videoPlayer">
                <?php if ($isEnrolled || $course['preview_video'] == 'yes'): ?>
                    <?php if (!empty($course['video_url'])):
                        $video_url = trim($course['video_url']);
                        echo generateVideoEmbed($video_url, false);
                    else: ?>
                        <img src="<?= htmlspecialchars('../admin/' . $course['image']) ?>" 
                             style="width:100%;height:100%;object-fit:cover;">
                    <?php endif; ?>
                <?php else: ?>
                    <div class="locked-overlay">
                        <div class="lock-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h6>Course Locked</h6>
                        <p>Please enroll to access course content</p>
                        <a href="apply-admission.php?course_id=<?= $course['id'] ?>" 
                           class="btn btn-primary">Enroll Now</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="video-controls" id="videoControls" style="display: none;">
                <div class="current-lesson-title" id="currentLessonTitle">Select a lesson to play</div>
                <button class="btn btn-outline btn-sm" id="nextLessonBtn">
                    <i class="fas fa-forward"></i> Next
                </button>
            </div>
        </div>

        <!-- Course Progress -->
        <?php if ($isEnrolled): ?>
            <?php
            $progress = 0;
            if ($totalLessons > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$_SESSION['user_id'], $course_id]);
                $completed = $stmt->fetchColumn();
                $progress = round(($completed / $totalLessons) * 100);
            }
            ?>
            <div class="progress-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6>Your Progress</h6>
                    <span class="badge bg-white text-success"><?php echo $progress; ?>%</span>
                </div>
                <p class="opacity-75"><?php echo $completed; ?> of <?php echo $totalLessons; ?> lessons completed</p>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
                <?php if ($progress >= 100): ?>
                    <div class="mt-3 text-center">
                        <a href="certificate.php?id=<?php echo $enrollment['id'] ?? 0; // Use enrollment_id if available, but certificate.php uses course_id logic too?>" 
                           onclick="window.location.href='certificate.php?id=<?php echo $course_id; ?>'; return false;"
                           class="btn btn-sm btn-outline-light text-white border-white">
                            <i class="fas fa-certificate me-1"></i> Download Certificate
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Course Description -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-book"></i>
                    Course Description
                </h3>
            </div>
            <div class="card-body">
                <?php echo strip_tags(
                    $course['description'],
                    '<p><a><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><pre><code><img><br><div><span>'
                ); ?>
            </div>
        </div>

        <!-- Course Curriculum -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    Course Curriculum
                </h3>
            </div>
            <div class="card-body">
                <?php 
                $section_counter = 0;
                foreach ($sections as $index => $section): 
                    $isFreeSection = $index < 3;
                ?>
                    <div class="section-accordion">
                        <button class="section-toggle <?php echo $index === 0 ? 'active' : ''; ?>" 
                                data-section="<?php echo $section['id']; ?>">
                            <span><?php echo htmlspecialchars($section['section_title']); ?></span>
                            <div>
                                <?php if (!$isFreeSection && !$isEnrolled): ?>
                                    <span class="badge badge-premium">Premium</span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                        </button>
                        <div class="lessons-container <?php echo $index === 0 ? 'show' : ''; ?>" 
                             id="section-<?php echo $section['id']; ?>">
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM course_lessons WHERE section_id = :section_id ORDER BY order_no ASC");
                            $stmt->execute([':section_id' => $section['id']]);
                            $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $stmt = $pdo->prepare("SELECT lesson_id FROM user_progress WHERE user_id = :user_id AND course_id = :course_id");
                            $stmt->execute([':user_id' => $_SESSION['user_id'], ':course_id' => $course_id]);
                            $completedLessons = $stmt->fetchAll(PDO::FETCH_COLUMN);

                            $isFirstThreeSections = $index < 3;
                            $maxFreeLessonsPerSection = 5;
                            $lesson_counter = 0;
                            foreach ($lessons as $lesson):
                                $isCompleted = in_array($lesson['id'], $completedLessons);
                                $isPreview = $lesson['is_preview'] == 1;
                                $isFreeLesson = $isFirstThreeSections && ($lesson_counter < $maxFreeLessonsPerSection);
                                $canAccess = $isEnrolled || $isFreeLesson || $isPreview;
                            ?>
                                <div class="lesson-item <?php echo !$canAccess ? 'locked-lesson' : ''; ?> <?php echo $isCompleted ? 'completed-lesson' : ''; ?>"
                                     data-video-url="<?php echo htmlspecialchars($lesson['video_url']); ?>"
                                     data-lesson-title="<?php echo htmlspecialchars($lesson['title']); ?>"
                                     data-lesson-id="<?php echo $lesson['id']; ?>"
                                     data-can-access="<?php echo $canAccess ? 'true' : 'false'; ?>">
                                    <div class="lesson-icon">
                                        <?php if (!$canAccess): ?>
                                            <i class="fas fa-lock"></i>
                                        <?php elseif ($isCompleted): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-play"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                                        <div class="lesson-meta">
                                            <span><?php echo $lesson['duration'] ?? '00:00'; ?></span>
                                            <?php if ($isPreview && !$isEnrolled): ?>
                                                <span class="badge badge-preview">Preview</span>
                                            <?php elseif ($isFreeLesson && !$isEnrolled): ?>
                                                <span class="badge badge-free">Free</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                            $lesson_counter++;
                            endforeach; ?>
                        </div>
                    </div>
                <?php 
                $section_counter++;
                endforeach; ?>
            </div>
        </div>

        <!-- Rest of the content remains the same -->
        <!-- Instructor, Course Details, Reviews, Action Buttons sections -->
        <?php if ($instructor): ?>
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user"></i>
                        Meet Your Instructor
                    </h3>
                </div>
                <div class="card-body">
                    <div class="instructor-section">
                        <img src="../<?php echo !empty('../'.$instructor['image_path']) && file_exists('../'.$instructor['image_path']) 
                                  ? htmlspecialchars($instructor['image_path']) 
                                  : 'assets/images/thumbs/default-instructor.png'; ?>" 
                             class="instructor-avatar">
                        <div class="instructor-info">
                            <h3><?php echo htmlspecialchars($instructor['name']); ?></h3>
                            <p><?php echo htmlspecialchars($instructor['role']); ?></p>
                            <div class="d-flex align-items-center gap-2">
                                <?php
                                $rating = round($instructor['rating'], 1);
                                $fullStars = floor($rating);
                                $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $fullStars) {
                                        echo '<i class="fas fa-star text-warning"></i>';
                                    } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                        echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-warning"></i>';
                                    }
                                }
                                ?>
                                <span><?php echo $rating; ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars(substr($instructor['bio'], 0, 150) . (strlen($instructor['bio']) > 150 ? '...' : ''))); ?></p>
                    <a href="instructor-details.php?id=<?php echo $instructor['id']; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-right"></i>
                        View Full Profile
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="content-card">
            <div class="card-body">
                <?php if ($isEnrolled): ?>
                    <a href="course-details.php?id=<?php echo $course_id; ?>" class="btn btn-success mb-2">
                        <i class="fas fa-play"></i>
                        Continue Learning
                    </a>
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE course_id = ?");
                    $stmt->execute([$course_id]);
                    $hasQuizzes = $stmt->fetchColumn() > 0;
                    if ($hasQuizzes): ?>
                        <a href="course-quiz.php?course_id=<?php echo $course_id; ?>" class="btn btn-info">
                            <i class="fas fa-question"></i>
                            Take Quiz
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="apply-admission.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        Enroll Now for ₹<?php echo $price; ?>
                    </a>
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
        $(document).ready(function() {
            // Store original video content
            const originalVideoContent = $('#videoPlayer').html();
            const lessons = <?php echo json_encode($allLessons); ?>;
            const isEnrolled = <?php echo $isEnrolled ? 'true' : 'false'; ?>;
            let currentLessonIndex = -1;
            let videoEnded = false;

            // Section toggle functionality
            $('.section-toggle').click(function() {
                const sectionId = $(this).data('section');
                const container = $(`#section-${sectionId}`);
                const icon = $(this).find('.toggle-icon');
                
                $(this).toggleClass('active');
                container.toggleClass('show');
                
                $('.section-toggle').not(this).removeClass('active');
                $('.lessons-container').not(container).removeClass('show');
            });

            // Function to generate video embed HTML
            function generateVideoEmbed(videoUrl, autoPlay = false) {
                videoUrl = videoUrl.trim();
                
                // Check if it's an iframe code (Adilo, etc.)
                if (videoUrl.includes('<iframe')) {
                    return videoUrl;
                }
                // Check if it's YouTube
                else if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
                    const videoId = videoUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1] || '';
                    if (videoId) {
                        return `<iframe src="https://www.youtube.com/embed/${videoId}?rel=0${autoPlay ? '&autoplay=1' : ''}&enablejsapi=1" 
                                frameborder="0" allowfullscreen
                                style="width:100%;height:100%;"></iframe>`;
                    }
                }
                // Check if it's Vimeo
                else if (videoUrl.includes('vimeo.com')) {
                    const videoId = videoUrl.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|)(\d+)(?:|\/\?)/)?.[1] || '';
                    if (videoId) {
                        return `<iframe src="https://player.vimeo.com/video/${videoId}${autoPlay ? '?autoplay=1' : ''}" 
                                frameborder="0" allowfullscreen
                                style="width:100%;height:100%;"></iframe>`;
                    }
                }
                // Check if it's a direct video file
                else if (videoUrl.match(/\.(mp4|webm|ogg|mov|avi)$/i)) {
                    const ext = videoUrl.split('.').pop().toLowerCase();
                    let mimeType = '';
                    switch (ext) {
                        case 'mp4': mimeType = 'video/mp4'; break;
                        case 'webm': mimeType = 'video/webm'; break;
                        case 'ogg': mimeType = 'video/ogg'; break;
                        case 'mov': mimeType = 'video/quicktime'; break;
                        case 'avi': mimeType = 'video/x-msvideo'; break;
                    }
                    if (mimeType) {
                        return `<video controls ${autoPlay ? 'autoplay' : ''} style="width:100%;height:100%;">
                                <source src="${videoUrl}" type="${mimeType}">
                                Your browser does not support the video tag.
                            </video>`;
                    }
                }
                // Default - try to embed as iframe
                else if (videoUrl.startsWith('http')) {
                    return `<iframe src="${videoUrl}" 
                            frameborder="0" allowfullscreen
                            style="width:100%;height:100%;"></iframe>`;
                }
                
                return '<div class="alert alert-warning">Unable to play this video format</div>';
            }

            // Function to play a lesson
            function playLesson(lesson, autoPlay = true) {
                if (!lesson.can_access) {
                    alert('Please enroll in the course to access this lesson');
                    window.location.href = 'apply-admission.php?course_id=<?php echo $course['id']; ?>';
                    return;
                }

                $('.lesson-item').removeClass('current-lesson');
                $(`.lesson-item[data-lesson-id="${lesson.id}"]`).addClass('current-lesson');

                const videoEmbed = generateVideoEmbed(lesson.video_url, autoPlay);
                $('#videoPlayer').html(videoEmbed);
                $('#currentLessonTitle').text(lesson.title);
                $('#videoControls').show();

                // Mark as completed if enrolled
                if (isEnrolled && !$(`.lesson-item[data-lesson-id="${lesson.id}"]`).hasClass('completed-lesson')) {
                    $.ajax({
                        url: 'update-progress.php',
                        method: 'POST',
                        data: {
                            user_id: <?php echo $_SESSION['user_id']; ?>,
                            course_id: <?php echo $course_id; ?>,
                            lesson_id: lesson.id,
                            action: 'complete'
                        },
                        success: function(response) {
                            $(`.lesson-item[data-lesson-id="${lesson.id}"]`).addClass('completed-lesson');
                            $(`.lesson-item[data-lesson-id="${lesson.id}"] .lesson-icon`).html('<i class="fas fa-check"></i>');
                            const progress = response.progress || 0;
                            $('.progress-bar-fill').css('width', progress + '%');
                            $('.badge.text-success').text(progress + '%');
                             
                            if (progress >= 100) {
                                // Reload to show certificate button or show specific message
                                // Ideally, we could just append the button, but reload is safer for now to ensure server state consistency
                                location.reload();
                            }
                        }
                    });
                }

                // Set up video end detection
                setTimeout(() => {
                    const videoElement = $('#videoPlayer video')[0];
                    const iframeElement = $('#videoPlayer iframe')[0];
                    
                    if (videoElement) {
                        videoElement.onended = function() {
                            videoEnded = true;
                            playNextLesson();
                        };
                    }
                    
                    // For iframes (YouTube, Vimeo, Adilo), we can't detect end automatically
                    // So we rely on the next button
                    if (iframeElement) {
                        videoEnded = false;
                    }
                }, 1000);

                $('html, body').animate({ scrollTop: 0 }, 300);
            }

            // Function to play the next accessible lesson
            function playNextLesson() {
                let nextIndex = currentLessonIndex + 1;
                while (nextIndex < lessons.length) {
                    if (lessons[nextIndex].can_access) {
                        currentLessonIndex = nextIndex;
                        playLesson(lessons[nextIndex], true);
                        break;
                    }
                    nextIndex++;
                }
                
                // If no more lessons, show message
                if (nextIndex >= lessons.length) {
                    $('#currentLessonTitle').text('Course Completed!');
                    $('#videoPlayer').html('<div class="locked-overlay" style="background: var(--success-gradient);"><div class="lock-icon" style="background: white; color: var(--success);"><i class="fas fa-trophy"></i></div><h6>Course Completed!</h6><p>You have finished all available lessons</p></div>');
                }
            }

            // Handle lesson clicks
            $('.lesson-item').click(function() {
                if ($(this).hasClass('locked-lesson')) {
                    alert('Please enroll in the course to access this lesson');
                    window.location.href = 'apply-admission.php?course_id=<?php echo $course['id']; ?>';
                    return;
                }
                
                const lessonId = $(this).data('lesson-id');
                currentLessonIndex = lessons.findIndex(lesson => lesson.id == lessonId);
                if (currentLessonIndex !== -1) {
                    playLesson(lessons[currentLessonIndex], true);
                }
            });

            // Next lesson button
            $('#nextLessonBtn').click(function() {
                playNextLesson();
            });

            // Auto-play first accessible lesson on page load
            for (let i = 0; i < lessons.length; i++) {
                if (lessons[i].can_access) {
                    currentLessonIndex = i;
                    // Don't auto-play on page load, just load the first accessible lesson
                    playLesson(lessons[i], false);
                    break;
                }
            }

            // Handle review sorting
            $('#reviewSort').change(function() {
                const sort = $(this).val();
                window.location.href = `course-details.php?id=<?php echo $course_id; ?>&sort=${sort}`;
            });

            // Auto-hide toast after 5 seconds
            <?php if (isset($toast_message)): ?>
                setTimeout(() => {
                    const toast = document.querySelector('.toast-container');
                    if (toast) toast.style.display = 'none';
                }, 5000);
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Function to generate video embed code
function generateVideoEmbed($video_url, $autoPlay = false) {
    $video_url = trim($video_url);
    
    // Check if it's an iframe code (Adilo, etc.)
    if (strpos($video_url, '<iframe') !== false) {
        return $video_url;
    }
    // Check if it's YouTube
    elseif (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
        $video_id = $matches[1] ?? '';
        if ($video_id) {
            return '<iframe src="https://www.youtube.com/embed/' . $video_id . '?rel=0' . ($autoPlay ? '&autoplay=1' : '') . '&enablejsapi=1" 
                    frameborder="0" allowfullscreen
                    style="width:100%;height:100%;"></iframe>';
        }
    }
    // Check if it's Vimeo
    elseif (strpos($video_url, 'vimeo.com') !== false) {
        preg_match('/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|)(\d+)(?:|\/\?)/', $video_url, $matches);
        $video_id = $matches[1] ?? '';
        if ($video_id) {
            return '<iframe src="https://player.vimeo.com/video/' . $video_id . ($autoPlay ? '?autoplay=1' : '') . '" 
                    frameborder="0" allowfullscreen
                    style="width:100%;height:100%;"></iframe>';
        }
    }
    // Check if it's a direct video file
    elseif (preg_match('/\.(mp4|webm|ogg|mov|avi)$/i', $video_url)) {
        $ext = pathinfo($video_url, PATHINFO_EXTENSION);
        $mime_type = '';
        switch (strtolower($ext)) {
            case 'mp4': $mime_type = 'video/mp4'; break;
            case 'webm': $mime_type = 'video/webm'; break;
            case 'ogg': $mime_type = 'video/ogg'; break;
            case 'mov': $mime_type = 'video/quicktime'; break;
            case 'avi': $mime_type = 'video/x-msvideo'; break;
        }
        if ($mime_type) {
            return '<video controls ' . ($autoPlay ? 'autoplay' : '') . ' style="width:100%;height:100%;">
                    <source src="' . htmlspecialchars($video_url) . '" type="' . $mime_type . '">
                    Your browser does not support the video tag.
                </video>';
        }
    }
    // Default - try to embed as iframe
    elseif (strpos($video_url, 'http') === 0) {
        return '<iframe src="' . htmlspecialchars($video_url) . '" 
                frameborder="0" allowfullscreen
                style="width:100%;height:100%;"></iframe>';
    }
    
    return '<div class="alert alert-warning">Unable to play this video format</div>';
}
?>