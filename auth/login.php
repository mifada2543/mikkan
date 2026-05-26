<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('mikkan');
    session_start();
}
include 'db.php'; // Merujuk pada db.php

// Logika Backend dari Template Login Anda[cite: 5]
$error_msg = "";
$max_login_attempts = 5;
$lockout_time = 300;
$is_locked = false;
$remaining = 0;

if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
    $is_locked = true;
    $remaining = $_SESSION['login_locked_until'] - time();
}

if (isset($_POST['login'])) {
    if (!verify_csrf()) {
        $error_msg = "Sesi keamanan kadaluarsa.";
    } else {
        $user_input = trim($_POST['username'] ?? '');
        $pass_input = $_POST['password'] ?? '';
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $user_input);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($u = $result->fetch_assoc()) {
                if (password_verify($pass_input, $u['PASSWORD'] ?? $u['password'])) {
                    if ($u['is_active'] == 0 || $u['is_active'] == 2) {
                        $error_msg = ($u['is_active'] == 2) ? "Menunggu verifikasi admin." : "Akses ditolak.";
                    } else {
                        unset($_SESSION['login_fail_count']);
                        $_SESSION['user_id'] = $u['id'];
                        $_SESSION['username'] = $u['username'];
                        $last_session = session_id();
                        $upd = $conn->prepare("UPDATE users SET last_session_id = ?, last_activity = NOW() WHERE id = ?");
                        $upd->bind_param("si", $last_session, $u['id']);
                        $upd->execute();
                        header("Location: ../index.php");
                        exit;
                    }
                } else {
                    $_SESSION['login_fail_count'] = ($_SESSION['login_fail_count'] ?? 0) + 1;
                    if ($_SESSION['login_fail_count'] >= $max_login_attempts) $_SESSION['login_locked_until'] = time() + $lockout_time;
                    $error_msg = "Username atau password salah!";
                }
            } else { $error_msg = "Username atau password salah!"; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mikkan - Login</title>
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/lucide.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { turquoise: '#40E0D0', darkbg: '#0B0F19' } } } }
    </script>
</head>
<body class="bg-darkbg text-white h-screen flex items-center justify-center font-sans">
    <div class="w-full max-w-md p-8">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black text-turquoise tracking-tighter italic">MIKKAN<span class="text-white">.AI</span></h1>
            <p class="text-gray-500 text-sm mt-2 uppercase tracking-widest">Authentication Terminal</p>
        </div>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <?php if($error_msg): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 p-3 rounded-xl text-xs text-center">
                    <?= $error_msg ?> <?= $is_locked ? "Coba lagi dalam $remaining detik." : "" ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div class="relative group">
                    <i data-lucide="user" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 group-focus-within:text-turquoise"></i>
                    <input type="text" name="username" placeholder="Username" required 
                        class="w-full bg-black/40 border border-white/10 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-turquoise/50 transition-all">
                </div>
                <div class="relative group">
                    <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 group-focus-within:text-turquoise"></i>
                    <input type="password" name="password" placeholder="Password" required 
                        class="w-full bg-black/40 border border-white/10 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-turquoise/50 transition-all">
                </div>
            </div>

            <button type="submit" name="login" <?= $is_locked ? 'disabled' : '' ?>
                class="w-full bg-turquoise text-darkbg font-bold py-4 rounded-2xl hover:bg-white transition-all transform active:scale-95 shadow-[0_0_20px_rgba(64,224,208,0.3)]">
                LOGIN SYSTEM
            </button>
        </form>

        <p class="text-center mt-8 text-sm text-gray-500">
            Belum punya akses? <a href="register.php" class="text-turquoise hover:underline">Daftar di sini</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>