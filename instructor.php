<?php
// Ensure config/db.php provides a valid PDO instance
require_once "includes/db.php";

// Query to get top instructors for mobile (limit to 3)
try {
    $sql = "SELECT i.id, i.name, i.role, i.image_path, i.rating, i.learners, 
            COUNT(c.id) as course_count
            FROM instructor i
            LEFT JOIN courses c ON i.id = c.instructor_id
            WHERE i.is_active = 1
            GROUP BY i.id
            ORDER BY rating DESC, learners DESC
            LIMIT 3";
    $stmt = $pdo->query($sql);
    $instructors = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<p style='color: red; text-align: center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $instructors = [];
}
?>

<!-- Mobile Instructors Section -->
<div class="mobile-instructors-section py-1">
    <h5 class="section-title">Top Instructors</h5>
    <div class="scroll-container">
        <?php if (empty($instructors)): ?>
            <div class="text-center py-4">
                <p>No instructors available</p>
            </div>
        <?php else: ?>
            <?php foreach ($instructors as $instructor): 
                // Validate image path with fallback
                $image_path = !empty('../'.$instructor['image_path']) && file_exists('../'.$instructor['image_path'])
                    ? '../'.htmlspecialchars($instructor['image_path'], ENT_QUOTES, 'UTF-8')
                    : 'assets/images/thumbs/default-instructor.png';
            ?>
                <a href="instructor-details.php?id=<?= $instructor['id'] ?>" class="instructor-card" style="text-decoration:none;">
                    <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($instructor['name']) ?>">
                    <div class="card-body">
                        <h6><?= htmlspecialchars($instructor['name']) ?></h6>
                        <p><?= htmlspecialchars($instructor['role']) ?></p>
                        <div class="instructor-meta">
                            <span class="rating">
                                <i class="fa fa-star text-warning"></i> 
                                <?= number_format($instructor['rating'], 1) ?>
                            </span>
                            <span class="students">
                                <i class="fa fa-users"></i> 
                                <?= number_format($instructor['learners']) ?>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($instructors)): ?>
        <!--<div class="text-center mt-3">-->
        <!--    <a href="instructors.php" class="btn btn-outline-primary btn-sm">View All Instructors</a>-->
        <!--</div>-->
    <?php endif; ?>
</div>

<style>
.mobile-instructors-section {
    margin-bottom: 20px;
}

.scroll-container {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 10px;
    scrollbar-width: thin;
}

.instructor-card {
    min-width: 180px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: white;
}

.instructor-card img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.instructor-card .card-body {
    padding: 12px;
}

.instructor-card h6 {
    font-size: 14px;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.instructor-card p {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}

.instructor-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #555;
}

.instructor-meta .rating {
    color: #ffc107;
}

.instructor-meta .students {
    color: #6c757d;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}
</style>