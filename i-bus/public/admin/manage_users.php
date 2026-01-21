<?php
require_once '../includes/auth.php';
checkRole('admin');

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
    
    // Handle Add User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $new_username = $_POST['username'];
        $new_email = $_POST['email'];
        $new_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $new_full_name = $_POST['full_name'];
        $new_phone = $_POST['phone_number'];
        $new_role = $_POST['role'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone_number, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        if ($stmt->execute([$new_username, $new_email, $new_password, $new_full_name, $new_phone, $new_role])) {
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user!";
        }
    }
    
    // Handle Edit User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
        $edit_id = $_POST['user_id'];
        $edit_username = $_POST['username'];
        $edit_email = $_POST['email'];
        $edit_full_name = $_POST['full_name'];
        $edit_phone = $_POST['phone_number'];
        $edit_role = $_POST['role'];
        $edit_status = $_POST['status'];
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $edit_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone_number = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$edit_username, $edit_email, $edit_password, $edit_full_name, $edit_phone, $edit_role, $edit_status, $edit_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone_number = ?, role = ?, status = ? WHERE id = ?");
            $stmt->execute([$edit_username, $edit_email, $edit_full_name, $edit_phone, $edit_role, $edit_status, $edit_id]);
        }
        
        $success = "User updated successfully!";
    }
    
    // Handle Delete User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
        $delete_id = $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = "User deleted successfully!";
        } else {
            $error = "Failed to delete user!";
        }
    }
    
    // Handle Ban User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
        $ban_id = $_POST['ban_user_id'];
        $ban_type = $_POST['ban_type'] ?? 'permanent';
        $ban_reason = $_POST['ban_reason'] ?? 'No reason provided';
        $ban_until = null;
        
        if ($ban_type === '1day') {
            $ban_until = date('Y-m-d H:i:s', strtotime('+1 day'));
        } elseif ($ban_type === '1week') {
            $ban_until = date('Y-m-d H:i:s', strtotime('+1 week'));
        } elseif ($ban_type === '1month') {
            $ban_until = date('Y-m-d H:i:s', strtotime('+1 month'));
        } elseif ($ban_type === 'custom' && !empty($_POST['custom_ban_date'])) {
            $ban_until = date('Y-m-d H:i:s', strtotime($_POST['custom_ban_date']));
        }
        
        if ($ban_type === 'permanent') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'banned', ban_reason = ?, banned_at = NOW(), ban_until = NULL WHERE id = ? AND id != ?");
            $stmt->execute([$ban_reason, $ban_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = 'banned', ban_reason = ?, banned_at = NOW(), ban_until = ? WHERE id = ? AND id != ?");
            $stmt->execute([$ban_reason, $ban_until, $ban_id, $user_id]);
        }
        
        if ($ban_type === 'permanent') {
            $success = "User banned permanently!";
        } else {
            $success = "User banned until " . date('M d, Y H:i', strtotime($ban_until));
        }
    }
    
    // Handle Unban User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
        $unban_id = $_POST['unban_user_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'active', ban_reason = NULL, banned_at = NULL, ban_until = NULL WHERE id = ?");
        $stmt->execute([$unban_id]);
        $success = "User unbanned successfully!";
    }
    
    // Get filter parameters
    $role_filter = $_GET['role'] ?? 'all';
    $status_filter = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Build query with filters
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if ($role_filter !== 'all') {
        $query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-sidebar.css">
    <style>
        :root {
            --primary-blue: #2C3E50;
            --accent-blue: #3498DB;
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--accent-blue) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .stat-box.blue { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-box.green { background: rgba(39, 174, 96, 0.1); color: #27AE60; }
        .stat-box.orange { background: rgba(243, 156, 18, 0.1); color: #F39C12; }
        .stat-box h3 { margin: 0; font-size: 2rem; font-weight: 700; }
        .stat-box p { margin: 5px 0 0 0; font-size: 0.9rem; }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .table-actions {
            display: flex;
            gap: 5px;
        }
        .badge-role {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-student { background: #3498DB; color: white; }
        .badge-driver { background: #27AE60; color: white; }
        .badge-admin { background: #E74C3C; color: white; }
        
        .badge-active { background: #D1ECF1; color: #0C5460; }
        .badge-inactive { background: #F8D7DA; color: #721C24; }
        .badge-suspended { background: #FFF3CD; color: #856404; }
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
            <a class="nav-link active" href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
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
            <h2><i class="fas fa-users"></i> Manage Users</h2>
            <p class="mb-0">Add, edit, and manage all system users</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <h3><?php echo $user_stats['student'] ?? 0; ?></h3>
                    <p><i class="fas fa-user-graduate"></i> Students</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <h3><?php echo $user_stats['driver'] ?? 0; ?></h3>
                    <p><i class="fas fa-id-card"></i> Drivers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <h3><?php echo $user_stats['admin'] ?? 0; ?></h3>
                    <p><i class="fas fa-user-shield"></i> Admins</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box blue">
                    <h3><?php echo count($users); ?></h3>
                    <p><i class="fas fa-users"></i> Total Users</p>
                </div>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-filter"></i> Filter & Search</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            
            <form method="GET" class="filter-section">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Username, email, or name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="driver" <?php echo $role_filter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-card">
            <h4 class="mb-3"><i class="fas fa-list"></i> Users List (<?php echo count($users); ?>)</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                            <td>
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone_number'] ?? '-'); ?></td>
                            <td>
                                <span class="badge-role badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['status']; ?> badge-role">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-sm btn-info" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['status'] === 'banned'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Unban this user?')">
                                        <input type="hidden" name="unban_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="unban_user" class="btn btn-sm btn-success" title="Unban user">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-warning" onclick="openBanModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Ban user">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($user['id'] != $user_id): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" id="edit_phone_number" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Ban User Modal -->
    <div class="modal fade" id="banModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-ban"></i> Ban User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong>User:</strong> <span id="ban_user_info"></span></p>
                        <p class="text-muted mb-3">Select ban duration and provide a reason:</p>
                        
                        <input type="hidden" name="ban_user_id" id="ban_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Ban Duration:</strong></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ban_type" id="ban_permanent" value="permanent" checked onchange="toggleCustomDate()">
                                <label class="form-check-label" for="ban_permanent">
                                    <i class="fas fa-infinity"></i> Permanent Ban
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ban_type" id="ban_1day" value="1day" onchange="toggleCustomDate()">
                                <label class="form-check-label" for="ban_1day">
                                    <i class="fas fa-calendar-day"></i> 1 Day
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ban_type" id="ban_1week" value="1week" onchange="toggleCustomDate()">
                                <label class="form-check-label" for="ban_1week">
                                    <i class="fas fa-calendar-week"></i> 1 Week
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ban_type" id="ban_1month" value="1month" onchange="toggleCustomDate()">
                                <label class="form-check-label" for="ban_1month">
                                    <i class="fas fa-calendar"></i> 1 Month
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ban_type" id="ban_custom" value="custom" onchange="toggleCustomDate()">
                                <label class="form-check-label" for="ban_custom">
                                    <i class="fas fa-calendar-alt"></i> Custom Date
                                </label>
                            </div>
                            <div id="customDateDiv" class="mt-2" style="display: none;">
                                <input type="datetime-local" name="custom_ban_date" class="form-control" id="custom_ban_date">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Ban Reason:</strong></label>
                            <select class="form-select mb-2" id="ban_reason_select" onchange="updateBanReason()">
                                <option value="">-- Select a reason --</option>
                                <option value="Multiple policy violations">Multiple policy violations</option>
                                <option value="Spam or advertising">Spam or advertising</option>
                                <option value="Inappropriate behavior">Inappropriate behavior</option>
                                <option value="Harassment or bullying">Harassment or bullying</option>
                                <option value="Fake or fraudulent activity">Fake or fraudulent activity</option>
                                <option value="Payment issues">Payment issues</option>
                                <option value="Safety concerns">Safety concerns</option>
                                <option value="Terms of service violation">Terms of service violation</option>
                                <option value="custom">Custom reason (type below)</option>
                            </select>
                            <textarea class="form-control" name="ban_reason" id="ban_reason_text" rows="3" placeholder="Enter ban reason..." required></textarea>
                        </div>
                        
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This user will not be able to log in or access the system.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="ban_user" class="btn btn-warning">
                            <i class="fas fa-ban"></i> Confirm Ban
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_phone_number').value = user.phone_number || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function openBanModal(user) {
            document.getElementById('ban_user_id').value = user.id;
            document.getElementById('ban_user_info').textContent = user.full_name + ' (' + user.username + ')';
            
            const modal = new bootstrap.Modal(document.getElementById('banModal'));
            modal.show();
        }

        function toggleCustomDate() {
            const customRadio = document.getElementById('ban_custom');
            const customDateDiv = document.getElementById('customDateDiv');
            const customDateInput = document.getElementById('custom_ban_date');
            
            if (customRadio.checked) {
                customDateDiv.style.display = 'block';
                customDateInput.required = true;
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                customDateInput.min = now.toISOString().slice(0, 16);
            } else {
                customDateDiv.style.display = 'none';
                customDateInput.required = false;
            }
        }

        function updateBanReason() {
            const select = document.getElementById('ban_reason_select');
            const textarea = document.getElementById('ban_reason_text');
            
            if (select.value && select.value !== 'custom') {
                textarea.value = select.value;
            } else if (select.value === 'custom') {
                textarea.value = '';
                textarea.focus();
            }
        }
    </script>
</body>
</html>
