<?php
// Check if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika belum login
function redirectIfNotLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

// Cek role user
function checkRole($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        header("Location: ../unauthorized.php");
        exit();
    }
}

// Cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Logout function
function logout() {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
?>