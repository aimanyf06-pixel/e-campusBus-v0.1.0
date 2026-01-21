<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('driver');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get pending notification count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_notifications WHERE driver_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_notifications = $stmt->fetchColumn() ?? 0;
    
    // Get all scheduled bookings for this driver (only accepted notifications)
    $stmt = $pdo->prepare("
        SELECT b.*, r.from_location, r.to_location, r.distance, r.duration, r.base_fare, u.full_name as student_name, u.email as student_email
        FROM bookings b
        JOIN booking_notifications bn ON bn.booking_id = b.booking_id AND bn.driver_id = ? AND bn.status = 'accepted'
        JOIN routes r ON b.route_id = r.route_id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.booking_date DESC, b.booking_time DESC
    ");
    $stmt->execute([$user_id]);
    $all_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate by status
    $upcoming = array_filter($all_bookings, fn($b) => in_array($b['status'], ['pending', 'confirmed']) && strtotime($b['booking_date']) >= strtotime('today'));
    $completed = array_filter($all_bookings, fn($b) => $b['status'] === 'confirmed' && strtotime($b['booking_date']) < strtotime('today'));
    $cancelled = array_filter($all_bookings, fn($b) => $b['status'] === 'cancelled');
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
            --success-green: #27AE60;
            --warning-orange: #F39C12;
            --danger-red: #E74C3C;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }
        .sidebar {
            background: linear-gradient(180deg, var(--primary-blue) 0%, #1a2530 100%);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .sidebar-header p {
            color: rgba(255, 255, 255, 0.7);
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--accent-blue);
            font-weight: 600;
        }
        .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 10px;
            padding: 2px 7px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--accent-blue) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .stat-box h3 {
            margin: 10px 0 0 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .stat-box i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-box.blue { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-box.green { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .stat-box.orange { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .stat-box.red { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        
        .schedule-card {
            background: white;
            border-left: 4px solid var(--accent-blue);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        .schedule-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateX(5px);
        }
        .schedule-card.completed {
            border-left-color: var(--success-green);
        }
        .schedule-card.cancelled {
            border-left-color: var(--danger-red);
            opacity: 0.8;
        }
        .route-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 12px;
        }
        .route-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 12px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .detail-item strong {
            color: var(--primary-blue);
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pending { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .badge-confirmed { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .badge-cancelled { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        
        .tab-section {
            margin-bottom: 30px;
        }
        .tab-section h4 {
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent-blue);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-bus-alt"></i> e-campusBus</h3>
            <p>Driver Portal</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="notifications.php">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($pending_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $pending_notifications > 99 ? '99+' : $pending_notifications; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link active" href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
            <a class="nav-link" href="passengers.php"><i class="fas fa-users"></i> Passengers</a>
            <a class="nav-link" href="performance.php"><i class="fas fa-trophy"></i> Performance</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-calendar"></i> Schedule</h2>
            <p class="mb-0">View and manage your trip schedule</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box blue">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo count($upcoming); ?></h3>
                    <p>Upcoming Trips</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo count($completed); ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box red">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo count($cancelled); ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Upcoming Trips -->
        <div class="tab-section">
            <h4><i class="fas fa-exclamation-circle"></i> Upcoming Trips</h4>
            <?php if (!empty($upcoming)): ?>
                <?php foreach($upcoming as $trip): ?>
                <div class="schedule-card">
                    <div class="route-title">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($trip['from_location']); ?> → <?php echo htmlspecialchars($trip['to_location']); ?>
                    </div>
                    <div class="route-details">
                        <div class="detail-item">
                            <i class="fas fa-user"></i>
                            <span><strong>Student:</strong> <?php echo htmlspecialchars($trip['student_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><strong>Date:</strong> <?php echo date('M d, Y', strtotime($trip['booking_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><strong>Time:</strong> <?php echo date('H:i', strtotime($trip['booking_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-chair"></i>
                            <span><strong>Seat:</strong> <?php echo $trip['seat_number']; ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-road"></i>
                            <span><strong>Distance:</strong> <?php echo $trip['distance']; ?> km</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-hourglass"></i>
                            <span><strong>Duration:</strong> <?php echo $trip['duration']; ?> min</span>
                        </div>
                    </div>
                    <div>
                        <span class="badge-status badge-<?php echo strtolower($trip['status']); ?>">
                            <?php echo ucfirst($trip['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                <i class="fas fa-calendar-check" style="font-size: 3rem; color: #ccc;"></i>
                <p style="color: #999; margin-top: 15px;">No upcoming trips scheduled</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Completed Trips -->
        <div class="tab-section">
            <h4><i class="fas fa-check-double"></i> Completed Trips</h4>
            <?php if (!empty($completed)): ?>
                <?php foreach($completed as $trip): ?>
                <div class="schedule-card completed">
                    <div class="route-title">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($trip['from_location']); ?> → <?php echo htmlspecialchars($trip['to_location']); ?>
                    </div>
                    <div class="route-details">
                        <div class="detail-item">
                            <i class="fas fa-user"></i>
                            <span><strong>Student:</strong> <?php echo htmlspecialchars($trip['student_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><strong>Date:</strong> <?php echo date('M d, Y', strtotime($trip['booking_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span><strong>Fare:</strong> RM <?php echo number_format($trip['amount'], 2); ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="badge-status badge-confirmed">
                            Completed
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                <i class="fas fa-history" style="font-size: 3rem; color: #ccc;"></i>
                <p style="color: #999; margin-top: 15px;">No completed trips yet</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Cancelled Trips -->
        <div class="tab-section">
            <h4><i class="fas fa-ban"></i> Cancelled Trips</h4>
            <?php if (!empty($cancelled)): ?>
                <?php foreach($cancelled as $trip): ?>
                <div class="schedule-card cancelled">
                    <div class="route-title">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($trip['from_location']); ?> → <?php echo htmlspecialchars($trip['to_location']); ?>
                    </div>
                    <div class="route-details">
                        <div class="detail-item">
                            <i class="fas fa-user"></i>
                            <span><strong>Student:</strong> <?php echo htmlspecialchars($trip['student_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><strong>Date:</strong> <?php echo date('M d, Y', strtotime($trip['booking_date'])); ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="badge-status badge-cancelled">
                            Cancelled
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                <i class="fas fa-thumbs-up" style="font-size: 3rem; color: #ccc;"></i>
                <p style="color: #999; margin-top: 15px;">No cancelled trips</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let previousPendingCount = <?php echo $pending_notifications; ?>;

        function formatBadgeCount(count) {
            return count > 99 ? '99+' : count;
        }
        function playNotificationSound() {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
        function checkForNewNotifications() {
            fetch('../api/check_notifications.php?filter=pending', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stats) {
                    const currentPendingCount = data.stats.pending_count || 0;
                    if (currentPendingCount > previousPendingCount) {
                        playNotificationSound();
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.textContent = formatBadgeCount(currentPendingCount);
                        else if (currentPendingCount > 0) {
                            const notifLink = document.querySelector('a[href="notifications.php"]');
                            if (notifLink) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = formatBadgeCount(currentPendingCount);
                                notifLink.appendChild(newBadge);
                            }
                        }
                    } else if (currentPendingCount === 0) {
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.remove();
                    }
                    previousPendingCount = currentPendingCount;
                }
            })
            .catch(error => console.error('Error:', error));
        }
        setInterval(checkForNewNotifications, 5000);
    </script>
</body>
</html>
