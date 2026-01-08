// Preloader functionality
document.addEventListener('DOMContentLoaded', function() {
    const preloader = document.getElementById('mobile-preloader');
    
    // Minimum display time (1.5 seconds for smooth experience)
    const minDisplayTime = 1500;
    const startTime = Date.now();
    
    // Hide preloader when page is fully loaded
    window.addEventListener('load', function() {
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, minDisplayTime - elapsedTime);
        
        setTimeout(() => {
            preloader.classList.add('hidden');
            
            // Remove from DOM after animation
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }, remainingTime);
    });
    
    // Fallback: hide after 3 seconds max
    setTimeout(() => {
        if (!preloader.classList.contains('hidden')) {
            preloader.classList.add('hidden');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }
    }, 3000);
});

// Add preloader to all links for smooth transitions
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    
    if (link && !link.target && !link.href.includes('#') && 
        !link.classList.contains('no-preloader')) {
        e.preventDefault();
        
        // Show preloader
        const preloader = document.createElement('div');
        preloader.id = 'mobile-preloader';
        preloader.innerHTML = `
            <div class="preloader-logo">EA</div>
            <div class="loader"></div>
            <div class="loading-text">Loading<span class="loading-dots"></span></div>
        `;
        
        document.body.appendChild(preloader);
        
        // Navigate after short delay
        setTimeout(() => {
            window.location.href = link.href;
        }, 300);
    }
});