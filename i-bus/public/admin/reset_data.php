<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

checkRole('admin');

$database = new Database();
$pdo = $database->getConnection();

$success = '';
$error = '';

// Handle reset actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Clear Bookings + Notifications + Activity Logs (one click)
        if (isset($_POST['clear_bookings_notifs_logs'])) {
            $stmt = $pdo->query("DELETE FROM booking_notifications");
            $notif_count = $stmt->rowCount();
            $stmt = $pdo->query("DELETE FROM bookings");
            $booking_count = $stmt->rowCount();
            $stmt = $pdo->query("DELETE FROM activity_logs");
            $log_count = $stmt->rowCount();
            $success = "Cleared {$booking_count} bookings, {$notif_count} notifications, and {$log_count} activity logs successfully!";
        }

        // Clear Bookings
        elseif (isset($_POST['clear_bookings'])) {
            $stmt = $pdo->query("DELETE FROM booking_notifications");
            $notif_count = $stmt->rowCount();
            $stmt = $pdo->query("DELETE FROM bookings");
            $booking_count = $stmt->rowCount();
            $success = "Cleared {$booking_count} bookings and {$notif_count} notifications successfully!";
        }
        
        // Clear Activity Logs
        elseif (isset($_POST['clear_logs'])) {
            $stmt = $pdo->query("DELETE FROM activity_logs");
            $count = $stmt->rowCount();
            $success = "Cleared {$count} activity logs successfully!";
        }
        
        // Clear All Notifications
        elseif (isset($_POST['clear_notifications'])) {
            $stmt = $pdo->query("DELETE FROM booking_notifications");
            $count = $stmt->rowCount();
            $success = "Cleared {$count} booking notifications successfully!";
        }
        
        // Remove All Students
        elseif (isset($_POST['remove_students'])) {
            // Delete student bookings first
            $stmt = $pdo->query("DELETE FROM booking_notifications WHERE booking_id IN (SELECT booking_id FROM bookings WHERE user_id IN (SELECT id FROM users WHERE role='student'))");
            $stmt = $pdo->query("DELETE FROM bookings WHERE user_id IN (SELECT id FROM users WHERE role='student')");
            $stmt = $pdo->query("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE role='student')");
            $stmt = $pdo->query("DELETE FROM users WHERE role='student'");
            $count = $stmt->rowCount();
            $success = "Removed {$count} student accounts successfully!";
        }
        
        // Unassign All Drivers from Buses
        elseif (isset($_POST['unassign_drivers'])) {
            $stmt = $pdo->query("UPDATE buses SET driver_id = NULL");
            $count = $stmt->rowCount();
            $success = "Unassigned {$count} drivers from buses successfully!";
        }
        
        // Remove All Drivers
        elseif (isset($_POST['remove_drivers'])) {
            // Unassign buses first
            $stmt = $pdo->query("UPDATE buses SET driver_id = NULL WHERE driver_id IS NOT NULL");
            // Delete driver data
            $stmt = $pdo->query("DELETE FROM booking_notifications WHERE driver_id IN (SELECT id FROM users WHERE role='driver')");
            $stmt = $pdo->query("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE role='driver')");
            $stmt = $pdo->query("DELETE FROM users WHERE role='driver'");
            $count = $stmt->rowCount();
            $success = "Removed {$count} driver accounts successfully!";
        }
        
        // Clear All Buses
        elseif (isset($_POST['clear_buses'])) {
            $stmt = $pdo->query("DELETE FROM buses");
            $count = $stmt->rowCount();
            $success = "Cleared {$count} buses successfully!";
        }
        
        // Clear All Routes
        elseif (isset($_POST['clear_routes'])) {
            // Check if there are bookings using routes
            $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
            $booking_count = $stmt->fetchColumn();
            
            if ($booking_count > 0) {
                $error = "Cannot delete routes while there are active bookings. Please clear bookings first.";
            } else {
                $stmt = $pdo->query("DELETE FROM routes");
                $count = $stmt->rowCount();
                $success = "Cleared {$count} routes successfully!";
            }
        }
        
        // Reset All User Passwords
        elseif (isset($_POST['reset_passwords'])) {
            $default_password = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role != 'admin'");
            $stmt->execute([$default_password]);
            $count = $stmt->rowCount();
            $success = "Reset passwords for {$count} users to 'password123' successfully!";
        }
        
        // Nuclear Option - Clear Everything (except admin)
        elseif (isset($_POST['nuclear_reset'])) {
            $stmt = $pdo->query("DELETE FROM booking_notifications");
            $stmt = $pdo->query("DELETE FROM bookings");
            $stmt = $pdo->query("DELETE FROM activity_logs WHERE user_id != (SELECT id FROM users WHERE role='admin' LIMIT 1)");
            $stmt = $pdo->query("UPDATE buses SET driver_id = NULL");
            $stmt = $pdo->query("DELETE FROM routes");
            $stmt = $pdo->query("DELETE FROM users WHERE role != 'admin'");
            $success = "🔥 NUCLEAR RESET COMPLETE! All data cleared (admin account preserved).";
        }
        
        // Delete Selected Users
        elseif (isset($_POST['delete_selected_users']) && !empty($_POST['selected_users'])) {
            $user_ids = $_POST['selected_users'];
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            
            // Delete user-related data first
            $stmt = $pdo->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id IN ($placeholders)");
            $stmt->execute($user_ids);
            
            $stmt = $pdo->prepare("DELETE FROM booking_notifications WHERE driver_id IN ($placeholders)");
            $stmt->execute($user_ids);
            
            $stmt = $pdo->prepare("DELETE FROM booking_notifications WHERE booking_id IN (SELECT booking_id FROM bookings WHERE user_id IN ($placeholders))");
            $stmt->execute($user_ids);
            
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE user_id IN ($placeholders)");
            $stmt->execute($user_ids);
            
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id IN ($placeholders)");
            $stmt->execute($user_ids);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role != 'admin'");
            $stmt->execute($user_ids);
            $count = $stmt->rowCount();
            
            $success = "Deleted {$count} selected user(s) successfully!";
        }
        
        $pdo->commit();
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Get statistics
try {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'");
    $stats['students'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='driver'");
    $stats['drivers'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
    $stats['bookings'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM booking_notifications");
    $stats['notifications'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM buses");
    $stats['buses'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM routes");
    $stats['routes'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $stats['logs'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM buses WHERE driver_id IS NOT NULL");
    $stats['assigned_buses'] = $stmt->fetchColumn();
    
    // Get all users (students and drivers) for selection
    $stmt = $pdo->query("SELECT id, full_name, username, email, role, status, created_at FROM users WHERE role != 'admin' ORDER BY role, full_name");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Error getting statistics: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset & Clear Data - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-sidebar.css">
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
            --danger-red: #E74C3C;
            --warning-orange: #F39C12;
            --success-green: #27AE60;
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
            background: linear-gradient(135deg, var(--danger-red) 0%, #c0392b 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid var(--accent-blue);
        }
        .stat-card.danger { border-top-color: var(--danger-red); }
        .stat-card.warning { border-top-color: var(--warning-orange); }
        .stat-card.success { border-top-color: var(--success-green); }
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin: 10px 0;
        }
        .stat-card p {
            color: #666;
            margin: 0;
            font-weight: 600;
        }
        .stat-card i {
            font-size: 2rem;
            opacity: 0.6;
        }
        .action-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .action-section h4 {
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .action-card:hover {
            border-color: var(--accent-blue);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }
        .action-card.danger:hover {
            border-color: var(--danger-red);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }
        .action-card h5 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        .action-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .nuclear-section {
            background: linear-gradient(135deg, #c0392b 0%, #8e2de2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            0%, 100% { box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4); }
            50% { box-shadow: 0 8px 35px rgba(231, 76, 60, 0.6); }
        }
        .nuclear-section h3 {
            font-weight: 700;
            margin-bottom: 15px;
        }
        .btn-nuclear {
            background: linear-gradient(135deg, #ff0000 0%, #8e0000 100%);
            border: 3px solid #fff;
            color: white;
            font-weight: 700;
            padding: 15px 40px;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-nuclear:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.5);
            color: white;
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
            <a class="nav-link" href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a class="nav-link" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
            <a class="nav-link" href="manage_routes.php"><i class="fas fa-route"></i> Manage Routes</a>
            <a class="nav-link" href="manage_buses.php"><i class="fas fa-bus"></i> Manage Buses</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a class="nav-link active" href="reset_data.php"><i class="fas fa-database"></i> Reset Data</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-database"></i> Data Reset & Clear Center</h2>
            <p class="mb-0">⚠️ Danger Zone - Use with caution! These actions cannot be undone.</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stat-grid">
            <div class="stat-card">
                <i class="fas fa-user-graduate text-primary"></i>
                <h3><?php echo $stats['students']; ?></h3>
                <p><i class="fas fa-users"></i> Students</p>
            </div>
            <div class="stat-card warning">
                <i class="fas fa-user-tie" style="color: var(--warning-orange);"></i>
                <h3><?php echo $stats['drivers']; ?></h3>
                <p><i class="fas fa-id-card"></i> Drivers</p>
            </div>
            <div class="stat-card danger">
                <i class="fas fa-calendar-check" style="color: var(--danger-red);"></i>
                <h3><?php echo $stats['bookings']; ?></h3>
                <p><i class="fas fa-ticket-alt"></i> Bookings</p>
            </div>
            <div class="stat-card success">
                <i class="fas fa-bell" style="color: var(--success-green);"></i>
                <h3><?php echo $stats['notifications']; ?></h3>
                <p><i class="fas fa-comment-dots"></i> Notifications</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-bus text-info"></i>
                <h3><?php echo $stats['buses']; ?></h3>
                <p><i class="fas fa-parking"></i> Buses</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-route text-secondary"></i>
                <h3><?php echo $stats['routes']; ?></h3>
                <p><i class="fas fa-map-marked-alt"></i> Routes</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clipboard-list text-muted"></i>
                <h3><?php echo $stats['logs']; ?></h3>
                <p><i class="fas fa-history"></i> Activity Logs</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-bus-alt text-success"></i>
                <h3><?php echo $stats['assigned_buses']; ?></h3>
                <p><i class="fas fa-link"></i> Assigned Buses</p>
            </div>
        </div>

        <!-- Booking & Notification Actions -->
        <div class="action-section">
            <h4><i class="fas fa-ticket-alt"></i> Booking & Notification Management</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-trash-alt"></i> Clear All Bookings</h5>
                        <p>Delete all booking records and related notifications. This will reset the booking system.</p>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL bookings? This cannot be undone!')">
                            <button type="submit" name="clear_bookings" class="btn btn-danger w-100">
                                <i class="fas fa-eraser"></i> Clear Bookings (<?php echo $stats['bookings']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-bell-slash"></i> Clear Notifications</h5>
                        <p>Remove all booking notifications sent to drivers. Bookings will remain intact.</p>
                        <form method="POST" onsubmit="return confirm('Clear all booking notifications?')">
                            <button type="submit" name="clear_notifications" class="btn btn-warning w-100">
                                <i class="fas fa-trash"></i> Clear Notifications (<?php echo $stats['notifications']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-clipboard-list"></i> Clear Activity Logs</h5>
                        <p>Delete all activity logs and history records from the system.</p>
                        <form method="POST" onsubmit="return confirm('Delete all activity logs?')">
                            <button type="submit" name="clear_logs" class="btn btn-secondary w-100">
                                <i class="fas fa-broom"></i> Clear Logs (<?php echo $stats['logs']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <div class="action-card danger">
                        <h5><i class="fas fa-bomb"></i> Clear Bookings + Notifications + Logs</h5>
                        <p>Delete all bookings, booking notifications, and activity logs in one action. This cannot be undone.</p>
                        <form method="POST" onsubmit="return confirm('⚠️ This will delete ALL bookings, booking notifications, and activity logs. Proceed?')">
                            <button type="submit" name="clear_bookings_notifs_logs" class="btn btn-danger w-100">
                                <i class="fas fa-bomb"></i> Clear All (Bookings: <?php echo $stats['bookings']; ?> | Notifications: <?php echo $stats['notifications']; ?> | Logs: <?php echo $stats['logs']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management Actions -->
        <div class="action-section">
            <h4><i class="fas fa-users"></i> User Management</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-user-graduate"></i> Remove All Students</h5>
                        <p>Delete all student accounts and their booking history.</p>
                        <form method="POST" onsubmit="return confirm('⚠️ WARNING: This will delete ALL student accounts and their data!')">
                            <button type="submit" name="remove_students" class="btn btn-danger w-100">
                                <i class="fas fa-user-times"></i> Remove Students (<?php echo $stats['students']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-user-tie"></i> Remove All Drivers</h5>
                        <p>Delete all driver accounts and unassign them from buses.</p>
                        <form method="POST" onsubmit="return confirm('⚠️ WARNING: This will delete ALL driver accounts!')">
                            <button type="submit" name="remove_drivers" class="btn btn-danger w-100">
                                <i class="fas fa-user-slash"></i> Remove Drivers (<?php echo $stats['drivers']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card warning">
                        <h5><i class="fas fa-key"></i> Reset All Passwords</h5>
                        <p>Reset all user passwords (except admin) to default: <code>password123</code></p>
                        <form method="POST" onsubmit="return confirm('Reset all passwords to default?')">
                            <button type="submit" name="reset_passwords" class="btn btn-warning w-100">
                                <i class="fas fa-redo"></i> Reset Passwords
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selective User Deletion -->
        <div class="action-section">
            <h4><i class="fas fa-user-check"></i> Selective User Management</h4>
            <p class="text-muted mb-3">Select specific users to delete. Use filters to narrow down the list.</p>
            
            <form method="POST" id="deleteUsersForm" onsubmit="return confirmDeleteUsers()">
                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="filterUsers('all')">
                            <i class="fas fa-users"></i> All (<?php echo count($all_users); ?>)
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="filterUsers('student')">
                            <i class="fas fa-user-graduate"></i> Students (<?php echo $stats['students']; ?>)
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="filterUsers('driver')">
                            <i class="fas fa-user-tie"></i> Drivers (<?php echo $stats['drivers']; ?>)
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="selectAllUsers(true)">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="selectAllUsers(false)">
                            <i class="fas fa-square"></i> Deselect All
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAllCheckbox" onchange="selectAllUsers(this.checked)"></th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_users)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No users found (only admin exists)</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($all_users as $user): ?>
                            <tr class="user-row" data-role="<?php echo $user['role']; ?>">
                                <td>
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                </td>
                                <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'student'): ?>
                                        <span class="badge bg-success"><i class="fas fa-user-graduate"></i> Student</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><i class="fas fa-user-tie"></i> Driver</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <span id="selectedCount" class="text-muted">0 user(s) selected</span>
                    <button type="submit" name="delete_selected_users" class="btn btn-danger" id="deleteBtn" disabled>
                        <i class="fas fa-trash-alt"></i> Delete Selected
                    </button>
                </div>
            </form>
        </div>

        <!-- Bus & Route Actions -->
        <div class="action-section">
            <h4><i class="fas fa-bus"></i> Bus & Route Management</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="action-card">
                        <h5><i class="fas fa-unlink"></i> Unassign All Drivers</h5>
                        <p>Remove all driver assignments from buses. Drivers and buses remain in system.</p>
                        <form method="POST" onsubmit="return confirm('Unassign all drivers from their buses?')">
                            <button type="submit" name="unassign_drivers" class="btn btn-info w-100">
                                <i class="fas fa-user-minus"></i> Unassign (<?php echo $stats['assigned_buses']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-bus-alt"></i> Clear All Buses</h5>
                        <p>Delete all bus records. Will unassign all drivers first.</p>
                        <form method="POST" onsubmit="return confirm('⚠️ Delete ALL buses from the system?')">
                            <button type="submit" name="clear_buses" class="btn btn-danger w-100">
                                <i class="fas fa-trash"></i> Clear Buses (<?php echo $stats['buses']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="action-card danger">
                        <h5><i class="fas fa-route"></i> Clear All Routes</h5>
                        <p>Delete all route definitions. Requires no active bookings.</p>
                        <form method="POST" onsubmit="return confirm('⚠️ Delete ALL routes? (Must clear bookings first)')">
                            <button type="submit" name="clear_routes" class="btn btn-danger w-100">
                                <i class="fas fa-map-marked"></i> Clear Routes (<?php echo $stats['routes']; ?>)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nuclear Option -->
        <div class="nuclear-section">
            <div class="text-center">
                <h3><i class="fas fa-radiation"></i> NUCLEAR RESET OPTION</h3>
                <p class="mb-4">☢️ This will DELETE EVERYTHING except your admin account. All data will be permanently lost. Use only for complete system reset!</p>
                <p class="mb-4"><strong>This will clear:</strong> All students, drivers, bookings, notifications, buses, routes, activity logs</p>
                <form method="POST" onsubmit="return confirm('🔥 FINAL WARNING 🔥\n\nThis will DELETE ALL DATA!\n\nType YES in the next prompt to confirm.') && prompt('Type YES to confirm nuclear reset:') === 'YES'">
                    <button type="submit" name="nuclear_reset" class="btn btn-nuclear">
                        <i class="fas fa-bomb"></i> NUCLEAR RESET - DELETE EVERYTHING
                    </button>
                </form>
                <p class="mt-3 mb-0" style="font-size: 0.9rem; opacity: 0.8;">Admin account will be preserved</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Track selected users
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count + ' user(s) selected';
            document.getElementById('deleteBtn').disabled = count === 0;
        }

        // Select/deselect all users
        function selectAllUsers(checked) {
            const visibleCheckboxes = document.querySelectorAll('.user-row:not([style*="display: none"]) .user-checkbox');
            visibleCheckboxes.forEach(cb => cb.checked = checked);
            document.getElementById('selectAllCheckbox').checked = checked;
            updateSelectedCount();
        }

        // Filter users by role
        function filterUsers(role) {
            const rows = document.querySelectorAll('.user-row');
            rows.forEach(row => {
                if (role === 'all' || row.dataset.role === role) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            updateSelectedCount();
        }

        // Confirm deletion
        function confirmDeleteUsers() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one user to delete.');
                return false;
            }
            
            const userNames = Array.from(checkboxes).map(cb => {
                return cb.closest('tr').querySelector('td:nth-child(2)').textContent;
            });
            
            const message = `⚠️ WARNING: You are about to delete ${checkboxes.length} user(s):\n\n${userNames.join('\n')}\n\nThis action cannot be undone. Continue?`;
            return confirm(message);
        }

        // Add event listeners to checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
        });
    </script>
</body>
</html>
