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
    
    // Overall statistics for this driver
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings b JOIN booking_notifications bn ON bn.booking_id = b.booking_id WHERE bn.driver_id = ? AND bn.status = 'accepted' AND b.status = 'confirmed'");
    $stmt->execute([$user_id]);
    $total_trips = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings b JOIN booking_notifications bn ON bn.booking_id = b.booking_id WHERE bn.driver_id = ? AND b.status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_trips = $stmt->fetchColumn() ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings b JOIN booking_notifications bn ON bn.booking_id = b.booking_id WHERE bn.driver_id = ? AND b.status = 'cancelled'");
    $stmt->execute([$user_id]);
    $cancelled_trips = $stmt->fetchColumn() ?? 0;
    
    // Monthly performance for this driver
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(b.booking_date, '%Y-%m') as month,
            COUNT(*) as trips,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM bookings b
        JOIN booking_notifications bn ON bn.booking_id = b.booking_id
        WHERE bn.driver_id = ? AND bn.status = 'accepted' AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(b.booking_date, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$user_id]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Performance metrics
    $completion_rate = $total_trips > 0 ? round(($total_trips / ($total_trips + $pending_trips + $cancelled_trips)) * 100, 1) : 0;
    $cancellation_rate = ($total_trips + $pending_trips + $cancelled_trips) > 0 ? round(($cancelled_trips / ($total_trips + $pending_trips + $cancelled_trips)) * 100, 1) : 0;
    
    // Route performance
    $stmt = $pdo->query("
        SELECT r.from_location, r.to_location,
            COUNT(b.booking_id) as bookings,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as completed
        FROM routes r
        LEFT JOIN bookings b ON r.route_id = b.route_id
        GROUP BY r.route_id
        ORDER BY bookings DESC
        LIMIT 10
    ");
    $route_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .stat-box {
            text-align: center;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        .stat-box h3 {
            margin: 10px 0 0 0;
            font-size: 2.8rem;
            font-weight: 700;
        }
        .stat-box p {
            margin: 8px 0 0 0;
            font-size: 0.95rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .stat-box i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.8;
        }
        .stat-box.blue { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-box.green { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .stat-box.orange { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .stat-box.red { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .content-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }
        .content-card h5 {
            color: var(--primary-blue);
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--accent-blue);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 20px;
            padding: 10px;
        }
        
        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            border-bottom: 1px solid #e8e8e8;
            transition: all 0.2s ease;
        }
        .performance-item:hover {
            background: #f8f9fa;
            padding-left: 22px;
        }
        .performance-item:last-child {
            border-bottom: none;
        }
        .route-name {
            font-weight: 600;
            color: var(--primary-blue);
            flex: 1;
            font-size: 1rem;
        }
        .route-stats {
            display: flex;
            gap: 20px;
            align-items: center;
            font-size: 1.05rem;
        }
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #e0e0e0;
            overflow: hidden;
            margin-top: 8px;
            min-width: 150px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-green), var(--accent-blue));
        }
        
        .rating-box {
            text-align: center;
            padding: 40px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--accent-blue), var(--success-green));
            color: white;
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
            margin-bottom: 10px;
        }
        .rating-value {
            font-size: 4rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .rating-stars {
            font-size: 1.8rem;
            margin-bottom: 15px;
            letter-spacing: 5px;
        }
        .rating-box p {
            margin: 0;
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
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
            <a class="nav-link" href="passengers.php"><i class="fas fa-users"></i> Passengers</a>
            <a class="nav-link active" href="performance.php"><i class="fas fa-trophy"></i> Performance</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-chart-bar"></i> Performance</h2>
            <p class="mb-0">Track your performance metrics and statistics</p>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $total_trips; ?></h3>
                    <p>Completed Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box blue">
                    <i class="fas fa-hourglass"></i>
                    <h3><?php echo $pending_trips; ?></h3>
                    <p>Pending Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <i class="fas fa-percent"></i>
                    <h3><?php echo $completion_rate; ?>%</h3>
                    <p>Completion Rate</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box red">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo $cancelled_trips; ?></h3>
                    <p>Cancelled Trips</p>
                </div>
            </div>
        </div>

        <!-- Rating -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="rating-box">
                    <div class="rating-value">4.8</div>
                    <div class="rating-stars">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p style="margin: 0; font-size: 0.95rem;">Based on passenger feedback</p>
                </div>
            </div>
        </div>

        <!-- Monthly Performance Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="content-card">
                    <h5><i class="fas fa-calendar-alt"></i> Monthly Performance</h5>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Details -->
        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-info-circle"></i> Performance Summary</h5>
                    <div class="performance-item">
                        <div class="route-name">Completion Rate</div>
                        <div class="route-stats">
                            <span style="min-width: 50px; text-align: right; font-weight: 600; color: var(--success-green);"><?php echo $completion_rate; ?>%</span>
                        </div>
                    </div>
                    <div class="performance-item">
                        <div class="route-name">Cancellation Rate</div>
                        <div class="route-stats">
                            <span style="min-width: 50px; text-align: right; font-weight: 600; color: var(--danger-red);"><?php echo $cancellation_rate; ?>%</span>
                        </div>
                    </div>
                    <div class="performance-item">
                        <div class="route-name">Total Trips</div>
                        <div class="route-stats">
                            <span style="min-width: 50px; text-align: right; font-weight: 600;"><?php echo $total_trips + $pending_trips + $cancelled_trips; ?></span>
                        </div>
                    </div>
                    <div class="performance-item">
                        <div class="route-name">Avg Rating</div>
                        <div class="route-stats">
                            <span style="min-width: 50px; text-align: right; font-weight: 600; color: var(--warning-orange);">4.8 ⭐</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="fas fa-route"></i> Top Performing Routes</h5>
                    <?php if (!empty($route_performance)): ?>
                        <?php $count = 0; foreach($route_performance as $route): if ($count++ >= 5) break; ?>
                        <div class="performance-item">
                            <div class="route-name">
                                <?php echo htmlspecialchars($route['from_location']); ?> → <?php echo htmlspecialchars($route['to_location']); ?>
                            </div>
                            <div class="route-stats">
                                <span style="min-width: 50px; text-align: right; font-weight: 600;"><?php echo $route['bookings']; ?> trips</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">No route data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Performance Chart
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        const months = monthlyData.map(m => m.month).reverse();
        const completed = monthlyData.map(m => m.completed).reverse();
        const cancelled = monthlyData.map(m => m.cancelled).reverse();

        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Completed',
                        data: completed,
                        backgroundColor: 'rgba(39, 174, 96, 0.8)',
                        borderColor: '#27AE60',
                        borderWidth: 1
                    },
                    {
                        label: 'Cancelled',
                        data: cancelled,
                        backgroundColor: 'rgba(231, 76, 60, 0.8)',
                        borderColor: '#E74C3C',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
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
