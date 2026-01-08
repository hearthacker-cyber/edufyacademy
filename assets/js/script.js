 
// Toggle between login and signup forms if you have a single page approach
document.addEventListener('DOMContentLoaded', function() {
    // You can add any client-side validation here
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Client-side validation example
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
    });
});