<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . ($_SESSION['role'] ?? 'index.php'));
    exit();
}

$error = '';
$success = '';
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);

// Validate token
if(empty($token) || strlen($token) !== 64) {
    $error = "Invalid reset token.";
} else {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if token exists and is not expired
        $query = "SELECT id, username FROM users WHERE reset_token = :token AND reset_expiry > NOW()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->execute();
        
        if($stmt->rowCount() === 0) {
            $error = "Invalid or expired reset token. Please request a new password reset.";
        } else {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $user['id'];
            
            // Process password reset
            if($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Validate passwords
                if(empty($password) || empty($confirm_password)) {
                    $error = "Please fill in all fields.";
                } elseif($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif(strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } elseif(!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                    $error = "Password must contain both letters and numbers.";
                } else {
                    // Hash new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update password and clear reset token
                    $update_query = "UPDATE users SET password = :password, reset_token = NULL, reset_expiry = NULL, 
                                    login_attempts = 0, locked_until = NULL WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(":password", $hashed_password);
                    $update_stmt->bindParam(":id", $user_id);
                    
                    if($update_stmt->execute()) {
                        // Add notification
                        $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                              VALUES (:user_id, 'Password Reset', 'Your password has been reset successfully.', 'system')";
                        $notification_stmt = $db->prepare($notification_query);
                        $notification_stmt->bindParam(":user_id", $user_id);
                        $notification_stmt->execute();
                        
                        // Log the reset
                        error_log("Password reset successful for user ID: " . $user_id);
                        
                        $success = "Password has been reset successfully. You can now login with your new password.";
                    } else {
                        $error = "Failed to reset password. Please try again.";
                    }
                }
            }
        }
    } catch(PDOException $e) {
        error_log("Reset password error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - i-Bus System</title>
    
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

        .reset-container {
            width: 100%;
            max-width: 500px;
        }

        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .reset-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .reset-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary), #2980B9);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .password-strength {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .requirements-list li {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color: #6c757d;
        }

        .requirements-list li.valid {
            color: var(--success);
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

        .security-tips {
            background: #fff8e1;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #ffa000;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Reset Your Password</h2>
                <p>Create a new secure password</p>
            </div>
            
            <div class="p-4">
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <?php if(strpos($error, 'expired') !== false): ?>
                            <div class="mt-3">
                                <a href="forgot_password.php" class="btn btn-primary btn-sm me-2">
                                    <i class="fas fa-redo me-2"></i>Request New Link
                                </a>
                                <a href="login.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-success">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Now
                            </a>
                        </div>
                    </div>
                <?php elseif(empty($error) && isset($user)): ?>
                    <!-- User Info -->
                    <div class="user-info d-flex align-items-center gap-3">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Hello, <?php echo htmlspecialchars($user['username']); ?>!</h5>
                            <p class="text-muted mb-0">Please create a new secure password</p>
                        </div>
                    </div>
                    
                    <!-- Reset Form -->
                    <form method="POST" action="" id="resetForm">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter new password"
                                       minlength="8"
                                       required>
                                <button type="button" class="btn btn-outline-secondary password-toggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters with letters and numbers.</div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirm new password"
                                       required>
                            </div>
                            <div class="invalid-feedback" id="passwordMatchError">Passwords do not match.</div>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="mb-4">
                            <h6 class="mb-2">Password Requirements</h6>
                            <ul class="requirements-list" id="requirementsList">
                                <li id="reqLength"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> At least 8 characters</li>
                                <li id="reqLetter"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Contains at least one letter</li>
                                <li id="reqNumber"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Contains at least one number</li>
                                <li id="reqMatch"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Passwords match</li>
                            </ul>
                        </div>
                        
                        <!-- Security Tips -->
                        <div class="security-tips mb-4">
                            <h6><i class="fas fa-lightbulb me-2"></i>Security Tips</h6>
                            <ul class="mb-0 ps-3">
                                <li>Use a unique password you don't use elsewhere</li>
                                <li>Avoid personal information like birthdays</li>
                                <li>Consider using a password manager</li>
                                <li>Update your password regularly</li>
                            </ul>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-key me-2"></i>Reset Password
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>
                    </form>
                    
                    <!-- Security Info -->
                    <div class="text-center mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-2"></i>
                            This link will expire after use for security
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.closest('.input-group').querySelector('input')
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password'
                input.setAttribute('type', type)
                this.querySelector('i').className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash'
            })
        })

        // Password strength checker
        const passwordInput = document.getElementById('password')
        const confirmInput = document.getElementById('confirm_password')
        const strengthBar = document.getElementById('passwordStrengthBar')
        const requirements = {
            reqLength: document.getElementById('reqLength'),
            reqLetter: document.getElementById('reqLetter'),
            reqNumber: document.getElementById('reqNumber'),
            reqMatch: document.getElementById('reqMatch')
        }
        const submitBtn = document.getElementById('submitBtn')

        function updatePasswordRequirements() {
            const password = passwordInput.value
            const confirmPassword = confirmInput.value
            
            // Check requirements
            const hasLength = password.length >= 8
            const hasLetter = /[a-zA-Z]/.test(password)
            const hasNumber = /[0-9]/.test(password)
            const hasMatch = password === confirmPassword && password !== ''
            
            // Update UI
            updateRequirement(requirements.reqLength, hasLength)
            updateRequirement(requirements.reqLetter, hasLetter)
            updateRequirement(requirements.reqNumber, hasNumber)
            updateRequirement(requirements.reqMatch, hasMatch)
            
            // Update strength bar
            let strength = 0
            if (hasLength) strength += 25
            if (hasLetter) strength += 25
            if (hasNumber) strength += 25
            if (hasMatch) strength += 25
            
            strengthBar.style.width = strength + '%'
            strengthBar.style.backgroundColor = getStrengthColor(strength)
            
            // Validate password match
            if (confirmPassword && !hasMatch) {
                confirmInput.classList.add('is-invalid')
            } else {
                confirmInput.classList.remove('is-invalid')
            }
            
            // Enable/disable submit button
            const allValid = hasLength && hasLetter && hasNumber && hasMatch
            submitBtn.disabled = !allValid
        }

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i')
            if (isValid) {
                icon.className = 'fas fa-check-circle me-2 text-success'
                element.classList.add('valid')
            } else {
                icon.className = 'fas fa-circle me-2'
                element.classList.remove('valid')
            }
        }

        function getStrengthColor(strength) {
            if (strength <= 25) return '#dc3545'
            if (strength <= 50) return '#fd7e14'
            if (strength <= 75) return '#ffc107'
            return '#28a745'
        }

        // Event listeners
        passwordInput.addEventListener('input', updatePasswordRequirements)
        confirmInput.addEventListener('input', updatePasswordRequirements)
        
        // Initialize
        updatePasswordRequirements()

        // Form submission
        const form = document.getElementById('resetForm')
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault()
                return false
            }
            
            // Show loading
            const originalHTML = submitBtn.innerHTML
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting...'
            submitBtn.disabled = true
            
            return true
        })

        // Add bootstrap validation
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    </script>
</body>
</html>