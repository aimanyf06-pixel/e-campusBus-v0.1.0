<?php
session_start();

// Jika sudah login redirect
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $redirect = "index.php";
    if($_SESSION['role'] === 'admin') $redirect = "admin/dashboard.php";
    elseif($_SESSION['role'] === 'driver') $redirect = "driver/dashboard.php";
    elseif($_SESSION['role'] === 'student') $redirect = "student/dashboard.php";
    header("Location: $redirect");
    exit();
}

$error = '';
$username = '';

// Handle login form
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $error = "Username dan password harus diisi";
    } else {
        try {
            // Connect to database
            $conn = new PDO(
                "mysql:host=localhost;dbname=bus_management;charset=utf8mb4",
                "root",
                "",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Simple query without named parameters
            $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $username]);
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Check password
                if(password_verify($password, $user['password'])) {
                    // Login successful
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Update last login
                    $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->execute([$user['id']]);
                    
                    // Redirect
                    if($user['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } elseif($user['role'] === 'driver') {
                        header("Location: driver/dashboard.php");
                    } elseif($user['role'] === 'student') {
                        header("Location: student/dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                } else {
                    $error = "Username atau password salah";
                }
            } else {
                $error = "Username atau password salah";
            }
            
            $conn = null;
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - i-Bus System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #3498DB;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #1a2530 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }

        .login-body {
            padding: 2rem;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--secondary), #2980B9);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
            color: white;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-bus login-icon"></i>
                <h1 class="mb-0">i-Bus System</h1>
                <p class="mb-0">Login Portal</p>
            </div>

            <div class="login-body">
                <?php if($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($username); ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <div class="mb-3 form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="remember" 
                            name="remember"
                        >
                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="btn btn-login w-100 text-white mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>

                <div style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-top: 20px; border-left: 4px solid var(--secondary);">
                    <p class="mb-2"><strong>Demo Credentials:</strong></p>
                    <small>
                        <p class="mb-1"><strong>Student:</strong> student1 / password123</p>
                        <p class="mb-1"><strong>Driver:</strong> driver1 / password123</p>
                        <p class="mb-1"><strong>Admin:</strong> admin1 / password123</p>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
