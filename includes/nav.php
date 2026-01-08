<div class="header">
  <div class="left-icons me-auto">
    <i class="fa fa-bars" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu" aria-controls="offcanvasMenu"></i>
  </div>

  <div class="right-icons position-relative">
    <!-- Add search icon before notification icon -->
    <i class="fa fa-search me-3" id="mobileSearchToggle"></i>
    

    <?php
    require_once 'includes/db.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        // Fetch user data from database
        $stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            // If user has an avatar image
           if (!empty($user['avatar'])) {
    // Check if the avatar is an absolute URL (starts with http:// or https://)
    $avatarUrl = (preg_match('/^https?:\/\//', $user['avatar']))
        ? htmlspecialchars($user['avatar']) // Use absolute URL as-is (e.g., Google profile picture)
        : '../' . htmlspecialchars($user['avatar']); // Prepend ../ for local paths
    echo '<a href="profile.php" style="text-decoration: none;"><img src="' . $avatarUrl . '" alt="Profile" class="profile-pic"></a>';
} else {
    // If no avatar, show first letter of first name
    $firstLetter = strtoupper(substr($user['first_name'], 0, 1));
    echo '<a href="profile.php" style="text-decoration: none;"><div class="profile-letter">' . $firstLetter . '</div></a>';
}
        } else {
            // Fallback if user not found
            echo '<a href="profile.php" style="text-decoration: none;"><img src="https://360hcskills.com/wp-content/uploads/2023/11/cropped-360-Logo-4-scaled-1-2048x2048.jpg" alt="Profile" class="profile-pic"></a>';
        }

        // Optional: Close statement (not strictly needed with PDO, but safe practice)
        $stmt = null;
    } else {
        // Default image if not logged in - link to login
        echo '<a href="auth/login.php" style="text-decoration: none;"><img src="https://360hcskills.com/wp-content/uploads/2023/11/cropped-360-Logo-4-scaled-1-2048x2048.jpg" alt="Profile" class="profile-pic"></a>';
    }
    ?>

    
  </div>
</div>

<style>
  .profile-pic {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    cursor: pointer;
  }

  .profile-letter {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #ffffffff;
    color: blue;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    cursor: pointer;
  }

  /* Search bar styles */
  .search-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 1050;
    transform: translateY(-150%);
    transition: transform 0.3s ease;
  }

  .search-bar.visible {
    transform: translateY(0);
  }
    #mobileSearchResults{
        background: rgba(255, 255, 255,0.99);
        border-radius: 5px;
        margin-top: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .search-result{
        border-bottom: 1px solid #F7F7F7;
        padding:10px;
    }
  /* Adjust header when search is visible */
  body.search-active .header {
    margin-top: 0px; /* Height of search bar */
  }

  /* Style for search toggle icon */
  #mobileSearchToggle {
    cursor: pointer;
    font-size: 18px;
    color: white;
  }
</style>

<div class="search-bar">
    <div class="">
        <input type="text" id="mobileCourseSearch"
            class=""
            placeholder="Search courses..." autocomplete="off">
       
        <div id="mobileSearchResults"
            class="search-results-dropdown">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle search bar visibility
    const searchToggle = document.getElementById('mobileSearchToggle');
    const searchBar = document.querySelector('.search-bar');
    const body = document.body;

    if (searchToggle && searchBar) {
        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Toggle search bar visibility
            searchBar.classList.toggle('visible');
            body.classList.toggle('search-active');
            
            // Focus on input when search bar is shown
            if (searchBar.classList.contains('visible')) {
                document.getElementById('mobileCourseSearch').focus();
            }
        });
    }

    // Close search when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchBar.contains(e.target) && e.target !== searchToggle) {
            searchBar.classList.remove('visible');
            body.classList.remove('search-active');
        }
    });

    // Mobile search functionality
    const mobileSearchInput = document.getElementById('mobileCourseSearch');
    const mobileSearchResults = document.getElementById('mobileSearchResults');
    let mobileDebounceTimer;

    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('input', function(e) {
            clearTimeout(mobileDebounceTimer);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                mobileSearchResults.classList.add('d-none');
                mobileSearchResults.innerHTML = '';
                return;
            }

            mobileDebounceTimer = setTimeout(() => {
                searchCourses(query, mobileSearchResults);
            }, 300);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileSearchInput.contains(e.target) && !mobileSearchResults.contains(e.target)) {
                mobileSearchResults.classList.add('d-none');
            }
        });
    }

    // Generic search function
    function searchCourses(query, resultsContainer) {
        fetch(`search_courses.php?query=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                resultsContainer.innerHTML = '';
                
                if (data.length > 0) {
                    data.forEach(course => {
                        const resultItem = document.createElement('div');
                        resultItem.className = 'search-result-item';
                        
                        resultItem.innerHTML = `
                            <div class="d-flex align-items-center search-result">
                                ${course.image ? `<img src="${course.image}" class="me-3" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` : ''}
                                <div>
                                    <div class="">${course.title}</div>
                                    <small class="text-muted">${course.instructor || ''}</small>
                                </div>
                            </div>
                        `;
                        
                        resultItem.addEventListener('click', () => {
                            window.location.href = `course-details.php?id=${course.id}`;
                            searchBar.classList.remove('visible');
                            body.classList.remove('search-active');
                        });
                        resultsContainer.appendChild(resultItem);
                    });
                    resultsContainer.classList.remove('d-none');
                } else {
                    const noResults = document.createElement('div');
                    noResults.className = 'search-result-item text-muted p-3';
                    noResults.textContent = 'No courses found';
                    resultsContainer.appendChild(noResults);
                    resultsContainer.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsContainer.innerHTML = `
                    <div class="search-result-item text-danger">
                        Search temporarily unavailable
                    </div>
                `;
                resultsContainer.classList.remove('d-none');
            });
    }
});
</script>