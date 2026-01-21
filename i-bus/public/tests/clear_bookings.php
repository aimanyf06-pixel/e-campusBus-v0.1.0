<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete booking notifications first (foreign key constraint)
    $stmt = $pdo->query("DELETE FROM booking_notifications");
    $notifications_deleted = $stmt->rowCount();
    
    // Delete bookings
    $stmt = $pdo->query("DELETE FROM bookings");
    $bookings_deleted = $stmt->rowCount();
    
    // Delete related activity logs
    $stmt = $pdo->query("DELETE FROM activity_logs WHERE action LIKE '%booking%' OR action LIKE '%Accepted%' OR action LIKE '%Rejected%'");
    $logs_deleted = $stmt->rowCount();
    
    // Commit transaction
    $pdo->commit();
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Clear Bookings - Success</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='card shadow'>
                <div class='card-header bg-success text-white'>
                    <h4 class='mb-0'><i class='fas fa-check-circle'></i> Bookings Cleared Successfully</h4>
                </div>
                <div class='card-body'>
                    <div class='alert alert-success'>
                        <h5>Data Deleted:</h5>
                        <ul class='mb-0'>
                            <li><strong>{$bookings_deleted}</strong> bookings deleted</li>
                            <li><strong>{$notifications_deleted}</strong> booking notifications deleted</li>
                            <li><strong>{$logs_deleted}</strong> activity logs deleted</li>
                        </ul>
                    </div>
                    <p class='text-muted'><i class='fas fa-info-circle'></i> All booking data has been cleared. You can now test making new bookings.</p>
                    <div class='mt-3'>
                        <a href='../student/make_booking.php' class='btn btn-primary'>
                            <i class='fas fa-plus'></i> Make New Booking
                        </a>
                        <a href='../driver/notifications.php' class='btn btn-secondary'>
                            <i class='fas fa-bell'></i> Check Driver Notifications
                        </a>
                        <a href='../student/dashboard.php' class='btn btn-info'>
                            <i class='fas fa-home'></i> Student Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Clear Bookings - Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='card shadow'>
                <div class='card-header bg-danger text-white'>
                    <h4 class='mb-0'><i class='fas fa-exclamation-circle'></i> Error Clearing Bookings</h4>
                </div>
                <div class='card-body'>
                    <div class='alert alert-danger'>
                        <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                    </div>
                    <a href='javascript:history.back()' class='btn btn-secondary'>
                        <i class='fas fa-arrow-left'></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>";
}
?>
