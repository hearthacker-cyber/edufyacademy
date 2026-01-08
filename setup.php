<?php
// /app/setup.php - Run once to set up directories
echo "Setting up mobile app structure...<br>";

// Create directories
$dirs = [
    'config',
    'assets/css',
    'assets/js',
    'assets/images',
    'logs'
];

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir<br>";
    }
}

// Check if we can access main config
if (file_exists('../config/db.php')) {
    echo "✓ Main config found<br>";
    
    // Create config symlink
    if (!file_exists('config') && is_writable('.')) {
        if (symlink('../config', 'config')) {
            echo "✓ Created config symlink<br>";
        } else {
            echo "✗ Could not create symlink. Creating config copies...<br>";
            
            // Copy config files
            $config_files = ['db.php', 'razorpay.php', 'update-payment-log.php'];
            foreach ($config_files as $file) {
                if (file_exists("../config/$file")) {
                    copy("../config/$file", "config/$file");
                    echo "✓ Copied config/$file<br>";
                }
            }
        }
    }
} else {
    echo "✗ Main config not found. Please copy config files manually.<br>";
}

echo "<br>Setup complete!<br>";
?>