<?php
require_once '../includes/auth.php';
header('Content-Type: application/json');

// Check if user is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$driver_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$booking_id = $_POST['booking_id'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (empty($action) || empty($booking_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Verify booking exists and get details
    $stmt = $pdo->prepare("
        SELECT b.*, r.route_id, u.full_name as student_name, u.phone_number
        FROM bookings b
        JOIN routes r ON b.route_id = r.route_id
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    // Check if notification exists for this driver
    $stmt = $pdo->prepare("
        SELECT * FROM booking_notifications 
        WHERE booking_id = ? AND driver_id = ?
    ");
    $stmt->execute([$booking_id, $driver_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notification) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this booking']);
        exit;
    }
    
    if ($action === 'accept') {
        // Update booking status to confirmed
        $stmt = $pdo->prepare("
            UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?
        ");
        $stmt->execute([$booking_id]);
        
        // Update this driver's notification to accepted
        $stmt = $pdo->prepare("
            UPDATE booking_notifications 
            SET status = 'accepted', responded_at = NOW() 
            WHERE booking_id = ? AND driver_id = ?
        ");
        $stmt->execute([$booking_id, $driver_id]);
        
        // Auto-reject all other drivers' notifications for this booking
        $stmt = $pdo->prepare("
            UPDATE booking_notifications 
            SET status = 'rejected', response_reason = 'Another driver already accepted this booking', responded_at = NOW() 
            WHERE booking_id = ? AND driver_id != ? AND status = 'pending'
        ");
        $stmt->execute([$booking_id, $driver_id]);
        
        // Create activity log
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, created_at)
            VALUES (?, 'booking_accepted', ?, NOW())
        ");
        $stmt->execute([$driver_id, 'Accepted booking #' . $booking_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking accepted successfully',
            'booking_id' => $booking_id,
            'status' => 'confirmed'
        ]);
        
    } else if ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'No reason provided';
        
        // Update booking status back to pending
        $stmt = $pdo->prepare("
            UPDATE bookings SET status = 'pending' WHERE booking_id = ?
        ");
        $stmt->execute([$booking_id]);
        
        // Update notification status
        $stmt = $pdo->prepare("
            UPDATE booking_notifications 
            SET status = 'rejected', response_reason = ?, responded_at = NOW() 
            WHERE booking_id = ? AND driver_id = ?
        ");
        $stmt->execute([$reason, $booking_id, $driver_id]);
        
        // Create activity log
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, created_at)
            VALUES (?, 'booking_rejected', ?, NOW())
        ");
        $stmt->execute([$driver_id, 'Rejected booking #' . $booking_id . ': ' . $reason]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking rejected successfully',
            'booking_id' => $booking_id,
            'status' => 'pending'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
