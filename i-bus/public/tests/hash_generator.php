<?php
// Generate correct bcrypt hash for password123

$password = "password123";
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Hash yang benar untuk password 'password123':\n\n";
echo $hash . "\n\n";

// Copy paste SQL ini langsung ke phpMyAdmin
echo "========== COPY SQL INI KE phpMyAdmin ==========\n\n";

$sql = "UPDATE users SET password = '" . $hash . "' WHERE username IN ('student1', 'student2', 'driver1', 'admin1');";
echo $sql;

?>
