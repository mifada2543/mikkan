<?php
session_name('mikkan');
session_start();
include 'db.php'; // Merujuk pada db.php[cite: 4]

// Logika Backend dari Template Register Anda
$message = "";
$msg_type = "";

if (isset($_POST['register'])) {
    verify_csrf(); // Fungsi dari db.php[cite: 4]
    $user = trim($_POST['username']);
    $pass_raw = $_POST['password'];

    if (strlen($user) < 4 || strlen($pass_raw) < 8) {
        $message = "Username & Password min 8 karakter!";
        $msg_type = "warning";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $user);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "Username sudah terdaftar!";
            $msg_type = "warning";
        } else {
            $pass_hashed = password_hash($pass_raw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, PASSWORD, role, is_active) VALUES (?, ?, 'user', 2)");
            $stmt->bind_param("ss", $user, $pass_hashed);
            if ($stmt->execute()) {
                $message = "Registrasi berhasil! Tunggu verifikasi admin.";
                $msg_type = "success";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mikkan - Register</title>
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        turquoise: '#40E0D0',
                        darkbg: '#0B0F19'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-darkbg text-white h-screen flex items-center justify-center font-sans">
    <div class="w-full max-w-md p-8">
        <div class="text-center mb-10">
            <h1 class="text-4xl font-black text-turquoise tracking-tighter italic">CREATE<span class="text-white">ACCOUNT</span></h1>
            <p class="text-gray-500 text-sm mt-2 uppercase tracking-widest">Join Mikkan Network</p>
        </div>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <?php if ($message): ?>
                <div class="<?= $msg_type == 'success' ? 'bg-turquoise/10 border-turquoise/50 text-turquoise' : 'bg-orange-500/10 border-orange-500/50 text-orange-500' ?> border p-3 rounded-xl text-xs text-center">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <div class="relative group">
                    <i data-lucide="user-plus" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 group-focus-within:text-turquoise"></i>
                    <input type="text" name="username" placeholder="New Username" required
                        class="w-full bg-black/40 border border-white/10 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-turquoise/50 transition-all">
                </div>
                <div class="relative group">
                    <i data-lucide="shield-check" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 group-focus-within:text-turquoise"></i>
                    <input type="password" name="password" placeholder="New Password" required
                        class="w-full bg-black/40 border border-white/10 rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-turquoise/50 transition-all">
                </div>
            </div>

            <button type="submit" name="register"
                class="w-full bg-transparent border-2 border-turquoise text-turquoise font-bold py-4 rounded-2xl hover:bg-turquoise hover:text-darkbg transition-all transform active:scale-95 shadow-[0_0_15px_rgba(64,224,208,0.1)]">
                INITIALIZE REGISTRATION
            </button>
        </form>

        <p class="text-center mt-8 text-sm text-gray-500">
            Sudah punya akun? <a href="login.php" class="text-turquoise hover:underline">Kembali ke Login</a>
        </p>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>