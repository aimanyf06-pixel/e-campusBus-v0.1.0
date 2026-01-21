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
    
    // Get date range from filters
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
    $report_type = $_GET['report_type'] ?? 'summary';
    
    // Booking Statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as total_revenue,
            AVG(CASE WHEN status != 'cancelled' THEN amount ELSE NULL END) as avg_booking_value
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Daily Breakdown
    $stmt = $pdo->prepare("
        SELECT 
            DATE(booking_date) as date,
            COUNT(*) as bookings,
            SUM(CASE WHEN status != 'cancelled' THEN amount ELSE 0 END) as revenue
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
        GROUP BY DATE(booking_date)
        ORDER BY date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Routes
    $stmt = $pdo->prepare("
        SELECT 
            r.from_location, r.to_location, r.base_fare,
            COUNT(b.booking_id) as booking_count,
            SUM(CASE WHEN b.status != 'cancelled' THEN b.amount ELSE 0 END) as revenue
        FROM routes r
        LEFT JOIN bookings b ON r.route_id = b.route_id 
            AND b.booking_date BETWEEN ? AND ?
        GROUP BY r.route_id
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Students
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name, u.username,
            COUNT(b.booking_id) as booking_count,
            SUM(CASE WHEN b.status != 'cancelled' THEN b.amount ELSE 0 END) as total_spent
        FROM users u
        JOIN bookings b ON u.id = b.user_id
        WHERE u.role = 'student' AND b.booking_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System Overview
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
    $total_students = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM routes WHERE status = 'active'");
    $total_routes = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'available'");
    $total_buses = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
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
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
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
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid;
        }
        .stat-card.blue { border-left-color: var(--accent-blue); }
        .stat-card.green { border-left-color: #27AE60; }
        .stat-card.orange { border-left-color: #F39C12; }
        .stat-card.red { border-left-color: #E74C3C; }
        .stat-card h3 { margin: 0; font-size: 2.5rem; font-weight: 700; color: var(--primary-blue); }
        .stat-card p { margin: 5px 0 0 0; color: #7f8c8d; }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #27AE60, #229954);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        @media print {
            .sidebar, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar no-print">
        <div class="sidebar-header">
            <h3><i class="fas fa-bus-alt"></i> e-campusBus</h3>
            <p>Admin Panel</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
            <a class="nav-link" href="manage_routes.php"><i class="fas fa-route"></i> Manage Routes</a>
            <a class="nav-link" href="manage_buses.php"><i class="fas fa-bus"></i> Manage Buses</a>
            <a class="nav-link active" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a class="nav-link" href="reset_data.php"><i class="fas fa-database"></i> Reset Data</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
                    <p class="mb-0">Comprehensive system reports and statistics</p>
                </div>
                <button onclick="window.print()" class="btn btn-light no-print">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="content-card no-print">
            <h4 class="mb-3"><i class="fas fa-filter"></i> Report Filters</h4>
            <form method="GET" class="filter-section">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
            <div class="text-center">
                <p class="text-muted mb-0">
                    <i class="fas fa-calendar"></i> Report Period: 
                    <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> to 
                    <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong>
                </p>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card blue">
                    <h3><?php echo number_format($booking_stats['total_bookings'] ?? 0); ?></h3>
                    <p><i class="fas fa-ticket-alt"></i> Total Bookings</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <h3>RM <?php echo number_format($booking_stats['total_revenue'] ?? 0, 2); ?></h3>
                    <p><i class="fas fa-money-bill-wave"></i> Total Revenue</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card orange">
                    <h3>RM <?php echo number_format($booking_stats['avg_booking_value'] ?? 0, 2); ?></h3>
                    <p><i class="fas fa-chart-line"></i> Avg. Booking Value</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card red">
                    <h3><?php echo number_format($booking_stats['cancelled'] ?? 0); ?></h3>
                    <p><i class="fas fa-ban"></i> Cancellations</p>
                </div>
            </div>
        </div>

        <!-- Booking Status Breakdown -->
        <div class="row">
            <div class="col-md-12">
                <div class="content-card">
                    <h4 class="mb-3"><i class="fas fa-chart-pie"></i> Booking Status Breakdown</h4>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <h2 class="text-success"><?php echo $booking_stats['confirmed'] ?? 0; ?></h2>
                                <p class="text-muted">Confirmed Bookings</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($booking_stats['total_bookings'] > 0) ? (($booking_stats['confirmed'] / $booking_stats['total_bookings']) * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <h2 class="text-warning"><?php echo $booking_stats['pending'] ?? 0; ?></h2>
                                <p class="text-muted">Pending Bookings</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($booking_stats['total_bookings'] > 0) ? (($booking_stats['pending'] / $booking_stats['total_bookings']) * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <h2 class="text-danger"><?php echo $booking_stats['cancelled'] ?? 0; ?></h2>
                                <p class="text-muted">Cancelled Bookings</p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo ($booking_stats['total_bookings'] > 0) ? (($booking_stats['cancelled'] / $booking_stats['total_bookings']) * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-12">
                <div class="content-card">
                    <h4 class="mb-3"><i class="fas fa-chart-area"></i> Daily Revenue & Bookings</h4>
                    <canvas id="dailyChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Routes -->
        <div class="row">
            <div class="col-md-6">
                <div class="content-card">
                    <h4 class="mb-3"><i class="fas fa-fire"></i> Top 10 Routes</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_routes as $route): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($route['from_location']); ?></strong>
                                        <i class="fas fa-arrow-right mx-1"></i>
                                        <strong><?php echo htmlspecialchars($route['to_location']); ?></strong>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo $route['booking_count']; ?></span></td>
                                    <td><strong>RM <?php echo number_format($route['revenue'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Students -->
            <div class="col-md-6">
                <div class="content-card">
                    <h4 class="mb-3"><i class="fas fa-user-graduate"></i> Top 10 Students</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Bookings</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_students as $student): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($student['full_name']); ?>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($student['username']); ?></small>
                                    </td>
                                    <td><span class="badge bg-success"><?php echo $student['booking_count']; ?></span></td>
                                    <td><strong>RM <?php echo number_format($student['total_spent'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="content-card">
            <h4 class="mb-3"><i class="fas fa-info-circle"></i> System Overview</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h3><?php echo $total_students; ?></h3>
                        <p class="text-muted">Total Students</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <i class="fas fa-route fa-3x text-success mb-3"></i>
                        <h3><?php echo $total_routes; ?></h3>
                        <p class="text-muted">Active Routes</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-3">
                        <i class="fas fa-bus fa-3x text-info mb-3"></i>
                        <h3><?php echo $total_buses; ?></h3>
                        <p class="text-muted">Available Buses</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Footer -->
        <div class="content-card text-center">
            <p class="text-muted mb-0">
                <i class="fas fa-clock"></i> Report Generated: <?php echo date('d M Y, h:i A'); ?> | 
                <i class="fas fa-user"></i> Generated by: <?php echo htmlspecialchars($full_name); ?>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_reverse($daily_data)); ?>;
        
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dailyData.map(item => new Date(item.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })),
                datasets: [{
                    label: 'Bookings',
                    data: dailyData.map(item => item.bookings),
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: '#3498DB',
                    borderWidth: 2,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (RM)',
                    data: dailyData.map(item => item.revenue),
                    backgroundColor: 'rgba(39, 174, 96, 0.7)',
                    borderColor: '#27AE60',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Bookings'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (RM)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    </script>
</body>
</html>
