<?php
require_once 'config.php';
session_start();

// Redirect if already logged in
requireGuest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart Traffic Management AI</title>
    <link rel="icon" type="image/png" href="logo1.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bookman+Old+Style:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #333333;
            --accent-color: #666666;
            --success-color: #4a4a4a;
            --warning-color: #555555;
            --danger-color: #222222;
            --light-color: #f8f9fa;
            --dark-color: #000000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Bookman Old Style', serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 50%, #dee2e6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #000000, #333333, #666666, #999999);
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #000000, #333333);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .register-logo img {
            width: 50px;
            height: 50px;
            filter: brightness(0) invert(1);
        }

        .register-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #000000, #333333);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .register-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            background: white;
        }

        .form-control.is-valid {
            border-color: var(--success-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2327ae60' d='m2.3 6.73.94-.94 1.88 1.88L7.7 5.5l.94.94L4.12 10.5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23e74c3c'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4 1.4-1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 6px;
            background: #e1e8ed;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        .strength-weak .strength-fill {
            background: var(--danger-color);
            width: 25%;
        }

        .strength-fair .strength-fill {
            background: var(--warning-color);
            width: 50%;
        }

        .strength-good .strength-fill {
            background: #f39c12;
            width: 75%;
        }

        .strength-strong .strength-fill {
            background: var(--success-color);
            width: 100%;
        }

        .strength-text {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .strength-weak .strength-text {
            color: var(--danger-color);
        }

        .strength-fair .strength-text {
            color: var(--warning-color);
        }

        .strength-good .strength-text {
            color: #f39c12;
        }

        .strength-strong .strength-text {
            color: var(--success-color);
        }

        .strength-criteria {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .strength-criteria .criterion {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }

        .strength-criteria .criterion i {
            margin-right: 5px;
            width: 12px;
        }

        .strength-criteria .criterion.met {
            color: var(--success-color);
        }

        .strength-criteria .criterion.not-met {
            color: #999;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--accent-color);
        }

        .btn-register {
            background: linear-gradient(135deg, #000000, #333333);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-loading {
            display: none;
        }

        .btn-register.loading .btn-text {
            display: none;
        }

        .btn-register.loading .btn-loading {
            display: inline-block;
        }

        .social-divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .social-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e1e8ed;
        }

        .social-divider span {
            background: white;
            padding: 0 20px;
            color: #666;
            font-size: 0.9rem;
        }

        .social-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn-social {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            background: white;
            color: #666;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-social:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-google:hover {
            border-color: #db4437;
            color: #db4437;
        }

        .btn-facebook:hover {
            border-color: #3b5998;
            color: #3b5998;
        }

        .btn-github:hover {
            border-color: #333;
            color: #333;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--primary-color);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(74, 74, 74, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
            border-radius: 12px;
        }

        .alert-success i {
            color: var(--success-color);
        }

        #countdown {
            font-weight: bold;
            color: var(--primary-color);
        }

        .alert-danger {
            background: rgba(34, 34, 34, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
            border-radius: 12px;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--danger-color);
        }

        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--success-color);
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #666;
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .back-to-home:hover {
            color: var(--accent-color);
        }

        /* Success Modal Styles */
        .success-animation {
            position: relative;
            display: inline-block;
        }

        .success-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #000000, #333333);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            animation: successPulse 2s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .success-circle i {
            font-size: 2.5rem;
            color: white;
            animation: successCheck 0.8s ease-in-out;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes successCheck {
            0% { transform: scale(0) rotate(45deg); }
            50% { transform: scale(1.2) rotate(45deg); }
            100% { transform: scale(1) rotate(45deg); }
        }

        .countdown-container {
            position: relative;
        }

        .countdown-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #000000, #333333);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            animation: countdownPulse 1s ease-in-out infinite;
        }

        .countdown-circle::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            border: 3px solid #000000;
            animation: countdownRing 5s linear;
        }

        .countdown-circle span {
            font-size: 1.8rem;
            font-weight: bold;
            color: white;
            z-index: 1;
        }

        @keyframes countdownPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes countdownRing {
            0% { 
                border-color: #000000;
                transform: rotate(0deg);
            }
            100% { 
                border-color: #666666;
                transform: rotate(360deg);
            }
        }

        .countdown-text {
            font-size: 1rem;
            color: #333333;
            margin-top: 15px;
        }

        .modal-content {
            animation: modalSlideIn 0.5s ease-out;
        }

        @keyframes modalSlideIn {
            0% {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .register-title {
                font-size: 2rem;
            }
            
            .social-buttons {
                flex-direction: column;
            }

            .success-circle {
                width: 80px;
                height: 80px;
            }

            .success-circle i {
                font-size: 2rem;
            }

            .countdown-circle {
                width: 60px;
                height: 60px;
            }

            .countdown-circle span {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-to-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Home
    </a>

    <div class="register-container">
        <div class="register-header">
            <div class="register-logo">
                <img src="logo1.png" alt="AI SAPP Logo">
            </div>
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Join the magical world of AI-powered education</p>
        </div>

        <div id="alertContainer"></div>

        <form id="registerForm" method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="firstName" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="firstName" name="first_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="lastName" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="lastName" name="last_name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label for="phoneNumber" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phoneNumber" name="phone_number" placeholder="+2507XXXXXXXX or 07XXXXXXXX" required>
                <small class="text-muted">Rwanda format only: +2507XXXXXXXX or 07XXXXXXXX</small>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label for="userType" class="form-label">User Type</label>
                <select class="form-control" id="userType" name="user_type" required>
                    <option value="">Select user type</option>
                    <option value="driver">Driver</option>
                    <option value="traffic_authority">Traffic Authorities</option>
                </select>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button type="button" class="password-toggle" id="passwordToggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill"></div>
                    </div>
                    <div class="strength-text">Password Strength</div>
                    <div class="strength-criteria">
                        <div class="criterion" data-criterion="length">
                            <i class="fas fa-times"></i>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="criterion" data-criterion="uppercase">
                            <i class="fas fa-times"></i>
                            <span>One uppercase letter</span>
                        </div>
                        <div class="criterion" data-criterion="lowercase">
                            <i class="fas fa-times"></i>
                            <span>One lowercase letter</span>
                        </div>
                        <div class="criterion" data-criterion="number">
                            <i class="fas fa-times"></i>
                            <span>One number</span>
                        </div>
                        <div class="criterion" data-criterion="special">
                            <i class="fas fa-times"></i>
                            <span>One special character</span>
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback"></div>
            </div>

            <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                    <button type="button" class="password-toggle" id="confirmPasswordToggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="invalid-feedback"></div>
            </div>

            

            <button type="submit" class="btn btn-register">
                <span class="btn-text">
                    <i class="fas fa-wand-magic-sparkles me-2"></i>Create Account
                </span>
                <span class="btn-loading">
                    <i class="fas fa-spinner fa-spin me-2"></i>Creating Account...
                </span>
            </button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);">
                <div class="modal-body text-center p-5">
                    <div class="success-animation mb-4">
                        <div class="success-circle">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h4 class="modal-title mb-3" style="color: #000000; font-weight: 700;">
                        Account Created Successfully!
                    </h4>
                    <p class="text-muted mb-4" style="color: #333333;">
                        Welcome to Smart Traffic Management AI!<br>
                        Your account has been created and you can now access all features.
                    </p>
                    <div class="countdown-container mb-4">
                        <div class="countdown-circle">
                            <span id="modalCountdown">5</span>
                        </div>
                        <p class="countdown-text mt-2">Redirecting to login in <span id="modalCountdownText">5</span> seconds...</p>
                    </div>
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-outline-secondary" id="skipRedirectBtn">
                            <i class="fas fa-forward me-2"></i>Skip & Go to Login
                        </button>
                        <button type="button" class="btn btn-primary" id="stayHereBtn">
                            <i class="fas fa-home me-2"></i>Stay Here
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Password visibility toggle
            $('#passwordToggle, #confirmPasswordToggle').on('click', function() {
                const input = $(this).siblings('input');
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Password strength validation
            $('#password').on('input', function() {
                updatePasswordStrength();
            });

            // Form validation
            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    submitRegistration();
                }
            });

            // Removed social sign-up buttons
        });

        function updatePasswordStrength() {
            const password = $('#password').val();
            const strengthContainer = $('.password-strength');
            const strengthBar = $('.strength-fill');
            const strengthText = $('.strength-text');
            const criteria = $('.strength-criteria .criterion');

            if (password.length === 0) {
                strengthContainer.removeClass('strength-weak strength-fair strength-good strength-strong');
                strengthBar.css('width', '0%');
                strengthText.text('Password Strength');
                criteria.removeClass('met not-met').addClass('not-met');
                return;
            }

            let score = 0;
            const checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };

            Object.values(checks).forEach(check => {
                if (check) score++;
            });

            criteria.each(function() {
                const criterion = $(this).data('criterion');
                const icon = $(this).find('i');
                
                if (checks[criterion]) {
                    $(this).removeClass('not-met').addClass('met');
                    icon.removeClass('fa-times').addClass('fa-check');
                } else {
                    $(this).removeClass('met').addClass('not-met');
                    icon.removeClass('fa-times').addClass('fa-check');
                }
            });

            strengthContainer.removeClass('strength-weak strength-fair strength-good strength-strong');
            
            if (score <= 2) {
                strengthContainer.addClass('strength-weak');
                strengthText.text('Weak Password');
            } else if (score === 3) {
                strengthContainer.addClass('strength-fair');
                strengthText.text('Fair Password');
            } else if (score === 4) {
                strengthContainer.addClass('strength-good');
                strengthText.text('Good Password');
            } else {
                strengthContainer.addClass('strength-strong');
                strengthText.text('Strong Password');
            }
        }

        function validateForm() {
            let isValid = true;
            const form = $('#registerForm');

            // Reset validation
            form.find('.form-control').removeClass('is-valid is-invalid');
            form.find('.invalid-feedback').text('');

            // Validate first name
            const firstName = $('#firstName').val().trim();
            if (firstName.length < 2) {
                $('#firstName').addClass('is-invalid');
                $('#firstName').siblings('.invalid-feedback').text('First name must be at least 2 characters');
                isValid = false;
            } else {
                $('#firstName').addClass('is-valid');
            }

            // Validate last name
            const lastName = $('#lastName').val().trim();
            if (lastName.length < 2) {
                $('#lastName').addClass('is-invalid');
                $('#lastName').siblings('.invalid-feedback').text('Last name must be at least 2 characters');
                isValid = false;
            } else {
                $('#lastName').addClass('is-valid');
            }

            // Validate email
            const email = $('#email').val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                $('#email').addClass('is-invalid');
                $('#email').siblings('.invalid-feedback').text('Please enter a valid email address');
                isValid = false;
            } else {
                $('#email').addClass('is-valid');
            }

            // Validate username
            const username = $('#username').val().trim();
            if (username.length < 3) {
                $('#username').addClass('is-invalid');
                $('#username').siblings('.invalid-feedback').text('Username must be at least 3 characters');
                isValid = false;
            } else {
                $('#username').addClass('is-valid');
            }

            // Validate Rwanda phone number
            const phoneNumberRaw = $('#phoneNumber').val().trim();
            const normalizedPhone = normalizeRwandaPhone(phoneNumberRaw);
            if (!normalizedPhone) {
                $('#phoneNumber').addClass('is-invalid');
                $('#phoneNumber').siblings('.invalid-feedback').text('Use Rwanda format: +2507XXXXXXXX or 07XXXXXXXX');
                isValid = false;
            } else {
                $('#phoneNumber').val(normalizedPhone);
                $('#phoneNumber').addClass('is-valid');
            }

            // Validate user type (administrator is intentionally excluded here)
            const userType = $('#userType').val();
            if (!['driver', 'traffic_authority'].includes(userType)) {
                $('#userType').addClass('is-invalid');
                $('#userType').siblings('.invalid-feedback').text('Please select a valid user type');
                isValid = false;
            } else {
                $('#userType').addClass('is-valid');
            }

            // Validate password
            const password = $('#password').val();
            if (password.length < 8) {
                $('#password').addClass('is-invalid');
                $('#password').siblings('.invalid-feedback').text('Password must be at least 8 characters');
                isValid = false;
            } else {
                $('#password').addClass('is-valid');
            }

            // Validate confirm password
            const confirmPassword = $('#confirmPassword').val();
            if (password !== confirmPassword) {
                $('#confirmPassword').addClass('is-invalid');
                $('#confirmPassword').siblings('.invalid-feedback').text('Passwords do not match');
                isValid = false;
            } else if (confirmPassword.length > 0) {
                $('#confirmPassword').addClass('is-valid');
            }

            return isValid;
        }

        function submitRegistration() {
            const form = $('#registerForm');
            const submitBtn = $('.btn-register');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');

            // Show loading state
            submitBtn.addClass('loading').prop('disabled', true);

            // Prepare form data
            const formData = {
                first_name: $('#firstName').val().trim(),
                last_name: $('#lastName').val().trim(),
                email: $('#email').val().trim(),
                username: $('#username').val().trim(),
                phone_number: $('#phoneNumber').val().trim(),
                user_type: $('#userType').val(),
                password: $('#password').val(),
                confirm_password: $('#confirmPassword').val()
            };

            // Submit via AJAX
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    action: 'register',
                    ...formData
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showSuccessMessage();
                    } else {
                        showAlert('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Registration error:', error);
                    showAlert('danger', 'An error occurred. Please try again.');
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.removeClass('loading').prop('disabled', false);
                }
            });
        }

        function showSuccessMessage() {
            // Hide the registration form
            $('#registerForm').hide();
            
            // Show the success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            let countdown = 5;
            let countdownInterval;
            
            // Update countdown display
            function updateCountdown() {
                $('#modalCountdown').text(countdown);
                $('#modalCountdownText').text(countdown);
            }
            
            // Start countdown
            countdownInterval = setInterval(() => {
                countdown--;
                updateCountdown();
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    successModal.hide();
                    window.location.href = 'login.php';
                }
            }, 1000);
            
            // Skip redirect button
            $('#skipRedirectBtn').on('click', function() {
                clearInterval(countdownInterval);
                successModal.hide();
                window.location.href = 'login.php';
            });
            
            // Stay here button
            $('#stayHereBtn').on('click', function() {
                clearInterval(countdownInterval);
                successModal.hide();
                $('#registerForm').show();
                // Reset form
                $('#registerForm')[0].reset();
                $('.form-control').removeClass('is-valid is-invalid');
                $('.password-strength').removeClass('strength-weak strength-fair strength-good strength-strong');
                $('.strength-fill').css('width', '0%');
                $('.strength-text').text('Password Strength');
                $('.strength-criteria .criterion').removeClass('met not-met').addClass('not-met');
                $('.strength-criteria .criterion i').removeClass('fa-check').addClass('fa-times');
            });
            
            // Initialize countdown display
            updateCountdown();
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
        }

        function normalizeRwandaPhone(rawPhone) {
            const cleaned = String(rawPhone || '').replace(/[^\d+]/g, '');

            if (/^\+2507\d{8}$/.test(cleaned)) return cleaned;
            if (/^2507\d{8}$/.test(cleaned)) return `+${cleaned}`;
            if (/^07\d{8}$/.test(cleaned)) return `+250${cleaned.slice(1)}`;
            if (/^7\d{8}$/.test(cleaned)) return `+250${cleaned}`;
            return null;
        }
    </script>
</body>
</html>
