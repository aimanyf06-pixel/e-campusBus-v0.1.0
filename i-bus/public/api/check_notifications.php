<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'driver') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

require_once __DIR__ . '/../includes/functions.php';

$driver_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'pending';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if driver has assigned a bus
    $stmt = $pdo->prepare("SELECT bus_id FROM buses WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $assigned_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no bus assigned, return empty response
    if (!$assigned_bus) {
        echo json_encode([
            'success' => true,
            'html' => '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-lock"></i></div><h3 class="empty-state-title">Notifications Locked</h3><p class="empty-state-text">Please assign a bus to your account to start receiving booking notifications from customers.</p></div>',
            'stats' => ['pending_count' => 0, 'accepted_count' => 0, 'rejected_count' => 0, 'total_count' => 0]
        ]);
        exit();
    }
    
    // Get notifications with booking details
    $query = "
        SELECT bn.*, b.booking_id, b.booking_date, b.booking_time, b.seat_number, b.amount, b.status as booking_status,
               r.from_location, r.to_location, r.distance, r.duration, r.base_fare,
               u.full_name as student_name, u.phone_number, u.email
        FROM booking_notifications bn
        JOIN bookings b ON bn.booking_id = b.booking_id
        JOIN routes r ON b.route_id = r.route_id
        JOIN users u ON b.user_id = u.id
        WHERE bn.driver_id = ?
    ";
    
    $params = [$driver_id];
    
    if ($filter !== 'all') {
        $query .= " AND bn.status = ?";
        $params[] = $filter;
    }
    
    $query .= " ORDER BY bn.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_count,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
            COUNT(*) as total_count
        FROM booking_notifications
        WHERE driver_id = ?
    ");
    $stmt->execute([$driver_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Generate HTML for notifications
    $html = '';
    
    if (empty($notifications)) {
        $empty_msg = match($filter) {
            'pending' => 'You have no pending booking requests at the moment.',
            'accepted' => 'You have not accepted any bookings yet.',
            'rejected' => 'You have not rejected any bookings yet.',
            default => 'There are no notifications to display.'
        };
        
        $html = '
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3 class="empty-state-title">No Notifications</h3>
                <p class="empty-state-text">' . $empty_msg . '</p>
            </div>
        ';
    } else {
        foreach ($notifications as $notif) {
            $status_icon = match($notif['status']) {
                'pending' => 'hourglass-half',
                'accepted' => 'check-circle',
                'rejected' => 'times-circle',
                default => 'info-circle'
            };
            
            $html .= '
                <div class="notification-card ' . htmlspecialchars($notif['status']) . '">
                    <!-- Header -->
                    <div class="notification-header">
                        <div>
                            <div class="notification-title">
                                <i class="fas fa-map-marker-alt"></i>
                                ' . htmlspecialchars($notif['from_location']) . ' → ' . htmlspecialchars($notif['to_location']) . '
                            </div>
                            <small style="color: #999;">Booking ID: #' . $notif['booking_id'] . ' | Date: ' . date('M d, Y', strtotime($notif['booking_date'])) . ' | Time: ' . date('H:i A', strtotime($notif['booking_time'])) . '</small>
                        </div>
                        <span class="notification-badge badge-' . htmlspecialchars($notif['status']) . '">
                            <i class="fas fa-' . $status_icon . '"></i>
                            ' . ucfirst($notif['status']) . '
                        </span>
                    </div>
                    
                    <!-- Details Grid -->
                    <div class="notification-details-grid">
                        <div class="detail-box">
                            <i class="fas fa-road"></i>
                            <div>
                                <span class="detail-label">Distance</span>
                                <span class="detail-value">' . htmlspecialchars($notif['distance']) . ' km</span>
                            </div>
                        </div>
                        <div class="detail-box">
                            <i class="fas fa-clock"></i>
                            <div>
                                <span class="detail-label">Duration</span>
                                <span class="detail-value">' . htmlspecialchars($notif['duration']) . ' min</span>
                            </div>
                        </div>
                        <div class="detail-box">
                            <i class="fas fa-chair"></i>
                            <div>
                                <span class="detail-label">Seat</span>
                                <span class="detail-value">' . htmlspecialchars($notif['seat_number']) . '</span>
                            </div>
                        </div>
                        <div class="detail-box">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>
                                <span class="detail-label">Fare</span>
                                <span class="detail-value">₱' . number_format($notif['amount'], 2) . '</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Info -->
                    <div class="student-info">
                        <h5><i class="fas fa-user-circle"></i> Student Information</h5>
                        <div class="student-info-item">
                            <span class="label">Name:</span>
                            <span class="value">' . htmlspecialchars($notif['student_name']) . '</span>
                        </div>
                        <div class="student-info-item">
                            <span class="label">Phone:</span>
                            <span class="value">' . htmlspecialchars($notif['phone_number'] ?? 'N/A') . '</span>
                        </div>
                        <div class="student-info-item">
                            <span class="label">Email:</span>
                            <span class="value">' . htmlspecialchars($notif['email'] ?? 'N/A') . '</span>
                        </div>
                    </div>
            ';
            
            // Show rejection reason if rejected
            if ($notif['status'] === 'rejected' && !empty($notif['response_reason'])) {
                $html .= '
                    <!-- Rejection Reason -->
                    <div class="rejection-reason">
                        <h5><i class="fas fa-exclamation-circle"></i> Rejection Reason</h5>
                        <p>' . htmlspecialchars($notif['response_reason']) . '</p>
                    </div>
                ';
            }
            
            // Show action buttons if pending
            if ($notif['status'] === 'pending') {
                $html .= '
                    <!-- Action Buttons -->
                    <div class="notification-actions">
                        <button class="btn-accept" onclick="acceptBooking(' . $notif['booking_id'] . ')">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="btn-reject" onclick="openRejectModal(' . $notif['booking_id'] . ')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                ';
            } else {
                // Show response timestamp
                $html .= '
                    <!-- Response Info -->
                    <div class="response-info">
                        <i class="fas fa-info-circle"></i> 
                        <span>Responded on ' . date('M d, Y H:i A', strtotime($notif['responded_at'])) . '</span>
                    </div>
                ';
            }
            
            $html .= '
                </div>
            ';
        }
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'stats' => $stats
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
