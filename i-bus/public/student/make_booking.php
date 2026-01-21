<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('student');

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
    
    // Handle booking submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $route_id = $_POST['route_id'];
        $seat_number = $_POST['seat_number'];
        $booking_date = $_POST['booking_date'] ?? date('Y-m-d');
        $booking_time = $_POST['booking_time'] ?? date('H:i:s');
        
        // Validate date is not in the past
        if (strtotime($booking_date) < strtotime(date('Y-m-d'))) {
            $error = "Cannot book for past dates!";
        } else {
            // Get route fare and driver info
            $stmt = $pdo->prepare("
                SELECT r.*, b.driver_id, b.bus_number, u.full_name as driver_name, u.status as driver_status
                FROM routes r
                LEFT JOIN buses b ON b.bus_id = (
                    SELECT bus_id FROM buses 
                    WHERE status = 'available' AND driver_id IS NOT NULL 
                    ORDER BY bus_id DESC LIMIT 1
                )
                LEFT JOIN users u ON u.id = b.driver_id
                WHERE r.route_id = ?
            ");
            $stmt->execute([$route_id]);
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($route) {
                // Resolve driver: prefer route's bus driver; fallback to any available driver
                $driverId = $route['driver_id'] ?? null;
                if (!$driverId) {
                    $driverInfo = get_driver_for_route($route_id);
                    if ($driverInfo) {
                        $driverId = $driverInfo['id'];
                        $route['driver_name'] = $driverInfo['full_name'];
                    }
                }
                
                // Require a driver
                if (!$driverId) {
                    $error = "No driver is assigned or available for this route right now. Please choose another route.";
                }
                
                // Check driver availability
                if (!isset($error)) {
                    $stmt = $pdo->prepare("
                        SELECT status FROM users WHERE id = ? AND role = 'driver'
                    ");
                    $stmt->execute([$driverId]);
                    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $driver_available = $driver && $driver['status'] === 'active';
                } else {
                    $driver_available = false;
                }
                
                // Check if seat already booked
                $stmt = $pdo->prepare("
                    SELECT * FROM bookings 
                    WHERE route_id = ? AND seat_number = ? AND booking_date = ? AND status != 'cancelled'
                ");
                $stmt->execute([$route_id, $seat_number, $booking_date]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Seat " . htmlspecialchars($seat_number) . " is already booked for this date!";
                } else if (!$driver_available) {
                    $error = "Driver is not available for this booking. Please select another route.";
                } else {
                    // Create booking
                    $stmt = $pdo->prepare("
                        INSERT INTO bookings (user_id, route_id, seat_number, booking_date, booking_time, status, amount) 
                        VALUES (?, ?, ?, ?, ?, 'pending', ?)
                    ");
                    $stmt->execute([$user_id, $route_id, $seat_number, $booking_date, $booking_time, $route['base_fare']]);
                    $booking_id = $pdo->lastInsertId();
                    
                    // Create notifications for ALL active drivers who have assigned a bus
                    $stmt = $pdo->prepare("
                        SELECT u.id FROM users u 
                        WHERE u.role = 'driver' AND u.status = 'active'
                        AND EXISTS (SELECT 1 FROM buses b WHERE b.driver_id = u.id)
                    ");
                    $stmt->execute();
                    $active_drivers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($active_drivers)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO booking_notifications (booking_id, driver_id, status, created_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ");
                        foreach ($active_drivers as $driver_id) {
                            $stmt->execute([$booking_id, $driver_id]);
                        }
                    }
                    
                    $success = "Booking successful! Booking ID: #" . $booking_id;
                }
            } else {
                $error = "Invalid route selected!";
            }
        }
    }
    
    // Get available routes with driver and bus info
    $stmt = $pdo->query("
        SELECT r.*,
               b.bus_id, b.bus_number, b.capacity,
               u.id as driver_id, u.full_name as driver_name, u.status as driver_status,
               (SELECT COUNT(*) FROM bookings WHERE route_id = r.route_id AND booking_date = CURDATE() AND status != 'cancelled') as booked_today
        FROM routes r
        LEFT JOIN buses b ON b.status = 'available'
        LEFT JOIN users u ON u.role = 'driver' AND u.status = 'active'
        WHERE r.status = 'active'
        GROUP BY r.route_id
        ORDER BY r.from_location, r.to_location ASC
    ");
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get next 30 days
    $available_dates = [];
    for ($i = 0; $i < 30; $i++) {
        $available_dates[] = date('Y-m-d', strtotime("+{$i} days"));
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Booking - e-campusBus System</title>
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
            --light-gray: #f5f7fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: #333;
        }

        /* Sidebar Navigation */
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
            font-size: 0.9rem;
            margin: 5px 0 0 0;
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
            padding: 30px 20px;
            min-height: 100vh;
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--accent-blue) 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 15px;
            margin-bottom: 40px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.1rem;
            margin: 0;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        /* Alert Styling */
        .alert-success, .alert-danger {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 30px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-green);
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-red);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Booking Container */
        .booking-container {
            max-width: 100%;
        }

        /* Route Card */
        .route-card {
            background: white;
            border-radius: 15px;
            padding: 0;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            border-left: 6px solid var(--accent-blue);
        }

        .route-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .route-card.unavailable {
            opacity: 0.7;
            border-left-color: var(--danger-red);
        }

        /* Route Header */
        .route-header {
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            padding: 25px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .route-locations {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .location-box {
            text-align: center;
        }

        .location-label {
            font-size: 0.75rem;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .location-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .route-arrow {
            color: var(--accent-blue);
            font-size: 1.5rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(8px); }
        }

        .route-status {
            display: flex;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge-available {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.15) 0%, rgba(39, 174, 96, 0.05) 100%);
            color: var(--success-green);
        }

        .badge-unavailable {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(231, 76, 60, 0.05) 100%);
            color: var(--danger-red);
        }

        /* Route Details Grid */
        .route-details {
            padding: 30px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            border-radius: 12px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .detail-item:hover {
            border-color: var(--accent-blue);
            background: linear-gradient(135deg, white 0%, #f8f9fa 100%);
        }

        .detail-icon {
            font-size: 2rem;
            color: var(--accent-blue);
            margin-bottom: 10px;
        }

        .detail-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Driver Info Section */
        .driver-info-section {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .driver-avatar {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .driver-details {
            flex: 1;
        }

        .driver-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .driver-status {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .driver-status.available {
            color: #2ecc71;
        }

        .driver-status.unavailable {
            color: #e74c3c;
        }

        /* Booking Form Section */
        .booking-form-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .form-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-blue);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 10px;
            font-size: 1rem;
        }

        /* Calendar Widget */
        .calendar-container {
            position: relative;
        }

        #calendar {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            color: var(--primary-blue);
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        #calendar:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Time Picker */
        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .time-input-group {
            position: relative;
        }

        .time-input-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            color: var(--primary-blue);
            transition: all 0.3s ease;
        }

        .time-input-group input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Seat Selection */
        .seat-selection {
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .seat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .seat-btn {
            width: 100%;
            aspect-ratio: 1;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-blue);
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .seat-btn:hover {
            border-color: var(--accent-blue);
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(52, 152, 219, 0.05) 100%);
        }

        .seat-btn.selected {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%);
            color: white;
            border-color: var(--accent-blue);
        }

        .seat-btn:disabled {
            background: #ddd;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .seat-legend {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            font-size: 0.9rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-box {
            width: 25px;
            height: 25px;
            border-radius: 5px;
        }

        .legend-available { background: white; border: 2px solid #e9ecef; }
        .legend-selected { background: var(--accent-blue); }
        .legend-booked { background: #ddd; }

        /* Booking Summary */
        .booking-summary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #1a2530 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
        }

        .summary-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .summary-value {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .total-fare {
            font-size: 2rem;
            font-weight: 700;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
        }

        /* Buttons */
        .btn-book {
            width: 100%;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-book:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.4);
        }

        .btn-book:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--accent-blue);
            margin-bottom: 20px;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }

        .empty-state-text {
            color: #999;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
                padding: 20px 15px;
            }

            .header {
                padding: 30px 20px;
            }

            .header h2 {
                font-size: 1.8rem;
            }

            .route-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .details-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .time-inputs {
                grid-template-columns: 1fr;
            }

            .driver-info-section {
                flex-direction: column;
                text-align: center;
            }

            .booking-summary {
                margin-top: 20px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                margin-left: 0;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .seat-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .header {
                padding: 25px 15px;
                border-radius: 10px;
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
            <h3><i class="fas fa-bus"></i> e-campusBus</h3>
            <p>Student Portal</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a class="nav-link active" href="make_booking.php"><i class="fas fa-ticket-alt"></i> Book Bus</a>
            <a class="nav-link" href="bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="routes.php"><i class="fas fa-route"></i> Routes</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h2><i class="fas fa-ticket-alt"></i> Book Your Trip</h2>
            <p>Select a route, choose your date & time, and reserve your seat</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <a href="bookings.php" class="btn btn-sm btn-outline-success ms-3">View My Bookings</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Booking Container -->
        <div class="booking-container">
            <?php if (empty($routes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-bus-alt"></i>
                    </div>
                    <h3 class="empty-state-title">No Routes Available</h3>
                    <p class="empty-state-text">There are currently no active routes. Please check back later.</p>
                    <a href="routes.php" class="btn btn-primary" style="background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%); border: none; padding: 10px 30px; border-radius: 8px;">
                        <i class="fas fa-route"></i> Browse All Routes
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($routes as $route): 
                    $max_seats = $route['capacity'] ?? 40;
                    $booked_today = $route['booked_today'] ?? 0;
                    $available_seats = $max_seats - $booked_today;
                    $is_available = $available_seats > 0 && $route['driver_status'] === 'active';
                    $occupied_seats = [];
                    
                    // Get booked seats for today
                    $stmt = $pdo->prepare("
                        SELECT seat_number FROM bookings 
                        WHERE route_id = ? AND booking_date = CURDATE() AND status != 'cancelled'
                    ");
                    $stmt->execute([$route['route_id']]);
                    $occupied_seats = $stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <div class="route-card <?php echo !$is_available ? 'unavailable' : ''; ?>">
                    <!-- Route Header -->
                    <div class="route-header">
                        <div class="route-locations">
                            <div class="location-box">
                                <div class="location-label">From</div>
                                <div class="location-name"><?php echo htmlspecialchars($route['from_location']); ?></div>
                            </div>
                            <div class="route-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                            <div class="location-box">
                                <div class="location-label">To</div>
                                <div class="location-name"><?php echo htmlspecialchars($route['to_location']); ?></div>
                            </div>
                        </div>
                        <div class="route-status">
                            <div class="status-badge <?php echo $is_available ? 'badge-available' : 'badge-unavailable'; ?>">
                                <i class="fas <?php echo $is_available ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $is_available ? 'Available' : 'Unavailable'; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Route Details -->
                    <div class="route-details">
                        <!-- Details Grid -->
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-road"></i></div>
                                <div class="detail-value"><?php echo $route['distance']; ?> km</div>
                                <div class="detail-label">Distance</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                <div class="detail-value"><?php echo $route['duration']; ?> min</div>
                                <div class="detail-label">Duration</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="detail-value">RM <?php echo number_format($route['base_fare'], 2); ?></div>
                                <div class="detail-label">Base Fare</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-chair"></i></div>
                                <div class="detail-value"><?php echo $available_seats; ?>/<?php echo $max_seats; ?></div>
                                <div class="detail-label">Available Seats</div>
                            </div>
                        </div>

                        <?php if (!empty($route['driver_name'])): ?>
                        <!-- Driver Info Section -->
                        <div class="driver-info-section">
                            <div class="driver-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="driver-details">
                                <div class="driver-name"><?php echo htmlspecialchars($route['driver_name']); ?></div>
                                <div class="driver-status <?php echo $route['driver_status'] === 'active' ? 'available' : 'unavailable'; ?>">
                                    <i class="fas <?php echo $route['driver_status'] === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $route['driver_status'] === 'active' ? 'Driver Available' : 'Driver Unavailable'; ?>
                                </div>
                            </div>
                            <?php if (!empty($route['bus_number'])): ?>
                            <div style="text-align: right;">
                                <div style="font-size: 0.9rem; opacity: 0.9;">Bus</div>
                                <div style="font-size: 1.2rem; font-weight: 700;"><?php echo htmlspecialchars($route['bus_number']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Booking Form -->
                        <?php if ($is_available): ?>
                        <form method="POST" id="bookingForm<?php echo $route['route_id']; ?>">
                            <input type="hidden" name="route_id" value="<?php echo $route['route_id']; ?>">
                            
                            <div class="booking-form-section">
                                <!-- Date & Time Selection -->
                                <div class="form-section-title">
                                    <i class="fas fa-calendar-alt"></i> Select Date & Time
                                </div>

                                <div class="form-group">
                                    <label for="calendar<?php echo $route['route_id']; ?>" class="form-label">
                                        <i class="fas fa-calendar"></i> Travel Date
                                    </label>
                                    <input type="date" id="calendar<?php echo $route['route_id']; ?>" name="booking_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required style="padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem;">
                                </div>

                                <div class="form-group">
                                    <label for="time<?php echo $route['route_id']; ?>" class="form-label">
                                        <i class="fas fa-clock"></i> Preferred Time
                                    </label>
                                    <input type="time" id="time<?php echo $route['route_id']; ?>" name="booking_time" class="form-control" value="<?php echo date('H:i'); ?>" required style="padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem;">
                                </div>

                                <!-- Seat Selection -->
                                <div class="form-section-title" style="margin-top: 30px;">
                                    <i class="fas fa-chair"></i> Select Your Seat
                                </div>

                                <div class="seat-selection">
                                    <div class="seat-grid">
                                        <?php
                                        $seats = [];
                                        for ($i = 1; $i <= $max_seats; $i++) {
                                            $seat_letter = chr(64 + ceil($i / 10)); // A, B, C, D, E
                                            $seat_number = $seat_letter . str_pad(($i % 10) ?: 10, 2, '0', STR_PAD_LEFT);
                                            $is_booked = in_array($seat_number, $occupied_seats);
                                            $seats[] = $seat_number;
                                        ?>
                                        <button type="button" class="seat-btn <?php echo $is_booked ? 'booked' : ''; ?>" 
                                                data-seat="<?php echo $seat_number; ?>"
                                                onclick="selectSeat(this, '<?php echo $route['route_id']; ?>')"
                                                <?php echo $is_booked ? 'disabled' : ''; ?>>
                                            <?php echo $seat_number; ?>
                                        </button>
                                        <?php } ?>
                                    </div>

                                    <div class="seat-legend">
                                        <div class="legend-item">
                                            <div class="legend-box legend-available"></div>
                                            <span>Available</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box legend-selected"></div>
                                            <span>Selected</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-box legend-booked"></div>
                                            <span>Booked</span>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="selectedSeat<?php echo $route['route_id']; ?>" name="seat_number" value="">

                                <!-- Booking Summary -->
                                <div class="booking-summary">
                                    <div class="summary-title">Booking Summary</div>
                                    <div class="summary-item">
                                        <span class="summary-label">Route</span>
                                        <span class="summary-value"><?php echo htmlspecialchars($route['from_location']); ?> → <?php echo htmlspecialchars($route['to_location']); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Selected Seat</span>
                                        <span class="summary-value" id="summarySeating<?php echo $route['route_id']; ?>">--</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Travel Date</span>
                                        <span class="summary-value" id="summaryDate<?php echo $route['route_id']; ?>">Select date</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Travel Time</span>
                                        <span class="summary-value" id="summaryTime<?php echo $route['route_id']; ?>">Select time</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Base Fare</span>
                                        <span class="summary-value">RM <?php echo number_format($route['base_fare'], 2); ?></span>
                                    </div>
                                    <div class="total-fare">
                                        <i class="fas fa-money-bill-wave"></i> RM <?php echo number_format($route['base_fare'], 2); ?>
                                    </div>
                                </div>

                                <!-- Book Button -->
                                <button type="submit" class="btn-book" id="bookBtn<?php echo $route['route_id']; ?>" disabled>
                                    <i class="fas fa-check-circle"></i> Confirm Booking
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="booking-form-section text-center">
                            <i class="fas fa-lock" style="font-size: 3rem; color: var(--danger-red); margin-bottom: 15px;"></i>
                            <h4 style="color: var(--danger-red); margin-bottom: 10px;">Booking Unavailable</h4>
                            <p style="color: #999;">
                                <?php if ($available_seats <= 0): ?>
                                    This route is fully booked for today. Please select another route or date.
                                <?php else: ?>
                                    Driver is currently unavailable. Please select another route.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectSeat(element, routeId) {
            // Remove previous selection in this route
            const form = document.getElementById('bookingForm' + routeId);
            if (!form) return;
            
            const selectedSeats = form.querySelectorAll('.seat-btn.selected');
            selectedSeats.forEach(seat => seat.classList.remove('selected'));
            
            // Add selection to clicked seat
            element.classList.add('selected');
            
            // Update hidden input
            document.getElementById('selectedSeat' + routeId).value = element.dataset.seat;
            
            // Update summary
            document.getElementById('summarySeating' + routeId).textContent = element.dataset.seat;
            
            // Check if all required fields are filled
            validateForm(routeId);
        }

        function validateForm(routeId) {
            const form = document.getElementById('bookingForm' + routeId);
            if (!form) return;
            
            const seat = document.getElementById('selectedSeat' + routeId).value;
            const date = document.getElementById('calendar' + routeId).value;
            const time = document.getElementById('time' + routeId).value;
            const bookBtn = document.getElementById('bookBtn' + routeId);
            
            // Update summary
            if (date) {
                const dateObj = new Date(date);
                document.getElementById('summaryDate' + routeId).textContent = dateObj.toLocaleDateString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
            
            if (time) {
                document.getElementById('summaryTime' + routeId).textContent = time;
            }
            
            // Enable button only if all fields are filled
            bookBtn.disabled = !(seat && date && time);
        }

        // Add event listeners to date and time inputs
        document.querySelectorAll('[id^="calendar"]').forEach(input => {
            input.addEventListener('change', function() {
                const routeId = this.id.replace('calendar', '');
                validateForm(routeId);
            });
        });

        document.querySelectorAll('[id^="time"]').forEach(input => {
            input.addEventListener('change', function() {
                const routeId = this.id.replace('time', '');
                validateForm(routeId);
            });
        });

        // Form submission validation
        document.querySelectorAll('[id^="bookingForm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const seat = this.querySelector('[name="seat_number"]').value;
                if (!seat) {
                    e.preventDefault();
                    alert('Please select a seat');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
