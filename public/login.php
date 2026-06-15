<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['role'] == 'super_admin') {
        header('Location: ../superadmin/dashboard.php');
    } else {
        header('Location: ../adminbpk/dashboard.php');
    }
    exit();
}

$error = '';
$show_animation = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['bpk_id'] = $user['bpk_id'];

            $show_animation = true;
            $redirect_url = ($user['role'] == 'super_admin') ? '../superadmin/dashboard.php' : '../adminbpk/dashboard.php';
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BARRES 698</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --jet-black: #0D0D0D;
            --dark-grey: #2A2A2A;
            --gold: #F7B801;
            --gold-dark: #E0A600;
            --off-white: #F5F5F5;
            --off-white-dim: #E8E5DF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--jet-black);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            top: -20%;
            right: -10%;
            width: 55%;
            height: 140%;
            background: linear-gradient(160deg, rgba(247, 184, 1, 0.08) 0%, rgba(247, 184, 1, 0.02) 60%, transparent 100%);
            transform: skewX(-8deg);
            z-index: 0;
        }

        /* Floating particles */
        .particle {
            position: fixed;
            background: rgba(247, 184, 1, 0.08);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-30px) rotate(180deg);
            }
        }

        /* Main container */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 460px;
        }

        /* Main card */
        .login-card {
            background: var(--dark-grey);
            border-radius: 28px;
            padding: 40px 36px;
            border: 1px solid rgba(247, 184, 1, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-card.success {
            animation: cardFloatOut 0.6s ease forwards;
        }

        @keyframes cardFloatOut {
            0% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }

            100% {
                transform: scale(0.8) translateY(-100px);
                opacity: 0;
            }
        }

        /* Logo section */
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: rgba(247, 184, 1, 0.1);
            border: 2px solid var(--gold);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            transition: transform 0.3s ease;
            overflow: hidden;
            padding: 12px;
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-circle:hover {
            transform: scale(1.05);
            border-color: var(--gold-dark);
        }

        .logo-section h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 28px;
            color: #fff;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .logo-section p {
            color: rgba(255, 255, 255, .5);
            font-size: 13px;
            font-weight: 400;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 24px;
        }

        .input-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-family: 'DM Mono', monospace;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(247, 184, 1, 0.2);
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .input-wrapper:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(247, 184, 1, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }

        .input-icon {
            padding: 14px 0 14px 18px;
            color: rgba(255, 255, 255, .4);
            transition: color 0.2s;
        }

        .input-wrapper:focus-within .input-icon {
            color: var(--gold);
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 12px;
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
        }

        .input-wrapper input::placeholder {
            color: rgba(255, 255, 255, .25);
            font-weight: 400;
        }

        .toggle-password {
            padding: 14px 18px;
            cursor: pointer;
            color: rgba(255, 255, 255, .4);
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--gold);
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            border: none;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            color: var(--jet-black);
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px -8px rgba(247, 184, 1, 0.5);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-submit .btn-text {
            display: inline-block;
        }

        .btn-submit.loading .btn-text {
            visibility: hidden;
        }

        .btn-submit .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none;
        }

        .btn-submit.loading .loading-spinner {
            display: block;
        }

        /* Alert styling */
        .alert-custom {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 24px;
            color: #ff6b6b;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-custom i {
            font-size: 16px;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid rgba(247, 184, 1, 0.1);
        }

        .back-link a {
            color: rgba(255, 255, 255, .5);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .back-link a:hover {
            color: var(--gold);
            transform: translateX(-3px);
        }

        /* Demo credentials card */
        .demo-card {
            background: rgba(42, 42, 42, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            transition: all 0.3s;
        }

        .demo-card:hover {
            border-color: rgba(247, 184, 1, 0.3);
        }

        .demo-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(247, 184, 1, 0.15);
        }

        .demo-header i {
            color: var(--gold);
            font-size: 16px;
        }

        .demo-header h6 {
            margin: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            font-family: 'DM Mono', monospace;
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .credential-row:last-child {
            border-bottom: none;
        }

        .role-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 30px;
            font-family: 'Poppins', sans-serif;
        }

        .role-badge.super {
            background: var(--gold);
            color: var(--jet-black);
        }

        .role-badge.bpk {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, .7);
        }

        .credential-details {
            display: flex;
            gap: 16px;
            font-size: 12px;
            font-family: 'DM Mono', monospace;
        }

        .credential-details code {
            background: rgba(0, 0, 0, 0.3);
            padding: 4px 10px;
            border-radius: 8px;
            color: var(--gold);
            font-weight: 500;
            font-family: 'DM Mono', monospace;
        }

        .demo-note {
            margin-top: 14px;
            padding-top: 10px;
            font-size: 10px;
            color: rgba(255, 255, 255, .35);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-top: 1px solid rgba(247, 184, 1, 0.08);
            font-family: 'Poppins', sans-serif;
        }

        .demo-note strong {
            color: var(--gold);
        }

        /* Success overlay */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(13, 13, 13, 0.98);
            backdrop-filter: blur(12px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .success-overlay.show {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .success-content {
            text-align: center;
            animation: bounceScale 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes bounceScale {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(247, 184, 1, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon i {
            font-size: 48px;
            color: var(--gold);
        }

        .success-content h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 28px;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .success-content p {
            color: rgba(255, 255, 255, .6);
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }

            .logo-circle {
                width: 70px;
                height: 70px;
            }

            .logo-section h1 {
                font-size: 24px;
            }

            .credential-details {
                flex-direction: column;
                gap: 6px;
                align-items: flex-end;
            }
        }
    </style>
</head>

<body>

    <!-- Floating particles -->
    <div class="particle" style="width: 120px; height: 120px; top: 10%; left: 5%; animation-duration: 25s;"></div>
    <div class="particle" style="width: 80px; height: 80px; bottom: 15%; right: 8%; animation-duration: 18s;"></div>
    <div class="particle" style="width: 50px; height: 50px; top: 60%; left: 88%; animation-duration: 22s;"></div>
    <div class="particle" style="width: 60px; height: 60px; bottom: 30%; left: 12%; animation-duration: 28s;"></div>

    <div class="login-wrapper">
        <div class="login-card" id="loginCard">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo-circle">
                    <img src="../assets/barres2.png" alt="BARRES Logo">
                </div>
                <h1>BARRES 698</h1>
                <p>Banjarbaru Rescue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-custom">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="input-label">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="input-label">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" placeholder="Masukkan password" required id="passwordInput">
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="loginButton">
                    <span class="btn-text">MASUK</span>
                    <div class="loading-spinner">
                        <div class="spinner-border spinner-border-sm text-dark" role="status"></div>
                    </div>
                </button>
            </form>

            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>

        <!-- Demo Credentials -->
        <div class="demo-card">
            <div class="demo-header">
                <i class="fas fa-key"></i>
                <h6>AKUN DEMO</h6>
            </div>
            <div class="credential-row">
                <span class="role-badge super">Super Admin</span>
                <div class="credential-details">
                    <code>superadmin</code>
                    <code>admin123</code>
                </div>
            </div>
            <div class="credential-row">
                <span class="role-badge bpk">Admin BPK</span>
                <div class="credential-details">
                    <code>admin_bpk1</code>
                    <code>admin123</code>
                </div>
            </div>
            <div class="demo-note">
                <i class="fas fa-info-circle"></i>
                <span>Jika password error, jalankan <strong>reset_password.php</strong></span>
            </div>
        </div>
    </div>

    <!-- Success Overlay -->
    <?php if ($show_animation): ?>
        <div class="success-overlay show" id="successOverlay">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Login Berhasil!</h2>
                <p>Mengalihkan ke dashboard...</p>
                <div class="spinner-border text-warning mt-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            button.classList.add('loading');
        });

        // Success animation redirect
        <?php if ($show_animation): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const card = document.getElementById('loginCard');
                setTimeout(() => {
                    card.classList.add('success');
                }, 500);
                setTimeout(() => {
                    window.location.href = '<?= $redirect_url ?>';
                }, 2000);
            });
        <?php endif; ?>

        // Auto hide alert after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert-custom');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    </script>
</body>

</html>