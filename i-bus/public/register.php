<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . ($_SESSION['role'] ?? 'index.php'));
    exit();
}

// Validate registration type
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
$allowed_types = ['student', 'driver'];
if(!in_array($type, $allowed_types)) {
    $type = 'student';
    header("Location: register.php?type=student");
    exit();
}

$error = '';
$success = '';
$form_data = [];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $form_data = [
        'username' => filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING),
        'phone' => filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING)
    ];
    
    // Add type-specific fields
    if($type === 'student') {
        $form_data['student_matrix'] = filter_input(INPUT_POST, 'student_matrix', FILTER_SANITIZE_STRING);
        $form_data['faculty'] = filter_input(INPUT_POST, 'faculty', FILTER_SANITIZE_STRING);
        $form_data['address'] = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    } else {
        $form_data['license_number'] = filter_input(INPUT_POST, 'license_number', FILTER_SANITIZE_STRING);
        $form_data['years_experience'] = filter_input(INPUT_POST, 'years_experience', FILTER_SANITIZE_NUMBER_INT);
        $form_data['emergency_contact'] = filter_input(INPUT_POST, 'emergency_contact', FILTER_SANITIZE_STRING);
    }
    
    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'confirm_password', 'full_name', 'phone'];
    if($type === 'student') {
        $required_fields = array_merge($required_fields, ['student_matrix', 'faculty', 'address']);
    } else {
        $required_fields = array_merge($required_fields, ['license_number', 'years_experience']);
    }
    
    $missing_fields = [];
    foreach($required_fields as $field) {
        if(empty($form_data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if(!empty($missing_fields)) {
        $error = "Please fill in all required fields.";
    } elseif(!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif(strlen($form_data['password']) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif($form_data['password'] !== $form_data['confirm_password']) {
        $error = "Passwords do not match.";
    } elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $error = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Additional validations
        if($type === 'student' && !preg_match('/^[A-Za-z0-9]{6,}$/', $form_data['student_matrix'])) {
            $error = "Please enter a valid student matrix number.";
        } elseif($type === 'driver' && ($form_data['years_experience'] < 0 || $form_data['years_experience'] > 50)) {
            $error = "Please enter valid years of experience (0-50).";
        }
        
        if(!$error) {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if username or email already exists
                $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(":username", $form_data['username']);
                $check_stmt->bindParam(":email", $form_data['email']);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $error = "Username or email already exists.";
                } else {
                    // Hash password
                    $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                    
                    // Begin transaction
                    $db->beginTransaction();
                    
                    try {
                        // Insert user
                        $user_query = "INSERT INTO users (username, password, email, role, full_name, phone, created_at) 
                                      VALUES (:username, :password, :email, :role, :full_name, :phone, NOW())";
                        
                        $user_stmt = $db->prepare($user_query);
                        $user_stmt->bindParam(":username", $form_data['username']);
                        $user_stmt->bindParam(":password", $hashed_password);
                        $user_stmt->bindParam(":email", $form_data['email']);
                        $user_stmt->bindParam(":role", $type);
                        $user_stmt->bindParam(":full_name", $form_data['full_name']);
                        $user_stmt->bindParam(":phone", $form_data['phone']);
                        
                        if($user_stmt->execute()) {
                            $user_id = $db->lastInsertId();
                            
                            // Insert type-specific data
                            if($type === 'student') {
                                $student_query = "INSERT INTO students (user_id, student_matrix, faculty, address) 
                                                 VALUES (:user_id, :student_matrix, :faculty, :address)";
                                $student_stmt = $db->prepare($student_query);
                                $student_stmt->bindParam(":user_id", $user_id);
                                $student_stmt->bindParam(":student_matrix", $form_data['student_matrix']);
                                $student_stmt->bindParam(":faculty", $form_data['faculty']);
                                $student_stmt->bindParam(":address", $form_data['address']);
                                $student_stmt->execute();
                            } else {
                                $driver_query = "INSERT INTO drivers (user_id, license_number, years_experience, emergency_contact) 
                                                VALUES (:user_id, :license_number, :years_experience, :emergency_contact)";
                                $driver_stmt = $db->prepare($driver_query);
                                $driver_stmt->bindParam(":user_id", $user_id);
                                $driver_stmt->bindParam(":license_number", $form_data['license_number']);
                                $driver_stmt->bindParam(":years_experience", $form_data['years_experience']);
                                $driver_stmt->bindParam(":emergency_contact", $form_data['emergency_contact']);
                                $driver_stmt->execute();
                            }
                            
                            // Add notification
                            $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                                  VALUES (:user_id, 'Welcome', 'Your account has been created successfully.', 'system', NOW())";
                            $notification_stmt = $db->prepare($notification_query);
                            $notification_stmt->bindParam(":user_id", $user_id);
                            $notification_stmt->execute();
                            
                            $db->commit();
                            $success = "Registration successful! You can now login.";
                            $form_data = []; // Clear form
                        }
                        
                    } catch(Exception $e) {
                        $db->rollBack();
                        error_log("Registration error: " . $e->getMessage());
                        $error = "An error occurred during registration. Please try again.";
                    }
                }
                
            } catch(PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - i-Bus System</title>
    
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
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }

        .register-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .type-selector .card {
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            height: 100%;
        }

        .type-selector .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .type-selector .card.active {
            border-color: var(--secondary);
            background: rgba(52, 152, 219, 0.05);
        }

        .type-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }

        .type-icon.student {
            background: linear-gradient(135deg, var(--secondary), #2980B9);
        }

        .type-icon.driver {
            background: linear-gradient(135deg, var(--success), #229954);
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title i {
            font-size: 1.5rem;
            color: var(--secondary);
            margin-right: 10px;
        }

        .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }

        .form-control {
            border-left: none;
            padding: 12px 15px;
        }

        .form-control:focus {
            border-color: #ced4da;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
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

        .btn-submit {
            background: linear-gradient(135deg, var(--secondary), #2980B9);
            border: none;
            padding: 15px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }

        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.5rem;
            color: white;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">Join i-Bus System</h1>
            <p class="lead text-muted">Register for a free account and experience seamless campus transportation</p>
        </div>
        
        <!-- Registration Type Selector -->
        <div class="row g-4 mb-5 type-selector">
            <div class="col-md-6">
                <a href="?type=student" class="text-decoration-none">
                    <div class="card p-4 <?php echo $type === 'student' ? 'active' : ''; ?>">
                        <div class="type-icon student">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="text-center mb-3">Student Registration</h3>
                        <p class="text-muted text-center">Register as a student to book bus seats, track buses, and manage travel</p>
                        <ul class="list-unstyled mt-3">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Easy bus booking</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Real-time tracking</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Booking history</li>
                            <li><i class="fas fa-check text-success me-2"></i> Driver ratings</li>
                        </ul>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6">
                <a href="?type=driver" class="text-decoration-none">
                    <div class="card p-4 <?php echo $type === 'driver' ? 'active' : ''; ?>">
                        <div class="type-icon driver">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3 class="text-center mb-3">Driver Registration</h3>
                        <p class="text-muted text-center">Register as a driver to manage schedule, view passengers, and receive ratings</p>
                        <ul class="list-unstyled mt-3">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Schedule management</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Passenger lists</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Performance tracking</li>
                            <li><i class="fas fa-check text-success me-2"></i> Rating system</li>
                        </ul>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Registration Form -->
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
        <?php else: ?>
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registrationForm" class="needs-validation" novalidate>
                <!-- Account Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-circle"></i>
                        <h4>Account Information</h4>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" 
                                       placeholder="Enter username"
                                       pattern="[a-zA-Z0-9_]+"
                                       required>
                            </div>
                            <small class="form-text text-muted">Letters, numbers, and underscores only</small>
                            <div class="invalid-feedback">Please enter a valid username (letters, numbers, underscores only).</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                                       placeholder="Enter email"
                                       required>
                            </div>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter password"
                                       minlength="8"
                                       required>
                                <button type="button" class="btn btn-outline-secondary password-toggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters long.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirm password"
                                       required>
                            </div>
                            <div class="invalid-feedback" id="passwordMatchError">Passwords do not match.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-id-card"></i>
                        <h4>Personal Information</h4>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="full_name" 
                                       name="full_name" 
                                       value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" 
                                       placeholder="Enter full name"
                                       required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" 
                                       placeholder="Enter phone number"
                                       required>
                            </div>
                        </div>
                        
                        <?php if($type === 'student'): ?>
                            <div class="col-md-6">
                                <label for="student_matrix" class="form-label">Student Matrix <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="student_matrix" 
                                           name="student_matrix" 
                                           value="<?php echo htmlspecialchars($form_data['student_matrix'] ?? ''); ?>" 
                                           placeholder="Enter matrix number"
                                           pattern="[A-Za-z0-9]{6,}"
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="faculty" class="form-label">Faculty <span class="text-danger">*</span></label>
                                <select class="form-select" id="faculty" name="faculty" required>
                                    <option value="">Select faculty</option>
                                    <option value="Computer Science" <?php echo ($form_data['faculty'] ?? '') === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Engineering" <?php echo ($form_data['faculty'] ?? '') === 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                    <option value="Business" <?php echo ($form_data['faculty'] ?? '') === 'Business' ? 'selected' : ''; ?>>Business</option>
                                    <option value="Medicine" <?php echo ($form_data['faculty'] ?? '') === 'Medicine' ? 'selected' : ''; ?>>Medicine</option>
                                    <option value="Law" <?php echo ($form_data['faculty'] ?? '') === 'Law' ? 'selected' : ''; ?>>Law</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" 
                                          id="address" 
                                          name="address" 
                                          rows="2" 
                                          placeholder="Enter address"
                                          required><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6">
                                <label for="license_number" class="form-label">License Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="license_number" 
                                           name="license_number" 
                                           value="<?php echo htmlspecialchars($form_data['license_number'] ?? ''); ?>" 
                                           placeholder="Enter license number"
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="years_experience" class="form-label">Years of Experience <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="years_experience" 
                                           name="years_experience" 
                                           value="<?php echo htmlspecialchars($form_data['years_experience'] ?? ''); ?>" 
                                           placeholder="Years"
                                           min="0" 
                                           max="50"
                                           required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="emergency_contact" 
                                           name="emergency_contact" 
                                           value="<?php echo htmlspecialchars($form_data['emergency_contact'] ?? ''); ?>" 
                                           placeholder="Emergency contact">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-list-check"></i>
                        <h4>Password Requirements</h4>
                    </div>
                    
                    <ul class="requirements-list" id="requirementsList">
                        <li id="reqLength"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> At least 8 characters</li>
                        <li id="reqLetter"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Contains at least one letter</li>
                        <li id="reqNumber"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Contains at least one number</li>
                        <li id="reqMatch"><i class="fas fa-circle me-2" style="font-size: 0.5rem;"></i> Passwords match</li>
                    </ul>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="form-section">
                    <div class="form-check mb-3">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="terms" 
                               name="terms" 
                               required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> 
                            and <a href="privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                        <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="text-center mb-5">
                    <button type="submit" class="btn btn-submit btn-lg px-5">
                        <i class="fas fa-user-plus me-2"></i>
                        Register as <?php echo ucfirst($type); ?>
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary btn-lg ms-3">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Already have an account?
                    </a>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- Features -->
        <div class="row g-4 mt-5">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon" style="background: linear-gradient(135deg, var(--secondary), #2980B9);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h5>Secure & Safe</h5>
                    <p class="text-muted">Your data is encrypted and protected with advanced security measures.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon" style="background: linear-gradient(135deg, var(--success), #229954);">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h5>Quick Registration</h5>
                    <p class="text-muted">Complete registration in minutes and start using immediately.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #E67E22, #D35400);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h5>24/7 Support</h5>
                    <p class="text-muted">Get assistance anytime with our dedicated support team.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

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
        const form = document.getElementById('registrationForm')
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault()
                return false
            }
            
            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]')
            const originalHTML = submitBtn.innerHTML
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...'
            submitBtn.disabled = true
            
            return true
        })

        // Phone number formatting
        const phoneInput = document.getElementById('phone')
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '')
                if (value.length > 3 && value.length <= 6) {
                    value = value.replace(/(\d{3})(\d+)/, '$1-$2')
                } else if (value.length > 6) {
                    value = value.replace(/(\d{3})(\d{3})(\d+)/, '$1-$2-$3')
                }
                e.target.value = value
            })
        }
    </script>
</body>
</html>