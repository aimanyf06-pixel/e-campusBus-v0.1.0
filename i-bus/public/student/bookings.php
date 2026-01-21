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
    
    // Handle cancellation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND user_id = ?");
        if ($stmt->execute([$booking_id, $user_id])) {
            $success = "Booking cancelled successfully!";
        }
    }
    
    // Get filter
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
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
    
    // Get bookings with filter
    $query = "
        SELECT b.*, r.from_location, r.to_location, r.distance, r.duration
        FROM bookings b
        JOIN routes r ON b.route_id = r.route_id
        WHERE b.user_id = ?
    ";
    
    if ($filter !== 'all') {
        $query .= " AND b.status = ?";
    }
    
    if (!empty($search)) {
        $query .= " AND (r.from_location LIKE ? OR r.to_location LIKE ?)";
    }
    
    $query .= " ORDER BY b.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $params = [$user_id];
    
    if ($filter !== 'all') {
        $params[] = $filter;
    }
    
    if (!empty($search)) {
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - e-campusBus System</title>
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
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .stat-box i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-box h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .stat-box p {
            margin: 0;
            font-size: 0.9rem;
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
        
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .filter-tab:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }
        .filter-tab.active {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
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
        .booking-card.confirmed { border-left-color: var(--success-green); }
        .booking-card.pending { border-left-color: var(--warning-orange); }
        .booking-card.cancelled { border-left-color: var(--danger-red); opacity: 0.7; }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .route-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 0.9rem;
        }
        .detail-item i {
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
        
        .btn-cancel {
            background: linear-gradient(135deg, var(--danger-red), #c0392b);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
            color: white;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--accent-blue);
        }
        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
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
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a class="nav-link" href="make_booking.php"><i class="fas fa-ticket-alt"></i> Book Bus</a>
            <a class="nav-link active" href="bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="routes.php"><i class="fas fa-route"></i> Routes</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-list-alt"></i> My Bookings</h2>
            <p class="mb-0">View and manage all your bus bookings</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <i class="fas fa-clipboard-list"></i>
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['confirmed'] ?? 0; ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box red">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo $stats['cancelled'] ?? 0; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="content-card">
            <div class="search-box">
                <form method="GET" action="">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="text" name="search" placeholder="Search by location..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>

            <div class="filter-tabs">
                <a href="?filter=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All
                </a>
                <a href="?filter=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                </a>
                <a href="?filter=confirmed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Confirmed
                </a>
                <a href="?filter=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Cancelled
                </a>
            </div>

            <?php if (!empty($bookings)): ?>
                <?php foreach($bookings as $booking): ?>
                <div class="booking-card <?php echo $booking['status']; ?>">
                    <div class="booking-header">
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
                        <div class="detail-item">
                            <i class="fas fa-hashtag"></i>
                            <span><strong>ID:</strong> <?php echo htmlspecialchars($booking['booking_id']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-chair"></i>
                            <span>Seat <?php echo htmlspecialchars($booking['seat_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-money-bill"></i>
                            <span>RM <?php echo number_format($booking['amount'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-credit-card"></i>
                            <span><?php echo ucfirst($booking['payment_status']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                    <div class="mt-3">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                            <button type="submit" name="cancel_booking" class="btn-cancel" 
                                    onclick="return confirm('Are you sure you want to cancel this booking?')">
                                <i class="fas fa-times"></i> Cancel Booking
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Bookings Found</h4>
                <p>You don't have any bookings<?php echo $filter !== 'all' ? ' with status: ' . $filter : ''; ?></p>
                <a href="make_booking.php" class="btn btn-primary mt-3" style="background: var(--accent-blue); border: none; padding: 10px 30px; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-plus"></i> Book Now
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
