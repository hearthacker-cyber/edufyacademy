<?php
include "includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: sign-in.php");
    exit();
}

try {
    // Get user details
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: signin.php");
        exit();
    }

    // Get enrolled courses with progress information (limited to 3 for mobile view)
    $enrollments_stmt = $pdo->prepare("
        SELECT e.*, c.title, c.description, c.image, c.level, 
               (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) AS total_lessons,
               (SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND course_id = c.id) AS completed_lessons,
               (SELECT AVG(rating) FROM reviews WHERE course_id = c.id) AS avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE course_id = c.id) AS review_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrollment_date DESC
        LIMIT 3
    ");
    $enrollments_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $enrollments = $enrollments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recommended courses (not already enrolled)
    $recommended_stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT AVG(rating) FROM reviews WHERE course_id = c.id) AS avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE course_id = c.id) AS review_count
        FROM courses c
        WHERE c.id NOT IN (
            SELECT course_id FROM enrollments WHERE user_id = ?
        )
        ORDER BY 
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) DESC,
            avg_rating DESC
        LIMIT 3
    ");
    $recommended_stmt->execute([$_SESSION['user_id']]);
    $recommended_courses = $recommended_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<?php if (!empty($enrollments)): ?>
<div class="mobile-courses-section py-1">
    <h5 class="section-title">Enrolled Courses</h5>
    <div class="scroll-container">
        <?php foreach ($enrollments as $enrollment):
            $progress = $enrollment['total_lessons'] > 0
                ? round(($enrollment['completed_lessons'] / $enrollment['total_lessons']) * 100)
                : 0;
            $avg_rating = $enrollment['avg_rating'] ? round($enrollment['avg_rating'], 1) : 0;
            $review_count = $enrollment['review_count'] ? $enrollment['review_count'] : 0;
        ?>
            <a href="course-details.php?id=<?= htmlspecialchars($enrollment['id']) ?>" class="enrolled-card" style="text-decoration:none;">
                <img src="../admin/<?= htmlspecialchars($enrollment['image']) ?>" alt="<?= htmlspecialchars($enrollment['title']) ?>">
                <div class="card-body">
                    <h6><?= htmlspecialchars($enrollment['title']) ?></h6>
                    <p class="mb-0">
                        <i class="fa fa-star text-warning"></i> 
                        <?= $avg_rating ?> (<?= $review_count ?> Reviews)
                    </p>
                    <div class="progress mt-2">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?= $progress ?>%; background:#6B4EF9;" 
                             aria-valuenow="<?= $progress ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="text-center mt-3">
        <a href="my-courses.php" class="btn btn-outline-primary btn-sm">View All Courses</a>
    </div>
</div>
<?php endif; ?>

<!-- Recommended Courses Section -->
<h5 class="section-title mt-4">Recommended for You</h5>
<div class="scroll-container">
    <?php if (empty($recommended_courses)): ?>
        <div class="text-center py-4">
            <p>No recommendations available at this time</p>
        </div>
    <?php else: ?>
        <?php foreach ($recommended_courses as $course):
            $avg_rating = $course['avg_rating'] ? round($course['avg_rating'], 1) : 0;
            $review_count = $course['review_count'] ? $course['review_count'] : 0;
        ?>
            <a href="course-details.php?id=<?= htmlspecialchars($course['id']) ?>" class="recommended-card" style="text-decoration:none;">
                <img src="../admin/<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                <div class="card-body">
                    <h6><?= htmlspecialchars($course['title']) ?></h6>
                    <p class="mb-0">
                        <i class="fa fa-star text-warning"></i> 
                        <?= $avg_rating ?> (<?= $review_count ?> Reviews)
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.scroll-container {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 10px;
    scrollbar-width: none !important; /* Firefox */
    -ms-overflow-style: none;  /* Internet Explorer 10+ */
}

.scroll-container::-webkit-scrollbar {
    display: none !important; /* Chrome, Safari, Opera */
}


.enrolled-card, .recommended-card {
    min-width: 220px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: white;
}

.enrolled-card img, .recommended-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.enrolled-card .card-body, .recommended-card .card-body {
    padding: 12px;
}

.enrolled-card h6, .recommended-card h6 {
    font-size: 14px;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.enrolled-card p, .recommended-card p {
    font-size: 12px;
}

.progress {
    height: 5px;
    background-color: #f0f0f0;
    border-radius: 3px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}
</style>