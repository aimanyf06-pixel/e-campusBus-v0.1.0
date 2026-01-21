<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . ($_SESSION['role'] ?? 'index.php'));
    exit();
}

$message = '';
$error = '';
$email = '';
$show_demo_link = false;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    // Validate email
    if(empty($email)) {
        $error = "Please enter your email address.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if email exists
            $query = "SELECT id, username FROM users WHERE email = :email AND (locked_until IS NULL OR locked_until < NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate reset token (32 bytes = 64 hex characters)
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database
                $update_query = "UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(":token", $token);
                $update_stmt->bindParam(":expiry", $expiry);
                $update_stmt->bindParam(":id", $user['id']);
                
                if($update_stmt->execute()) {
                    // Create reset link
                    $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                                 "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                                 "/reset_password.php?token=" . $token;
                    
                    // For demo purposes
                    $demo_message = "Password reset link has been generated. For demo: <a href='$reset_link' class='btn btn-sm btn-primary mt-2'>Click here to reset password</a>";
                    $show_demo_link = true;
                    
                    // In production, send email here
                    /*
                    $to = $email;
                    $subject = "i-Bus System - Password Reset";
                    $message = "Hello " . htmlspecialchars($user['username']) . ",\n\n";
                    $message .= "You have requested to reset your password.\n";
                    $message .= "Click the following link to reset your password:\n";
                    $message .= $reset_link . "\n\n";
                    $message .= "This link will expire in 1 hour.\n\n";
                    $message .= "If you didn't request this, please ignore this email.\n";
                    $headers = "From: noreply@ibus.edu.my\r\n";
                    
                    mail($to, $subject, $message, $headers);
                    */
                    
                    // Log the reset request
                    error_log("Password reset requested for: " . $email . " - Token: " . $token);
                    
                    $message = "Password reset instructions have been sent to your email.";
                } else {
                    $error = "Failed to generate reset token. Please try again.";
                }
            } else {
                // Don't reveal that email doesn't exist for security
                $message = "If an account exists with this email, you will receive reset instructions.";
            }
            
        } catch(PDOException $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - i-Bus System</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #3498DB;
            --success: #27AE60;
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

        .forgot-container {
            width: 100%;
            max-width: 500px;
        }

        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .forgot-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .forgot-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .process-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 20px;
            margin: 30px 0;
        }

        .process-steps:before {
            content: '';
            position: absolute;
            top: 25px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e9ecef;
            z-index: 1;
        }

        .step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.2rem;
            color: #6c757d;
            transition: all 0.3s;
        }

        .step.active .step-icon {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
            box-shadow: 0 0 0 5px rgba(52, 152, 219, 0.2);
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

        .register-options .btn {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="forgot-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Forgot Your Password?</h2>
                <p>We'll help you reset it quickly and securely</p>
            </div>
            
            <div class="p-4">
                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <?php if($show_demo_link): ?>
                            <div class="mt-3">
                                <?php echo $demo_message; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Back to Login
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="forgotForm">
                        <div class="mb-4">
                            <label for="email" class="form-label">Enter Your Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       placeholder="Enter your registered email"
                                       required>
                            </div>
                            <small class="form-text text-muted">
                                We'll send you a secure link to reset your password. The link expires in 1 hour.
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2 mb-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>
                    </form>
                    
                    <!-- Process Steps -->
                    <div class="process-steps">
                        <div class="step active">
                            <div class="step-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="step-label">Enter Email</div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-label">Check Email</div>
                        </div>
                        
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="step-label">Reset Password</div>
                        </div>
                    </div>
                    
                    <!-- Alternative Options -->
                    <div class="text-center mt-4">
                        <p class="text-muted mb-3">Don't have an account yet?</p>
                        <div class="register-options d-flex flex-column flex-md-row gap-2 justify-content-center">
                            <a href="register.php?type=student" class="btn btn-outline-primary">
                                <i class="fas fa-user-graduate me-2"></i>Register as Student
                            </a>
                            <a href="register.php?type=driver" class="btn btn-outline-success">
                                <i class="fas fa-user-tie me-2"></i>Register as Driver
                            </a>
                        </div>
                    </div>
                    
                    <!-- Security Info -->
                    <div class="text-center mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-2"></i>
                            Your information is secure and will not be shared
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        const form = document.getElementById('forgotForm');
        const emailInput = document.getElementById('email');
        
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if(!email) {
                e.preventDefault();
                showError('Please enter your email address');
                return false;
            }
            
            if(!emailRegex.test(email)) {
                e.preventDefault();
                showError('Please enter a valid email address');
                return false;
            }
            
            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        function showError(message) {
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert');
            if(existingAlert) {
                existingAlert.remove();
            }
            
            // Create error alert
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert after header
            const cardBody = document.querySelector('.p-4');
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
            
            // Scroll to error
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Real-time email validation
        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if(email && !emailRegex.test(email)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            if(emailInput && !emailInput.value) {
                setTimeout(() => {
                    emailInput.focus();
                }, 300);
            }
        });
    </script>
</body>
</html>