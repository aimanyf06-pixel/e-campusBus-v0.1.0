<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember token from database
if(isset($_COOKIE['ibus_remember'])) {
    require_once 'config/database.php';
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE remember_token = :token";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":token", $_COOKIE['ibus_remember']);
        $stmt->execute();
        
        // Clear the cookie
        setcookie('ibus_remember', '', time() - 3600, '/', '', true, true);
    } catch(PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
    }
}

// Clear all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time() - 3600);
        setcookie($name, '', time() - 3600, '/');
    }
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Set logout message
session_start();
$_SESSION['logout_message'] = "You have been successfully logged out.";
session_write_close();

// Redirect to login page
header("Location: login.php?logout=success");
exit();
?>