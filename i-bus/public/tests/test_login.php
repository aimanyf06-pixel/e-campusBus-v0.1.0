<?php
// Test login script - debug semua masalah

session_start();

$db_host = "localhost";
$db_name = "bus_management";
$db_user = "root";
$db_pass = "";

echo "<h2>Login Debug Test</h2>";

// 1. Test connection
echo "<h3>1. Database Connection</h3>";
try {
    $conn = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
    echo "<p style='color:green;'>✅ Connected to database</p>";
} catch(PDOException $e) {
    echo "<p style='color:red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    exit();
}

// 2. Test query user
echo "<h3>2. Query User 'student1'</h3>";
try {
    $username = "student1";
    $query = "SELECT id, username, password, role FROM users WHERE username = :username";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $username, PDO::PARAM_STR);
    $stmt->execute();
    
    if($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        echo "<p style='color:green;'>✅ User found</p>";
        echo "<p><strong>ID:</strong> " . $user['id'] . "</p>";
        echo "<p><strong>Username:</strong> " . $user['username'] . "</p>";
        echo "<p><strong>Role:</strong> " . $user['role'] . "</p>";
        echo "<p><strong>Password Hash:</strong> " . substr($user['password'], 0, 50) . "...</p>";
    } else {
        echo "<p style='color:red;'>❌ User not found</p>";
    }
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Query error: " . $e->getMessage() . "</p>";
}

// 3. Test password verify
echo "<h3>3. Test Password Verify</h3>";
$test_password = "password123";
$result = password_verify($test_password, $user['password']);
echo "<p>password_verify('password123', hash) = <strong style='color:" . ($result ? "green" : "red") . ";'>" . ($result ? "✅ TRUE" : "❌ FALSE") . "</strong></p>";

if(!$result) {
    echo "<p style='color:red;'>⚠️ Password tidak cocok! Hash di database mungkin belum di-update</p>";
    
    // Generate hash baru
    $new_hash = password_hash($test_password, PASSWORD_BCRYPT, ['cost' => 10]);
    echo "<h3>4. Generated Correct Hash</h3>";
    echo "<p><strong>Hash baru:</strong></p>";
    echo "<pre style='background:#f0f0f0; padding:10px; word-wrap:break-word;'>" . $new_hash . "</pre>";
    
    // Update database dengan hash baru
    try {
        $update_query = "UPDATE users SET password = :password WHERE username IN ('student1', 'student2', 'driver1', 'admin1')";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(":password", $new_hash, PDO::PARAM_STR);
        $update_stmt->execute();
        
        echo "<p style='color:green;'>✅ Password updated for all users!</p>";
        echo "<p>Coba login lagi dengan username: <strong>student1</strong> password: <strong>password123</strong></p>";
    } catch(Exception $e) {
        echo "<p style='color:red;'>❌ Update failed: " . $e->getMessage() . "</p>";
    }
}

?>
