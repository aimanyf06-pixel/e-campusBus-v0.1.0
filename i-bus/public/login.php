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
$remember_me = false;

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
    <title>Login - e-campusBus System</title>
    
    <!-- CSS -->
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
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
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

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #2980B9);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
        }

        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary);
        }

        .demo-credentials h6 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-right: none;
        }

        .form-control:focus + .input-group-text {
            border-color: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to your account</p>
            </div>
            
            <div class="p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Registration successful! Please login.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($username); ?>" 
                                   placeholder="Enter username or email"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter password"
                                   required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="remember" 
                               name="remember" 
                               <?php echo $remember_me ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="remember">Remember me</label>
                        <a href="forgot_password.php" class="float-end">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                    
                    <div class="text-center mb-3">
                        <span class="text-muted">Don't have an account?</span>
                        <a href="register.php?type=student">Register</a>
                    </div>
                    
                    <div class="demo-credentials">
                        <h6><i class="fas fa-vial me-2"></i>Demo Credentials</h6>
                        <small class="d-block mb-1"><strong>Admin:</strong> admin1 / admin123</small>
                        <small class="d-block mb-1"><strong>Driver:</strong> driver1 / driver123</small>
                        <small class="d-block"><strong>Student:</strong> student1 / student123</small>
                        <small class="d-block mt-2 text-muted">Note: Demo users will be redirected to respective dashboards</small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        // Form validation
        const form = document.getElementById('loginForm');
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if(!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Logging in...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if(usernameField && !usernameField.value) {
                setTimeout(() => {
                    usernameField.focus();
                }, 300);
            }
        });
        
        // Remove query parameters from URL without refresh
        if(window.history.replaceState && window.location.search) {
            const cleanURL = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({}, document.title, cleanURL);
        }
    </script>
</body>
</html>