<?php
require_once '../includes/auth.php';
checkRole('student');

$full_name = $_SESSION['full_name'] ?? 'Student';
$user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Student</title>
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
        body { margin:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f7fa; }
        .sidebar { width:250px; background: linear-gradient(180deg, var(--primary-blue) 0%, #1a2530 100%); color:white; height:100vh; position:fixed; left:0; top:0; overflow-y:auto; z-index:1000; }
        .sidebar-header { padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { margin:0; font-size:1.5rem; font-weight:700; color:white; }
        .sidebar-header p { margin:5px 0 0; color:rgba(255,255,255,0.7); font-size:0.9rem; }
        .nav { list-style:none; padding:0; margin:0; }
        .nav-link { display:block; color:rgba(255,255,255,0.8); padding:12px 20px; text-decoration:none; border-left:3px solid transparent; transition:all 0.3s ease; font-size:0.95rem; }
        .nav-link i { margin-right:12px; width:20px; text-align:center; }
        .nav-link:hover, .nav-link.active { background-color:rgba(255,255,255,0.1); color:white; border-left-color:var(--accent-blue); font-weight:600; }
        .main-content { margin-left:250px; padding:30px; }
        .header { background:white; padding:18px 22px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px; }
        .header h2 { margin:0; color:#2c3e50; }
        .card { background:white; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); overflow:hidden; }
        .card-header { padding:16px 20px; border-bottom:1px solid #e9ecef; font-weight:700; color:#2c3e50; display:flex; align-items:center; gap:10px; }
        .notification-list { max-height:480px; overflow-y:auto; }
        .notification-item { padding:14px 20px; border-bottom:1px solid #f1f3f5; display:flex; gap:12px; align-items:flex-start; transition:background 0.2s ease; }
        .notification-item:hover { background:#f8f9fa; }
        .notification-icon { color:#3498db; font-size:1.2rem; }
        .notification-text h4 { margin:0 0 6px 0; font-size:1rem; color:#2c3e50; }
        .notification-text p { margin:0; color:#666; font-size:0.95rem; }
        .notification-time { margin-top:4px; color:#999; font-size:0.85rem; }
        .empty-state { text-align:center; padding:30px; color:#999; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-bus-alt"></i> e-campusBus</h3>
            <p>Student Portal</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a class="nav-link" href="make_booking.php"><i class="fas fa-ticket-alt"></i> Book Bus</a>
            <a class="nav-link" href="bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            <a class="nav-link active" href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
            <a class="nav-link" href="routes.php"><i class="fas fa-route"></i> Routes</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <p style="margin:6px 0 0; color:#666;">Your latest booking updates</p>
        </div>

        <div class="card">
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
        function render(notifs) {
            const list = document.getElementById('notificationList');
            if (!notifs.length) {
                list.innerHTML = '<div class="empty-state"><i class="fas fa-inbox" style="font-size:2rem;"></i><p>No notifications yet</p></div>';
                return;
            }
            list.innerHTML = notifs.map(n => `
                <div class="notification-item">
                    <div class="notification-icon"><i class="fas fa-ticket-alt"></i></div>
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
            body: 'role=student&user_id=<?php echo $user_id; ?>'
        }).then(r => r.json()).then(data => {
            if (data.success) render(data.notifications || []);
        }).catch(err => console.error(err));
    </script>
</body>
</html>
