<?php
session_start();

// If already logged in, show role info
$role = $_SESSION['role'] ?? null;
$user = $_SESSION['username'] ?? 'Guest';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2C3E50;
            --accent: #3498DB;
            --danger: #E74C3C;
            --bg: #f5f7fa;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg); color: #2d3436; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.08); padding: 32px; max-width: 520px; width: 100%; text-align: center; }
        .icon { width: 70px; height: 70px; border-radius: 50%; background: rgba(231,76,60,0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: var(--danger); font-size: 32px; }
        h1 { font-size: 1.8rem; margin-bottom: 10px; color: var(--primary); }
        p { margin: 8px 0; color: #555; }
        .role { margin: 12px 0; padding: 10px 14px; background: #ecf0f1; border-radius: 8px; color: #555; display: inline-block; }
        .actions { margin-top: 20px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.button { text-decoration: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; }
        a.primary { background: var(--accent); color: #fff; }
        a.secondary { background: #fff; color: var(--primary); border-color: #dfe6e9; }
        a.button:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fas fa-lock"></i></div>
        <h1>Unauthorized</h1>
        <p>You don't have permission to access this page.</p>
        <?php if ($role): ?>
            <div class="role"><i class="fas fa-user-shield"></i> Logged in as: <?php echo htmlspecialchars($user); ?> (<?php echo htmlspecialchars($role); ?>)</div>
        <?php else: ?>
            <div class="role"><i class="fas fa-user-slash"></i> Not logged in</div>
        <?php endif; ?>
        <div class="actions">
            <a class="button primary" href="login.php"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            <a class="button secondary" href="index.php"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    </div>
</body>
</html>
