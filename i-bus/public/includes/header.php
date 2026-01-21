<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            font-size: 2rem;
            color: #3498db;
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .logo span {
            color: #3498db;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
            font-weight: 500;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            color: #f39c12;
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
            border: 2px solid white;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            min-width: 350px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
            margin-top: 10px;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #2c3e50;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f8ff;
            border-left: 4px solid #3498db;
        }

        .notification-content {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .notification-icon {
            font-size: 1.2rem;
            color: #3498db;
            flex-shrink: 0;
        }

        .notification-text {
            flex: 1;
        }

        .notification-text h6 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .notification-text p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 5px;
        }

        .notification-empty {
            padding: 30px 20px;
            text-align: center;
            color: #999;
        }

        .notification-footer {
            padding: 10px 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .notification-footer a {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .notification-footer a:hover {
            text-decoration: underline;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .nav-container {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .nav-menu {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }

        .nav-menu a {
            padding: 15px 20px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .nav-menu a:hover {
            color: #3498db;
            background: #f8f9fa;
        }

        .nav-menu a.active {
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }

        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            min-height: calc(100vh - 200px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-menu {
                justify-content: center;
            }
            
            .main-container {
                padding: 0 1rem;
            }
        }

        /* Flash Message Styles */
        .flash-message {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 2rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .close-btn:hover {
            opacity: 1;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">🚌</div>
                <h1>Bus<span>Management</span></h1>
            </div>
            <div class="user-info">
                <div style="position: relative;">
                    <div class="notification-bell" id="notificationBell" onclick="toggleNotifications(event)" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                    </div>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <i class="fas fa-bell"></i> Notifications
                        </div>
                        <div id="notificationList"></div>
                        <div class="notification-footer">
                            <a href="#" onclick="closeNotifications()">Close</a>
                        </div>
                    </div>
                </div>
                <span>Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></span>
                <form action="../logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="nav-container">
        <nav class="nav-menu">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="buses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'buses.php' ? 'active' : ''; ?>">Manage Buses</a>
            <a href="routes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'routes.php' ? 'active' : ''; ?>">Routes</a>
            <a href="schedules.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedules.php' ? 'active' : ''; ?>">Schedules</a>
            <a href="bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>">Bookings</a>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">Users</a>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">Reports</a>
        </nav>
    </div>

    <?php if (isset($message) && !empty($message)): ?>
    <div class="flash-message">
        <div class="alert alert-<?php echo $message_type ?? 'info'; ?>">
            <span><?php echo htmlspecialchars($message); ?></span>
            <button class="close-btn" onclick="this.parentElement.style.display='none'">×</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-container">
    <script src=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js\"></script>
    <script>
        function toggleNotifications(event) {
            event.preventDefault();
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        }

        function closeNotifications() {
            document.getElementById('notificationDropdown').classList.remove('show');
        }

        function loadNotifications() {
            const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'user'; ?>';
            const userId = '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>';
            
            fetch('../api/get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId + '&role=' + userRole
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateNotificationBadge(data.count);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
        }

        function displayNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            
            if (notifications.length === 0) {
                notificationList.innerHTML = '<div class=\"notification-empty\"><i class=\"fas fa-inbox\" style=\"font-size: 2rem; margin-bottom: 10px;\"></i><p>No notifications</p></div>';
                return;
            }
            
            notificationList.innerHTML = notifications.map(notif => `
                <div class=\"notification-item ${notif.unread ? 'unread' : ''}\">
                    <div class=\"notification-content\">
                        <div class=\"notification-icon\">
                            <i class=\"${getNotificationIcon(notif.type)}\"></i>
                        </div>
                        <div class=\"notification-text\">
                            <h6>${notif.title}</h6>
                            <p>${notif.message}</p>
                            <div class=\"notification-time\">${getTimeAgo(notif.created_at)}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationCount');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function getNotificationIcon(type) {
            const icons = {
                'booking': 'fas fa-ticket-alt',
                'system': 'fas fa-cog',
                'alert': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle',
                'message': 'fas fa-envelope',
                'user': 'fas fa-user',
                'bus': 'fas fa-bus'
            };
            return icons[type] || 'fas fa-bell';
        }

        function getTimeAgo(datetime) {
            const now = new Date();
            const notifTime = new Date(datetime);
            const seconds = Math.floor((now - notifTime) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            return Math.floor(seconds / 86400) + 'd ago';
        }

        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            if (!dropdown.contains(event.target) && !bell.contains(event.target)) {
                closeNotifications();
            }
        });
    </script>