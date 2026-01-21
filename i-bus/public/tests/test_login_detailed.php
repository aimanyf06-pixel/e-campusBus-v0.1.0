<?php
// Simulate actual login process dengan error detail

session_start();

$db_host = "localhost";
$db_name = "bus_management";
$db_user = "root";
$db_pass = "";

echo "<h2>Simulate Login Process</h2>";

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate POST data
$_POST['username'] = 'student1';
$_POST['password'] = 'password123';
$_POST['remember'] = false;

$username = trim($_POST['username']);
$password = $_POST['password'];

echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
echo "<p><strong>Password:</strong> " . str_repeat("*", strlen($password)) . "</p>";

echo "<h3>Step 1: Connect to Database</h3>";
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
    echo "<p style='color:green;'>✅ Database connected</p>";
} catch(PDOException $e) {
    echo "<p style='color:red;'>❌ Connection Error: " . $e->getMessage() . "</p>";
    exit();
}

echo "<h3>Step 2: Prepare Query</h3>";
try {
    $query = "SELECT id, username, password, role, full_name, login_attempts, locked_until 
             FROM users 
             WHERE (username = :username OR email = :username) 
             AND (locked_until IS NULL OR locked_until < NOW())";
    
    echo "<p>Query: " . str_replace(":username", "'" . $username . "'", $query) . "</p>";
    
    $stmt = $conn->prepare($query);
    echo "<p style='color:green;'>✅ Query prepared</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Query Error: " . $e->getMessage() . "</p>";
    exit();
}

echo "<h3>Step 3: Execute Query</h3>";
try {
    $stmt->bindParam(":username", $username, PDO::PARAM_STR);
    $stmt->execute();
    echo "<p style='color:green;'>✅ Query executed</p>";
    echo "<p>Rows found: " . $stmt->rowCount() . "</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Execute Error: " . $e->getMessage() . "</p>";
    exit();
}

echo "<h3>Step 4: Fetch User</h3>";
if($stmt->rowCount() === 1) {
    $user = $stmt->fetch();
    echo "<p style='color:green;'>✅ User found</p>";
    echo "<p>ID: " . $user['id'] . "</p>";
    echo "<p>Username: " . $user['username'] . "</p>";
    echo "<p>Role: " . $user['role'] . "</p>";
} else {
    echo "<p style='color:red;'>❌ User not found (rowCount: " . $stmt->rowCount() . ")</p>";
    exit();
}

echo "<h3>Step 5: Verify Password</h3>";
$password_match = password_verify($password, $user['password']);
echo "<p>password_verify result: " . ($password_match ? "<span style='color:green;'>✅ TRUE</span>" : "<span style='color:red;'>❌ FALSE</span>") . "</p>";
echo "<p>Hash stored: " . substr($user['password'], 0, 50) . "...</p>";

if(!$password_match) {
    echo "<p style='color:red;'>❌ Password tidak cocok</p>";
    exit();
}

echo "<h3>Step 6: Update Login Info</h3>";
try {
    $reset_query = "UPDATE users SET login_attempts = 0, last_login = NOW() WHERE id = :id";
    $reset_stmt = $conn->prepare($reset_query);
    $reset_stmt->bindParam(":id", $user['id'], PDO::PARAM_INT);
    $reset_stmt->execute();
    echo "<p style='color:green;'>✅ Login info updated</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Update Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Step 7: Set Session Variables</h3>";
try {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['login_time'] = time();
    
    echo "<p style='color:green;'>✅ Session variables set</p>";
    echo "<p>Session: " . json_encode($_SESSION) . "</p>";
} catch(Exception $e) {
    echo "<p style='color:red;'>❌ Session Error: " . $e->getMessage() . "</p>";
}

echo "<h3>✅ LOGIN SUCCESSFUL!</h3>";
echo "<p>Redirect ke: " . ($user['role'] === 'student' ? 'student/dashboard.php' : 'index.php') . "</p>";
echo "<p><a href='login.php'>Kembali ke Login</a></p>";

?>
