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
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Add new route
                $stmt = $pdo->prepare("INSERT INTO routes (from_location, to_location, distance, duration, base_fare, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $_POST['from_location'],
                    $_POST['to_location'],
                    $_POST['distance'],
                    $_POST['duration'],
                    $_POST['base_fare']
                ]);
                $success = "Route added successfully!";
            } elseif ($_POST['action'] === 'edit') {
                // Update route
                $stmt = $pdo->prepare("UPDATE routes SET from_location = ?, to_location = ?, distance = ?, duration = ?, base_fare = ?, status = ? WHERE route_id = ?");
                $stmt->execute([
                    $_POST['from_location'],
                    $_POST['to_location'],
                    $_POST['distance'],
                    $_POST['duration'],
                    $_POST['base_fare'],
                    $_POST['status'],
                    $_POST['route_id']
                ]);
                $success = "Route updated successfully!";
            } elseif ($_POST['action'] === 'delete') {
                // Delete route
                $stmt = $pdo->prepare("DELETE FROM routes WHERE route_id = ?");
                $stmt->execute([$_POST['route_id']]);
                $success = "Route deleted successfully!";
            }
        }
    }
    
    // Get all routes
    $stmt = $pdo->query("SELECT * FROM routes ORDER BY route_id DESC");
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - e-campusBus System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .badge-active { background-color: #27AE60; color: white; }
        .badge-inactive { background-color: #E74C3C; color: white; }
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
            <a class="nav-link active" href="manage_routes.php"><i class="fas fa-route"></i> Manage Routes</a>
            <a class="nav-link" href="manage_buses.php"><i class="fas fa-bus"></i> Manage Buses</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a class="nav-link" href="reset_data.php"><i class="fas fa-database"></i> Reset Data</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-route"></i> Manage Routes</h2>
            <p class="mb-0">Add, edit, or delete bus routes</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Add Route Button -->
        <div class="mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouteModal">
                <i class="fas fa-plus"></i> Add New Route
            </button>
        </div>

        <!-- Routes Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Distance (km)</th>
                                <th>Duration (min)</th>
                                <th>Fare (RM)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($routes)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No routes found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach($routes as $route): ?>
                            <tr>
                                <td><?php echo $route['route_id']; ?></td>
                                <td><?php echo htmlspecialchars($route['from_location']); ?></td>
                                <td><?php echo htmlspecialchars($route['to_location']); ?></td>
                                <td><?php echo $route['distance']; ?></td>
                                <td><?php echo $route['duration']; ?></td>
                                <td>RM <?php echo number_format($route['base_fare'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $route['status']; ?>">
                                        <?php echo ucfirst($route['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editRoute(<?php echo htmlspecialchars(json_encode($route)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this route?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="route_id" value="<?php echo $route['route_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Route Modal -->
    <div class="modal fade" id="addRouteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Route</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">From Location</label>
                            <input type="text" name="from_location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">To Location</label>
                            <input type="text" name="to_location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Distance (km)</label>
                            <input type="number" step="0.01" name="distance" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Fare (RM)</label>
                            <input type="number" step="0.01" name="base_fare" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Route Modal -->
    <div class="modal fade" id="editRouteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Route</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="route_id" id="edit_route_id">
                        <div class="mb-3">
                            <label class="form-label">From Location</label>
                            <input type="text" name="from_location" id="edit_from_location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">To Location</label>
                            <input type="text" name="to_location" id="edit_to_location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Distance (km)</label>
                            <input type="number" step="0.01" name="distance" id="edit_distance" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration" id="edit_duration" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Fare (RM)</label>
                            <input type="number" step="0.01" name="base_fare" id="edit_base_fare" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Route</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editRoute(route) {
        document.getElementById('edit_route_id').value = route.route_id;
        document.getElementById('edit_from_location').value = route.from_location;
        document.getElementById('edit_to_location').value = route.to_location;
        document.getElementById('edit_distance').value = route.distance;
        document.getElementById('edit_duration').value = route.duration;
        document.getElementById('edit_base_fare').value = route.base_fare;
        document.getElementById('edit_status').value = route.status;
        new bootstrap.Modal(document.getElementById('editRouteModal')).show();
    }
    </script>
</body>
</html>
