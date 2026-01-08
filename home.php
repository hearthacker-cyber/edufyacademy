<?php session_start(); 
require_once 'includes/auto_login.php';
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Professional Learning App</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css"/>
  <link href="style.css" rel="stylesheet"/>
  
  <style>
    :root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --bg-card: #ffffff;
    --text-primary: #1e293b;
    --border-light: #e2e8f0;
  }
    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #333;
      padding-left: 0px !important
      ;
    }

    .categories-scroll-container {
      width: 100%;
      overflow-x: auto;
      padding-bottom: 10px;
      margin-bottom: 1.5rem;
    }

    .categories-scroll {
      display: inline-flex;
      gap: 0.8rem;
      padding: 0 5px 5px 0;
      white-space: nowrap;
    }


    /* Custom scrollbar styling */
    .categories-scroll-container::-webkit-scrollbar {
      height: 6px;
    }

    .categories-scroll-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 3px;
    }

    .categories-scroll-container::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 3px;
    }

    .categories-scroll-container::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }

    /* Slider Styles */
    .hero-slider {
      position: relative;
      margin-bottom: 2rem;
    }
    
    .slider-item {
      position: relative;
      height: 300px;
      background-size: cover;
      background-position: center;
      border-radius: 10px;
      overflow: hidden;
    }
    
    .slider-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 2rem;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      color: white;
    }
    
    .slider-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    
    .slider-description {
      margin-bottom: 1rem;
      font-size: 0.9rem;
    }
    
    .slider-btn {
      background-color: #4e73df;
      color: white;
      border: none;
      padding: 0.5rem 1.5rem;
      border-radius: 30px;
      font-weight: 600;
      transition: all 0.3s;
    }
    .categories-scroll-container{
      width: 100%;
      overflow-x: auto;
      padding-bottom: 10px;
      scrollbar-width: none; /* For Firefox */
      margin-bottom: 0px;
    }
    
    .categories-scroll-container::-webkit-scrollbar {
      display: none; /* For Chrome, Safari, and Edge */
    }

    .slider-btn:hover {
      background-color: #3a5ccc;
      transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
      .slider-item {
        height: 200px;
      }
      
      .slider-title {
        font-size: 1.2rem;
      }
      
      .slider-description {
        font-size: 0.8rem;
      }
    }
  </style>
  <style>

    
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


.category-item:hover {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  border-color: #d0d0d0;
  color: white !important;
}

.category-item:active {
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Optional active state */
.category-item.active {
  background-color: #007bff;
  color: white;
  border-color: #006fe6;
}

  .category-item {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.2rem;
    transition: all 0.3s ease;
    color: white !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    text-decoration: none;
  }

  .category-item.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
  }
</style>
</head>
<body>
  
<?php include('includes/nav.php');?>
  <!-- Offcanvas Menu -->
  <?php include('includes/menu.php');?>
  
  <?php include('includes/search.php');?>



<!-- Slider Section -->
<div class="container-fluid hero-slider px-3 px-md-5">
    <div class="owl-carousel owl-theme">
        <?php
        // Fetch active sliders from database
        $stmt = $pdo->query("SELECT * FROM sliders WHERE is_active = TRUE ORDER BY display_order");
        $sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sliders as $slider):
        ?>
        <div class="slider-item mt-4" style="background-image: url('../<?php echo htmlspecialchars($slider['image_url']); ?>')">
            
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
// Fetch active categories from database
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active'");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title" style="padding-left:0px !important;">Categories</div>
<!-- Scrollable Categories with Icons -->
<div class="py-2 px-3 categories-scroll-container" style="overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
  <div style="display: inline-flex; gap: 12px;">
    <?php foreach ($categories as $category): ?>
      <a href="courses.php?category_id=<?= $category['id'] ?>" class="category-item text-dark" style="display: inline-block; ">
        <?php if (!empty($category['icon'])): ?>
          <i class="<?= htmlspecialchars($category['icon']) ?> me-1"></i>
        <?php endif; ?>
        <?= htmlspecialchars($category['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>



<?php include('enroll.php');?>

<?php include('instructor.php');?>

<?php include('coures.php');?>

<?php include('includes/footer.php');?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

<script>
$(document).ready(function(){
    $(".owl-carousel").owlCarousel({
        items: 1,
        loop: true,
        autoplay: true,
        autoplayTimeout: 5000,
        autoplayHoverPause: true,
 
        dots: true,
        responsive:{
            0:{
                items:1
            },
            600:{
                items:1
            },
            1000:{
                items:1
            }
        }
    });
});
</script>
  
</body>
</html>