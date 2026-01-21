<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('driver');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

// Get driver info and statistics
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get driver info
    $stmt = $pdo->prepare("SELECT id, username, email, phone_number, full_name FROM users WHERE id = ? AND role = 'driver'");
    $stmt->execute([$user_id]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        header("Location: ../unauthorized.php");
        exit();
    }
    
    // Get assigned bus info
    $stmt = $pdo->prepare("SELECT b.* FROM buses b WHERE b.driver_id = ?");
    $stmt->execute([$user_id]);
    $assigned_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total trips
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id IN (SELECT id FROM users WHERE role = 'student') LIMIT 100");
    $stmt->execute();
    $total_trips = $stmt->fetchColumn() ?? 0;
    
    // Get total passengers (unique bookings)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT b.user_id) FROM bookings b");
    $total_passengers = $stmt->fetchColumn() ?? 0;
    
    // Get completed trips this month
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE()) AND status = 'confirmed'");
    $completed_trips_month = $stmt->fetchColumn() ?? 0;
    
    // Get pending notification count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_notifications WHERE driver_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_notifications = $stmt->fetchColumn() ?? 0;
    
    // Get upcoming schedules for this driver (only accepted bookings)
    $stmt = $pdo->prepare("
        SELECT b.*, r.from_location, r.to_location, u.full_name as student_name
        FROM bookings b
        JOIN booking_notifications bn ON bn.booking_id = b.booking_id AND bn.driver_id = ? AND bn.status = 'accepted'
        JOIN routes r ON b.route_id = r.route_id
        JOIN users u ON b.user_id = u.id
        WHERE b.status IN ('confirmed', 'pending')
        ORDER BY b.booking_date ASC, b.booking_time ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $upcoming_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity for this driver (only accepted bookings)
    $stmt = $pdo->prepare("
        SELECT b.*, r.from_location, r.to_location, u.full_name as student_name
        FROM bookings b
        JOIN booking_notifications bn ON bn.booking_id = b.booking_id AND bn.driver_id = ? AND bn.status = 'accepted'
        JOIN routes r ON b.route_id = r.route_id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
            --success-green: #27AE60;
            --warning-orange: #F39C12;
            --danger-red: #E74C3C;
            --info-cyan: #16A085;
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
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--accent-blue);
        }
        .nav-link.active {
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
            transition: all 0.3s;
        }
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        .stat-box.cyan { background: rgba(22, 160, 133, 0.1); color: var(--info-cyan); }
        
        .booking-card {
            background: white;
            border-left: 4px solid var(--accent-blue);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        .booking-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateX(5px);
        }
        .booking-title {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 1rem;
            margin-bottom: 8px;
        }
        .booking-info {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #6c757d;
            flex-wrap: wrap;
        }
        .booking-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pending { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .badge-confirmed { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .badge-cancelled { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .table-card h5 {
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 15px;
        }
        .table {
            margin: 0;
        }
        .table thead th {
            background: #f8f9fa;
            color: var(--primary-blue);
            font-weight: 700;
            border-bottom: 2px solid #e0e0e0;
            padding: 12px;
        }
        .table tbody td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .driver-info-card {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--accent-blue) 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .driver-info-card h5 {
            font-weight: 700;
            margin-bottom: 15px;
        }
        .driver-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .driver-info-item:last-child {
            border-bottom: none;
        }
        .driver-info-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .driver-info-value {
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            .main-content {
                margin-left: 200px;
                padding: 15px;
            }
            .header {
                padding: 20px;
            }
            .header h2 {
                font-size: 1.5rem;
            }
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
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="notifications.php">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($pending_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $pending_notifications > 99 ? '99+' : $pending_notifications; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
            <a class="nav-link" href="passengers.php"><i class="fas fa-users"></i> Passengers</a>
            <a class="nav-link" href="performance.php"><i class="fas fa-trophy"></i> Performance</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-tachometer-alt"></i> Driver Dashboard</h2>
            <p class="mb-0">Welcome back, <?php echo htmlspecialchars($full_name); ?>! Track your activity and manage your trips.</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <i class="fas fa-road"></i>
                    <h3><?php echo $total_trips; ?></h3>
                    <p>Total Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $completed_trips_month; ?></h3>
                    <p>This Month</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_passengers; ?></h3>
                    <p>Passengers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box cyan">
                    <i class="fas fa-star"></i>
                    <h3>4.8</h3>
                    <p>Rating</p>
                </div>
            </div>
        </div>

        <!-- Driver Info & Assigned Bus -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="driver-info-card">
                    <h5><i class="fas fa-id-card"></i> Driver Information</h5>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Full Name:</span>
                        <span class="driver-info-value"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Username:</span>
                        <span class="driver-info-value"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Email:</span>
                        <span class="driver-info-value"><?php echo htmlspecialchars($driver['email']); ?></span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Phone:</span>
                        <span class="driver-info-value"><?php echo htmlspecialchars($driver['phone_number'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Status:</span>
                        <span class="driver-info-value"><i class="fas fa-circle text-success"></i> Active</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="driver-info-card">
                    <h5><i class="fas fa-bus"></i> Assigned Bus</h5>
                    <?php if ($assigned_bus): ?>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Bus Number:</span>
                        <span class="driver-info-value"><?php echo htmlspecialchars($assigned_bus['bus_number']); ?></span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Capacity:</span>
                        <span class="driver-info-value"><?php echo $assigned_bus['capacity']; ?> seats</span>
                    </div>
                    <div class="driver-info-item">
                        <span class="driver-info-label">Status:</span>
                        <span class="driver-info-value"><?php echo ucfirst($assigned_bus['status']); ?></span>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-info-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                        <p style="margin-top: 10px; opacity: 0.9;">No bus assigned yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Bookings -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="content-card">
                    <h5 class="mb-3"><i class="fas fa-calendar-alt"></i> Upcoming Bookings</h5>
                    <?php if (!empty($upcoming_bookings)): ?>
                        <?php foreach($upcoming_bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-title">
                                <?php echo htmlspecialchars($booking['from_location']); ?> → <?php echo htmlspecialchars($booking['to_location']); ?>
                            </div>
                            <div class="booking-info">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['student_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($booking['booking_time'])); ?></span>
                                <span><i class="fas fa-chair"></i> Seat <?php echo $booking['seat_number']; ?></span>
                                <span class="badge-status badge-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div style="text-align: center; padding: 30px;">
                        <i class="fas fa-calendar-check" style="font-size: 3rem; color: #ccc;"></i>
                        <p style="color: #999; margin-top: 15px;">No upcoming bookings</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="table-card">
                    <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    <?php if (!empty($recent_activity)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Route</th>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Seat</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_activity as $activity): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-route"></i> 
                                    <?php echo htmlspecialchars($activity['from_location']); ?> → <?php echo htmlspecialchars($activity['to_location']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($activity['student_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($activity['booking_date'])); ?></td>
                                <td><?php echo $activity['seat_number']; ?></td>
                                <td>
                                    <span class="badge-status badge-<?php echo strtolower($activity['status']); ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 30px;">
                        <p style="color: #999;">No recent activity</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification sound and real-time checking
        let previousPendingCount = <?php echo $pending_notifications; ?>;

        function formatBadgeCount(count) {
            return count > 99 ? '99+' : count;
        }
        
        function playNotificationSound() {
            // Create audio context for notification beep
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800; // 800 Hz tone
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
        
        function checkForNewNotifications() {
            fetch('../api/check_notifications.php?filter=pending', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stats) {
                    const currentPendingCount = data.stats.pending_count || 0;
                    
                    // If new notifications arrived, play sound and update badge
                    if (currentPendingCount > previousPendingCount) {
                        playNotificationSound();
                        
                        // Update badge in navbar
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            badge.textContent = formatBadgeCount(currentPendingCount);
                        } else if (currentPendingCount > 0) {
                            // Create badge if it doesn't exist
                            const notifLink = document.querySelector('a[href="notifications.php"]');
                            if (notifLink) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = formatBadgeCount(currentPendingCount);
                                notifLink.appendChild(newBadge);
                            }
                        }
                    } else if (currentPendingCount === 0) {
                        // Remove badge if no pending notifications
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.remove();
                    }
                    
                    previousPendingCount = currentPendingCount;
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
        }
        
        // Check for new notifications every 5 seconds
        setInterval(checkForNewNotifications, 5000);
    </script>
</body>
</html>
