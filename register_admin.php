<?php
/**
 * STRGS — Standalone Administrator Registration
 *
 * This file is separate from register.php and the public registration API.
 * Use it only on a trusted machine to create the first administrator account(s).
 *
 * Security: set ADMIN_SETUP_SECRET below, then open:
 *   register_admin.php?key=YOUR_SECRET
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
session_start();

// ── Change this secret before use (keep private) ──
const ADMIN_SETUP_SECRET = 'STRGS_ADMIN_SETUP_2026';

$providedKey = trim((string)($_GET['key'] ?? $_POST['setup_key'] ?? ''));
$accessGranted = ADMIN_SETUP_SECRET !== '' && hash_equals(ADMIN_SETUP_SECRET, $providedKey);

$successMessage = '';
$errorMessage = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accessGranted) {
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($firstName === '' || strlen($firstName) < 2) {
        $fieldErrors['first_name'] = 'First name is required (min 2 characters).';
    }
    if ($lastName === '' || strlen($lastName) < 2) {
        $fieldErrors['last_name'] = 'Last name is required (min 2 characters).';
    }
    if ($username === '' || strlen($username) < 3) {
        $fieldErrors['username'] = 'Username is required (min 3 characters).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'A valid email address is required.';
    }
    if ($password === '' || strlen($password) < 8) {
        $fieldErrors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $fieldErrors['confirm_password'] = 'Passwords do not match.';
    }

    $normalizedPhone = null;
    if ($phone !== '') {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '250') && strlen($digits) === 12) {
            $normalizedPhone = '+' . $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $normalizedPhone = '+250' . substr($digits, 1);
        } elseif (preg_match('/^7\d{8}$/', $digits)) {
            $normalizedPhone = '+250' . $digits;
        } else {
            $fieldErrors['phone_number'] = 'Use a valid Rwanda number (+2507… or 07…).';
        }
    }

    if ($fieldErrors === []) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            if ($db === null) {
                throw new RuntimeException('Database connection failed.');
            }

            $user = new User($db);

            if ($user->emailExists($email)) {
                $fieldErrors['email'] = 'This email is already registered.';
            }
            if ($user->usernameExists($username)) {
                $fieldErrors['username'] = 'This username is already taken.';
            }
            if ($normalizedPhone !== null && $user->phoneExists($normalizedPhone)) {
                $fieldErrors['phone_number'] = 'This phone number is already registered.';
            }

            if ($fieldErrors === []) {
                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->username = $username;
                $user->email = $email;
                $user->phone_number = $normalizedPhone;
                $user->user_type = 'administrator';
                $user->license_id = null;
                $user->police_id = null;
                $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user->role = 'admin';
                $user->is_active = 1;

                if ($user->create()) {
                    $successMessage = 'Administrator account created successfully. You can now sign in from the login page.';
                    $_POST = [];
                } else {
                    $errorMessage = 'Could not save the account. Please try again.';
                }
            }
        } catch (Throwable $e) {
            $errorMessage = 'Registration failed: ' . $e->getMessage();
        }
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fieldError(array $errors, string $key): string
{
    return isset($errors[$key])
        ? '<div class="invalid-feedback d-block">' . h($errors[$key]) . '</div>'
        : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Administrator Registration — STRGS</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --card: #ffffff;
            --accent: #111827;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(17, 24, 39, 0.08), transparent 28%),
                linear-gradient(160deg, #f8fafc 0%, #eef2ff 45%, #f1f5f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .admin-register-wrap {
            width: 100%;
            max-width: 520px;
        }

        .admin-register-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .admin-register-top {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: #fff;
            padding: 28px 32px 24px;
            text-align: center;
        }

        .admin-register-top .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .admin-register-top h1 {
            font-size: 1.55rem;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .admin-register-top p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            font-size: 0.92rem;
        }

        .admin-register-body {
            padding: 28px 32px 32px;
        }

        .standalone-note {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.86rem;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--ink);
            font-size: 0.88rem;
        }

        .form-control {
            border-radius: 10px;
            border-color: var(--line);
            padding: 0.72rem 0.9rem;
        }

        .form-control:focus {
            border-color: #111827;
            box-shadow: 0 0 0 0.2rem rgba(17, 24, 39, 0.12);
        }

        .password-field-wrap {
            position: relative;
        }

        .password-field-wrap .form-control {
            padding-right: 2.75rem;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1.05rem;
            line-height: 1;
            padding: 4px;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #111827;
        }

        .password-toggle:focus {
            outline: none;
            color: #111827;
        }

        .btn-admin-register {
            background: #111827;
            border: none;
            color: #fff;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-weight: 600;
            width: 100%;
        }

        .btn-admin-register:hover {
            background: #000;
            color: #fff;
        }

        .access-denied {
            text-align: center;
            padding: 48px 28px;
        }

        .access-denied i {
            font-size: 2.4rem;
            color: #b91c1c;
            margin-bottom: 12px;
        }

        .footer-links {
            text-align: center;
            margin-top: 18px;
            font-size: 0.9rem;
        }

        .footer-links a {
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-register-wrap">
        <div class="admin-register-card">
            <div class="admin-register-top">
                <div class="icon"><i class="fas fa-user-shield"></i></div>
                <h1>Administrator Registration</h1>
                <p>STRGS — Remera Sector · Standalone setup form</p>
            </div>

            <?php if (!$accessGranted): ?>
                <div class="access-denied">
                    <i class="fas fa-lock"></i>
                    <h2 class="h5 mb-2">Access restricted</h2>
                    <p class="text-muted mb-0">
                        Open this page with your setup key:<br>
                        <code>register_admin.php?key=YOUR_SECRET</code>
                    </p>
                </div>
            <?php else: ?>
                <div class="admin-register-body">
                    <div class="standalone-note">
                        <i class="fas fa-circle-info me-1"></i>
                        This form is <strong>not</strong> linked to the public driver or traffic officer registration.
                        It only creates <strong>Administrator</strong> accounts for system management.
                    </div>

                    <?php if ($successMessage !== ''): ?>
                        <div class="alert alert-success"><?= h($successMessage) ?></div>
                    <?php endif; ?>

                    <?php if ($errorMessage !== ''): ?>
                        <div class="alert alert-danger"><?= h($errorMessage) ?></div>
                    <?php endif; ?>

                    <form method="post" action="register_admin.php?key=<?= h($providedKey) ?>" novalidate>
                        <input type="hidden" name="setup_key" value="<?= h($providedKey) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">First Name</label>
                                <input type="text" class="form-control<?= isset($fieldErrors['first_name']) ? ' is-invalid' : '' ?>"
                                       id="first_name" name="first_name" required minlength="2"
                                       placeholder="e.g. Jean"
                                       value="<?= h($_POST['first_name'] ?? '') ?>">
                                <?= fieldError($fieldErrors, 'first_name') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" class="form-control<?= isset($fieldErrors['last_name']) ? ' is-invalid' : '' ?>"
                                       id="last_name" name="last_name" required minlength="2"
                                       placeholder="e.g. Uwimana"
                                       value="<?= h($_POST['last_name'] ?? '') ?>">
                                <?= fieldError($fieldErrors, 'last_name') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" class="form-control<?= isset($fieldErrors['username']) ? ' is-invalid' : '' ?>"
                                       id="username" name="username" required minlength="3"
                                       placeholder="e.g. j.uwimana"
                                       value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username">
                                <?= fieldError($fieldErrors, 'username') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" class="form-control<?= isset($fieldErrors['email']) ? ' is-invalid' : '' ?>"
                                       id="email" name="email" required
                                       placeholder="e.g. admin@yourdomain.com"
                                       value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
                                <?= fieldError($fieldErrors, 'email') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="phone_number">Phone <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="tel" class="form-control<?= isset($fieldErrors['phone_number']) ? ' is-invalid' : '' ?>"
                                       id="phone_number" name="phone_number"
                                       placeholder="e.g. +250 788 123 456"
                                       value="<?= h($_POST['phone_number'] ?? '') ?>" autocomplete="tel">
                                <?= fieldError($fieldErrors, 'phone_number') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Password</label>
                                <div class="password-field-wrap">
                                    <input type="password" class="form-control<?= isset($fieldErrors['password']) ? ' is-invalid' : '' ?>"
                                           id="password" name="password" required minlength="8"
                                           placeholder="At least 8 characters"
                                           autocomplete="new-password">
                                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" title="Show password">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <?= fieldError($fieldErrors, 'password') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="confirm_password">Confirm Password</label>
                                <div class="password-field-wrap">
                                    <input type="password" class="form-control<?= isset($fieldErrors['confirm_password']) ? ' is-invalid' : '' ?>"
                                           id="confirm_password" name="confirm_password" required minlength="8"
                                           placeholder="Re-enter your password"
                                           autocomplete="new-password">
                                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show confirm password" title="Show password">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <?= fieldError($fieldErrors, 'confirm_password') ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-admin-register mt-4">
                            <i class="fas fa-user-plus me-2"></i>Create Administrator Account
                        </button>
                    </form>

                    <div class="footer-links">
                        <a href="login.php"><i class="fas fa-right-to-bracket me-1"></i>Go to Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.getAttribute('data-target'));
                const icon = btn.querySelector('i');
                if (!input || !icon) return;

                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('title', show ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>
</html>
