<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign Up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="style.css" rel="stylesheet"/>
</head>
<body>
  
  <!-- Navbar -->
  <?php include('nav.php');?>

  <!-- Offcanvas Menu -->
  <?php include('menu.php');?>

  <div class="container my-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <!-- Signup Form -->
        <div class="signup-form shadow-sm p-4 rounded bg-light">
          <h2 class="text-center text-primary">Sign Up</h2>
          <form>
            <!-- Full Name -->
            <div class="mb-3">
              <label for="fullName" class="form-label">Full Name</label>
              <input type="text" class="form-control" id="fullName" required>
            </div>

            <!-- Email -->
            <div class="mb-3">
              <label for="email" class="form-label">Email Address</label>
              <input type="email" class="form-control" id="email" required>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" required>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="confirmPassword" required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-3 shadow">Create Account</button>
          </form>

          <p class="text-center mt-3">Already have an account? <a href="login.php" class="text-primary">Login</a></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php include('footer.php');?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
