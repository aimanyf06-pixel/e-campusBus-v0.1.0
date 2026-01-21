<?php
// Check what users exist in database

try {
    $conn = new PDO(
        "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<h2>Users in Database:</h2>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Full Name</th></tr>";
    
    $sql = "SELECT id, username, email, role, full_name FROM users ORDER BY id";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $users = $stmt->fetchAll();
    
    if(count($users) > 0) {
        foreach($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='5'>❌ No users found</td></tr>";
    }
    
    echo "</table>";
    
    echo "<h3>📝 Note:</h3>";
    echo "<p>Guna username di atas untuk login, bukan 'admin'</p>";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
