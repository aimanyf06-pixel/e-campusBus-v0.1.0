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
    
    // Get all passengers (students with bookings)
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.full_name, u.email, u.phone_number, u.username,
            COUNT(b.booking_id) as total_bookings,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as completed_trips,
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_trips
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        WHERE u.role = 'student'
        GROUP BY u.id
        ORDER BY total_bookings DESC
    ");
    $passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $total_passengers = count($passengers);
    $regular_passengers = count(array_filter($passengers, fn($p) => $p['total_bookings'] >= 5));
    $new_passengers = count(array_filter($passengers, fn($p) => $p['total_bookings'] < 2));
    
    // Get recent passenger interactions
    $stmt = $pdo->query("
        SELECT DISTINCT u.id, u.full_name, u.email, u.phone_number,
            COUNT(b.booking_id) as bookings,
            MAX(b.created_at) as last_booking
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        WHERE u.role = 'student' AND b.created_at IS NOT NULL
        GROUP BY u.id
        ORDER BY last_booking DESC
        LIMIT 10
    ");
    $recent_passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passengers - e-campusBus System</title>
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
        
        .passenger-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        .passenger-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        .passenger-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .passenger-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }
        .passenger-username {
            font-size: 0.85rem;
            color: #999;
        }
        .passenger-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .info-item i {
            color: var(--accent-blue);
            width: 20px;
        }
        .passenger-stats {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-blue);
        }
        .stat-label {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .content-card h5 {
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent-blue);
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
            <a class="nav-link" href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a>
            <a class="nav-link active" href="passengers.php"><i class="fas fa-users"></i> Passengers</a>
            <a class="nav-link" href="performance.php"><i class="fas fa-trophy"></i> Performance</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-users"></i> Passengers</h2>
            <p class="mb-0">View and manage all your passengers</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box blue">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $total_passengers; ?></h3>
                    <p>Total Passengers</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box green">
                    <i class="fas fa-star"></i>
                    <h3><?php echo $regular_passengers; ?></h3>
                    <p>Regular Passengers</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box orange">
                    <i class="fas fa-user-plus"></i>
                    <h3><?php echo $new_passengers; ?></h3>
                    <p>New Passengers</p>
                </div>
            </div>
        </div>

        <!-- Recent Passengers -->
        <div class="content-card">
            <h5><i class="fas fa-history"></i> Recent Passengers</h5>
            <?php if (!empty($recent_passengers)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bookings</th>
                        <th>Last Booking</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_passengers as $passenger): ?>
                    <tr>
                        <td>
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($passenger['full_name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($passenger['email']); ?></td>
                        <td><?php echo htmlspecialchars($passenger['phone_number'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge bg-info"><?php echo $passenger['bookings']; ?></span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($passenger['last_booking'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc;"></i>
                <p style="color: #999; margin-top: 15px;">No recent passengers</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- All Passengers -->
        <div class="content-card">
            <h5><i class="fas fa-address-book"></i> All Passengers</h5>
            <?php if (!empty($passengers)): ?>
                <?php foreach($passengers as $passenger): ?>
                <div class="passenger-card">
                    <div class="passenger-header">
                        <div>
                            <div class="passenger-name">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($passenger['full_name']); ?>
                            </div>
                            <div class="passenger-username">@<?php echo htmlspecialchars($passenger['username']); ?></div>
                        </div>
                    </div>
                    <div class="passenger-info">
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($passenger['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($passenger['phone_number'] ?? 'Not provided'); ?></span>
                        </div>
                    </div>
                    <div class="passenger-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $passenger['total_bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: var(--success-green);"><?php echo $passenger['completed_trips']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: var(--warning-orange);"><?php echo $passenger['pending_trips']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-user-slash" style="font-size: 3rem; color: #ccc;"></i>
                <p style="color: #999; margin-top: 15px;">No passengers found</p>
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
