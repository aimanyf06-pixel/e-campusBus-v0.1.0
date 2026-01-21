<?php
require_once '../includes/auth.php';
checkRole('admin');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get dashboard statistics
    $stats = [];
    
    // Total Users by Role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['total_users'] = array_sum($user_stats);
    $stats['students'] = $user_stats['student'] ?? 0;
    $stats['drivers'] = $user_stats['driver'] ?? 0;
    $stats['admins'] = $user_stats['admin'] ?? 0;
    
    // Total Routes
    $stmt = $pdo->query("SELECT COUNT(*) FROM routes WHERE status = 'active'");
    $stats['active_routes'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM routes");
    $stats['total_routes'] = $stmt->fetchColumn();
    
    // Total Buses
    $stmt = $pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'available'");
    $stats['available_buses'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM buses");
    $stats['total_buses'] = $stmt->fetchColumn();
    
    // Bookings Statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
    $stats['total_bookings'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    $stats['confirmed_bookings'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'");
    $stats['cancelled_bookings'] = $stmt->fetchColumn();
    
    // Today's Bookings
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_date) = CURDATE()");
    $stats['today_bookings'] = $stmt->fetchColumn();
    
    // Revenue Statistics
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bookings WHERE status != 'cancelled'");
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bookings WHERE status != 'cancelled' AND MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())");
    $stats['monthly_revenue'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM bookings WHERE status != 'cancelled' AND DATE(booking_date) = CURDATE()");
    $stats['daily_revenue'] = $stmt->fetchColumn();
    
    // Recent Bookings
    $stmt = $pdo->query("
        SELECT b.*, u.full_name, u.username, r.from_location, r.to_location
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN routes r ON b.route_id = r.route_id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Popular Routes
    $stmt = $pdo->query("
        SELECT r.from_location, r.to_location, r.base_fare, COUNT(b.booking_id) as booking_count
        FROM routes r
        LEFT JOIN bookings b ON r.route_id = b.route_id AND b.status != 'cancelled'
        GROUP BY r.route_id
        ORDER BY booking_count DESC
        LIMIT 5
    ");
    $popular_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Booking Trend (Last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(booking_date, '%Y-%m') as month, COUNT(*) as count
        FROM bookings
        WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System Status
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stats['active_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $stats['unread_notifications'] = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="admin-sidebar.css">
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
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
        }
        .sidebar-header p {
            color: rgba(255, 255, 255, 0.7);
            margin: 5px 0 0 0;
            font-size: 0.85rem;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--accent-blue);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
            font-weight: 600;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .stat-card.blue { border-left-color: var(--accent-blue); }
        .stat-card.green { border-left-color: var(--success-green); }
        .stat-card.orange { border-left-color: var(--warning-orange); }
        .stat-card.red { border-left-color: var(--danger-red); }
        .stat-card.cyan { border-left-color: var(--info-cyan); }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            float: right;
        }
        .stat-card.blue .icon { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-card.green .icon { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .stat-card.orange .icon { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .stat-card.red .icon { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        .stat-card.cyan .icon { background: rgba(22, 160, 133, 0.1); color: var(--info-cyan); }
        
        .stat-card h3 {
            margin: 0 0 5px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .stat-card p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.95rem;
        }
        .stat-card small {
            color: #95a5a6;
            font-size: 0.85rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }
        .chart-card h4 {
            margin: 0 0 20px 0;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }
        .table-card h4 {
            margin: 0 0 20px 0;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-pending { background: #FFF3CD; color: #856404; }
        .badge-confirmed { background: #D1ECF1; color: #0C5460; }
        .badge-cancelled { background: #F8D7DA; color: #721C24; }
        
        .quick-action-btn {
            width: 100%;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateX(5px);
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .system-status {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .system-status .status-icon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-icon.online { background: var(--success-green); animation: pulse 2s infinite; }
        .status-icon.warning { background: var(--warning-orange); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .route-badge {
            display: inline-block;
            padding: 5px 10px;
            background: linear-gradient(135deg, var(--accent-blue), var(--info-cyan));
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 2px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-bus-alt"></i> e-campusBus</h3>
            <p>Admin Panel</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
            <a class="nav-link" href="manage_routes.php"><i class="fas fa-route"></i> Manage Routes</a>
            <a class="nav-link" href="manage_buses.php"><i class="fas fa-bus"></i> Manage Buses</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a class="nav-link" href="reset_data.php"><i class="fas fa-database"></i> Reset Data</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
            <p class="mb-0">Welcome to e-campusBus Admin Panel</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards Row 1 -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>Total Users</p>
                    <small><?php echo $stats['students']; ?> Students • <?php echo $stats['drivers']; ?> Drivers</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                    <h3><?php echo number_format($stats['total_bookings']); ?></h3>
                    <p>Total Bookings</p>
                    <small><?php echo $stats['today_bookings']; ?> today</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-route"></i></div>
                    <h3><?php echo number_format($stats['active_routes']); ?></h3>
                    <p>Active Routes</p>
                    <small><?php echo $stats['total_routes']; ?> total routes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card cyan">
                    <div class="icon"><i class="fas fa-bus"></i></div>
                    <h3><?php echo number_format($stats['available_buses']); ?></h3>
                    <p>Available Buses</p>
                    <small><?php echo $stats['total_buses']; ?> total buses</small>
                </div>
            </div>
        </div>

        <!-- Statistics Cards Row 2 - Revenue & Bookings Status -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <h3>RM <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                    <small>RM <?php echo number_format($stats['monthly_revenue'], 2); ?> this month</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <h3><?php echo number_format($stats['pending_bookings']); ?></h3>
                    <p>Pending Bookings</p>
                    <small>Awaiting confirmation</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-ban"></i></div>
                    <h3><?php echo number_format($stats['cancelled_bookings']); ?></h3>
                    <p>Cancelled Bookings</p>
                    <small><?php echo number_format(($stats['cancelled_bookings'] / max($stats['total_bookings'], 1)) * 100, 1); ?>% cancellation rate</small>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-card">
                    <h4><i class="fas fa-chart-area"></i> Booking Trends (Last 6 Months)</h4>
                    <canvas id="bookingTrendChart" height="80"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-card">
                    <h4><i class="fas fa-chart-pie"></i> Booking Status Distribution</h4>
                    <canvas id="bookingStatusChart"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fas fa-circle" style="color: #27AE60;"></i> Confirmed</span>
                            <strong><?php echo $stats['confirmed_bookings']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fas fa-circle" style="color: #F39C12;"></i> Pending</span>
                            <strong><?php echo $stats['pending_bookings']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-circle" style="color: #E74C3C;"></i> Cancelled</span>
                            <strong><?php echo $stats['cancelled_bookings']; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Routes & System Status Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="table-card">
                    <h4><i class="fas fa-fire"></i> Popular Routes</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Fare</th>
                                    <th>Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($popular_routes as $route): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($route['from_location']); ?></strong>
                                        <i class="fas fa-arrow-right mx-2"></i>
                                        <strong><?php echo htmlspecialchars($route['to_location']); ?></strong>
                                    </td>
                                    <td>RM <?php echo number_format($route['base_fare'], 2); ?></td>
                                    <td>
                                        <span class="route-badge"><?php echo $route['booking_count']; ?> bookings</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="table-card">
                    <h4><i class="fas fa-server"></i> System Status</h4>
                    
                    <div class="system-status">
                        <div class="status-icon online"></div>
                        <div>
                            <strong>Database Connection</strong>
                            <p class="mb-0 text-muted small">All systems operational</p>
                        </div>
                    </div>
                    
                    <div class="system-status">
                        <div class="status-icon online"></div>
                        <div>
                            <strong>Active Users</strong>
                            <p class="mb-0 text-muted small"><?php echo $stats['active_users']; ?> users currently active</p>
                        </div>
                    </div>
                    
                    <div class="system-status">
                        <div class="status-icon <?php echo $stats['pending_bookings'] > 10 ? 'warning' : 'online'; ?>"></div>
                        <div>
                            <strong>Pending Actions</strong>
                            <p class="mb-0 text-muted small"><?php echo $stats['pending_bookings']; ?> bookings need attention</p>
                        </div>
                    </div>
                    
                    <div class="system-status">
                        <div class="status-icon online"></div>
                        <div>
                            <strong>Notifications</strong>
                            <p class="mb-0 text-muted small"><?php echo $stats['unread_notifications']; ?> unread notifications</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mt-3 mb-3">Quick Actions</h5>
                    <a href="manage_routes.php" class="btn btn-primary quick-action-btn">
                        <i class="fas fa-plus"></i> Add New Route
                    </a>
                    <a href="manage_buses.php" class="btn btn-success quick-action-btn">
                        <i class="fas fa-bus"></i> Manage Buses
                    </a>
                    <a href="reports.php" class="btn btn-info quick-action-btn">
                        <i class="fas fa-download"></i> Download Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Bookings Table -->
        <div class="row">
            <div class="col-12">
                <div class="table-card">
                    <h4><i class="fas fa-history"></i> Recent Bookings</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Student</th>
                                    <th>Route</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Seat</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_bookings as $booking): ?>
                                <tr>
                                    <td><strong>#<?php echo $booking['booking_id']; ?></strong></td>
                                    <td>
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($booking['full_name']); ?>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($booking['username']); ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo htmlspecialchars($booking['from_location']); ?>
                                            <i class="fas fa-arrow-right"></i>
                                            <?php echo htmlspecialchars($booking['to_location']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($booking['seat_number']); ?></span></td>
                                    <td><strong>RM <?php echo number_format($booking['amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $payment_class = '';
                                        switch($booking['payment_status']) {
                                            case 'paid': $payment_class = 'success'; break;
                                            case 'unpaid': $payment_class = 'warning'; break;
                                            case 'refunded': $payment_class = 'info'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $payment_class; ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Booking Trend Chart
        const trendCtx = document.getElementById('bookingTrendChart').getContext('2d');
        const trendData = <?php echo json_encode($monthly_trend); ?>;
        
        const trendLabels = trendData.map(item => {
            const [year, month] = item.month.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const trendCounts = trendData.map(item => item.count);
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Bookings',
                    data: trendCounts,
                    borderColor: '#3498DB',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
        
        // Booking Status Pie Chart
        const statusCtx = document.getElementById('bookingStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Confirmed', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [
                        <?php echo $stats['confirmed_bookings']; ?>,
                        <?php echo $stats['pending_bookings']; ?>,
                        <?php echo $stats['cancelled_bookings']; ?>
                    ],
                    backgroundColor: ['#27AE60', '#F39C12', '#E74C3C'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>