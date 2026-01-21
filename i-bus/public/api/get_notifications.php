<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_POST['user_id'] ?? $_SESSION['user_id'] ?? 0;
$role = $_POST['role'] ?? $_SESSION['role'] ?? 'user';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $notifications = [];
    $count = 0;
    
    if ($role === 'admin') {
        // Admin notifications
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(id, UUID()) as id,
                'system' as type,
                title,
                message,
                created_at,
                0 as unread
            FROM (
                SELECT NULL as id, 'New Booking' as title, CONCAT('New booking #', b.booking_id, ' from ', u.full_name) as message, b.created_at
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                UNION ALL
                SELECT NULL as id, 'User Activity' as title, CONCAT(u.full_name, ' logged in') as message, al.created_at
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.action = 'login' AND al.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ) AS combined
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = min(5, count($notifications));
        
    } elseif ($role === 'driver') {
        // Driver notifications (booking notifications)
        $stmt = $pdo->prepare("
            SELECT 
                bn.id,
                'booking' as type,
                CONCAT(r.from_location, ' → ', r.to_location) as title,
                CONCAT(u.full_name, ' booked seat ', b.seat_number) as message,
                bn.created_at,
                CASE WHEN bn.status = 'pending' THEN 1 ELSE 0 END as unread
            FROM booking_notifications bn
            JOIN bookings b ON bn.booking_id = b.booking_id
            JOIN routes r ON b.route_id = r.route_id
            JOIN users u ON b.user_id = u.id
            WHERE bn.driver_id = ?
            ORDER BY bn.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_notifications WHERE driver_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();
        
    } elseif ($role === 'student') {
        // Student notifications: include driver responses (accept/reject) with driver name and reason
        $stmt = $pdo->prepare("
            SELECT * FROM (
                SELECT 
                    bn.notification_id as id,
                    'booking' as type,
                    CONCAT(r.from_location, ' → ', r.to_location) as title,
                    CASE 
                        WHEN bn.status = 'accepted' THEN CONCAT(d.username, ' accepted your trip')
                        WHEN bn.status = 'rejected' THEN CONCAT(d.username, ' rejected your trip: ', COALESCE(NULLIF(bn.response_reason, ''), 'No reason provided'))
                        ELSE CONCAT('Booking update: ', bn.status)
                    END as message,
                    COALESCE(bn.responded_at, bn.created_at) as created_at,
                    0 as unread
                FROM booking_notifications bn
                JOIN bookings b ON bn.booking_id = b.booking_id
                JOIN routes r ON b.route_id = r.route_id
                JOIN users d ON bn.driver_id = d.id
                WHERE b.user_id = ? AND bn.status IN ('accepted', 'rejected')

                UNION ALL

                SELECT 
                    b.booking_id as id,
                    'booking' as type,
                    CONCAT(r.from_location, ' → ', r.to_location) as title,
                    CONCAT('Your booking is ', b.status) as message,
                    b.created_at,
                    CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END as unread
                FROM bookings b
                JOIN routes r ON b.route_id = r.route_id
                WHERE b.user_id = ?
            ) AS combined
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total notifications shown (no explicit unread tracking yet)
        $count = count($notifications);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => $count
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
