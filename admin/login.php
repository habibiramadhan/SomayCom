<?php
session_start();
require_once '../config.php';

// Redirect jika sudah login
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Set session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'];

            // Update last login
            $stmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$admin['id']]);

            // Set remember me cookie if checked
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                setcookie('remember_token', $token, $expires, '/', '', true, true);
                
                // Store token in database (you'll need to add a remember_token column to admins table)
                $stmt = $pdo->prepare("UPDATE admins SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $admin['id']]);
            }

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Username atau password salah';
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Somay Ecommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF6B35',
                        secondary: '#F7931E',
                        accent: '#FFD23F',
                        dark: '#1E293B',
                        light: '#F8FAFC'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .floating-label {
            position: relative;
        }
        .floating-label input:focus + label,
        .floating-label input:not(:placeholder-shown) + label {
            transform: translateY(-1.5rem) scale(0.85);
            color: #FF6B35;
        }
        .floating-label label {
            position: absolute;
            left: 0.75rem;
            top: 0.75rem;
            transition: all 0.2s ease-in-out;
            pointer-events: none;
            color: #6B7280;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #FF6B35 0%, #F7931E 50%, #FFD23F 100%);
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .loading-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-10 left-10 w-20 h-20 bg-white rounded-full"></div>
        <div class="absolute top-32 right-20 w-16 h-16 bg-white rounded-full"></div>
        <div class="absolute bottom-20 left-20 w-12 h-12 bg-white rounded-full"></div>
        <div class="absolute bottom-40 right-10 w-24 h-24 bg-white rounded-full"></div>
        <div class="absolute top-1/2 left-1/3 w-8 h-8 bg-white rounded-full"></div>
        <div class="absolute top-1/4 right-1/3 w-6 h-6 bg-white rounded-full"></div>
    </div>

    <!-- Login Container -->
    <div class="w-full max-w-md">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-utensils text-3xl text-primary"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Somay Ecommerce</h1>
            <p class="text-white/80">Admin Panel Login</p>
        </div>

        <!-- Login Form -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8 border border-white/20">
            <!-- Alert Messages -->
            <div id="alertMessage" class="hidden mb-6 p-4 rounded-lg"></div>

            <form id="loginForm" class="space-y-6">
                <!-- Username Field -->
                <div class="floating-label">
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        placeholder=" "
                        required
                        class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200"
                    >
                    <label for="username" class="flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        Username
                    </label>
                </div>

                <!-- Password Field -->
                <div class="floating-label">
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        placeholder=" "
                        required
                        class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 pr-12"
                    >
                    <label for="password" class="flex items-center">
                        <i class="fas fa-lock mr-2"></i>
                        Password
                    </label>
                    <button 
                        type="button" 
                        id="togglePassword"
                        class="absolute right-3 top-3 text-gray-500 hover:text-primary transition-colors"
                    >
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="remember" name="remember" class="sr-only">
                        <div class="relative">
                            <div class="w-5 h-5 bg-gray-200 rounded border-2 border-gray-300 transition-all duration-200 checkbox-bg"></div>
                            <i class="fas fa-check absolute top-0.5 left-0.5 text-xs text-white opacity-0 transition-opacity duration-200 checkbox-icon"></i>
                        </div>
                        <span class="ml-3 text-sm text-gray-600">Ingat saya</span>
                    </label>
                    <a href="#" class="text-sm text-primary hover:text-secondary transition-colors">
                        Lupa password?
                    </a>
                </div>

                <!-- Login Button -->
                <button 
                    type="submit" 
                    id="loginBtn"
                    class="w-full bg-primary hover:bg-secondary text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                >
                    <i class="fas fa-sign-in-alt" id="loginIcon"></i>
                    <span id="loginText">Masuk Admin Panel</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-500">
                    © 2025 Somay Ecommerce. All rights reserved.
                </p>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="mt-6 text-center text-white/80 text-sm">
            <p>Demo Login:</p>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> password</p>
        </div>
    </div>

    <script>
        // Form elements
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const loginIcon = document.getElementById('loginIcon');
        const loginText = document.getElementById('loginText');
        const alertMessage = document.getElementById('alertMessage');
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        const rememberCheckbox = document.getElementById('remember');

        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Custom checkbox styling
        rememberCheckbox.addEventListener('change', function() {
            const checkboxBg = this.parentElement.querySelector('.checkbox-bg');
            const checkboxIcon = this.parentElement.querySelector('.checkbox-icon');
            
            if (this.checked) {
                checkboxBg.classList.add('bg-primary', 'border-primary');
                checkboxBg.classList.remove('bg-gray-200', 'border-gray-300');
                checkboxIcon.classList.remove('opacity-0');
                checkboxIcon.classList.add('opacity-100');
            } else {
                checkboxBg.classList.remove('bg-primary', 'border-primary');
                checkboxBg.classList.add('bg-gray-200', 'border-gray-300');
                checkboxIcon.classList.add('opacity-0');
                checkboxIcon.classList.remove('opacity-100');
            }
        });

        // Show alert message
        function showAlert(message, type = 'error') {
            alertMessage.className = `block mb-6 p-4 rounded-lg ${
                type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 
                type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' :
                'bg-blue-100 border border-blue-400 text-blue-700'
            }`;
            
            alertMessage.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'error' ? 'fa-exclamation-triangle' : 
                        type === 'success' ? 'fa-check-circle' : 'fa-info-circle'
                    } mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            if (type === 'error') {
                loginForm.classList.add('shake');
                setTimeout(() => loginForm.classList.remove('shake'), 500);
            }
        }

        // Set loading state
        function setLoadingState(loading) {
            if (loading) {
                loginBtn.disabled = true;
                loginBtn.classList.add('opacity-75', 'cursor-not-allowed');
                loginIcon.className = 'fas fa-spinner loading-spin';
                loginText.textContent = 'Memproses...';
            } else {
                loginBtn.disabled = false;
                loginBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                loginIcon.className = 'fas fa-sign-in-alt';
                loginText.textContent = 'Masuk Admin Panel';
            }
        }

        // Form submission
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const username = formData.get('username');
            const password = formData.get('password');
            const remember = formData.get('remember');

            // Basic validation
            if (!username || !password) {
                showAlert('Silakan isi username dan password', 'error');
                return;
            }

            if (username.length < 3) {
                showAlert('Username minimal 3 karakter', 'error');
                return;
            }

            if (password.length < 6) {
                showAlert('Password minimal 6 karakter', 'error');
                return;
            }

            setLoadingState(true);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Login berhasil! Mengalihkan...', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    showAlert(result.message || 'Username atau password salah', 'error');
                }
            } catch (error) {
                showAlert('Terjadi kesalahan. Silakan coba lagi.', 'error');
            } finally {
                setLoadingState(false);
            }
        });

        // Auto-fill remembered username
        window.addEventListener('load', function() {
            const rememberedUser = localStorage.getItem('somay_remember_user');
            if (rememberedUser) {
                document.getElementById('username').value = rememberedUser;
                rememberCheckbox.checked = true;
                rememberCheckbox.dispatchEvent(new Event('change'));
            }
        });

        // Input animations
        document.querySelectorAll('.floating-label input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Prevent form submission on Enter in password field for better UX
        passwordField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>