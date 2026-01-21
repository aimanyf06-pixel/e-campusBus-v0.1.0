<?php
session_start();

// Set session configuration for security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Test - i-Bus System</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; }
        .container { max-width: 800px; }
        .card { margin-bottom: 20px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1 class='mb-4'>Session Test Results</h1>";

// Test session settings
echo "<div class='card'>
    <div class='card-header bg-primary text-white'>
        <h5 class='mb-0'>Session Information</h5>
    </div>
    <div class='card-body'>";

echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . " (";

switch(session_status()) {
    case PHP_SESSION_DISABLED:
        echo "Sessions disabled";
        break;
    case PHP_SESSION_NONE:
        echo "No session";
        break;
    case PHP_SESSION_ACTIVE:
        echo "Session active";
        break;
}

echo ")</p>";
echo "<p><strong>Session Save Path:</strong> " . session_save_path() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";

// Test setting session variable
$_SESSION['test'] = 'Hello World';
echo "<p><strong>Test Session Variable:</strong> " . ($_SESSION['test'] ?? 'Not set') . "</p>";

// Check session file
$session_file = session_save_path() . '/sess_' . session_id();
if (file_exists($session_file)) {
    echo "<p><strong>Session File:</strong> Exists at " . htmlspecialchars($session_file) . "</p>";
    echo "<p><strong>File Size:</strong> " . filesize($session_file) . " bytes</p>";
    
    // Read and display session data (for debugging only)
    $session_data = file_get_contents($session_file);
    echo "<p><strong>Session Data:</strong></p><pre>" . htmlspecialchars($session_data) . "</pre>";
} else {
    echo "<p><strong>Session File:</strong> Does NOT exist at " . htmlspecialchars($session_file) . "</p>";
}

// Display all session variables
echo "<h5>All Session Variables:</h5>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Display cookie information
echo "<h5>Cookie Information:</h5>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test session configuration
echo "<h5>Session Configuration:</h5>";
echo "<ul>";
echo "<li>session.cookie_httponly: " . (ini_get('session.cookie_httponly') ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>session.cookie_secure: " . (ini_get('session.cookie_secure') ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>session.use_strict_mode: " . (ini_get('session.use_strict_mode') ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>session.cookie_samesite: " . ini_get('session.cookie_samesite') . "</li>";
echo "<li>session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . " seconds</li>";
echo "<li>session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . " seconds</li>";
echo "</ul>";

echo "</div></div>";

// Test form to set session variables
echo "<div class='card'>
    <div class='card-header bg-info text-white'>
        <h5 class='mb-0'>Test Session Variables</h5>
    </div>
    <div class='card-body'>
        <form method='post' class='mb-3'>
            <div class='mb-3'>
                <label for='key' class='form-label'>Key:</label>
                <input type='text' class='form-control' id='key' name='key' placeholder='Enter key'>
            </div>
            <div class='mb-3'>
                <label for='value' class='form-label'>Value:</label>
                <input type='text' class='form-control' id='value' name='value' placeholder='Enter value'>
            </div>
            <button type='submit' class='btn btn-primary'>Set Session Variable</button>
            <button type='submit' name='clear' class='btn btn-danger'>Clear All Session</button>
        </form>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear'])) {
        session_destroy();
        echo "<div class='alert alert-success'>Session cleared successfully!</div>";
        echo "<script>setTimeout(() => location.reload(), 1000);</script>";
    } elseif (!empty($_POST['key'])) {
        $_SESSION[$_POST['key']] = $_POST['value'];
        echo "<div class='alert alert-success'>Session variable '{$_POST['key']}' set to '{$_POST['value']}'</div>";
    }
}

echo "</div></div>";

// Display server information
echo "<div class='card'>
    <div class='card-header bg-secondary text-white'>
        <h5 class='mb-0'>Server Information</h5>
    </div>
    <div class='card-body'>
        <ul>
            <li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>
            <li><strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>
            <li><strong>Server Protocol:</strong> " . $_SERVER['SERVER_PROTOCOL'] . "</li>
            <li><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) ? 'Enabled' : 'Disabled') . "</li>
            <li><strong>Remote Address:</strong> " . $_SERVER['REMOTE_ADDR'] . "</li>
            <li><strong>User Agent:</strong> " . $_SERVER['HTTP_USER_AGENT'] . "</li>
        </ul>
    </div>
</div>";

// Quick links
echo "<div class='card'>
    <div class='card-header bg-success text-white'>
        <h5 class='mb-0'>Quick Links</h5>
    </div>
    <div class='card-body'>
        <div class='btn-group' role='group'>
            <a href='index.php' class='btn btn-outline-primary'>Home Page</a>
            <a href='login.php' class='btn btn-outline-primary'>Login Page</a>
            <a href='register.php' class='btn btn-outline-primary'>Register Page</a>
            <a href='check_database.php' class='btn btn-outline-primary'>Database Test</a>
        </div>
    </div>
</div>";

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>