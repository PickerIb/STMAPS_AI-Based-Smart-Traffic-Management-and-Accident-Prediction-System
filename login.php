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
    <title>Login - AI Student Performance Predictor</title>
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
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

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
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

        .login-logo img {
            width: 50px;
            height: 50px;
            filter: brightness(0) invert(1);
        }

        .login-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #000000, #333333);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .login-subtitle {
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

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-loading {
            display: none;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        .btn-login.loading .btn-loading {
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

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--primary-color);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
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

        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-password a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .login-title {
                font-size: 2rem;
            }
            
            .social-buttons {
                flex-direction: column;
            }
        }
        /* Access Granted Overlay */
        .access-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1055;
        }
        .access-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            padding: 28px 32px;
            text-align: center;
            width: 100%;
            max-width: 420px;
            transform: translateY(10px);
            opacity: 0;
            transition: opacity 240ms ease, transform 240ms ease;
        }
        .access-overlay.show .access-card {
            opacity: 1;
            transform: translateY(0);
        }
        .checkmark-wrapper {
            width: 96px;
            height: 96px;
            margin: 0 auto 16px;
            background: #eaf7ef;
            border-radius: 50%;
            display: grid;
            place-items: center;
        }
        .checkmark {
            color: #27ae60;
            font-size: 56px;
            animation: popIn 400ms ease forwards;
            transform: scale(0.6);
            opacity: 0;
        }
        @keyframes popIn {
            0% { transform: scale(0.6); opacity: 0; }
            60% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(1); }
        }
        .access-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .access-subtitle {
            font-size: 0.98rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-to-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Home
    </a>

    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <img src="logo1.png" alt="AI SAPP Logo">
            </div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to your magical AI account</p>
        </div>

        <div id="alertContainer"></div>

        <form id="loginForm" method="POST">
            <div class="form-group">
                <label for="email" class="form-label">Email or Username</label>
                <input type="text" class="form-control" id="email" name="email" required>
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
                <div class="invalid-feedback"></div>
            </div>


            

            <button type="submit" class="btn btn-login">
                <span class="btn-text">
                    <i class="fas fa-magic me-2"></i>Sign In
                </span>
                <span class="btn-loading">
                    <i class="fas fa-spinner fa-spin me-2"></i>Signing In...
                </span>
            </button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create one here</a></p>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Password visibility toggle
            $('#passwordToggle').on('click', function() {
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

            // Form validation
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    submitLogin();
                }
            });

            // Removed social sign-in buttons

        });

        function validateForm() {
            let isValid = true;
            const form = $('#loginForm');

            // Reset validation
            form.find('.form-control').removeClass('is-valid is-invalid');
            form.find('.invalid-feedback').text('');

            // Validate email/username
            const email = $('#email').val().trim();
            if (email.length === 0) {
                $('#email').addClass('is-invalid');
                $('#email').siblings('.invalid-feedback').text('Please enter your email or username');
                isValid = false;
            } else {
                $('#email').addClass('is-valid');
            }

            // Validate password
            const password = $('#password').val();
            if (password.length === 0) {
                $('#password').addClass('is-invalid');
                $('#password').siblings('.invalid-feedback').text('Please enter your password');
                isValid = false;
            } else {
                $('#password').addClass('is-valid');
            }

            return isValid;
        }

        function submitLogin() {
            const form = $('#loginForm');
            const submitBtn = $('.btn-login');
            const btnText = $('.btn-text');
            const btnLoading = $('.btn-loading');

            // Show loading state
            submitBtn.addClass('loading').prop('disabled', true);

            // Prepare form data
            const formData = {
                username: $('#email').val().trim(), // Send as username since backend expects username field
                password: $('#password').val()
            };

            // Submit via AJAX
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    action: 'login',
                    ...formData
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAccessGranted();
                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 1800);
                    } else {
                        showAlert('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Login error:', error);
                    showAlert('danger', 'An error occurred. Please try again.');
                },
                complete: function() {
                    // Hide loading state
                    submitBtn.removeClass('loading').prop('disabled', false);
                }
            });
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

        function showAccessGranted() {
            // Remove any existing overlay
            $('.access-overlay').remove();

            const overlay = $(`
                <div class="access-overlay" role="dialog" aria-live="assertive" aria-label="Access Granted">
                    <div class="access-card">
                        <div class="checkmark-wrapper">
                            <i class="fas fa-check checkmark"></i>
                        </div>
                        <div class="access-title">Access Granted</div>
                        <div class="access-subtitle">Welcome back to AI_SAPP</div>
                    </div>
                </div>
            `);

            $('body').append(overlay);
            // Use reflow to ensure the transition triggers
            requestAnimationFrame(() => {
                overlay.css('display', 'flex');
                overlay.addClass('show');
            });

            // Auto hide slightly before redirect in caller
            setTimeout(() => {
                overlay.removeClass('show');
                setTimeout(() => overlay.remove(), 200);
            }, 1500);
        }
    </script>
</body>
</html>
