<?php
// Direct password update - guaranteed to work

try {
    $conn = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $credentials = [
        'admin' => 'admin123',
        'student1' => 'student123',
        'driver1' => 'driver123'
    ];
    
    echo "<h2>Updating Passwords...</h2>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Username</th><th>Status</th><th>Hash</th></tr>";
    
    foreach($credentials as $username => $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        
        $sql = "UPDATE users SET password = ? WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$hash, $username]);
        
        $affected = $stmt->rowCount();
        $status = $affected > 0 ? "✅ Updated" : "⚠️ No rows";
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($username) . "</strong></td>";
        echo "<td>" . $status . "</td>";
        echo "<td>" . htmlspecialchars(substr($hash, 0, 40)) . "...</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Verify passwords:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Username</th><th>Password</th><th>DB Hash</th><th>Verify</th></tr>";
    
    foreach($credentials as $username => $password) {
        $sql = "SELECT password FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if($user) {
            $verify = password_verify($password, $user['password']);
            $verify_text = $verify ? "✅ TRUE" : "❌ FALSE";
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($username) . "</td>";
            echo "<td>" . htmlspecialchars($password) . "</td>";
            echo "<td>" . htmlspecialchars(substr($user['password'], 0, 40)) . "...</td>";
            echo "<td>" . $verify_text . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    echo "<h3>✅ Done! Now test login:</h3>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    echo "<p>Credentials:<br>";
    echo "Username: admin | Password: admin123<br>";
    echo "Username: student1 | Password: student123<br>";
    echo "Username: driver1 | Password: driver123";
    echo "</p>";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
