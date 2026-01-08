<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Course - Python for Data Science</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
</head>
<body>
  
  <!-- Navbar -->
  <?php include('includes/nav.php');?>

  <!-- Offcanvas Menu -->
  <?php include('includes/menu.php');?>

  <div class="container my-3">
    <!-- Course Header -->
    <div class="course-header shadow-sm p-3 rounded bg-light">
      <div class="row">
        <div class="col-md-4">
          <div class="course-image-container">
            <img src="https://360hcskills.com/wp-content/uploads/2021/08/HEL98KU.jpeg" alt="Course Image" class="img-fluid rounded">
          </div>
        </div>
        <div class="col-md-8 p-2">
          <h1 class="course-title text-primary">Python for Data Science</h1>
          <p class="course-description">Learn Python programming from scratch and apply it to real-world data science problems. Get hands-on experience with popular libraries such as Pandas, NumPy, and Matplotlib.</p>
          <p class="course-meta">
            <i class="fa fa-star text-warning"></i> <span class="rating">4.8</span> (120 Reviews) | 
            <span>Duration: 12 hours</span> | 
            <span>Level: Beginner to Advanced</span>
          </p>
          
          <div class="rating-system">
            <h5 class="mt-3">Rate this Course:</h5>
            <div id="course-rating" class="star-rating">
              <i class="fa fa-star" data-value="1"></i>
              <i class="fa fa-star" data-value="2"></i>
              <i class="fa fa-star" data-value="3"></i>
              <i class="fa fa-star" data-value="4"></i>
              <i class="fa fa-star" data-value="5"></i>
            </div>
          </div>
          
          <div class="course-progress mt-3">
            <h5>Course Progress</h5>
            <div class="progress">
              <div class="progress-bar" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span>60% Completed</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs for Course Overview, Syllabus & Reviews -->
    <ul class="nav nav-pills mt-5" id="course-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link active" id="overview-tab" data-bs-toggle="pill" href="#overview" role="tab" aria-controls="overview" aria-selected="true">Overview</a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" id="syllabus-tab" data-bs-toggle="pill" href="#syllabus" role="tab" aria-controls="syllabus" aria-selected="false">Syllabus</a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link" id="reviews-tab" data-bs-toggle="pill" href="#reviews" role="tab" aria-controls="reviews" aria-selected="false">Reviews</a>
      </li>
    </ul>
    <div class="tab-content mt-3" id="course-tabs-content">
      <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
        <p>Python for Data Science introduces programming with Python and its applications in data science. Learn essential skills for data analysis, visualization, and building machine learning models.</p>
      </div>
      <div class="tab-pane fade" id="syllabus" role="tabpanel" aria-labelledby="syllabus-tab">
        <ul class="list-group">
          <li class="list-group-item"><i class="fa fa-check-circle text-success"></i> Introduction to Python</li>
          <li class="list-group-item"><i class="fa fa-check-circle text-success"></i> Working with Data Structures</li>
          <li class="list-group-item"><i class="fa fa-check-circle text-success"></i> Data Visualization with Matplotlib</li>
          <li class="list-group-item"><i class="fa fa-check-circle text-success"></i> Data Cleaning and Transformation</li>
          <li class="list-group-item"><i class="fa fa-check-circle text-success"></i> Machine Learning Basics</li>
        </ul>
      </div>
      <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
        <p>Coming Soon!</p>
      </div>
    </div>

    <!-- Instructor Information -->
    <div class="instructor-info mt-5">
      <h5 class="section-title">Instructor</h5>
      <div class="d-flex align-items-center">
        <img src="https://www.svgrepo.com/show/213788/avatar-user.svg" alt="Instructor" class="rounded-circle" style="width: 70px; height: 70px;">
        <div class="ms-3">
          <h6 class="instructor-name">John Doe</h6>
          <p class="instructor-description">John is a Data Scientist with over 10 years of experience in machine learning and data analytics. He has worked with top tech companies and now teaches data science to aspiring professionals.</p>
        </div>
      </div>
    </div>

    <!-- Enrollment Button with Modal -->
    <div class="enroll-btn mt-2">
      <button class="btn btn-primary w-100 py-2 shadow" data-bs-toggle="modal" data-bs-target="#enrollmentModal">Enroll Now</button>
    </div>

    <!-- Enrollment Confirmation Modal -->
    <div class="modal fade" id="enrollmentModal" tabindex="-1" aria-labelledby="enrollmentModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="enrollmentModalLabel">Enrollment Confirmation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to enroll in this course?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary">Confirm Enrollment</button>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Footer -->
  <?php include('includes/footer.php');?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Star Rating System
    const stars = document.querySelectorAll('.star-rating i');
    stars.forEach(star => {
      star.addEventListener('click', () => {
        const rating = star.getAttribute('data-value');
        alert(`You rated this course ${rating} stars.`);
      });
    });
  </script>
</body>
</html>
