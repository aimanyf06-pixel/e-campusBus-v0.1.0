<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('driver');

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['full_name'];
$success_message = '';

// Handle delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
            "root",
            ""
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->beginTransaction();
        
        // Delete all notifications
        if (isset($_POST['delete_all_notifications'])) {
            $stmt = $pdo->prepare("DELETE FROM booking_notifications WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $count = $stmt->rowCount();
            $success_message = "Successfully deleted all {$count} notification(s)!";
        }
        
        // Delete all logs
        elseif (isset($_POST['delete_all_logs'])) {
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
            $stmt->execute([$driver_id]);
            $count = $stmt->rowCount();
            $success_message = "Successfully deleted all {$count} activity log(s)!";
        }
        
        // Delete all data (notifications + logs)
        elseif (isset($_POST['delete_all_driver_data'])) {
            $stmt = $pdo->prepare("DELETE FROM booking_notifications WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $notif_count = $stmt->rowCount();
            
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
            $stmt->execute([$driver_id]);
            $log_count = $stmt->rowCount();
            
            $success_message = "Successfully deleted all notifications ({$notif_count}) and activity logs ({$log_count})!";
        }
        
        $pdo->commit();
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error deleting data: " . $e->getMessage();
    }
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if driver has assigned a bus
    $stmt = $pdo->prepare("SELECT bus_id, bus_number FROM buses WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $assigned_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get filter
    $filter = $_GET['filter'] ?? 'pending';
    
    // Initialize variables
    $notifications = [];
    $stats = ['pending_count' => 0, 'accepted_count' => 0, 'rejected_count' => 0, 'total_count' => 0];
    
    // Only show notifications if driver has assigned a bus
    if ($assigned_bus) {
        // Get notifications with booking details
        $query = "
            SELECT bn.*, b.booking_id, b.booking_date, b.booking_time, b.seat_number, b.amount, b.status as booking_status,
                   r.from_location, r.to_location, r.distance, r.duration, r.base_fare,
                   u.full_name as student_name, u.phone_number, u.email
            FROM booking_notifications bn
            JOIN bookings b ON bn.booking_id = b.booking_id
            JOIN routes r ON b.route_id = r.route_id
            JOIN users u ON b.user_id = u.id
            WHERE bn.driver_id = ?
        ";
        
        $params = [$driver_id];
        
        if ($filter !== 'all') {
            $query .= " AND bn.status = ?";
            $params[] = $filter;
        }
        
        $query .= " ORDER BY bn.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_count,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                COUNT(*) as total_count
            FROM booking_notifications
            WHERE driver_id = ?
        ");
        $stmt->execute([$driver_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $notifications = [];
    $stats = ['pending_count' => 0, 'accepted_count' => 0, 'rejected_count' => 0, 'total_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Notifications - Driver Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
            --success-green: #27AE60;
            --warning-orange: #F39C12;
            --danger-red: #E74C3C;
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
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-blue) 0%, #1a2530 100%);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .notification-badge-nav {
            background: var(--danger-red);
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 24px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px 20px;
            min-height: 100vh;
        }

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

        /* Statistics Cards */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid var(--accent-blue);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.pending {
            border-top-color: var(--warning-orange);
        }

        .stat-card.accepted {
            border-top-color: var(--success-green);
        }

        .stat-card.rejected {
            border-top-color: var(--danger-red);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }

        .stat-label {
            color: #999;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-blue);
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%);
            color: white;
            border-color: var(--accent-blue);
        }

        /* Notification Cards */
        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border-left: 6px solid var(--accent-blue);
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .notification-card.accepted {
            border-left-color: var(--success-green);
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.02) 0%, white 100%);
        }

        .notification-card.rejected {
            border-left-color: var(--danger-red);
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.02) 0%, white 100%);
        }

        .notification-card.pending {
            border-left-color: var(--warning-orange);
            background: linear-gradient(135deg, rgba(243, 156, 18, 0.02) 0%, white 100%);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .notification-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .notification-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Route Info Grid */
        .route-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .info-label {
            font-size: 0.75rem;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        /* Student Info Section */
        .student-info {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2980B9 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .student-details {
            flex: 1;
        }

        .student-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .student-contact {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Action Buttons */
        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .btn-accept, .btn-reject {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-accept {
            background: linear-gradient(135deg, var(--success-green) 0%, #229954 100%);
            color: white;
        }

        .btn-accept:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, var(--danger-red) 0%, #C0392B 100%);
            color: white;
        }

        .btn-reject:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-accept:disabled, .btn-reject:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .rejection-reason {
            background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--danger-red);
            margin-top: 15px;
        }

        .rejection-label {
            font-size: 0.85rem;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .rejection-text {
            color: var(--danger-red);
            font-weight: 600;
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

        /* Delete All Button */
        .btn[name="delete_all_driver_data"] {
            background: linear-gradient(135deg, #C0392B 0%, #A93226 100%) !important;
            color: white !important;
            padding: 14px 40px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            box-shadow: 0 4px 15px rgba(192, 57, 43, 0.3) !important;
            font-size: 1rem !important;
        }

        .btn[name="delete_all_driver_data"]:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(192, 57, 43, 0.5) !important;
        }

        .btn[name="delete_all_driver_data"]:active {
            transform: translateY(0) !important;
        }

        /* Management Cards */
        .management-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .management-card.danger {
            border: 2px solid var(--danger-red);
        }

        .management-card h5 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .management-card p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .management-card button {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: white;
            font-size: 0.95rem;
        }

        .management-card button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .management-card button:active {
            transform: translateY(0);
        }
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

            .stat-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .notification-header {
                flex-direction: column;
            }

            .notification-actions {
                flex-direction: column;
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

            .stat-cards {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 25px 15px;
                border-radius: 10px;
            }

            .header h2 {
                font-size: 1.5rem;
            }

            .filter-tabs {
                flex-direction: column;
            }

            .filter-btn {
                width: 100%;
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
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link active" href="notifications.php" style="display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center;">
                    <i class="fas fa-bell"></i> Notifications
                </span>
                <?php if (($stats['pending_count'] ?? 0) > 0): ?>
                    <span class="notification-badge-nav"><?php echo ($stats['pending_count'] ?? 0) > 99 ? '99+' : ($stats['pending_count'] ?? 0); ?></span>
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
        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <div style="color: #28a745; font-size: 1.3rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="color: #155724;">
                    <strong><?php echo $success_message; ?></strong>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="header">
            <h2><i class="fas fa-bell"></i> Booking Notifications</h2>
            <p>Manage student booking requests and respond accordingly</p>
        </div>

        <!-- Statistics -->
        <div class="stat-cards">
            <div class="stat-card pending" onclick="filterNotifications('pending')">
                <div class="stat-icon" style="color: var(--warning-orange);">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-value" data-stat="pending"><?php echo $stats['pending_count']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card accepted" onclick="filterNotifications('accepted')">
                <div class="stat-icon" style="color: var(--success-green);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value" data-stat="accepted"><?php echo $stats['accepted_count']; ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-card rejected" onclick="filterNotifications('rejected')">
                <div class="stat-icon" style="color: var(--danger-red);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value" data-stat="rejected"><?php echo $stats['rejected_count']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card" onclick="filterNotifications('all')">
                <div class="stat-icon" style="color: var(--accent-blue);">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-value" data-stat="total"><?php echo $stats['total_count']; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs" style="margin-bottom: 30px;">
            <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-hourglass-half"></i> Pending
            </a>
            <a href="?filter=accepted" class="filter-btn <?php echo $filter === 'accepted' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Accepted
            </a>
            <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
            </a>
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> All
            </a>
        </div>

        <!-- Bus Assignment Required Alert -->
        <?php if (!$assigned_bus): ?>
            <div style="background: linear-gradient(135deg, #fff5e6 0%, #ffe8cc 100%); border-left: 4px solid #f39c12; padding: 25px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(243, 156, 18, 0.2);">
                <div style="display: flex; align-items: flex-start; gap: 20px;">
                    <div style="font-size: 2rem; color: #f39c12;">
                        <i class="fas fa-bus"></i>
                    </div>
                    <div style="flex: 1;">
                        <h4 style="color: #d68910; margin-bottom: 10px; font-weight: 700;">
                            <i class="fas fa-exclamation-triangle"></i> No Bus Assigned
                        </h4>
                        <p style="color: #7d5d0f; margin: 0 0 15px 0; line-height: 1.6;">
                            You need to assign a bus to your account before you can receive booking notifications from customers. This ensures that customers know which bus will be used for their journey.
                        </p>
                        <a href="assign_bus.php" style="display: inline-block; background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(243, 156, 18, 0.3);">
                            <i class="fas fa-plus-circle"></i> Assign a Bus Now
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid #27ae60; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <div style="color: #27ae60; font-size: 1.3rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div style="color: #1b5e20;">
                    <strong>Bus Assigned:</strong> <span style="font-weight: 600;"><?php echo htmlspecialchars($assigned_bus['bus_number']); ?></span> - Ready to receive notifications!
                </div>
            </div>
        <?php endif; ?>

        <!-- Notifications -->
        <div id="notifications-container">
        <?php if (!$assigned_bus): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="empty-state-title">Notifications Locked</h3>
                <p class="empty-state-text">
                    Please assign a bus to your account to start receiving booking notifications from customers.
                </p>
                <a href="assign_bus.php" style="display: inline-block; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fas fa-bus"></i> Assign Bus
                </a>
            </div>
        <?php elseif (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3 class="empty-state-title">No Notifications</h3>
                <p class="empty-state-text">
                    <?php 
                    if ($filter === 'pending') echo 'You have no pending booking requests at the moment.';
                    else if ($filter === 'accepted') echo 'You have not accepted any bookings yet.';
                    else if ($filter === 'rejected') echo 'You have not rejected any bookings yet.';
                    else echo 'There are no notifications to display.';
                    ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-card <?php echo $notif['status']; ?>">
                    <!-- Header -->
                    <div class="notification-header">
                        <div>
                            <div class="notification-title">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($notif['from_location']); ?> → <?php echo htmlspecialchars($notif['to_location']); ?>
                            </div>
                            <small style="color: #999;">Booking ID: #<?php echo $notif['booking_id']; ?> | Date: <?php echo date('M d, Y', strtotime($notif['booking_date'])); ?> | Time: <?php echo date('H:i A', strtotime($notif['booking_time'])); ?></small>
                        </div>
                        <span class="notification-badge badge-<?php echo $notif['status']; ?>">
                            <i class="fas fa-<?php echo $notif['status'] === 'pending' ? 'hourglass-half' : ($notif['status'] === 'accepted' ? 'check-circle' : 'times-circle'); ?>"></i>
                            <?php echo ucfirst($notif['status']); ?>
                        </span>
                    </div>

                    <!-- Route Info Grid -->
                    <div class="route-info-grid">
                        <div class="info-item">
                            <div class="info-label">Distance</div>
                            <div class="info-value"><?php echo $notif['distance']; ?> km</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Duration</div>
                            <div class="info-value"><?php echo $notif['duration']; ?> min</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Seat</div>
                            <div class="info-value"><?php echo htmlspecialchars($notif['seat_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Fare</div>
                            <div class="info-value">RM <?php echo number_format($notif['amount'], 2); ?></div>
                        </div>
                    </div>

                    <!-- Student Info -->
                    <div class="student-info">
                        <div class="student-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="student-details">
                            <div class="student-name"><?php echo htmlspecialchars($notif['student_name']); ?></div>
                            <div class="student-contact">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($notif['phone_number']); ?> | 
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($notif['email']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason (if rejected) -->
                    <?php if ($notif['status'] === 'rejected' && $notif['response_reason']): ?>
                        <div class="rejection-reason">
                            <div class="rejection-label"><i class="fas fa-exclamation-circle"></i> Rejection Reason</div>
                            <div class="rejection-text"><?php echo htmlspecialchars($notif['response_reason']); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <?php if ($notif['status'] === 'pending'): ?>
                        <div class="notification-actions" style="margin-top: 20px;">
                            <button type="button" class="btn-accept" onclick="acceptBooking(<?php echo $notif['booking_id']; ?>)">
                                <i class="fas fa-check-circle"></i> Accept Booking
                            </button>
                            <button type="button" class="btn-reject" onclick="openRejectModal(<?php echo $notif['booking_id']; ?>)">
                                <i class="fas fa-times-circle"></i> Reject Booking
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color: #999; margin-top: 20px;">
                            <small><i class="fas fa-info-circle"></i> Responded on <?php echo date('M d, Y \a\t H:i A', strtotime($notif['responded_at'])); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Reject Modal (Single Modal for All Bookings) -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please provide a reason for rejecting this booking:</p>
                    <form id="rejectForm">
                        <div class="mb-3">
                            <label for="rejectReason" class="form-label">Rejection Reason</label>
                            <textarea class="form-control" id="rejectReason" name="reason" rows="4" placeholder="e.g., Vehicle unavailable, Not on duty, Other reasons..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn">
                        <i class="fas fa-times-circle"></i> Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentRejectBookingId = null;
        const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
        
        function openRejectModal(bookingId) {
            currentRejectBookingId = bookingId;
            document.getElementById('rejectReason').value = '';
            rejectModal.show();
        }
        
        // Set up confirm button listener
        document.getElementById('confirmRejectBtn').addEventListener('click', function() {
            if (currentRejectBookingId) {
                rejectBooking(currentRejectBookingId);
            }
        });

        function filterNotifications(filter) {
            window.location.href = '?filter=' + filter;
        }

        function acceptBooking(bookingId) {
            if (confirm('Are you sure you want to accept this booking?')) {
                fetch('../api/handle_booking_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=accept&booking_id=' + bookingId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking accepted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request');
                });
            }
        }

        function rejectBooking(bookingId) {
            const reason = document.getElementById('rejectReason').value;
            if (!reason.trim()) {
                alert('Please provide a rejection reason');
                return;
            }

            fetch('../api/handle_booking_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reject&booking_id=' + bookingId + '&reason=' + encodeURIComponent(reason)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking rejected successfully!');
                    // Dismiss the modal and reload notifications
                    rejectModal.hide();
                    currentRejectBookingId = null;
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your request');
            });
        }
    </script>
</body>
</html>
