<?php 
session_start(); 
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's enrolled courses with progress
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.image, 
            c.level, 
           
            e.enrollment_date,
            (SELECT COUNT(*) FROM course_lessons WHERE course_id = c.id) AS total_lessons,
            (SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND course_id = c.id) AS completed_lessons,
            (SELECT AVG(rating) FROM reviews WHERE course_id = c.id) AS avg_rating
        FROM 
            enrollments e
        JOIN 
            courses c ON e.course_id = c.id
        WHERE 
            e.user_id = ?
        ORDER BY 
            e.enrollment_date DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get recommended courses (excluding already enrolled)
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.title, 
            c.image, 
            c.level, 
            
            (SELECT AVG(rating) FROM reviews WHERE course_id = c.id) AS avg_rating,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS enrollment_count
        FROM 
            courses c
        WHERE 
            c.id NOT IN (SELECT course_id FROM enrollments WHERE user_id = ?)
        ORDER BY 
            enrollment_count DESC
        LIMIT 4
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recommended_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Courses | Professional Learning App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <style>
    .course-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      height: 100%;
    }
    .course-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .course-img {
      height: 160px;
      object-fit: cover;
      width: 100%;
    }
    .progress {
      height: 6px;
      background-color: #f0f0f0;
    }
    .progress-bar {
      background-color: #6B4EF9;
    }
    .badge-level {
      background-color: #e9f7fe;
      color: #0d6efd;
    }
    .badge-rating {
      background-color: #fff8e1;
      color: #ffc107;
    }
    .empty-state {
      text-align: center;
      padding: 3rem;
      background-color: #f8f9fa;
      border-radius: 12px;
    }
    .empty-icon {
      font-size: 3rem;
      color: #6B4EF9;
      margin-bottom: 1rem;
    }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    .view-all {
      color: #6B4EF9;
      font-weight: 500;
      text-decoration: none;
    }
    .view-all:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  
<?php include('includes/nav.php');?>
<!-- Offcanvas Menu -->
<?php include('includes/menu.php');?>

<div class="container py-4">
  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">My Courses</h2>
    <div>
      <a href="courses.php" class="btn btn-outline-primary">Browse Courses</a>
    </div>
  </div>

  <!-- Enrolled Courses Section -->
  <div class="mb-5">
    <div class="section-header">
      <h4>Your Learning Journey</h4>
    </div>
    
    <?php if (empty($enrolled_courses)): ?>
      <div class="empty-state">
        <div class="empty-icon">
          <i class="fas fa-book-open"></i>
        </div>
        <h4>No courses enrolled yet</h4>
        <p class="text-muted mb-4">Start your learning journey by enrolling in courses that match your interests</p>
        <a href="courses.php" class="btn btn-primary">Browse Courses</a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($enrolled_courses as $course): 
          $progress = $course['total_lessons'] > 0 ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
        ?>
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="course-card">
              <a href="course-details.php?id=<?= $course['id'] ?>">
                <img src="../admin/<?= htmlspecialchars($course['image']) ?>" class="course-img" alt="<?= htmlspecialchars($course['title']) ?>">
              </a>
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge badge-level text-capitalize">
                    <?= htmlspecialchars($course['level']) ?>
                </span>

                  <?php if ($course['avg_rating']): ?>
                    <span class="badge badge-rating">
                      <i class="fas fa-star"></i> <?= number_format($course['avg_rating'], 1) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <h5 class="mb-2">
                  <a href="course-details.php?id=<?= $course['id'] ?>" class="text-dark text-decoration-none">
                    <?= htmlspecialchars($course['title']) ?>
                  </a>
                </h5>
                <div class="d-flex justify-content-between text-muted small mb-3">
                  
                  <span>Enrolled: <?= date('M j, Y', strtotime($course['enrollment_date'])) ?></span>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between small mb-1">
                    <span>Progress</span>
                    <span><?= $progress ?>%</span>
                  </div>
                  <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: <?= $progress ?>%"></div>
                  </div>
                </div>
                <a href="course-details.php?id=<?= $course['id'] ?>" class="btn btn-primary w-100 mt-2">
                  <?= $progress > 0 ? 'Continue Learning' : 'Start Learning' ?>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recommended Courses Section -->
  <?php if (!empty($recommended_courses)): ?>
    <div class="mb-5">
      <div class="section-header">
        <h4>Recommended For You</h4>
        <a href="courses.php" class="view-all">View All</a>
      </div>
      <div class="row g-4">
        <?php foreach ($recommended_courses as $course): ?>
          <div class="col-md-6 col-lg-4 col-xl-3">
            <div class="course-card">
              <a href="course-details.php?id=<?= $course['id'] ?>">
                <img src="../admin/<?= htmlspecialchars($course['image']) ?>" class="course-img" alt="<?= htmlspecialchars($course['title']) ?>">
              </a>
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge badge-level text-capitalize"><?= htmlspecialchars($course['level']) ?></span>
                  <?php if ($course['avg_rating']): ?>
                    <span class="badge badge-rating">
                      <i class="fas fa-star"></i> <?= number_format($course['avg_rating'], 1) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <h5 class="mb-2">
                  <a href="course-details.php?id=<?= $course['id'] ?>" class="text-dark text-decoration-none">
                    <?= htmlspecialchars($course['title']) ?>
                  </a>
                </h5>
                
                <a href="course-details.php?id=<?= $course['id'] ?>" class="btn btn-outline-primary w-100">
                  Explore Course
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include('includes/footer.php');?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>