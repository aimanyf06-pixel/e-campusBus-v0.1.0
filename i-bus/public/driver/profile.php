<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkRole('driver');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

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
    
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'driver'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        
        if (!empty($full_name) && !empty($email) && !empty($phone)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $message = "Profile updated successfully!";
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone_number'] = $phone;
            } else {
                $error = "Failed to update profile";
            }
        } else {
            $error = "All fields are required";
        }
    }
    
    // Handle password change
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $message = "Password changed successfully!";
            } else {
                $error = "Failed to change password";
            }
        }
    }
    
    // Get driver statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_trips,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM bookings
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .stat-box h3 {
            margin: 10px 0 0 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .stat-box i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-box.blue { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-box.green { background: rgba(39, 174, 96, 0.1); color: var(--success-green); }
        .stat-box.orange { background: rgba(243, 156, 18, 0.1); color: var(--warning-orange); }
        .stat-box.red { background: rgba(231, 76, 60, 0.1); color: var(--danger-red); }
        
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
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-blue), var(--success-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--primary-blue));
            border: none;
            color: white;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            color: white;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: var(--primary-blue);
            min-width: 150px;
        }
        .info-value {
            color: #666;
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
            <a class="nav-link" href="performance.php"><i class="fas fa-trophy"></i> Performance</a>
            <a class="nav-link active" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-user-circle"></i> My Profile</h2>
            <p class="mb-0">Manage your account and personal information</p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                    <p>Completed Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <i class="fas fa-calendar"></i>
                    <h3><?php echo $stats['total_trips'] ?? 0; ?></h3>
                    <p>Total Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <i class="fas fa-hourglass"></i>
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending Trips</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box red">
                    <i class="fas fa-times-circle"></i>
                    <h3><?php echo $stats['cancelled'] ?? 0; ?></h3>
                    <p>Cancelled Trips</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5><i class="fas fa-id-card"></i> Profile Information</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5><i class="fas fa-lock"></i> Change Password</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                            <small class="text-muted">At least 6 characters</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Details -->
        <div class="content-card">
            <h5><i class="fas fa-info-circle"></i> Account Details</h5>
            <div class="info-row">
                <span class="info-label">Account Created</span>
                <span class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Account Status</span>
                <span class="info-value">
                    <span class="badge" style="background-color: var(--success-green);">
                        <i class="fas fa-check-circle"></i> Active
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value">
                    <span class="badge" style="background-color: var(--accent-blue);">Driver</span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Login</span>
                <span class="info-value">Just now</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
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
