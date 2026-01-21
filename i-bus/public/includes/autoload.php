<?php
// Autoload configuration
<?php
// Autoload configuration
function load_config_files() {
    $config_files = [
        'database.php',
        'paths.php'
    ];
    
    foreach ($config_files as $file) {
        $file_path = __DIR__ . '/../config/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// Load all required files
load_config_files();
?>
?>