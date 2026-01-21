<?php
// Script ini untuk membuat admin user jika belum ada
$host = 'localhost';
$dbname = 'bus_management';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hash password
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Insert admin user
    $sql = "INSERT INTO users (username, email, password, full_name, role) 
            VALUES (:username, :email, :password, :full_name, :role) 
            ON DUPLICATE KEY UPDATE password = :password";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':username' => 'admin',
        ':email' => 'admin@ibus.com',
        ':password' => $hashed_password,
        ':full_name' => 'System Administrator',
        ':role' => 'admin'
    ]);
    
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>