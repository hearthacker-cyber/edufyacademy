<?php
require_once "includes/db.php";
$page_title = "Instructor Details";

// Validate and sanitize instructor ID from URL
$instructor_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$instructor_id) {
    die("<div class='alert alert-danger text-center'>Invalid instructor ID.</div>");
}

try {
    // Get instructor details
    $sql = "SELECT i.id, i.name, i.email, i.role, i.bio, i.expertise, i.image_path, i.rating, i.learners, i.experience
            FROM instructor i
            WHERE i.id = :id AND i.is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $instructor_id]);
    $instructor = $stmt->fetch();

    // Get instructor's courses (limit to 4 for mobile)
    $sql_courses = "SELECT c.id, c.title, c.price, c.level, c.image, c.certificate
                    FROM courses c
                    WHERE c.instructor_id = :id
                    LIMIT 4";
    $stmt_courses = $pdo->prepare($sql_courses);
    $stmt_courses->execute(['id' => $instructor_id]);
    $courses = $stmt_courses->fetchAll();

    if (!$instructor) {
        die("<div class='alert alert-warning text-center'>Instructor not found or inactive.</div>");
    }
} catch (PDOException $e) {
    die("<div class='alert alert-danger text-center'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
      <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Instructor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  
    <style>
        .mobile-instructor-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 0;
        }
        .mobile-instructor-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        .mobile-expertise-badge {
            background-color: #f1f8ff;
            color: #0366d6;
            font-size: 12px;
            padding: 4px 8px;
            margin: 2px;
        }
        .mobile-course-card {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        .mobile-course-card img {
            height: 120px;
            object-fit: cover;
        }
        .mobile-level-beginner { background-color: #e6ffed; color: #22863a; }
        .mobile-level-intermediate { background-color: #fff5b1; color: #735c0f; }
        .mobile-level-advanced { background-color: #ffdce0; color: #b31d28; }
        .mobile-stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .mobile-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 20px 0 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
<?php include('includes/nav.php');?>
  <!-- Offcanvas Menu -->
  <?php include('includes/menu.php');?>

    <div class="container-fluid px-0">
        <!-- Mobile Instructor Header -->
        <div class="mobile-instructor-header text-center">
            <img src="<?php echo !empty('../'.$instructor['image_path']) && file_exists('../'.$instructor['image_path']) 
                ? htmlspecialchars('../'.$instructor['image_path'], ENT_QUOTES, 'UTF-8') 
                : 'assets/images/thumbs/default-instructor.png'; ?>" 
                alt="<?php echo htmlspecialchars($instructor['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                class="mobile-instructor-img rounded-circle mb-3">
            
            <h4 class="mb-1"><?php echo htmlspecialchars($instructor['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p class="text-muted mb-3"><?php echo htmlspecialchars($instructor['role'], ENT_QUOTES, 'UTF-8'); ?></p>
            
            <div class="d-flex justify-content-center flex-wrap mb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary mx-1 mb-1">
                    <i class="fas fa-star text-warning me-1"></i>
                    <?php echo number_format($instructor['rating'], 1); ?> Rating
                </span>
                <span class="badge bg-primary bg-opacity-10 text-primary mx-1 mb-1">
                    <i class="fas fa-users me-1"></i>
                    <?php echo number_format($instructor['learners']); ?> Students
                </span>
                <span class="badge bg-primary bg-opacity-10 text-primary mx-1 mb-1">
                    <i class="fas fa-briefcase me-1"></i>
                    <?php echo $instructor['experience']; ?> Years Exp
                </span>
            </div>
            
            <div class="d-flex flex-wrap justify-content-center px-3">
                <?php 
                $expertiseItems = explode(',', $instructor['expertise']);
                foreach ($expertiseItems as $item):
                    $item = trim($item);
                    if (!empty($item)):
                ?>
                    <span class="mobile-expertise-badge rounded-pill"><?php echo htmlspecialchars($item); ?></span>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Mobile Content -->
        <div class="container py-3">
            <!-- Stats Card -->
            <div class="mobile-stat-card">
                <h5 class="d-flex align-items-center">
                    <i class="fas fa-chalkboard-teacher me-2"></i> Teaching Stats
                </h5>
                <div class="row text-center mt-3">
                    <div class="col-4">
                        <div class="text-primary fw-bold"><?php echo count($courses); ?></div>
                        <div class="text-muted small">Courses</div>
                    </div>
                    <div class="col-4">
                        <div class="text-primary fw-bold"><?php echo number_format($instructor['learners']); ?></div>
                        <div class="text-muted small">Students</div>
                    </div>
                    <div class="col-4">
                        <div class="text-primary fw-bold"><?php echo $instructor['experience']; ?></div>
                        <div class="text-muted small">Years Exp</div>
                    </div>
                </div>
            </div>

            <!-- About Section -->
            <h5 class="mobile-section-title">
                <i class="fas fa-user-graduate me-2"></i> About
            </h5>
            <p class="mb-4"><?php echo htmlspecialchars($instructor['bio'], ENT_QUOTES, 'UTF-8'); ?></p>

            <!-- Contact Section -->
            <h5 class="mobile-section-title">
                <i class="fas fa-at me-2"></i> Contact
            </h5>
            <p class="mb-4">
                <i class="fas fa-envelope me-2 text-primary"></i>
                <?php echo htmlspecialchars($instructor['email'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <!-- Courses Section -->
            <h5 class="mobile-section-title">
                <i class="fas fa-book me-2"></i> Courses
            </h5>
            
            <?php if (!empty($courses)): ?>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-12 mb-3">
                            <a href="course-details.php?id=<?php echo htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                               class="text-decoration-none">
                                <div class="mobile-course-card bg-white">
                                    <div class="row g-0">
                                        <div class="col-4">
                                            <img src="<?php echo !empty('../admin/'.$course['image']) && file_exists('../admin/'.$course['image']) 
                                                ? htmlspecialchars('../admin/'.$course['image'], ENT_QUOTES, 'UTF-8') 
                                                : 'assets/images/thumbs/default-course.png'; ?>" 
                                                class="w-100 h-100"
                                                alt="<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="col-8 p-3">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge mobile-level-<?php echo htmlspecialchars($course['level'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($course['level'], ENT_QUOTES, 'UTF-8')); ?>
                                                </span>
                                                <span class="text-success fw-bold">₹<?php echo number_format($course['price'], 2); ?></span>
                                            </div>
                                            <?php if ($course['certificate'] === 'yes'): ?>
                                                <span class="badge bg-light text-dark small">
                                                    <i class="fas fa-certificate me-1"></i> Certificate
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($courses) >= 4): ?>
                        <div class="col-12 text-center mt-2">
                            <a href="instructor-courses.php?id=<?php echo $instructor_id; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                View All Courses
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-book-open fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No courses available from this instructor</p>
                    <a href="courses.php" class="btn btn-primary btn-sm">Browse Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

   <?php include('includes/footer.php');?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>