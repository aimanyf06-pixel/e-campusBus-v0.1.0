<?php
require_once '../includes/auth.php';
checkRole('admin');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

$success = null;
$error = null;

// Safe defaults to avoid undefined variable notices if a DB error happens
$buses = [];
$available_drivers = [];
$all_drivers = [];
$status_stats = [];
$total_buses = 0;
$available_buses = 0;
$in_use_buses = 0;
$maintenance_buses = 0;

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle Add Bus
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bus'])) {
        $bus_number = strtoupper(trim($_POST['bus_number'] ?? ''));
        $capacity = (int)($_POST['capacity'] ?? 0);
        $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;
        $status = $_POST['status'] ?? 'available';

        if ($bus_number === '' || $capacity <= 0) {
            $error = "Please provide a valid bus number and capacity.";
        }
        
        // Check if bus number already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE bus_number = ?");
        $stmt->execute([$bus_number]);
        $bus_number_exists = $stmt->fetchColumn();
        if ($bus_number_exists > 0) {
            $error = "Bus number already exists. Please use a unique bus number.";
        }
        
        // Check if driver already has a bus assigned
        if ($driver_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $driver_has_bus = $stmt->fetchColumn();
            
            if ($driver_has_bus > 0) {
                $error = "This driver already has a bus assigned. Each driver can only have one bus.";
            }
        }
        
        if (!$error) {
            try {
                $stmt = $pdo->prepare("INSERT INTO buses (bus_number, capacity, driver_id, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$bus_number, $capacity, $driver_id, $status]);
                $success = "Bus added successfully!";
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $error = "Bus number already exists. Please use a unique bus number.";
                } else {
                    $error = "Failed to add bus: " . $ex->getMessage();
                }
            }
        }
    }
    
    // Handle Edit Bus
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bus'])) {
        $bus_id = $_POST['bus_id'];
        $bus_number = strtoupper(trim($_POST['bus_number'] ?? ''));
        $capacity = (int)($_POST['capacity'] ?? 0);
        $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;
        $status = $_POST['status'] ?? 'available';

        if ($bus_number === '' || $capacity <= 0) {
            $error = "Please provide a valid bus number and capacity.";
        }
        
        // Check if bus number already exists for another bus
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE bus_number = ? AND bus_id != ?");
        $stmt->execute([$bus_number, $bus_id]);
        $bus_number_exists = $stmt->fetchColumn();
        if ($bus_number_exists > 0) {
            $error = "Bus number already exists. Please use a unique bus number.";
        }
        
        // Check if driver already has a different bus assigned
        if ($driver_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE driver_id = ? AND bus_id != ?");
            $stmt->execute([$driver_id, $bus_id]);
            $driver_has_other_bus = $stmt->fetchColumn();
            
            if ($driver_has_other_bus > 0) {
                $error = "This driver already has a different bus assigned. Please unassign the other bus first.";
            }
        }
        
        if (!$error) {
            try {
                $stmt = $pdo->prepare("UPDATE buses SET bus_number = ?, capacity = ?, driver_id = ?, status = ? WHERE bus_id = ?");
                $stmt->execute([$bus_number, $capacity, $driver_id, $status, $bus_id]);
                $success = "Bus updated successfully!";
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $error = "Bus number already exists. Please use a unique bus number.";
                } else {
                    $error = "Failed to update bus: " . $ex->getMessage();
                }
            }
        }
    }
    
    // Handle Delete Bus
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bus'])) {
        $bus_id = $_POST['bus_id'];
        $stmt = $pdo->prepare("DELETE FROM buses WHERE bus_id = ?");
        if ($stmt->execute([$bus_id])) {
            $success = "Bus deleted successfully!";
        } else {
            $error = "Failed to delete bus!";
        }
    }
    
    // Get all buses with driver info
    $stmt = $pdo->query("
        SELECT b.*, u.full_name as driver_name, u.username as driver_username
        FROM buses b
        LEFT JOIN users u ON b.driver_id = u.id AND u.role = 'driver'
        ORDER BY b.bus_id DESC
    ");
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all drivers for dropdown (only drivers without assigned buses)
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, u.username 
        FROM users u
        WHERE u.role = 'driver' 
        AND u.id NOT IN (SELECT driver_id FROM buses WHERE driver_id IS NOT NULL)
        ORDER BY u.full_name
    ");
    $available_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all drivers (for edit modal - includes currently assigned driver)
    $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'driver' ORDER BY full_name");
    $all_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM buses GROUP BY status");
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    
    $total_buses = array_sum($status_stats);
    $available_buses = $status_stats['available'] ?? 0;
    $in_use_buses = $status_stats['in_use'] ?? 0;
    $maintenance_buses = $status_stats['maintenance'] ?? 0;
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buses - e-campusBus System</title>
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
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .stat-box.blue { background: rgba(52, 152, 219, 0.1); color: var(--accent-blue); }
        .stat-box.green { background: rgba(39, 174, 96, 0.1); color: #27AE60; }
        .stat-box.orange { background: rgba(243, 156, 18, 0.1); color: #F39C12; }
        .stat-box.red { background: rgba(231, 76, 60, 0.1); color: #E74C3C; }
        .stat-box h3 { margin: 0; font-size: 2.5rem; font-weight: 700; }
        .stat-box p { margin: 5px 0 0 0; font-size: 0.9rem; font-weight: 600; }
        
        .bus-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .bus-card:hover {
            border-color: var(--accent-blue);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
            transform: translateY(-2px);
        }
        .bus-icon {
            font-size: 3rem;
            color: var(--accent-blue);
            margin-bottom: 15px;
        }
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-available { background: #D1ECF1; color: #0C5460; }
        .badge-in_use { background: #D4EDDA; color: #155724; }
        .badge-maintenance { background: #FFF3CD; color: #856404; }
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
            <a class="nav-link active" href="manage_buses.php"><i class="fas fa-bus"></i> Manage Buses</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a class="nav-link" href="reset_data.php"><i class="fas fa-database"></i> Reset Data</a>
            <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2><i class="fas fa-bus"></i> Manage Buses</h2>
            <p class="mb-0">Add, edit, and manage bus fleet</p>
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box blue">
                    <h3><?php echo $total_buses; ?></h3>
                    <p><i class="fas fa-bus"></i> Total Buses</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box green">
                    <h3><?php echo $available_buses; ?></h3>
                    <p><i class="fas fa-check-circle"></i> Available</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box orange">
                    <h3><?php echo $in_use_buses; ?></h3>
                    <p><i class="fas fa-road"></i> In Use</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box red">
                    <h3><?php echo $maintenance_buses; ?></h3>
                    <p><i class="fas fa-wrench"></i> Maintenance</p>
                </div>
            </div>
        </div>

        <!-- Action Button -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-list"></i> Bus Fleet (<?php echo count($buses); ?>)</h4>
                <div>
                    <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#checkDriversModal">
                        <i class="fas fa-user-check"></i> Check Drivers
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusModal">
                        <i class="fas fa-plus"></i> Add New Bus
                    </button>
                </div>
            </div>
        </div>

        <!-- Buses Grid -->
        <div class="row">
            <?php if (empty($buses)): ?>
            <div class="col-12">
                <div class="content-card text-center">
                    <i class="fas fa-bus fa-4x text-muted mb-3"></i>
                    <h4>No Buses Available</h4>
                    <p>Click "Add New Bus" to add your first bus to the fleet.</p>
                </div>
            </div>
            <?php else: ?>
            <?php foreach($buses as $bus): ?>
            <div class="col-md-6 col-lg-4">
                <div class="bus-card">
                    <div class="text-center">
                        <div class="bus-icon">
                            <i class="fas fa-bus"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($bus['bus_number']); ?></h4>
                        <span class="badge-status badge-<?php echo $bus['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $bus['status'])); ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <i class="fas fa-chair"></i> 
                        <strong>Capacity:</strong> <?php echo $bus['capacity']; ?> seats
                    </div>
                    
                    <div class="mb-3">
                        <i class="fas fa-id-card"></i> 
                        <strong>Driver:</strong> 
                        <?php if ($bus['driver_name']): ?>
                            <?php echo htmlspecialchars($bus['driver_name']); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($bus['driver_username']); ?>)</small>
                        <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-info btn-sm flex-fill" onclick='editBus(<?php echo json_encode($bus); ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this bus?')">
                            <input type="hidden" name="bus_id" value="<?php echo $bus['bus_id']; ?>">
                            <button type="submit" name="delete_bus" class="btn btn-danger btn-sm w-100">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Bus Modal -->
    <div class="modal fade" id="addBusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bus"></i> Add New Bus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Bus Number *</label>
                            <input type="text" name="bus_number" class="form-control" placeholder="e.g., B001" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity (seats) *</label>
                            <input type="number" name="capacity" class="form-control" min="1" placeholder="e.g., 40" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Driver</label>
                            <select name="driver_id" class="form-select" id="addDriverSelect">
                                <option value="">No driver assigned</option>
                                <?php foreach($available_drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>">
                                    <?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['username']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Only drivers without assigned buses are shown</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="in_use">In Use</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_bus" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Bus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Bus Modal -->
    <div class="modal fade" id="editBusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Bus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="bus_id" id="edit_bus_id">
                        <div class="mb-3">
                            <label class="form-label">Bus Number *</label>
                            <input type="text" name="bus_number" id="edit_bus_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity (seats) *</label>
                            <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Driver</label>
                            <select name="driver_id" id="edit_driver_id" class="form-select">
                                <option value="">No driver assigned</option>
                                <?php foreach($all_drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>">
                                    <?php echo htmlspecialchars($driver['full_name']); ?> (<?php echo htmlspecialchars($driver['username']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Note: Each driver can only have one bus assigned</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="in_use">In Use</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_bus" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Bus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Check Drivers Modal -->
    <div class="modal fade" id="checkDriversModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2C3E50 0%, #3498DB 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-check"></i> Driver Status Monitor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="fas fa-check-circle"></i> Available Drivers</h6>
                                </div>
                                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($available_drivers)): ?>
                                        <p class="text-muted text-center mb-0">All drivers have been assigned to buses</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                        <?php foreach($available_drivers as $driver): ?>
                                            <li class="list-group-item d-flex align-items-center">
                                                <i class="fas fa-user-circle text-success me-2 fs-5"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($driver['full_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($driver['username']); ?></small>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Total: <strong><?php echo count($available_drivers); ?></strong> driver(s)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-bus"></i> Drivers with Assigned Buses</h6>
                                </div>
                                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                    <?php 
                                    $assigned_drivers = [];
                                    foreach($buses as $bus) {
                                        if ($bus['driver_id']) {
                                            $assigned_drivers[] = [
                                                'name' => $bus['driver_name'],
                                                'username' => $bus['driver_username'],
                                                'bus_number' => $bus['bus_number']
                                            ];
                                        }
                                    }
                                    ?>
                                    <?php if (empty($assigned_drivers)): ?>
                                        <p class="text-muted text-center mb-0">No drivers have been assigned to buses yet</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                        <?php foreach($assigned_drivers as $driver): ?>
                                            <li class="list-group-item d-flex align-items-center">
                                                <i class="fas fa-user-check text-primary me-2 fs-5"></i>
                                                <div class="flex-grow-1">
                                                    <strong><?php echo htmlspecialchars($driver['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($driver['username']); ?></small>
                                                </div>
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-bus"></i> <?php echo htmlspecialchars($driver['bus_number']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> Total: <strong><?php echo count($assigned_drivers); ?></strong> driver(s)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Each driver can only be assigned to one bus at a time. Available drivers can be assigned when adding or editing a bus.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBus(bus) {
            document.getElementById('edit_bus_id').value = bus.bus_id;
            document.getElementById('edit_bus_number').value = bus.bus_number;
            document.getElementById('edit_capacity').value = bus.capacity;
            document.getElementById('edit_driver_id').value = bus.driver_id || '';
            document.getElementById('edit_status').value = bus.status;
            
            const modal = new bootstrap.Modal(document.getElementById('editBusModal'));
            modal.show();
        }
    </script>
</body>
</html>
