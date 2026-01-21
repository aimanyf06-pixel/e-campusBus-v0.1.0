<?php
// Database connection test script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Test - i-Bus System</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.85rem; }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class='container'>
        <h1 class='mb-4'>Database Connection Test</h1>";

try {
    // Try to include database configuration
    if (!file_exists('config/database.php')) {
        throw new Exception("Database configuration file not found at: config/database.php");
    }
    
    require_once 'config/database.php';
    
    // Test 1: Create Database object
    echo "<div class='card'>
        <div class='card-header'>
            <h5 class='mb-0'>Test 1: Database Object Creation</h5>
        </div>
        <div class='card-body'>";
    
    try {
        $database = new Database();
        echo "<p class='success'>✓ Database object created successfully</p>";
    } catch(Exception $e) {
        echo "<p class='error'>✗ Failed to create database object: " . $e->getMessage() . "</p>";
        throw $e;
    }
    
    echo "</div></div>";
    
    // Test 2: Database connection
    echo "<div class='card'>
        <div class='card-header'>
            <h5 class='mb-0'>Test 2: Database Connection</h5>
        </div>
        <div class='card-body'>";
    
    try {
        $conn = $database->getConnection();
        if ($conn) {
            echo "<p class='success'>✓ Database connection successful</p>";
            echo "<p><strong>Database:</strong> " . $conn->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
            echo "<p><strong>Connection Status:</strong> <span class='status-badge status-success'>Connected</span></p>";
        } else {
            echo "<p class='error'>✗ Database connection failed</p>";
        }
    } catch(PDOException $e) {
        echo "<p class='error'>✗ Database connection error: " . $e->getMessage() . "</p>";
        echo "<div class='alert alert-warning'>
            <h6>Common Solutions:</h6>
            <ul>
                <li>Check database credentials in config/database.php</li>
                <li>Verify MySQL/MariaDB is running</li>
                <li>Check database host and port</li>
                <li>Verify database user permissions</li>
                <li>Check if database exists</li>
            </ul>
        </div>";
        throw $e;
    }
    
    echo "</div></div>";
    
    // Test 3: Check if tables exist
    echo "<div class='card'>
        <div class='card-header'>
            <h5 class='mb-0'>Test 3: Check Required Tables</h5>
        </div>
        <div class='card-body'>";
    
    $required_tables = ['users', 'students', 'drivers', 'notifications', 'buses', 'routes', 'bookings'];
    $existing_tables = [];
    
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Found " . count($tables) . " table(s) in database:</strong></p>";
    echo "<ul>";
    foreach($tables as $table) {
        echo "<li>" . $table . "</li>";
        $existing_tables[] = $table;
    }
    echo "</ul>";
    
    $missing_tables = array_diff($required_tables, $existing_tables);
    if (empty($missing_tables)) {
        echo "<p class='success'>✓ All required tables exist</p>";
    } else {
        echo "<p class='warning'>⚠ Missing tables: " . implode(', ', $missing_tables) . "</p>";
        
        // Create missing tables button
        echo "<button class='btn btn-warning btn-sm' onclick='createMissingTables()'>Create Missing Tables</button>";
    }
    
    echo "</div></div>";
    
    // Test 4: Check users table structure and data
    if (in_array('users', $existing_tables)) {
        echo "<div class='card'>
            <div class='card-header'>
                <h5 class='mb-0'>Test 4: Users Table</h5>
            </div>
            <div class='card-body'>";
        
        // Get table structure
        $stmt = $conn->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h6>Table Structure:</h6>";
        echo "<div class='table-responsive mb-4'>
            <table class='table table-sm'>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
        
        // Count users
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_count = $result['count'];
        
        echo "<p><strong>Total Users:</strong> " . $user_count . "</p>";
        
        // Display sample users
        if ($user_count > 0) {
            echo "<h6>Sample Users:</h6>";
            $stmt = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY id LIMIT 10");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='table-responsive'>
                <table class='table table-sm'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>";
            
            foreach($users as $user) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td>" . $user['username'] . "</td>";
                echo "<td>" . $user['email'] . "</td>";
                echo "<td><span class='status-badge'>" . $user['role'] . "</span></td>";
                echo "<td>" . $user['created_at'] . "</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table></div>";
            
            // Test password verification
            echo "<h6 class='mt-4'>Password Verification Test:</h6>";
            $stmt = $conn->query("SELECT username, password FROM users WHERE username IN ('admin', 'student1', 'driver1')");
            $test_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($test_users as $test_user) {
                $test_passwords = [
                    'admin' => 'admin123',
                    'student1' => 'student123',
                    'driver1' => 'driver123'
                ];
                
                if (isset($test_passwords[$test_user['username']])) {
                    $password_correct = password_verify($test_passwords[$test_user['username']], $test_user['password']);
                    echo "<p>" . $test_user['username'] . ": " . 
                         ($password_correct ? 
                         "<span class='success'>✓ Password verification works</span>" : 
                         "<span class='error'>✗ Password verification failed</span>") . "</p>";
                }
            }
        } else {
            echo "<p class='warning'>⚠ No users found in database</p>";
            
            // Button to create demo users
            echo "<button class='btn btn-primary btn-sm' onclick='createDemoUsers()'>Create Demo Users</button>";
        }
        
        echo "</div></div>";
    }
    
    // Test 5: Database version and info
    echo "<div class='card'>
        <div class='card-header'>
            <h5 class='mb-0'>Test 5: Database Information</h5>
        </div>
        <div class='card-body'>";
    
    try {
        $version = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
        $client_version = $conn->getAttribute(PDO::ATTR_CLIENT_VERSION);
        $connection_status = $conn->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        
        echo "<p><strong>Database Version:</strong> " . $version . "</p>";
        echo "<p><strong>Client Version:</strong> " . $client_version . "</p>";
        echo "<p><strong>Connection Status:</strong> " . $connection_status . "</p>";
        echo "<p><strong>Character Set:</strong> " . $conn->query("SELECT @@character_set_database")->fetchColumn() . "</p>";
        
        // Get database size
        $stmt = $conn->query("SELECT table_schema 'Database', 
                            SUM(data_length + index_length) / 1024 / 1024 'Size (MB)' 
                            FROM information_schema.tables 
                            WHERE table_schema = DATABASE() 
                            GROUP BY table_schema");
        $db_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_info) {
            echo "<p><strong>Database Size:</strong> " . round($db_info['Size (MB)'], 2) . " MB</p>";
        }
        
    } catch(Exception $e) {
        echo "<p class='warning'>⚠ Could not retrieve database information: " . $e->getMessage() . "</p>";
    }
    
    echo "</div></div>";
    
    // Summary
    echo "<div class='card'>
        <div class='card-header bg-success text-white'>
            <h5 class='mb-0'>Test Summary</h5>
        </div>
        <div class='card-body'>";
    
    echo "<p><strong>Database Connection:</strong> <span class='status-badge status-success'>✓ PASS</span></p>";
    echo "<p><strong>Required Tables:</strong> " . 
         (empty($missing_tables) ? 
         "<span class='status-badge status-success'>✓ COMPLETE</span>" : 
         "<span class='status-badge status-warning'>⚠ INCOMPLETE</span>") . "</p>";
    echo "<p><strong>Test Users:</strong> " . 
         ($user_count > 0 ? 
         "<span class='status-badge status-success'>✓ AVAILABLE</span>" : 
         "<span class='status-badge status-warning'>⚠ MISSING</span>") . "</p>";
    
    echo "<div class='mt-3'>
            <a href='index.php' class='btn btn-primary'>Go to Home Page</a>
            <a href='login.php' class='btn btn-success'>Test Login</a>
            <button class='btn btn-info' onclick='location.reload()'>Refresh Test</button>
        </div>";
    
    echo "</div></div>";
    
} catch(Exception $e) {
    echo "<div class='alert alert-danger'>
        <h4>Database Test Failed</h4>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>
        <p><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>
        <p><strong>Trace:</strong></p>
        <pre>" . $e->getTraceAsString() . "</pre>
    </div>";
    
    // Show configuration help
    echo "<div class='card'>
        <div class='card-header bg-warning'>
            <h5 class='mb-0'>Configuration Help</h5>
        </div>
        <div class='card-body'>
            <h6>Create config/database.php with this content:</h6>
            <pre>&lt;?php
class Database {
    private \$host = 'localhost';
    private \$db_name = 'ibus_system';
    private \$username = 'root';
    private \$password = '';
    private \$conn;
    
    public function getConnection() {
        \$this->conn = null;
        try {
            \$this->conn = new PDO('mysql:host=' . \$this->host . ';dbname=' . \$this->db_name,
                \$this->username, \$this->password);
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$this->conn->exec('set names utf8');
        } catch(PDOException \$e) {
            error_log('Connection error: ' . \$e->getMessage());
        }
        return \$this->conn;
    }
}
?&gt;</pre>
            
            <h6>Create database using MySQL:</h6>
            <pre>CREATE DATABASE ibus_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ibus_system;</pre>
        </div>
    </div>";
}

echo "</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
<script>
function createDemoUsers() {
    if(confirm('This will create demo users (admin, student1, driver1). Continue?')) {
        fetch('create_demo_users.php')
            .then(response => response.text())
            .then(data => {
                alert('Demo users created successfully!');
                location.reload();
            })
            .catch(error => {
                alert('Error creating demo users: ' + error);
            });
    }
}

function createMissingTables() {
    if(confirm('This will create missing tables. Continue?')) {
        fetch('create_tables.php')
            .then(response => response.text())
            .then(data => {
                alert('Tables created successfully!');
                location.reload();
            })
            .catch(error => {
                alert('Error creating tables: ' + error);
            });
    }
}
</script>
</body>
</html>";
?>