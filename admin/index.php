<?php
include '../auth/db.php'; // Sesuaikan dengan path koneksi Anda
session_name('mikkan');
session_start();

// 1. Verifikasi Role Admin
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ../auth/login.php");
    exit();
}

$query_user = $conn->prepare("SELECT role FROM users WHERE id = ?");
$query_user->bind_param("i", $user_id);
$query_user->execute();
$user_data = $query_user->get_result()->fetch_assoc();

if (!$user_data || $user_data['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// 2. Logika Aksi (Approve, Reject, Clean, Delete, Kick)
if (isset($_GET['approve_id'])) {
    $id = (int)$_GET['approve_id'];
    $conn->query("UPDATE users SET is_active = 1 WHERE id = $id");
    header("Location: index.php?msg=Approved");
    exit();
}

if (isset($_GET['reject_id'])) {
    $id = (int)$_GET['reject_id'];
    $conn->query("DELETE FROM users WHERE id = $id AND is_active = 2");
    header("Location: index.php?msg=Rejected");
    exit();
}

if (isset($_POST['clean_orphans'])) {
    $files = json_decode($_POST['files_to_delete'], true);
    foreach ($files as $f) {
        if (file_exists($f)) unlink($f);
    }
    header("Location: index.php?status=cleaned#monitor");
    exit();
}

if (isset($_GET['delete_user_id'])) {
    $id_to_delete = (int)$_GET['delete_user_id'];
    $check_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check_role->bind_param("i", $id_to_delete);
    $check_role->execute();
    $target_user = $check_role->get_result()->fetch_assoc();

    if ($id_to_delete === $_SESSION['user_id']) {
        header("Location: index.php?msg=Cannot_Delete_Self");
    } elseif ($target_user && $target_user['role'] === 'admin') {
        header("Location: index.php?msg=Cannot_Delete_Admin");
    } else {
        $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->bind_param("i", $id_to_delete);
        if ($stmt_del->execute()) {
            header("Location: index.php?msg=User_Deleted");
        }
    }
    exit();
}

if (isset($_GET['kick_user'])) {
    $target_username = $_GET['kick_user'];
    $stmt_kick = $conn->prepare("UPDATE users SET 
        last_session_id = 'KICKED', 
        last_page = 'KICKED BY ADMIN', 
        last_activity = DATE_SUB(NOW(), INTERVAL 10 MINUTE) 
        WHERE username = ?");
    $stmt_kick->bind_param("s", $target_username);
    if ($stmt_kick->execute()) {
        header("Location: index.php?msg=Kicked_Success#monitor");
        exit();
    }
}

// 3. Pengambilan Data & Statistik
function get_count($conn, $q) {
    $r = $conn->query($q);
    return ($r) ? ($r->fetch_row()[0] ?? 0) : 0;
}

$all_users = $conn->query("SELECT id, username, role, is_active, created_at FROM users ORDER BY role ASC, username ASC");
$pending_users = $conn->query("SELECT id, username, created_at FROM users WHERE is_active = 2");
$result_monitor = $conn->query("SELECT username, role, last_activity, last_page, user_agent, access_via, ip_address FROM users ORDER BY last_activity DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Center - Mikkan</title>
    <script src="../assets/js/tailwind.js"></script>
    <script src="../assets/js/lucide.js"></script>
</head>
<body class="bg-slate-900 text-slate-100 font-sans">

<div class="container mx-auto p-6">
    <header class="mb-8 flex justify-between items-center">
        <div onclick="window.location.href='../index.php'">
            <h1 class="text-3xl font-bold text-blue-400">System Admin</h1>
            <p class="text-slate-400">Logika kendali sistem Mikkan (MEeL)</p>
        </div>
        <a href="../auth/logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg text-sm transition">Logout</a>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Bagian Pending Users -->
        <section class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <div class="flex items-center mb-4 gap-2">
                <i data-lucide="user-plus" class="text-yellow-400"></i>
                <h2 class="text-xl font-semibold">Persetujuan User</h2>
            </div>
            <div class="space-y-3">
                <?php while($u = $pending_users->fetch_assoc()): ?>
                <div class="flex justify-between items-center p-3 bg-slate-700/50 rounded-lg">
                    <span><?= htmlspecialchars($u['username']) ?></span>
                    <div class="flex gap-2">
                        <a href="?approve_id=<?= $u['id'] ?>" class="text-green-400 hover:text-green-300"><i data-lucide="check-circle"></i></a>
                        <a href="?reject_id=<?= $u['id'] ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('Tolak user ini?')"><i data-lucide="x-circle"></i></a>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if($pending_users->num_rows == 0): ?>
                    <p class="text-slate-500 italic">Tidak ada user menunggu persetujuan.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Bagian Monitor Aktivitas -->
        <section id="monitor" class="bg-slate-800 p-6 rounded-xl border border-slate-700">
            <div class="flex items-center mb-4 gap-2">
                <i data-lucide="activity" class="text-blue-400"></i>
                <h2 class="text-xl font-semibold">Live Monitor (10 Besar)</h2>
            </div>
            <div class="overflow-x-auto text-xs">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-700 text-slate-400">
                            <th class="p-2">User</th>
                            <th class="p-2">Page</th>
                            <th class="p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($m = $result_monitor->fetch_assoc()): ?>
                        <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition">
                            <td class="p-2">
                                <span class="font-bold"><?= $m['username'] ?></span><br>
                                <span class="text-[10px] text-slate-500"><?= $m['ip_address'] ?></span>
                            </td>
                            <td class="p-2"><?= $m['last_page'] ?></td>
                            <td class="p-2">
                                <a href="?kick_user=<?= $m['username'] ?>" class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded hover:bg-orange-500 hover:text-white transition">Kick</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <!-- Bagian All Users Table -->
    <section class="mt-8 bg-slate-800 p-6 rounded-xl border border-slate-700">
        <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
            <i data-lucide="users" class="text-purple-400"></i> Database User
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-700 text-slate-400">
                        <th class="p-3 text-center w-12">ID</th>
                        <th class="p-3 text-center">Username</th>
                        <th class="p-3 text-center">Role</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-center">Opsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $all_users->fetch_assoc()): ?>
                    <tr class="border-b border-slate-700/50 hover:bg-slate-700/30">
                        <td class="p-3 text-center"><?= $user['id'] ?></td>
                        <td class="p-3 text-center font-medium"><?= $user['username'] ?></td>
                        <td class="p-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold <?= $user['role'] == 'admin' ? 'bg-purple-500 text-white' : 'bg-slate-600' ?>">
                                <?= $user['role'] ?>
                            </span>
                        </td>
                        <td class="p-3 text-center">
                            <?= $user['is_active'] == 1 ? '<span class="text-green-400">Aktif</span>' : '<span class="text-yellow-400">Pending</span>' ?>
                        </td>
                        <td class="p-3 text-center">
                            <a href="?delete_user_id=<?= $user['id'] ?>" 
                               class="text-red-500 hover:bg-red-500/20 p-2 rounded-lg inline-block transition"
                               onclick="return confirm('Hapus user ini selamanya?')">
                                <i data-lucide="trash-2" class="w-4 h-4 text-center"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
