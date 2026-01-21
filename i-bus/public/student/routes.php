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
    
    // Get search parameter
    $search = $_GET['search'] ?? '';
    
    // Get all active routes
    $query = "
        SELECT r.*, 
               COUNT(b.booking_id) as total_bookings,
               SUM(CASE WHEN b.user_id = ? THEN 1 ELSE 0 END) as my_bookings
        FROM routes r
        LEFT JOIN bookings b ON r.route_id = b.route_id
        WHERE r.status = 'active'
    ";
    
    if (!empty($search)) {
        $query .= " AND (r.from_location LIKE ? OR r.to_location LIKE ?)";
    }
    
    $query .= " GROUP BY r.route_id ORDER BY total_bookings DESC";
    
    $stmt = $pdo->prepare($query);
    $params = [$user_id];
    
    if (!empty($search)) {
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $stmt->execute($params);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM routes WHERE status = 'active'");
    $total_routes = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT route_id) as booked 
        FROM bookings 
        WHERE user_id = ? AND status IN ('confirmed', 'pending')
    ");
    $stmt->execute([$user_id]);
    $my_routes = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routes - e-campusBus System</title>
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
        .stat-box.cyan { border-top: 4px solid var(--info-cyan); }
        .stat-box.cyan i, .stat-box.cyan h3 { color: var(--info-cyan); }
        
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 30px;
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
        
        .route-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .route-card {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            border-left: 5px solid var(--accent-blue);
        }
        .route-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .route-card.popular {
            border-left-color: var(--warning-orange);
        }
        .route-card.booked {
            border-left-color: var(--success-green);
        }
        
        .route-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .route-title {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        .route-locations {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        .location-badge {
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .location-badge i {
            color: var(--accent-blue);
        }
        .arrow-separator {
            color: var(--accent-blue);
            font-size: 1.5rem;
        }
        
        .route-badges {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .badge-popular {
            background: linear-gradient(135deg, var(--warning-orange), #e67e22);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .badge-booked {
            background: linear-gradient(135deg, var(--success-green), #229954);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .route-body {
            padding: 25px;
        }
        .route-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .detail-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        .detail-box i {
            font-size: 1.8rem;
            color: var(--accent-blue);
            margin-bottom: 10px;
        }
        .detail-box .value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }
        .detail-box .label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        
        .route-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        .booking-stats {
            display: flex;
            gap: 25px;
        }
        .booking-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .booking-stat .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-blue);
        }
        .booking-stat.my-bookings .number {
            color: var(--success-green);
        }
        .booking-stat .text {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        
        .btn-book {
            background: linear-gradient(135deg, var(--accent-blue), var(--primary-blue));
            border: none;
            color: white;
            padding: 12px 40px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            color: white;
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
            <a class="nav-link" href="bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link active" href="routes.php"><i class="fas fa-route"></i> Routes</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-map-marked-alt"></i> Available Routes</h2>
            <p class="mb-0">Browse all available bus routes</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box blue">
                    <i class="fas fa-route"></i>
                    <h3><?php echo $total_routes ?? 0; ?></h3>
                    <p>Total Routes</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box green">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $my_routes ?? 0; ?></h3>
                    <p>My Active Routes</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box cyan">
                    <i class="fas fa-star"></i>
                    <h3><?php echo !empty($routes) ? $routes[0]['total_bookings'] : 0; ?></h3>
                    <p>Most Popular Route</p>
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="content-card">
            <div class="search-box">
                <form method="GET" action="">
                    <input type="text" name="search" placeholder="Search by location (from/to)..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>

            <!-- Routes List -->
            <?php if (!empty($routes)): ?>
            <div class="route-list">
                <?php 
                $max_bookings = !empty($routes) ? max(array_column($routes, 'total_bookings')) : 0;
                foreach($routes as $route): 
                    $is_popular = $route['total_bookings'] >= 5 && $route['total_bookings'] == $max_bookings;
                    $is_booked = $route['my_bookings'] > 0;
                    $card_class = $is_booked ? 'booked' : ($is_popular ? 'popular' : '');
                ?>
                <div class="route-card <?php echo $card_class; ?>">
                    <div class="route-header">
                        <div class="route-title">
                            <div class="route-locations">
                                <div class="location-badge">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($route['from_location']); ?>
                                </div>
                                <i class="fas fa-arrow-right arrow-separator"></i>
                                <div class="location-badge">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($route['to_location']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="route-badges">
                            <?php if ($is_popular): ?>
                            <span class="badge-popular">
                                <i class="fas fa-fire"></i> Popular
                            </span>
                            <?php endif; ?>
                            <?php if ($is_booked): ?>
                            <span class="badge-booked">
                                <i class="fas fa-check-circle"></i> Booked
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="route-body">
                        <div class="route-details">
                            <div class="detail-box">
                                <i class="fas fa-road"></i>
                                <div class="value"><?php echo htmlspecialchars($route['distance']); ?></div>
                                <div class="label">Distance</div>
                            </div>
                            <div class="detail-box">
                                <i class="fas fa-clock"></i>
                                <div class="value"><?php echo htmlspecialchars($route['duration']); ?></div>
                                <div class="label">Duration</div>
                            </div>
                            <div class="detail-box">
                                <i class="fas fa-money-bill-wave"></i>
                                <div class="value">RM <?php echo number_format($route['base_fare'], 2); ?></div>
                                <div class="label">Base Fare</div>
                            </div>
                            <div class="detail-box">
                                <i class="fas fa-circle" style="color: var(--success-green);"></i>
                                <div class="value" style="color: var(--success-green);">Active</div>
                                <div class="label">Status</div>
                            </div>
                        </div>
                        
                        <div class="route-footer">
                            <div class="booking-stats">
                                <div class="booking-stat">
                                    <div class="number"><?php echo $route['total_bookings']; ?></div>
                                    <div class="text">Total Bookings</div>
                                </div>
                                <div class="booking-stat my-bookings">
                                    <div class="number"><?php echo $route['my_bookings']; ?></div>
                                    <div class="text">My Bookings</div>
                                </div>
                            </div>
                            <a href="make_booking.php?route_id=<?php echo $route['route_id']; ?>" class="btn-book">
                                <i class="fas fa-ticket-alt"></i> Book This Route
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h4>No Routes Found</h4>
                <p><?php echo !empty($search) ? 'No routes match your search' : 'No routes available at the moment'; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
