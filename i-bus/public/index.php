<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>i-Bus System - Smart Campus Transportation</title>
    
    <!-- Core CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #3498DB;
            --success: #27AE60;
            --warning: #E67E22;
            --danger: #E74C3C;
            --light: #F8F9FA;
            --dark: #2C3E50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            overflow-x: hidden;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, #1a2530 100%);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
        }

        .navbar-brand i {
            color: var(--secondary);
            margin-right: 8px;
        }

        .nav-link {
            font-weight: 500;
            margin: 0 8px;
            color: rgba(255, 255, 255, 0.9) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--secondary) !important;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), 
                        url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            color: white;
            padding: 120px 0 100px;
            position: relative;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        /* Features */
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #eaeaea;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        /* Stats */
        .stats-section {
            background: linear-gradient(135deg, var(--primary) 0%, #1a2530 100%);
            color: white;
            padding: 80px 0;
        }

        .stat-item h3 {
            font-size: 3rem;
            font-weight: 700;
            color: var(--secondary);
        }

        /* Testimonials */
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 5px solid var(--secondary);
        }

        /* Footer */
        .footer {
            background: var(--primary);
            color: white;
            padding: 80px 0 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .feature-card {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 576px) {
            .hero-content h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-bus"></i> i-Bus System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if(isset($_SESSION['logged_in'])): ?>
                        <?php if($_SESSION['role'] == 'student'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="student/dashboard.php">
                                    <i class="fas fa-user-graduate"></i> Dashboard
                                </a>
                            </li>
                        <?php elseif($_SESSION['role'] == 'driver'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="driver/dashboard.php">
                                    <i class="fas fa-user-tie"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary" href="register.php?type=student">
                                Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1>Smart Campus Bus Management System</h1>
                        <p class="lead mb-4">Efficient, reliable, and convenient bus transportation for students and staff.</p>
                        <div class="hero-btns">
                            <?php if(!isset($_SESSION['logged_in'])): ?>
                                <a href="register.php?type=student" class="btn btn-primary btn-lg me-3">
                                    <i class="fas fa-user-graduate"></i> Student Register
                                </a>
                                <a href="register.php?type=driver" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-user-tie"></i> Driver Register
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-features mt-4 mt-lg-0 p-4 bg-white bg-opacity-10 rounded">
                        <h4 class="text-white mb-3">Why Choose i-Bus?</h4>
                        <ul class="list-unstyled text-white">
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Real-time GPS tracking</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Easy online booking</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Secure payment system</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Driver rating system</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Mobile-friendly design</li>
                            <li><i class="fas fa-check-circle text-success"></i> 24/7 support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">System Features</h2>
                <p class="lead text-muted">Designed for modern campus transportation needs</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--secondary), #2980B9);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h4>For Students</h4>
                        <p class="text-muted">Easy bus booking, real-time tracking, booking history, and driver ratings.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--success), #229954);">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h4>For Drivers</h4>
                        <p class="text-muted">Schedule management, passenger lists, performance tracking, and ratings.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--warning), #D35400);">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h4>System Features</h4>
                        <p class="text-muted">Secure authentication, real-time updates, QR validation, and reporting.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p>Students Registered</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-item">
                        <h3>50+</h3>
                        <p>Professional Drivers</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-item">
                        <h3>20+</h3>
                        <p>Bus Routes</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-item">
                        <h3>10K+</h3>
                        <p>Trips Completed</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">What Users Say</h2>
                <p class="lead text-muted">Hear from our satisfied users</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-primary p-3 me-3">
                                <i class="fas fa-user-graduate text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Ahmad Ali</h5>
                                <p class="text-muted mb-0">Computer Science Student</p>
                            </div>
                        </div>
                        <p class="fst-italic">"i-Bus has made my campus life so much easier. Booking buses is quick and reliable!"</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-success p-3 me-3">
                                <i class="fas fa-user-tie text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Siti Sarah</h5>
                                <p class="text-muted mb-0">Bus Driver</p>
                            </div>
                        </div>
                        <p class="fst-italic">"The driver dashboard is fantastic! Makes my job more organized and efficient."</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-warning p-3 me-3">
                                <i class="fas fa-user-md text-white"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Dr. Lim</h5>
                                <p class="text-muted mb-0">Faculty Head</p>
                            </div>
                        </div>
                        <p class="fst-italic">"This system has significantly improved our campus transportation efficiency."</p>
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5" style="background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');">
        <div class="container text-center text-white">
            <h2 class="display-5 fw-bold mb-4">Ready to Simplify Your Campus Transportation?</h2>
            <p class="lead mb-5">Join hundreds of students and drivers who trust i-Bus System.</p>
            <div>
                <?php if(!isset($_SESSION['logged_in'])): ?>
                    <a href="register.php?type=student" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login to Account
                    </a>
                <?php else: ?>
                    <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-5">
                    <a href="index.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
                        <i class="fas fa-bus fa-2x me-2 text-primary"></i>
                        <span class="fs-4 fw-bold">i-Bus System</span>
                    </a>
                    <p class="text-white-50">Campus bus management system for efficient transportation.</p>
                    <div class="d-flex mt-4">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-5">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-white-50 text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#features" class="text-white-50 text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="#testimonials" class="text-white-50 text-decoration-none">Testimonials</a></li>
                        <li class="mb-2"><a href="#contact" class="text-white-50 text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-5">
                    <h5 class="text-white mb-4">Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Bus Booking</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Route Information</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Driver Management</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Real-time Tracking</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-5">
                    <h5 class="text-white mb-4">Contact Us</h5>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            University Campus, Kajang, Selangor
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            +603-1234 5678
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            support@ibus.edu.my
                        </li>
                        <li>
                            <i class="fas fa-clock me-2 text-primary"></i>
                            Support: 24/7
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-top border-white-10 pt-4 mt-4 text-center">
                <p class="text-white-50 mb-0">
                    &copy; <?php echo date('Y'); ?> i-Bus System. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.backgroundColor = 'rgba(44, 62, 80, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.backgroundColor = '';
                navbar.style.backdropFilter = 'none';
            }
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 70,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>