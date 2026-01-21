<?php
require_once '../includes/auth.php';

// Check if user is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit;
}

$driver_id = $_SESSION['user_id'];
$driver_name = $_SESSION['full_name'];
$message = '';
$error = '';

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle bus assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_bus'])) {
        $bus_id = $_POST['bus_id'] ?? '';
        
        if (empty($bus_id)) {
            $error = 'Please select a bus';
        } else {
            // Check if bus exists and is available
            $stmt = $pdo->prepare("SELECT * FROM buses WHERE bus_id = ? AND status IN ('available', 'in_use')");
            $stmt->execute([$bus_id]);
            $bus = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bus) {
                $error = 'Selected bus is not available';
            } elseif ($bus['driver_id'] && $bus['driver_id'] != $driver_id) {
                // Check if bus is already assigned to another driver
                $error = 'This bus is already assigned to another driver. Please select a different bus.';
            } else {
                // Verify driver doesn't already have a different bus assigned
                $stmt = $pdo->prepare("SELECT bus_id FROM buses WHERE driver_id = ? AND bus_id != ?");
                $stmt->execute([$driver_id, $bus_id]);
                $existing_bus = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_bus) {
                    // Unassign previous bus
                    $stmt = $pdo->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ? AND bus_id != ?");
                    $stmt->execute([$driver_id, $bus_id]);
                }
                
                // Assign new bus
                $stmt = $pdo->prepare("UPDATE buses SET driver_id = ? WHERE bus_id = ? AND (driver_id IS NULL OR driver_id = ?)");
                $result = $stmt->execute([$driver_id, $bus_id, $driver_id]);
                
                if ($result && $stmt->rowCount() > 0) {
                    // Log activity
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, details, created_at)
                        VALUES (?, 'bus_assigned', ?, NOW())
                    ");
                    $stmt->execute([$driver_id, 'Assigned bus #' . $bus['bus_number']]);
                    
                    $message = 'Bus assigned successfully! You can now receive booking notifications.';
                } else {
                    $error = 'Failed to assign bus. The bus may have been assigned to another driver just now. Please try again.';
                }
            }
        }
    }
    
    // Get current assigned bus
    $stmt = $pdo->prepare("SELECT * FROM buses WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $assigned_bus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all available buses with driver assignment info
    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name as assigned_driver_name
        FROM buses b
        LEFT JOIN users u ON b.driver_id = u.id
        WHERE b.status IN ('available', 'in_use')
        ORDER BY b.bus_number ASC
    ");
    $stmt->execute();
    $available_buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get bus statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_buses,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_count,
            COUNT(CASE WHEN status = 'in_use' THEN 1 END) as in_use_count,
            COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_count,
            COUNT(CASE WHEN driver_id IS NOT NULL THEN 1 END) as assigned_count,
            AVG(capacity) as avg_capacity,
            MAX(capacity) as max_capacity,
            MIN(capacity) as min_capacity
        FROM buses
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent assignments
    $stmt = $pdo->query("
        SELECT u.full_name, b.bus_number, al.created_at
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        JOIN buses b ON CAST(SUBSTRING_INDEX(al.details, '#', -1) AS UNSIGNED) = b.bus_id
        WHERE al.action = 'bus_assigned'
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $recent_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Fleet Management - i-Bus Driver Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .container-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
            padding: 30px 0;
            color: white;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.4);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 0 20px 30px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .sidebar-brand h3 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar ul {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar ul li {
            margin-bottom: 5px;
        }

        .sidebar ul li a {
            color: #b0b0b0;
            text-decoration: none;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .sidebar ul li a:hover {
            background: rgba(74, 144, 226, 0.2);
            color: #ffffff;
            padding-left: 20px;
        }

        .sidebar ul li a.active {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.4);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            overflow-y: auto;
            max-height: 100vh;
        }

        /* ===== HEADER ===== */
        .page-header {
            margin-bottom: 40px;
        }

        .breadcrumb-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .breadcrumb-custom span {
            color: rgba(255,255,255,0.7);
        }

        .breadcrumb-custom span.active {
            color: white;
            font-weight: 600;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .page-title h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .page-title .title-icon {
            font-size: 2.5rem;
            color: #ffd700;
            animation: float 3s ease-in-out infinite;
        }

        .page-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 1.1rem;
            margin-top: 10px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* ===== ALERTS ===== */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            backdrop-filter: blur(10px);
            animation: slideInDown 0.5s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success-custom {
            background: rgba(72, 187, 120, 0.15);
            border-left: 4px solid #48BB78;
            color: #2F855A;
        }

        .alert-danger-custom {
            background: rgba(239, 68, 68, 0.15);
            border-left: 4px solid #ef4444;
            color: #dc2626;
        }

        .alert-custom .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .alert-custom .alert-content h4 {
            margin: 0 0 5px 0;
            font-weight: 700;
        }

        .alert-custom .alert-content p {
            margin: 0;
            opacity: 0.9;
        }

        /* ===== STATS SECTION ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            padding: 25px;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .stat-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* ===== CARDS ===== */
        .card-modern {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 30px;
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-header-modern {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            color: white;
            padding: 30px;
            border: none;
        }

        .card-header-modern h3 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .card-header-modern .header-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            margin: 0;
        }

        .card-body-modern {
            padding: 35px;
        }

        /* ===== CURRENT BUS SECTION ===== */
        .current-bus-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .bus-visual {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .bus-visual::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .bus-visual .bus-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .bus-visual .bus-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .bus-details-info {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.2);
            position: relative;
            z-index: 1;
        }

        .bus-detail-item {
            text-align: center;
        }

        .bus-detail-item .label {
            opacity: 0.8;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .bus-detail-item .value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .bus-info-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
        }

        .bus-info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .bus-info-item:last-child {
            border-bottom: none;
        }

        .bus-info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .bus-info-content h5 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .bus-info-content p {
            margin: 0;
            color: #999;
            font-size: 0.9rem;
        }

        /* ===== BUS GRID ===== */
        .bus-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .bus-card {
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .bus-card input[type="radio"] {
            display: none;
        }

        .bus-card-content {
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .bus-card-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4A90E2 0%, #50C9C3 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .bus-card input:checked + .bus-card-content {
            border-color: #4A90E2;
            background: linear-gradient(135deg, rgba(74,144,226,0.1) 0%, rgba(80,201,195,0.1) 100%);
            box-shadow: 0 10px 30px rgba(74,144,226,0.3);
            transform: translateY(-5px);
        }

        .bus-card input:checked + .bus-card-content::before {
            transform: scaleX(1);
        }

        .bus-card-icon {
            font-size: 3rem;
            color: #4A90E2;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .bus-card input:checked + .bus-card-content .bus-card-icon {
            color: #50C9C3;
            font-size: 3.5rem;
        }

        .bus-card-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .bus-card-capacity {
            color: #4A90E2;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        .bus-card-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-in-use {
            background: #fff3cd;
            color: #856404;
        }

        .bus-card:hover .bus-card-content {
            border-color: #50C9C3;
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(80,201,195,0.2);
        }

        /* ===== LOCKED/DISABLED BUS ===== */
        .bus-card.locked {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .bus-card.locked .bus-card-content {
            background: #f5f5f5;
            border-color: #ddd;
            position: relative;
        }

        .bus-card.locked .bus-card-content::before {
            display: none;
        }

        .bus-card.locked:hover .bus-card-content {
            transform: none;
            box-shadow: none;
            border-color: #ddd;
        }

        .bus-card.locked .bus-card-icon {
            color: #999;
        }

        .bus-card.locked .bus-card-number,
        .bus-card.locked .bus-card-capacity {
            color: #999;
        }

        .bus-card.locked input {
            cursor: not-allowed;
        }

        .locked-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 10;
        }

        .locked-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }

        /* ===== FILTERS ===== */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .filter-btn {
            background: white;
            border: 2px solid #e0e0e0;
            color: #2c3e50;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-color: transparent;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(74,144,226,0.4);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-color: transparent;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74,144,226,0.5);
        }

        .filter-btn i {
            font-size: 1rem;
        }

        /* ===== BUTTONS ===== */
        .btn-custom {
            padding: 14px 35px;
            border-radius: 10px;
            font-weight: 700;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(74,144,226,0.4);
        }

        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(74,144,226,0.5);
            color: white;
        }

        .btn-secondary-custom {
            background: #6c757d;
            color: white;
        }

        .btn-secondary-custom:hover {
            background: #5a6268;
            color: white;
            transform: translateY(-2px);
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        /* ===== RECENT ASSIGNMENTS ===== */
        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 60px;
            width: 2px;
            height: 60px;
            background: #e0e0e0;
        }

        .timeline-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .timeline-content h6 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .timeline-content p {
            margin: 0;
            color: #999;
            font-size: 0.9rem;
        }

        /* ===== HELP TEXT ===== */
        .help-section {
            background: linear-gradient(135deg, #E8F4F8 0%, #F0F9FF 100%);
            border-left: 4px solid #4A90E2;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }

        .help-section h6 {
            color: #4A90E2;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .help-section p {
            color: #555;
            margin: 8px 0;
            font-size: 0.95rem;
        }

        .help-section ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .help-section li {
            color: #555;
            margin: 5px 0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .current-bus-display {
                grid-template-columns: 1fr;
            }

            .bus-selection-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px 0;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
                max-height: none;
            }

            .page-title h1 {
                font-size: 1.8rem;
            }

            .bus-selection-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .button-group {
                flex-direction: column;
            }

            .btn-custom {
                width: 100%;
                justify-content: center;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #4A90E2 0%, #50C9C3 100%);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #50C9C3 0%, #4A90E2 100%);
        }
    </style>
</head>
<body>
    <div class="container-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <h3><i class="fas fa-bus"></i> i-Bus</h3>
            </div>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="assign_bus.php" class="active"><i class="fas fa-bus-alt"></i> Assign Bus</a></li>
                <li><a href="schedule.php"><i class="fas fa-calendar"></i> Schedule</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="passengers.php"><i class="fas fa-users"></i> Passengers</a></li>
                <li><a href="performance.php"><i class="fas fa-chart-bar"></i> Performance</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="breadcrumb-custom">
                    <a href="dashboard.php" style="color: rgba(255,255,255,0.7); text-decoration: none;">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <span><i class="fas fa-chevron-right"></i></span>
                    <span class="active">Bus Fleet Management</span>
                </div>
                <div class="page-title">
                    <div class="title-icon"><i class="fas fa-bus"></i></div>
                    <h1>Bus Fleet Management</h1>
                </div>
                <p class="page-subtitle">Select and assign a bus to your account to start receiving booking notifications</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert-custom alert-success-custom">
                    <div class="alert-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="alert-content">
                        <h4>Success!</h4>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-custom alert-danger-custom">
                    <div class="alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="alert-content">
                        <h4>Error!</h4>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #ffd700;"><i class="fas fa-bus"></i></div>
                    <div class="stat-value"><?php echo $stats['total_buses']; ?></div>
                    <div class="stat-label">Total Buses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #20c997;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $stats['available_count']; ?></div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #ffc107;"><i class="fas fa-spinner"></i></div>
                    <div class="stat-value"><?php echo $stats['in_use_count']; ?></div>
                    <div class="stat-label">In Use</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #20c997;"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $stats['avg_capacity']; ?></div>
                    <div class="stat-label">Avg Capacity</div>
                </div>
            </div>

            <!-- Current Bus Assignment -->
            <?php if ($assigned_bus): ?>
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h3><i class="fas fa-check-circle"></i> Current Assignment</h3>
                        <p class="header-subtitle">Your actively assigned bus</p>
                    </div>
                    <div class="card-body-modern">
                        <div class="current-bus-display">
                            <div class="bus-visual">
                                <div class="bus-icon"><i class="fas fa-bus"></i></div>
                                <div class="bus-number"><?php echo htmlspecialchars($assigned_bus['bus_number']); ?></div>
                                <div class="bus-details-info">
                                    <div class="bus-detail-item">
                                        <div class="label">Capacity</div>
                                        <div class="value"><?php echo $assigned_bus['capacity']; ?></div>
                                    </div>
                                    <div class="bus-detail-item">
                                        <div class="label">Status</div>
                                        <div class="value" style="text-transform: capitalize; font-size: 1rem;"><?php echo $assigned_bus['status']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="bus-info-section">
                                <div class="bus-info-item">
                                    <div class="bus-info-icon"><i class="fas fa-chair"></i></div>
                                    <div class="bus-info-content">
                                        <h5>Total Capacity</h5>
                                        <p><?php echo $assigned_bus['capacity']; ?> passenger seats</p>
                                    </div>
                                </div>
                                <div class="bus-info-item">
                                    <div class="bus-info-icon"><i class="fas fa-sync"></i></div>
                                    <div class="bus-info-content">
                                        <h5>Current Status</h5>
                                        <p><?php echo ucfirst($assigned_bus['status']); ?> for operations</p>
                                    </div>
                                </div>
                                <div class="bus-info-item">
                                    <div class="bus-info-icon"><i class="fas fa-bell"></i></div>
                                    <div class="bus-info-content">
                                        <h5>Notifications</h5>
                                        <p>Receiving booking requests</p>
                                    </div>
                                </div>
                                <div class="bus-info-item">
                                    <div class="bus-info-icon"><i class="fas fa-check"></i></div>
                                    <div class="bus-info-content">
                                        <h5>Ready to Go</h5>
                                        <p>All systems operational</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-modern" style="border-left: 4px solid #ffc107;">
                    <div class="card-body-modern" style="padding-top: 25px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="font-size: 2rem; color: #ffc107;"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <h5 style="margin: 0 0 5px 0; color: #2c3e50;">No Bus Assigned Yet</h5>
                                <p style="margin: 0; color: #999;">Select and assign a bus below to start receiving booking notifications from customers.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bus Selection -->
            <div class="card-modern">
                <div class="card-header-modern">
                    <h3><i class="fas fa-list"></i> Available Buses</h3>
                    <p class="header-subtitle">Select a bus to assign to your account</p>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($available_buses)): ?>
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"><i class="fas fa-inbox"></i></div>
                            <h5 style="color: #999; margin-bottom: 10px;">No Buses Available</h5>
                            <p style="color: #ccc;">Please contact the administrator to add buses to the system.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="busAssignmentForm">
                            <div class="filter-section" style="margin-bottom: 30px;">
                                <button type="button" class="filter-btn active" data-filter="all" onclick="filterBuses('all')">
                                    <i class="fas fa-th"></i> All Buses
                                </button>
                                <button type="button" class="filter-btn" data-filter="available" onclick="filterBuses('available')">
                                    <i class="fas fa-check-circle"></i> Available Only
                                </button>
                                <button type="button" class="filter-btn" data-filter="in_use" onclick="filterBuses('in_use')">
                                    <i class="fas fa-spinner"></i> In Use Only
                                </button>
                            </div>

                            <div class="bus-selection-grid">
                                <?php foreach ($available_buses as $bus): 
                                    // Check if bus is assigned to another driver
                                    $is_assigned_to_other = ($bus['driver_id'] && $bus['driver_id'] != $driver_id);
                                    $is_my_bus = ($assigned_bus && $assigned_bus['bus_id'] === $bus['bus_id']);
                                ?>
                                    <label class="bus-card <?php echo $is_assigned_to_other ? 'locked' : ''; ?>" 
                                           data-status="<?php echo $bus['status']; ?>"
                                           data-locked="<?php echo $is_assigned_to_other ? 'true' : 'false'; ?>"
                                           <?php echo $is_assigned_to_other ? 'title="This bus is assigned to ' . htmlspecialchars($bus['assigned_driver_name']) . '"' : ''; ?>>
                                        <input type="radio" 
                                               name="bus_id" 
                                               value="<?php echo $bus['bus_id']; ?>" 
                                               <?php echo $is_my_bus ? 'checked' : ''; ?>
                                               <?php echo $is_assigned_to_other ? 'disabled' : ''; ?> />
                                        <div class="bus-card-content">
                                            <?php if ($is_assigned_to_other): ?>
                                                <div class="locked-badge">
                                                    <i class="fas fa-lock"></i> Assigned
                                                </div>
                                            <?php endif; ?>
                                            <div class="bus-card-icon"><i class="fas fa-bus"></i></div>
                                            <div class="bus-card-number"><?php echo htmlspecialchars($bus['bus_number']); ?></div>
                                            <div class="bus-card-capacity">
                                                <i class="fas fa-chair"></i> <?php echo $bus['capacity']; ?> Seats
                                            </div>
                                            <span class="bus-card-status status-<?php echo $bus['status']; ?>">
                                                <?php echo ucfirst($bus['status']); ?>
                                            </span>
                                            <?php if ($is_assigned_to_other): ?>
                                                <div style="margin-top: 10px; font-size: 0.8rem; color: #999;">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($bus['assigned_driver_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="button-group">
                                <button type="submit" name="assign_bus" class="btn-custom btn-primary-custom" id="assignBtn" disabled>
                                    <i class="fas fa-check-circle"></i> Assign Selected Bus
                                </button>
                                <button type="reset" class="btn-custom btn-secondary-custom" onclick="resetSelection()">
                                    <i class="fas fa-redo"></i> Clear Selection
                                </button>
                            </div>

                            <div class="help-section">
                                <h6><i class="fas fa-lightbulb"></i> Bus Assignment Rules</h6>
                                <ul>
                                    <li><strong>One Bus Per Driver:</strong> You can only have ONE bus assigned to your account at a time</li>
                                    <li><strong>Higher Capacity:</strong> More available seats = more booking opportunities</li>
                                    <li><strong>Availability Status:</strong> "Available" buses are ready for immediate assignment</li>
                                    <li><strong>Change Anytime:</strong> You can reassign to a different bus, which will automatically unassign your current bus</li>
                                    <li><strong>Locked Buses:</strong> Buses with a lock icon are already assigned to other drivers and cannot be selected</li>
                                    <li><strong>Notifications:</strong> Once assigned, you'll receive all incoming booking requests</li>
                                </ul>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Assignments -->
            <?php if (!empty($recent_assignments)): ?>
                <div class="card-modern">
                    <div class="card-header-modern">
                        <h3><i class="fas fa-history"></i> Recent Assignments</h3>
                        <p class="header-subtitle">Latest bus assignments in the system</p>
                    </div>
                    <div class="card-body-modern">
                        <div class="timeline">
                            <?php foreach ($recent_assignments as $assignment): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon"><i class="fas fa-bus"></i></div>
                                    <div class="timeline-content">
                                        <h6><?php echo htmlspecialchars($assignment['full_name']); ?> assigned <strong><?php echo htmlspecialchars($assignment['bus_number']); ?></strong></h6>
                                        <p><i class="fas fa-clock"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($assignment['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterBuses(filter) {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.closest('.filter-btn').classList.add('active');

            // Show/hide buses based on filter
            document.querySelectorAll('.bus-card').forEach(card => {
                const status = card.getAttribute('data-status');
                const isLocked = card.getAttribute('data-locked') === 'true';
                let shouldShow = false;

                if (filter === 'all') {
                    // Show all buses
                    shouldShow = true;
                } else if (filter === 'available') {
                    // Show only available AND not locked buses
                    shouldShow = (status === 'available' && !isLocked);
                } else if (filter === 'in_use') {
                    // Show in_use buses OR locked buses (buses assigned to drivers)
                    shouldShow = (status === 'in_use' || isLocked);
                }

                if (shouldShow) {
                    card.style.display = 'block';
                    setTimeout(() => card.style.opacity = '1', 10);
                } else {
                    card.style.opacity = '0';
                    setTimeout(() => card.style.display = 'none', 300);
                }
            });
        }

        function resetSelection() {
            document.querySelectorAll('input[name="bus_id"]').forEach(radio => radio.checked = false);
            document.getElementById('assignBtn').disabled = true;
        }

        // Enable/disable submit button based on selection
        document.querySelectorAll('input[name="bus_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('assignBtn').disabled = false;
            });
        });

        // Check initial state
        const selectedRadio = document.querySelector('input[name="bus_id"]:checked');
        if (selectedRadio) {
            document.getElementById('assignBtn').disabled = false;
        }

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
