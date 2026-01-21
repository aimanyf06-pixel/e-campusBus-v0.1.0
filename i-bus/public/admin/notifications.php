<?php
require_once '../includes/auth.php';
checkRole('admin');

$username = $_SESSION['username'] ?? 'Admin';
$full_name = $_SESSION['full_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin</title>
    <link rel="stylesheet" href="admin-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h2 { margin: 0; color: #2c3e50; }
        .notifications-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 0;
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-list { max-height: 500px; overflow-y: auto; }
        .notification-item {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f3f5;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: background 0.2s ease;
        }
        .notification-item:hover { background: #f8f9fa; }
        .notification-icon { color: #3498db; font-size: 1.2rem; }
        .notification-text h4 { margin: 0 0 6px 0; font-size: 1rem; color: #2c3e50; }
        .notification-text p { margin: 0; color: #666; font-size: 0.95rem; }
        .notification-time { margin-top: 4px; color: #999; font-size: 0.85rem; }
        .empty-state { text-align: center; padding: 30px; color: #999; }
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
            <a class="nav-link active" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
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
        <div class="header">
            <div>
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <p style="margin:4px 0 0; color:#666;">Latest system and booking updates</p>
            </div>
            <span style="color:#888;">Logged in as <?php echo htmlspecialchars($full_name); ?></span>
        </div>

        <div class="notifications-card">
            <div class="card-header"><i class="fas fa-bell"></i> Recent Notifications</div>
            <div id="notificationList" class="notification-list"></div>
        </div>
    </div>

    <script>
        function getTimeAgo(datetime) {
            const now = new Date();
            const t = new Date(datetime);
            const s = Math.floor((now - t) / 1000);
            if (s < 60) return 'Just now';
            if (s < 3600) return Math.floor(s / 60) + 'm ago';
            if (s < 86400) return Math.floor(s / 3600) + 'h ago';
            return Math.floor(s / 86400) + 'd ago';
        }

        function getIcon(type) {
            const map = {
                booking: 'fa-ticket-alt',
                system: 'fa-cog',
                alert: 'fa-exclamation-triangle',
                info: 'fa-info-circle',
                message: 'fa-envelope',
                user: 'fa-user',
                bus: 'fa-bus'
            };
            return map[type] || 'fa-bell';
        }

        function render(notifications) {
            const list = document.getElementById('notificationList');
            if (!notifications.length) {
                list.innerHTML = '<div class="empty-state"><i class="fas fa-inbox" style="font-size:2rem;"></i><p>No notifications</p></div>';
                return;
            }
            list.innerHTML = notifications.map(n => `
                <div class="notification-item">
                    <div class="notification-icon"><i class="fas ${getIcon(n.type)}"></i></div>
                    <div class="notification-text">
                        <h4>${n.title}</h4>
                        <p>${n.message}</p>
                        <div class="notification-time">${getTimeAgo(n.created_at)}</div>
                    </div>
                </div>
            `).join('');
        }

        fetch('../api/get_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'role=admin'
        }).then(r => r.json()).then(data => {
            if (data.success) render(data.notifications || []);
        }).catch(err => console.error(err));
    </script>
</body>
</html>
