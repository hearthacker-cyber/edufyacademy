<?php session_start(); 
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Courses - Professional Learning App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <style>
    :root {
      --primary-color: #4e73df;
      --secondary-color: #f8f9fa;
      --text-dark: #333;
      --text-muted: #6c757d;
      --border-color: #e0e0e0;
      --success-color: #28a745;
      --warning-color: #ffc107;
      --danger-color: #dc3545;
      --info-color: #17a2b8;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f7fb;
      color: var(--text-dark);
    }
    a{
        text-decoration: none;
    }
    .course-header {
      background-color: white;
      padding: 1rem;
      border-bottom: 1px solid var(--border-color);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .search-bar {
      padding: 0.5rem 1rem;
      background-color: white;
    }
    
    .search-bar input {
      width: 100%;
      padding: 0.5rem 1rem;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      font-size: 0.9rem;
    }
    
    .filter-section {
      padding: 1rem;
      background-color: white;
      margin-bottom: 1rem;
      border-bottom: 1px solid var(--border-color);
    }
    
    .filter-select {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid var(--border-color);
      border-radius: 5px;
      font-size: 0.9rem;
    }
    
    .course-card {
      text-decoration: none;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 1rem;

      transition: transform 0.3s ease;
    }
    
    .course-card:hover {
      transform: translateY(-5px);
    }
    
    .course-thumbnail {
      width: 100%;
      height: 180px;
      object-fit: cover;
    }
    
    .course-content {
      padding: 1rem;
      background: white;
      margin-bottom: 10px;
      border-radius: 5px;
    }
    
    .course-title {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--text-dark);
    }
    
    .course-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
    
    .course-instructor {
      display: flex;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    
    .instructor-avatar {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      margin-right: 0.5rem;
    }
    
    .course-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 0.5rem;
      border-top: 1px solid var(--border-color);
      margin-top: 0.5rem;
    }
    
    .course-price {
      font-weight: 600;
    }
    
    .original-price {
      text-decoration: line-through;
      color: var(--text-muted);
      font-size: 0.8rem;
      margin-left: 0.5rem;
    }
    
    .discount-price {
      color: var(--danger-color);
    }
    
    .btn-enroll {
      background-color: var(--primary-color);
      color: white;
      border: none;
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
    }
    
    .btn-continue {
      background-color: var(--success-color);
      color: white;
      border: none;
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
    }
    
    .badge-level {
      position: absolute;
      bottom: 10px;
      right: 10px;
      font-size: 0.7rem;
      padding: 0.2rem 0.5rem;
    }
    
    .badge-beginner {
      background-color: var(--success-color);
    }
    
    .badge-intermediate {
      background-color: var(--warning-color);
      color: var(--text-dark);
    }
    
    .badge-advanced {
      background-color: var(--danger-color);
    }
    
    .badge-certificate {
      background-color: var(--info-color);
      color: white;
      font-size: 0.7rem;
      padding: 0.2rem 0.5rem;
      margin-bottom: 0.5rem;
      display: inline-block;
    }
    
    .rating {
      display: flex;
      align-items: center;
      font-size: 0.8rem;
    }
    
    .stars {
      color: var(--warning-color);
      margin-right: 0.3rem;
    }
    
    .no-courses {
      text-align: center;
      padding: 2rem;
      background-color: white;
      border-radius: 10px;
      margin: 1rem;
    }
    
    .thumbnail-container {
      position: relative;
     
    }
    .thumbnail-container img{
        border-radius:5px 5px 0px 0px;
    }
  </style>
</head>
<body>
    <?php include('includes/nav.php');?>
  <!-- Offcanvas Menu -->
  <?php include('includes/menu.php');?>


  <div class="container mb-3">
    <h4 class="my-3">Select Course</h4>
  </div>

<?php
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active'");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected category ID from URL if present
$selectedCategoryId = isset($_GET['category_id']) ? $_GET['category_id'] : null;
?>
<style>
  :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --bg-card: #ffffff;
    --text-primary: #1e293b;
    --border-light: #e2e8f0;
  }

  .categories-scroll-container {
    overflow-x: auto;
    white-space: nowrap;
    padding: 0.5rem 0;
    margin-bottom: 1rem;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .categories-scroll-container::-webkit-scrollbar {
    display: none;
  }

  .category-item {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.9rem;
    margin-right: 0.5rem;
    transition: all 0.3s ease;
  }

  .category-item.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
  }
</style>

<div class="container categories-scroll-container px-3">
  <div style="display: inline-flex; gap: 0.5rem;">
    <!-- All Category -->
    <a href="courses.php" class="category-item <?php echo is_null($selectedCategoryId) ? 'active' : ''; ?>">
      <i class="fas fa-th-large me-1"></i> All
    </a>

    <?php foreach ($categories as $category): ?>
      <a href="courses.php?category_id=<?= $category['id'] ?>" class="category-item <?php echo $selectedCategoryId == $category['id'] ? 'active' : ''; ?>">
        <?php if (!empty($category['icon'])): ?>
          <i class="<?= htmlspecialchars($category['icon']) ?> me-1"></i>
        <?php endif; ?>
        <?= htmlspecialchars($category['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<!-- Course List -->
<div class="container">
  <?php
  try {
    // Build the base query
    $query = "SELECT c.* FROM courses c";
    $params = [];

    // Add category filter if selected
    if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
      $query .= " WHERE c.category_id = :category_id";
      $params[':category_id'] = $_GET['category_id'];
    }

    // Add order by newest
    $query .= " ORDER BY c.created_at DESC";

    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalCourses = count($courses);

    // Get user's enrolled courses if logged in
    $enrolledCourses = [];
    if (isset($_SESSION['user_id'])) {
      $stmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      $enrolledCourses = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    if ($totalCourses > 0): 
      foreach ($courses as $course):
        // For each course, get the total lessons count
        $stmt = $pdo->prepare("
          SELECT COUNT(*) as total_lessons 
          FROM course_lessons 
          WHERE course_id = :course_id
        ");
        $stmt->execute([':course_id' => $course['id']]);
        $lessonsData = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalLessons = $lessonsData['total_lessons'];

        // Get average rating and total reviews
        $stmt = $pdo->prepare("
          SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
          FROM reviews 
          WHERE course_id = :course_id
        ");
        $stmt->execute([':course_id' => $course['id']]);
        $reviewData = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgRating = number_format($reviewData['avg_rating'] ?? 0, 1);
        $totalReviews = $reviewData['total_reviews'] ?? 0;

        // Check if course is already purchased
        $isEnrolled = in_array($course['id'], $enrolledCourses);
  ?>
        <a href="course-details.php?id=<?= $course['id'] ?>" class="course-card bg-white" style="border:1px soild red;">
          <div class="thumbnail-container">
            <img src="../admin/<?= $course['image'] ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="course-thumbnail">
            <span class="badge badge-level <?= 'badge-' . $course['level'] ?>">
              <?= ucfirst($course['level']) ?>
            </span>
          </div>
          
          <div class="course-content">
            <?php if ($course['certificate'] === 'yes'): ?>
              <span class="badge-certificate">
                <i class="fas fa-certificate me-1"></i> Certificate
              </span>
            <?php endif; ?>
            
            <h5 class="course-title"><?= htmlspecialchars($course['title']) ?></h5>
            
            <div class="course-meta">
              <span><i class="fas fa-video me-1"></i> <?= $totalLessons ?> Lessons</span>
              <div class="rating">
                <div class="stars">
                  <?= str_repeat('<i class="fas fa-star"></i>', floor($avgRating)) ?>
                  <?= ($avgRating - floor($avgRating)) >= 0.5 ? '<i class="fas fa-star-half-alt"></i>' : '' ?>
                </div>
                <span>(<?= $totalReviews ?>)</span>
              </div>
            </div>
            
            <div class="course-instructor">
              <?php
              // Fetch instructor details
              $instructor_stmt = $pdo->prepare("SELECT name, image_path FROM instructor WHERE id = ?");
              $instructor_stmt->execute([$course['instructor_id']]);
              $instructor = $instructor_stmt->fetch(PDO::FETCH_ASSOC);
              ?>
              <img src="../<?= !empty($instructor['image_path']) ? $instructor['image_path'] : 'assets/images/default-avatar.jpg' ?>" 
                   alt="Instructor" class="instructor-avatar">
              <span class="text-muted small"><?= $instructor['name'] ?? 'Admin' ?></span>
            </div>
            
            <div class="course-footer">
              <div class="course-price">
                <?php if ($course['discount_price']): ?>
                  <span class="discount-price">₹<?= number_format($course['discount_price'], 2) ?></span>
                  <span class="original-price">₹<?= number_format($course['price'], 2) ?></span>
                <?php else: ?>
                  <span>₹<?= number_format($course['price'], 2) ?></span>
                <?php endif; ?>
              </div>
              
              <?php if ($isEnrolled): ?>
                <a href="course-details.php?id=<?= $course['id'] ?>" class="btn-continue">
                  Continue <i class="fas fa-arrow-right ms-1"></i>
                </a>
              <?php else: ?>
                <a href="apply-admission.php?course_id=<?= $course['id'] ?>" class="btn-enroll">
                  Enroll Now <i class="fas fa-shopping-cart ms-1"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-courses">
        <i class="fas fa-book-open fa-3x mb-3 text-muted"></i>
        <h5>No Courses Available</h5>
        <p class="text-muted">There are currently no courses matching your criteria.</p>
        <?php if (isset($_GET['category_id'])): ?>
          <a href="course.php" class="btn btn-primary">
            View All Courses
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php } catch (PDOException $e) {
    echo "<div class='alert alert-danger m-3'>Error loading courses: " . $e->getMessage() . "</div>";
  }
  ?>
</div>
<?php include('includes/footer.php');?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Add event listener for category filter
  document.getElementById('categoryFilter').addEventListener('change', function() {
    const categoryId = this.value;
    if (categoryId === '0') {
      window.location.href = 'courses.php';
    } else {
      window.location.href = 'courses.php?category_id=' + categoryId;
    }
  });
</script>

</body>
</html>