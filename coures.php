<?php
// Ensure config/db.php provides a valid PDO instance
require_once "includes/db.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get top 6 popular courses (ordered by enrollment count and rating)
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS enrollment_count,
              (SELECT AVG(rating) FROM reviews WHERE course_id = c.id) AS avg_rating,
              (SELECT COUNT(*) FROM reviews WHERE course_id = c.id) AS review_count
              FROM courses c
              
              ORDER BY enrollment_count DESC, avg_rating DESC
              LIMIT 6";
    
    $stmt = $pdo->query($query);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's enrolled courses if logged in
    $enrolledCourses = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $enrolledCourses = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
} catch (PDOException $e) {
    echo "<p style='color: red; text-align: center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $courses = [];
}
?>

<!-- Mobile Courses Section -->
<div class="mobile-courses-section py-4">
    <h5 class="section-title">Popular Courses</h5>
    
    <div class="px-3">
        <?php if (empty($courses)): ?>
            <div class="text-center py-4">
                <p>No courses available at this time</p>
                <a href="course.php" class="btn btn-primary btn-sm">Browse Courses</a>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): 
                $isEnrolled = in_array($course['id'], $enrolledCourses);
                $avgRating = number_format($course['avg_rating'] ?? 0, 1);
                $reviewCount = $course['review_count'] ?? 0;
                $price = $course['discount_price'] > 0 ? $course['discount_price'] : $course['price'];
            ?>
                <a href="course-details.php?id=<?= $course['id'] ?>" class="course-item" style="text-decoration:none;">
                    <img src="../admin/<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                    <div>
                        <h6><?= htmlspecialchars($course['title']) ?></h6>
                        <p class="mb-0 text-sm">
                            <i class="fa fa-star text-warning"></i>
                            <?= $avgRating ?> (<?= $reviewCount ?> Reviews)
                        </p>
                        <p class="text-sm muted-text mb-0">
                            <?= htmlspecialchars(substr($course['short_description'], 0, 60)) ?><?= strlen($course['short_description']) > 60 ? '...' : '' ?>
                        </p>
                        <?php if ($isEnrolled): ?>
                            <span class="badge bg-success mt-2">Enrolled</span>
                        <?php else: ?>
                            <span class="text-main-600 fw-bold mt-2">₹<?= number_format($price, 2) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($courses)): ?>
        <div class="text-center mt-3">
            <a href="courses.php" class="btn btn-outline-primary btn-sm">View All Courses</a>
        </div>
    <?php endif; ?>
</div>

<style>
.mobile-courses-section {
    margin-bottom: 20px;
}

.course-item {
    display: flex;
    gap: 15px;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.course-item img {
    width: 100px;
    height: 80px;
    border-radius: 6px;
    object-fit: cover;
}

.course-item h6 {
    font-size: 14px;
    margin-bottom: 5px;
    color: #333;
}

.course-item .text-sm {
    font-size: 12px;
}

.course-item .muted-text {
    color: #666;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-left: 15px;
}

.badge {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
}
</style>