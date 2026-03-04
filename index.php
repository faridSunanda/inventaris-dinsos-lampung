<?php
/**
 * Login Page - Sistem Inventaris Dinas Sosial Lampung
 */

require_once __DIR__ . '/includes/auth.php';


// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        if (login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'catalina': {
                            '50': '#eaf8ff',
                            '100': '#d0f0ff',
                            '200': '#abe7ff',
                            '300': '#71daff',
                            '400': '#2ec2ff',
                            '500': '#009cff',
                            '600': '#0074ff',
                            '700': '#005aff',
                            '800': '#004bde',
                            '900': '#0045ad',
                            '950': '#04337c',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body
    class="bg-gradient-to-br from-catalina-50 via-white to-catalina-100 min-h-screen flex items-center justify-center p-4">
    <!-- Background Pattern -->
    <div class="fixed inset-0 z-0 opacity-30">
        <div
            class="absolute top-0 left-0 w-72 h-72 bg-catalina-300 rounded-full mix-blend-multiply filter blur-3xl animate-pulse">
        </div>
        <div class="absolute top-0 right-0 w-72 h-72 bg-catalina-400 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"
            style="animation-delay: 1s;"></div>
        <div class="absolute bottom-0 left-1/2 w-72 h-72 bg-catalina-200 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"
            style="animation-delay: 2s;"></div>
    </div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md">
        <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl p-8 border border-white/50">
            <!-- Logo & Header -->
            <div class="text-center mb-8">
                <div
                    class="inline-flex items-center justify-center w-20 h-20 border-2 border-catalina-500 rounded-2xl shadow-lg mb-4">
                    <img src="assets/image/logo.png" alt="Logo">
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Sistem Inventaris</h1>
                <p class="text-gray-500 mt-1">Dinas Sosial Provinsi Lampung</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div
                    class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 fade-in">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="space-y-5">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2 text-catalina-500"></i>Username
                    </label>
                    <input type="text" id="username" name="username" required
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-catalina-500 focus:ring-2 focus:ring-catalina-200 transition-all duration-200 bg-white/50"
                        placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2 text-catalina-500"></i>Password
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-catalina-500 focus:ring-2 focus:ring-catalina-200 transition-all duration-200 bg-white/50 pr-12"
                            placeholder="Masukkan password">
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-catalina-500 transition-colors">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-3 px-4 bg-gradient-to-r from-catalina-600 to-catalina-700 hover:from-catalina-700 hover:to-catalina-800 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk
                </button>
            </form>

            <!-- Footer Info -->
            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-xs text-gray-400">
                    &copy; <?= date('Y') ?> Dinas Sosial Provinsi Lampung
                </p>
            </div>
        </div>

        <!-- Demo Credentials -->
        <div class="mt-6 bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-white/50">
            <p class="text-sm text-gray-600 font-medium mb-2 text-center">
                <i class="fas fa-info-circle mr-1 text-catalina-500"></i>Demo Login
            </p>
            <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
                <div class="bg-white/80 rounded-lg p-2">
                    <span class="font-semibold text-catalina-700">Admin:</span> admin / admin123
                </div>
                <div class="bg-white/80 rounded-lg p-2">
                    <span class="font-semibold text-catalina-700">Petugas:</span> petugas / admin123
                </div>
                <div class="bg-white/80 rounded-lg p-2">
                    <span class="font-semibold text-catalina-700">Pegawai:</span> pegawai / admin123
                </div>
                <div class="bg-white/80 rounded-lg p-2">
                    <span class="font-semibold text-catalina-700">Pimpinan:</span> pimpinan / admin123
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
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
    </script>
</body>

</html>