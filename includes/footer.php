<div class="footer">
    <a href="home.php">
      <i class="fa fa-home"></i>
      <span>Home</span>
    </a>
<a href="courses.php">
  <i class="fa fa-graduation-cap"></i>
  <span>Courses</span>
</a>

<a href="my-courses.php">
  <i class="fa fa-book"></i>
  <span>My Course</span>
</a>


    <a href="profile.php">
      <i class="fa fa-user"></i>
      <span>Profile</span>
    </a>
  </div>
  <script>
    // Notification dropdown toggle
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationDropdown = document.getElementById('notificationDropdown');

    notificationIcon.addEventListener('click', () => {
      notificationDropdown.classList.toggle('active');
    });

    // Close notification dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.remove('active');
      }
    });
  </script>