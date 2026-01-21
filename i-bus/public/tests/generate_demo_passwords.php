<?php
// Generate bcrypt hashes untuk demo credentials

echo "<h2>Generate Password Hashes untuk Demo Credentials</h2>";

$credentials = [
    'admin1' => 'admin123',
    'student1' => 'student123',
    'driver1' => 'driver123',
    'student2' => 'student123'
];

echo "<h3>SQL UPDATE Query:</h3>";
echo "<pre style='background:#f0f0f0; padding:15px; border-radius:5px;'>";

$sql = "-- Update all demo users passwords\n";
foreach($credentials as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $sql .= "UPDATE users SET password = '" . $hash . "' WHERE username = '" . $username . "';\n";
}

echo htmlspecialchars($sql);
echo "</pre>";

echo "<h3>Test Password Hashes:</h3>";
echo "<table border='1' cellpadding='10' style='width:100%; margin-top:20px;'>";
echo "<tr><th>Username</th><th>Password</th><th>Hash</th><th>Verify</th></tr>";

foreach($credentials as $username => $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $verify = password_verify($password, $hash);
    $verify_text = $verify ? "✅ TRUE" : "❌ FALSE";
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($username) . "</td>";
    echo "<td>" . htmlspecialchars($password) . "</td>";
    echo "<td>" . htmlspecialchars(substr($hash, 0, 50)) . "...</td>";
    echo "<td>" . $verify_text . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>📋 Instruction:</h3>";
echo "<ol>";
echo "<li>Copy SQL query di atas</li>";
echo "<li>Buka phpMyAdmin → database bus_management</li>";
echo "<li>Klik tab SQL</li>";
echo "<li>Paste SQL query kemudian tekan Go</li>";
echo "<li>Coba login dengan credential baru</li>";
echo "</ol>";

?>
