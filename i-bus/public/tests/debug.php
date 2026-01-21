<?php
// Debug file - check what's happening with login

session_start();

$db_host = "localhost";
$db_name = "bus_management";
$db_user = "root";
$db_pass = "";

echo "<h2>Debug Info</h2>";

// Test database connection
try {
    $conn = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "<p style='color:green;'>✅ Database connection successful!</p>";
} catch(PDOException $e) {
    echo "<p style='color:red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit();
}

// Test table exists
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch();
    echo "<p>✅ Users table exists with " . $row['count'] . " users</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Users table error: " . $e->getMessage() . "</p>";
}

// Show users
try {
    $stmt = $conn->query("SELECT id, username, email, role, password FROM users");
    $users = $stmt->fetchAll();
    
    echo "<h3>Users in Database:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Password Hash</th></tr>";
    foreach($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . substr($user['password'], 0, 30) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Error fetching users: " . $e->getMessage() . "</p>";
}

// Test login with student1
echo "<h3>Testing password_verify:</h3>";
$test_password = "password123";
$hash = '$2y$10$F1l.NKbNfxXjGPV/IVlF9.f9I5xGYvYG8JfP8Ls1lJkR0V5XH1N0.';
$verify = password_verify($test_password, $hash);
echo "<p>Password 'password123' verify result: " . ($verify ? "✅ TRUE" : "❌ FALSE") . "</p>";

// Check PHP error log
echo "<h3>Recent PHP Errors:</h3>";
echo "<pre>";
if(file_exists("/xampp/apache/logs/error.log")) {
    $lines = file("/xampp/apache/logs/error.log");
    $recent = array_slice($lines, -20);
    foreach($recent as $line) {
        echo htmlspecialchars($line);
    }
} else {
    echo "Error log not found at expected location";
}
echo "</pre>";

?>
