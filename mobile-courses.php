<?php
session_start();
include "../config/db.php";

// Fetch courses
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE status = 'active' ORDER BY created_at DESC");
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching courses: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - Edufy Mobile</title>
    <style>
        /* Add mobile-friendly styles */
    </style>
</head>
<body>
    <!-- Mobile course listing -->
    <?php foreach ($courses as $course): ?>
        <div class="course-card">
            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
            <p>₹<?php echo number_format($course['price'], 2); ?></p>
            <a href="mobile-enroll.php?course_id=<?php echo $course['id']; ?>">
                Enroll Now
            </a>
        </div>
    <?php endforeach; ?>
</body>
</html>