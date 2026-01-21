<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('student');

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
    
    // Get booking statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as total_spent
        FROM bookings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get this month's bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as month_bookings
        FROM bookings 
        WHERE user_id = ? 
        AND MONTH(booking_date) = MONTH(CURDATE()) 
        AND YEAR(booking_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$user_id]);
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get upcoming bookings (next 5)
    $stmt = $pdo->prepare("
        SELECT b.*, r.from_location, r.to_location, r.distance, r.duration,
               u.full_name as driver_name
        FROM bookings b
        JOIN routes r ON b.route_id = r.route_id
        LEFT JOIN buses bus ON r.route_id = bus.bus_id
        LEFT JOIN users u ON bus.driver_id = u.id
        WHERE b.user_id = ? 
        AND b.status IN ('pending', 'confirmed')
        AND b.booking_date >= CURDATE()
        ORDER BY b.booking_date ASC, b.booking_time ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $upcoming_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activity (last 8)
    $stmt = $pdo->prepare("
        SELECT b.*, r.from_location, r.to_location
        FROM bookings b
        JOIN routes r ON b.route_id = r.route_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get popular routes
    $stmt = $pdo->prepare("
        SELECT r.from_location, r.to_location, COUNT(b.booking_id) as booking_count
        FROM routes r
        LEFT JOIN bookings b ON r.route_id = b.route_id
        WHERE b.user_id = ?
        GROUP BY r.route_id
        ORDER BY booking_count DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $popular_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly booking trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(booking_date, '%Y-%m') as month,
            COUNT(*) as bookings
        FROM bookings
        WHERE user_id = ?
        AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$user_id]);
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - e-campusBus System</title>
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
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .stat-box i {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .stat-box h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .stat-box p {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .stat-box.blue { border-top: 4px solid var(--accent-blue); }
        .stat-box.blue i, .stat-box.blue h3 { color: var(--accent-blue); }
        .stat-box.green { border-top: 4px solid var(--success-green); }
        .stat-box.green i, .stat-box.green h3 { color: var(--success-green); }
        .stat-box.orange { border-top: 4px solid var(--warning-orange); }
        .stat-box.orange i, .stat-box.orange h3 { color: var(--warning-orange); }
        .stat-box.red { border-top: 4px solid var(--danger-red); }
        .stat-box.red i, .stat-box.red h3 { color: var(--danger-red); }
        .stat-box.cyan { border-top: 4px solid var(--info-cyan); }
        .stat-box.cyan i, .stat-box.cyan h3 { color: var(--info-cyan); }
        
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
        
        .booking-card {
            background: white;
            border-left: 4px solid var(--accent-blue);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .booking-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }
        .booking-card .route-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }
        .booking-card .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .booking-detail-item {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 0.9rem;
        }
        .booking-detail-item i {
            margin-right: 8px;
            color: var(--accent-blue);
            width: 20px;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-confirmed {
            background-color: var(--success-green);
            color: white;
        }
        .badge-pending {
            background-color: var(--warning-orange);
            color: white;
        }
        .badge-cancelled {
            background-color: var(--danger-red);
            color: white;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-modern thead th {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: white;
            padding: 12px;
            font-weight: 600;
            text-align: left;
            border: none;
        }
        .table-modern thead th:first-child {
            border-radius: 10px 0 0 0;
        }
        .table-modern thead th:last-child {
            border-radius: 0 10px 0 0;
        }
        .table-modern tbody td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .table-modern tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-bus-alt"></i> e-campusBus</h3>
            <p>Student Portal</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a class="nav-link" href="make_booking.php"><i class="fas fa-ticket-alt"></i> Book Bus</a>
            <a class="nav-link" href="bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="routes.php"><i class="fas fa-route"></i> Routes</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><?php echo get_greeting(); ?>, <?php echo htmlspecialchars($full_name); ?>!</h2>
            <p class="mb-0">Welcome to your student dashboard</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <i class="fas fa-ticket-alt"></i>
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
                    <p>Confirmed Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending Bookings</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box cyan">
                    <i class="fas fa-calendar-alt"></i>
                    <h3><?php echo $month_stats['month_bookings'] ?? 0; ?></h3>
                    <p>This Month</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Upcoming Bookings -->
            <div class="col-lg-8">
                <div class="content-card">
                    <h5><i class="fas fa-calendar-check"></i> Upcoming Bookings</h5>
                    <?php if (!empty($upcoming_bookings)): ?>
                        <?php foreach($upcoming_bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="route-title">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($booking['from_location']); ?> 
                                    <i class="fas fa-arrow-right mx-2"></i> 
                                    <?php echo htmlspecialchars($booking['to_location']); ?>
                                </div>
                                <span class="badge badge-<?php echo $booking['status']; ?>">
                                    <?php echo strtoupper($booking['status']); ?>
                                </span>
                            </div>
                            <div class="booking-details">
                                <div class="booking-detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                                <div class="booking-detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></span>
                                </div>
                                <div class="booking-detail-item">
                                    <i class="fas fa-chair"></i>
                                    <span>Seat <?php echo htmlspecialchars($booking['seat_number']); ?></span>
                                </div>
                                <div class="booking-detail-item">
                                    <i class="fas fa-money-bill"></i>
                                    <span>RM <?php echo number_format($booking['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming bookings</p>
                        <a href="make_booking.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus"></i> Book Now
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Monthly Trend Chart -->
                <div class="content-card">
                    <h5><i class="fas fa-chart-line"></i> Monthly Booking Trend</h5>
                    <div class="chart-container">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="content-card mb-3">
                    <h5><i class="fas fa-info-circle"></i> Quick Stats</h5>
                    <div style="border-bottom: 1px solid #e0e0e0; padding: 12px 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-weight: 600; color: var(--primary-blue);">Total Spent</span>
                            <span style="color: var(--success-green); font-weight: 700; font-size: 1.1rem;">
                                RM <?php echo number_format($stats['total_spent'] ?? 0, 2); ?>
                            </span>
                        </div>
                    </div>
                    <div style="border-bottom: 1px solid #e0e0e0; padding: 12px 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-weight: 600; color: var(--primary-blue);">Cancellation Rate</span>
                            <span style="color: var(--danger-red); font-weight: 700;">
                                <?php 
                                $total = $stats['total'] ?? 0;
                                $cancelled = $stats['cancelled'] ?? 0;
                                echo $total > 0 ? round(($cancelled / $total) * 100, 1) : 0;
                                ?>%
                            </span>
                        </div>
                    </div>
                    <div style="padding: 12px 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-weight: 600; color: var(--primary-blue);">Member Since</span>
                            <span style="color: #666;">
                                <?php echo date('M Y'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Popular Routes -->
                <div class="content-card">
                    <h5><i class="fas fa-star"></i> My Popular Routes</h5>
                    <?php if (!empty($popular_routes)): ?>
                        <?php foreach($popular_routes as $route): ?>
                        <div style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                            <div style="font-weight: 600; color: var(--primary-blue); margin-bottom: 5px;">
                                <?php echo htmlspecialchars($route['from_location']); ?> → 
                                <?php echo htmlspecialchars($route['to_location']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">
                                <i class="fas fa-ticket-alt"></i> 
                                <?php echo $route['booking_count']; ?> bookings
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p style="text-align: center; color: #999; padding: 20px;">No routes booked yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-card">
            <h5><i class="fas fa-history"></i> Recent Activity</h5>
            <?php if (!empty($recent_activity)): ?>
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Seat</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_activity as $activity): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($activity['from_location']); ?></strong>
                            <i class="fas fa-arrow-right mx-1" style="font-size: 0.8rem; color: var(--accent-blue);"></i>
                            <strong><?php echo htmlspecialchars($activity['to_location']); ?></strong>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($activity['booking_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($activity['booking_time'])); ?></td>
                        <td><?php echo htmlspecialchars($activity['seat_number']); ?></td>
                        <td>RM <?php echo number_format($activity['amount'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $activity['status']; ?>">
                                <?php echo strtoupper($activity['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No recent activity</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Trend Chart
        const monthlyData = <?php echo json_encode($monthly_trend); ?>;
        const months = monthlyData.map(m => m.month);
        const bookings = monthlyData.map(m => m.bookings);

        const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Bookings',
                    data: bookings,
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderColor: '#3498DB',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3498DB',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>