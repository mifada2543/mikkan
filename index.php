<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'auth/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_name('mikkan');
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$stmt_user = $conn->prepare("SELECT username, role, model_name, model_desc FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();

$user_name = $user_data['username'] ?? 'Kuro';
$user_role = $user_data['role'] ?? 'user';
$model_name = $user_data['model_name'] ?? 'Mikkan AI';
$model_desc = $user_data['model_desc'] ?? 'Siap membantu Anda';
$yachio_label = ucwords(str_replace('-', ' ', getRomajiName('八千代辉夜姬')));
$asset_version = (string) filemtime(__FILE__);

$_SESSION['role'] = $user_role;
$_SESSION['username'] = $user_name;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Mikkan - AI & L2D Interface</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    
    <!-- ===== CRITICAL: Load Local Live2D Runtime FIRST ===== -->
    <script src="assets/js/live2dcubismcore.min.js?v=<?= urlencode($asset_version) ?>"></script>

    <!-- ===== PIXI Libraries ===== -->
    <script src="assets/js/pixi.js?v=<?= urlencode($asset_version) ?>"></script>
    <script src="assets/js/pixi-live2d-cubism4.js?v=<?= urlencode($asset_version) ?>"></script>
    <script src="assets/js/lucide.js?v=<?= urlencode($asset_version) ?>"></script>
    <script src="assets/js/tailwind.js?v=<?= urlencode($asset_version) ?>"></script>
    
    <!-- ===== CSS ===== -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    
    <script>
        // Tailwind config
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        turquoise: {
                            DEFAULT: '#40E0D0',
                            light: '#72EFDF',
                            dark: '#28B5A4'
                        },
                        darkbg: '#0B0F19',
                        chatbg: '#111827'
                    }
                }
            }
        }
    </script>
    
    <style>
        /* ===== FIX HALAMAN PUTIH ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #0B0F19;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #40E0D0;
            border-radius: 4px;
        }

        #live2d-canvas {
            display: block;
            background: linear-gradient(135deg, rgba(64, 224, 208, 0.1) 0%, rgba(11, 15, 25, 0.95) 100%);
        }

        main {
            display: flex;
            width: 100%;
            height: 100%;
        }

        .live2d-section {
            flex: 3;
            position: relative;
            overflow: hidden;
        }

        .chat-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #111827;
            border-left: 1px solid rgba(64, 224, 208, 0.2);
        }

        canvas {
            display: block;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="absolute top-0 w-full flex justify-between items-start px-8 pt-6 z-50 bg-gradient-to-b from-black/60 to-transparent pointer-events-none">
        <!-- Left Side: User Info & Model Info -->
        <div class="pointer-events-auto flex flex-col gap-3">
            <!-- User Greeting -->
            <div class="bg-turquoise/20 text-turquoise px-4 py-2 rounded-lg">
                <p class="text-sm">Halo, <span class="font-semibold"><?= htmlspecialchars($user_name) ?></span>! Anda masuk sebagai <span class="italic"><?= htmlspecialchars($user_role) ?></span>.</p>
            </div>
            <!-- Model Info -->
            <div>
                <h1 class="text-turquoise font-extrabold text-lg tracking-wide uppercase leading-tight"><?= htmlspecialchars($model_name) ?></h1>
                <p class="text-[10px] text-turquoise-light/60 uppercase tracking-tighter"><?= htmlspecialchars($model_desc) ?></p>
            </div>
        </div>

        <!-- Right Side: Menu Button -->
        <div class="relative pointer-events-auto">
            <button id="menu-btn" class="p-2 text-turquoise border border-turquoise/30 rounded-xl hover:bg-turquoise hover:text-darkbg transition-all duration-300">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>

            <!-- Dropdown Menu -->
            <div id="model-dropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-chatbg/95 backdrop-blur-md border border-turquoise/20 rounded-2xl shadow-2xl overflow-hidden z-[60]">
                <div class="p-4 border-b border-turquoise/10">
                    <span class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Available Models</span>
                    <p id="model-debug-status" class="mt-2 text-[11px] text-turquoise-light/70 normal-case tracking-normal">
                        Debug tombol model: menunggu interaksi.
                    </p>
                </div>
                <ul class="py-2">
                    <!-- Misan Model -->
                    <li>
                        <button id="switch-misan-btn" type="button" data-model-option="misan" class="w-full text-left flex items-center justify-between gap-3 px-4 py-3 text-gray-400 hover:bg-turquoise/10 hover:text-turquoise transition-colors rounded-xl">
                            <span class="flex items-center gap-3">
                            <i data-lucide="cpu" class="w-4 h-4"></i>
                            <span class="text-sm font-semibold">Misan (IceGirl)</span>
                            </span>
                            <span class="model-active-badge hidden text-[10px] font-bold uppercase tracking-widest text-darkbg bg-turquoise px-2 py-1 rounded-full">Active</span>
                        </button>
                    </li>
                    <!-- Yachio Model -->
                    <li>
                        <button id="switch-yachio-btn" type="button" data-model-option="yachio" class="w-full text-left flex items-center justify-between gap-3 px-4 py-3 text-gray-400 hover:bg-turquoise/10 hover:text-turquoise transition-colors rounded-xl">
                            <span class="flex items-center gap-3">
                            <i data-lucide="cpu" class="w-4 h-4"></i>
                            <span class="text-sm font-semibold">Yachio (<?= htmlspecialchars($yachio_label) ?>)</span>
                            </span>
                            <span class="model-active-badge hidden text-[10px] font-bold uppercase tracking-widest text-darkbg bg-turquoise px-2 py-1 rounded-full">Active</span>
                        </button>
                    </li>
                    <!-- Huohuo Model -->
                    <li>
                        <button id="switch-huohuo-btn" type="button" data-model-option="huohuo" class="w-full text-left flex items-center justify-between gap-3 px-4 py-3 text-gray-400 hover:bg-turquoise/10 hover:text-turquoise transition-colors rounded-xl">
                            <span class="flex items-center gap-3">
                            <i data-lucide="cpu" class="w-4 h-4"></i>
                            <span class="text-sm font-semibold">Huohuo</span>
                            </span>
                            <span class="model-active-badge hidden text-[10px] font-bold uppercase tracking-widest text-darkbg bg-turquoise px-2 py-1 rounded-full">Active</span>
                        </button>
                    </li>
                    
                    <li class="border-t border-turquoise/10 my-2"></li>
                    
                    <!-- Thinking Mode Toggle -->
                    <li>
                        <button id="thinking-toggle-btn" type="button" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-purple-300 hover:bg-purple-500/10 transition-colors text-left">
                            <span class="flex items-center gap-3">
                                <i data-lucide="brain" class="w-4 h-4"></i>
                                <span class="text-sm">Mode Berfikir</span>
                            </span>
                            <span id="thinking-status-badge" class="text-[10px] font-bold uppercase tracking-widest text-darkbg bg-gray-500 px-2 py-1 rounded-full">OFF</span>
                        </button>
                    </li>
                    
                    <!-- TTS Toggle -->
                    <li>
                        <button id="tts-toggle-btn" type="button" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-blue-300 hover:bg-blue-500/10 transition-colors text-left">
                            <span class="flex items-center gap-3">
                                <i data-lucide="volume-2" class="w-4 h-4"></i>
                                <span class="text-sm">Suara AI</span>
                            </span>
                            <span id="tts-status-badge" class="text-[10px] font-bold uppercase tracking-widest text-darkbg bg-gray-500 px-2 py-1 rounded-full">OFF</span>
                        </button>
                    </li>
                    
                    <?php if ($user_role === 'admin'): ?>
                        <li>
                            <a href="admin/index.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-white/5 hover:text-white transition-colors">
                                <i data-lucide="settings-2" class="w-4 h-4"></i>
                                <span class="text-sm">Manage Users (Admin)</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-500/10 transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span class="text-sm">Logout</span>
                        </a>
                    </li>
                    <li>
                        <button id="clear-chat-btn" type="button" class="w-full flex items-center gap-3 px-4 py-3 text-amber-300 hover:bg-amber-500/10 transition-colors text-left">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span class="text-sm">Hapus Chat</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main>
        <!-- SEBELAH KIRI: Live2D Area -->
        <section class="live2d-section">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-turquoise/5 via-darkbg to-darkbg"></div>
            <canvas id="live2d-canvas" class="absolute inset-0 w-full h-full"></canvas>
            <!-- Loading indicator -->
            <div id="live2d-loading" class="absolute inset-0 flex items-center justify-center z-20 bg-darkbg/50 backdrop-blur-sm">
                <div class="text-center">
                    <div class="text-turquoise text-4xl mb-4 animate-pulse">⏳</div>
                    <p class="text-turquoise text-sm font-medium">Memuat Live2D Model...</p>
                    <p class="text-gray-500 text-xs mt-3">Ini adalah saat yang sempurna untuk menikmati secangkir kopi ☕</p>
                </div>
            </div>
        </section>

        <!-- SEBELAH KANAN: Chat Interface -->
        <section class="chat-section">
            <div id="chat-container" class="flex-1 overflow-y-auto p-5 flex flex-col gap-5 pt-32">
                <!-- Pesan pembuka AI -->
                <div class="flex flex-col items-start">
                    <span class="text-[10px] text-turquoise/80 mb-1 ml-1 uppercase font-semibold"><?= htmlspecialchars($model_name) ?></span>
                    <div class="bg-darkbg text-gray-200 p-3.5 rounded-2xl rounded-tl-sm border border-turquoise/20 text-sm">
                        Sistem Mikkan siap. Halo, <?= htmlspecialchars($user_name) ?>. Ada yang bisa saya bantu?
                    </div>
                </div>
            </div>

            <!-- Input Form -->
            <div class="p-5 border-t border-turquoise/20 bg-chatbg">
                <form id="chat-form" class="flex items-center gap-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="text" id="chat-input" placeholder="Tuliskan perintah..." required
                        class="flex-1 bg-darkbg border border-turquoise/40 text-gray-100 rounded-2xl py-3.5 px-4 focus:outline-none focus:border-turquoise text-sm placeholder-gray-600">
                    <button type="submit" class="p-2 bg-turquoise text-darkbg rounded-xl hover:bg-turquoise-light transition-colors flex-shrink-0">
                        <i data-lucide="send-horizontal" class="w-5 h-5"></i>
                    </button>
                </form>
            </div>
        </section>
    </main>

    <!-- ===== LOAD SCRIPT LAST ===== -->
    <script src="assets/js/script.js?v=<?= urlencode($asset_version) ?>"></script>
    
    <script>
        // ===== CHAT FUNCTIONALITY =====
        const chatForm = document.getElementById('chat-form');
        const chatInput = document.getElementById('chat-input');
        const chatContainer = document.getElementById('chat-container');
        const menuBtn = document.getElementById('menu-btn');
        const dropdown = document.getElementById('model-dropdown');
        const clearChatBtn = document.getElementById('clear-chat-btn');
        const switchMisanBtn = document.getElementById('switch-misan-btn');
        const switchYachioBtn = document.getElementById('switch-yachio-btn');
        const switchHuohuoBtn = document.getElementById('switch-huohuo-btn');
        const modelDebugStatus = document.getElementById('model-debug-status');
        const ttsToggleBtn = document.getElementById('tts-toggle-btn');
        const ttsStatusBadge = document.getElementById('tts-status-badge');

        const userId = <?= (int)$user_id ?>;
        const userName = '<?= addslashes($user_name) ?>';
        const userRole = '<?= addslashes($user_role) ?>';
        const modelName = '<?= addslashes($model_name) ?>';
        const defaultWelcomeMessage = 'Sistem Mikkan siap. Halo, ' + userName + '. Ada yang bisa saya bantu?';
        const storageKey = `chat_history_${userId}`;
        const API_BASE = './api';
        
        // ===== TTS STATE =====
        let ttsEnabled = localStorage.getItem('mikkan_tts_enabled') === 'true';
        let isPlayingAudio = false;
        const TTS_API = './api/tts.php';
        
        // ===== THINKING MODE STATE =====
        let thinkingMode = localStorage.getItem('mikkan_thinking_mode') === 'true';
        const thinkingStatusBadge = document.getElementById('thinking-status-badge');
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function sanitizeMessage(text) {
            return text
                .replace(/```/g, ' ')
                .replace(/`+/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function setModelDebugStatus(message, type = 'info') {
            if (!modelDebugStatus) return;

            const colorClass = type === 'error'
                ? 'text-red-300'
                : type === 'success'
                    ? 'text-green-300'
                    : 'text-turquoise-light/70';

            modelDebugStatus.className = `mt-2 text-[11px] normal-case tracking-normal ${colorClass}`;
            modelDebugStatus.textContent = `Debug tombol model: ${message}`;
        }

        function updateActiveModelHighlight() {
            const activeModel = typeof window.getActiveModelName === 'function'
                ? window.getActiveModelName()
                : localStorage.getItem('mikkan_live2d_model') || 'misan';

            const modelButtons = document.querySelectorAll('[data-model-option]');

            modelButtons.forEach((button) => {
                const isActive = button.dataset.modelOption === activeModel;
                const badge = button.querySelector('.model-active-badge');

                button.classList.toggle('bg-turquoise', isActive);
                button.classList.toggle('text-darkbg', isActive);
                button.classList.toggle('shadow-lg', isActive);
                button.classList.toggle('shadow-turquoise/20', isActive);
                button.classList.toggle('ring-1', isActive);
                button.classList.toggle('ring-turquoise/40', isActive);
                button.classList.toggle('text-gray-400', !isActive);

                if (badge) {
                    badge.classList.toggle('hidden', !isActive);
                }
            });

            setModelDebugStatus(`model aktif saat ini: ${activeModel}`, 'success');
        }

        async function checkApiHealth(showSuccess = false) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);

            try {
                const response = await fetch(`${API_BASE}/health.php`, {
                    method: 'GET',
                    signal: controller.signal
                });
                clearTimeout(timeoutId);

                if (!response.ok) {
                    throw new Error(`health check gagal (${response.status})`);
                }

                const data = await response.json();
                const suffix = data.model_loaded ? 'LLM siap dipakai.' : 'server hidup, tetapi model belum termuat.';
                if (showSuccess) {
                    addMessageToUI('System', `Backend terhubung: ${suffix}`, true);
                }
                return {
                    ok: true,
                    modelLoaded: !!data.model_loaded
                };
            } catch (error) {
                clearTimeout(timeoutId);
                console.error('API Health Error:', error);
                const healthMessage = error.name === 'AbortError'
                    ? `backend AI timeout saat dicek melalui proxy ${API_BASE}.`
                    : `backend AI offline atau proxy ${API_BASE} belum dapat menjangkau server LLM lokal.`;
                setModelDebugStatus(healthMessage, 'error');
                return {
                    ok: false,
                    error
                };
            }
        }

        function resetChatContainer() {
            chatContainer.innerHTML = `
                <div class="flex flex-col items-start">
                    <span class="text-[10px] text-turquoise/80 mb-1 ml-1 uppercase font-semibold">${escapeHtml(modelName)}</span>
                    <div class="bg-darkbg text-gray-200 p-3.5 rounded-2xl rounded-tl-sm border border-turquoise/20 text-sm">
                        ${escapeHtml(defaultWelcomeMessage)}
                    </div>
                </div>
            `;
        }

        function addMessageToUI(sender, text, isAI = false) {
            const cleanSender = escapeHtml(sender);
            const cleanText = escapeHtml(sanitizeMessage(text));
            const alignment = isAI ? 'items-start' : 'items-end';
            const senderColor = isAI ? 'text-turquoise/80' : 'text-gray-500';
            const senderAlign = isAI ? 'ml-1' : 'mr-1';
            const messageStyle = isAI ?
                'bg-darkbg text-gray-200 border border-turquoise/20 rounded-tl-sm' :
                'bg-turquoise text-darkbg font-bold rounded-tr-sm';

            const msgHtml = `
            <div class="flex flex-col ${alignment}">
                <span class="text-[10px] ${senderColor} mb-1 ${senderAlign} uppercase font-semibold">${cleanSender}</span>
                <div class="${messageStyle} p-3.5 rounded-2xl text-sm shadow-md leading-relaxed max-w-[90%]">
                    ${cleanText}
                </div>
            </div>`;

            chatContainer.insertAdjacentHTML('beforeend', msgHtml);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function showLoadingIndicator() {
            const loadingHtml = `
            <div class="flex flex-col items-start" id="loading-indicator">
                <span class="text-[10px] text-turquoise/80 mb-1 ml-1 uppercase font-semibold">${modelName}</span>
                <div class="bg-darkbg text-gray-400 p-3.5 rounded-2xl rounded-tl-sm border border-turquoise/30 text-sm flex items-center gap-2">
                    <span class="inline-flex gap-1">
                        <span class="w-1.5 h-1.5 bg-turquoise rounded-full animate-bounce" style="animation-delay: 0s"></span>
                        <span class="w-1.5 h-1.5 bg-turquoise rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                        <span class="w-1.5 h-1.5 bg-turquoise rounded-full animate-bounce" style="animation-delay: 0.4s"></span>
                    </span>
                    <span>Berpikir dengan konteks...</span>
                </div>
            </div>`;
            chatContainer.insertAdjacentHTML('beforeend', loadingHtml);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        function removeLoadingIndicator() {
            const loader = document.getElementById('loading-indicator');
            if (loader) loader.remove();
        }

        async function resetChatSession(showFeedback = false) {
            resetChatContainer();
            localStorage.removeItem(storageKey);

            try {
                await fetch(`${API_BASE}/chat-reset.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId
                    })
                });

                if (showFeedback) {
                    addMessageToUI('System', 'Riwayat chat berhasil dihapus.', true);
                }
            } catch (error) {
                console.error('Reset Chat Error:', error);
                if (showFeedback) {
                    addMessageToUI('System', 'Riwayat lokal sudah dihapus. (Server tidak terhubung)', true);
                }
            }
        }

        // ===== TTS FUNCTIONS =====
        function updateTTSBadge() {
            if (!ttsStatusBadge) return;
            if (ttsEnabled) {
                ttsStatusBadge.textContent = 'ON';
                ttsStatusBadge.className = 'text-[10px] font-bold uppercase tracking-widest text-darkbg bg-green-500 px-2 py-1 rounded-full';
            } else {
                ttsStatusBadge.textContent = 'OFF';
                ttsStatusBadge.className = 'text-[10px] font-bold uppercase tracking-widest text-darkbg bg-gray-500 px-2 py-1 rounded-full';
            }
        }

        async function speakText(text) {
            if (!ttsEnabled || isPlayingAudio) return;
            
            try {
                isPlayingAudio = true;
                console.log('[TTS] Generating audio for:', text.substring(0, 50));
                
                const response = await fetch('./api/tts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        text: text,
                        user_id: userId
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    console.warn('[TTS] Error:', error.error);
                    isPlayingAudio = false;
                    return;
                }

                // Get audio blob
                const audioBlob = await response.blob();
                const audioUrl = URL.createObjectURL(audioBlob);
                
                // Play audio
                const audio = new Audio(audioUrl);
                audio.onended = () => {
                    isPlayingAudio = false;
                    URL.revokeObjectURL(audioUrl);
                };
                
                audio.play().catch(err => {
                    console.error('[TTS] Playback error:', err);
                    isPlayingAudio = false;
                });
                
            } catch (error) {
                console.error('[TTS] Error:', error);
                isPlayingAudio = false;
            }
        }

        // Handle form submit
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = chatInput.value.trim();
            if (!msg) return;

            chatInput.disabled = true;
            addMessageToUI(userName, msg, false);
            chatInput.value = '';
            showLoadingIndicator();

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 180000);

            try {
                const healthStatus = await checkApiHealth(false);
                if (!healthStatus.ok) {
                    throw new Error(`Backend AI tidak aktif. Proxy ${API_BASE} belum dapat menjangkau server Flask lokal.`);
                }

                const response = await fetch(`${API_BASE}/chat.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message: msg,
                        role: userRole,
                        username: userName,
                        user_id: userId,
                        thinking_mode: thinkingMode
                    }),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);
                removeLoadingIndicator();

                if (!response.ok) {
                    if (response.status === 429) {
                        const data = await response.json();
                        addMessageToUI('System', data.response, true);
                    } else {
                        throw new Error(`HTTP Error: ${response.status}`);
                    }
                } else {
                    const data = await response.json();
                    if (data.response) {
                        addMessageToUI(modelName, data.response, true);
                        // Speak the response if TTS is enabled
                        if (ttsEnabled) {
                            setTimeout(() => speakText(data.response), 300);
                        }
                    } else {
                        throw new Error('Response tidak valid');
                    }
                }

            } catch (error) {
                clearTimeout(timeoutId);
                removeLoadingIndicator();

                if (error.name === 'AbortError') {
                    addMessageToUI('System', '⏱️ Timeout: AI tidak merespons dalam 3 menit. Coba pertanyaan yang lebih singkat atau reset chat.', true);
                } else {
                    console.error('Chat Error:', error);
                    addMessageToUI('System', `❌ Error: ${error.message || 'Koneksi gagal.'}`, true);
                }
            } finally {
                chatInput.disabled = false;
                chatInput.focus();
            }
        });

        // Menu dropdown toggle
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !menuBtn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        clearChatBtn.addEventListener('click', async () => {
            dropdown.classList.add('hidden');
            await resetChatSession(true);
        });

        // Init on load
        window.addEventListener('DOMContentLoaded', () => {
            updateActiveModelHighlight();
            updateTTSBadge();
            updateThinkingBadge();
            resetChatSession(false);
            checkApiHealth(false);
        });

        // Hide loading indicator
        window.addEventListener('load', () => {
            setTimeout(() => {
                const loadingEl = document.getElementById('live2d-loading');
                if (loadingEl) {
                    loadingEl.style.opacity = '0';
                    loadingEl.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(() => {
                        if (loadingEl.parentElement) {
                            loadingEl.remove();
                        }
                    }, 500);
                }
            }, 2000);
        });

        // ===== THINKING MODE EVENT LISTENERS =====
        function updateThinkingBadge() {
            if (!thinkingStatusBadge) return;
            if (thinkingMode) {
                thinkingStatusBadge.textContent = 'ON';
                thinkingStatusBadge.className = 'text-[10px] font-bold uppercase tracking-widest text-darkbg bg-purple-500 px-2 py-1 rounded-full';
            } else {
                thinkingStatusBadge.textContent = 'OFF';
                thinkingStatusBadge.className = 'text-[10px] font-bold uppercase tracking-widest text-darkbg bg-gray-500 px-2 py-1 rounded-full';
            }
        }
        
        const thinkingToggleBtn = document.getElementById('thinking-toggle-btn');
        if (thinkingToggleBtn) {
            thinkingToggleBtn.addEventListener('click', () => {
                thinkingMode = !thinkingMode;
                localStorage.setItem('mikkan_thinking_mode', thinkingMode);
                updateThinkingBadge();
                console.log('[THINKING MODE] Toggled:', thinkingMode ? 'ON' : 'OFF');
            });
        }
        
        // ===== TTS EVENT LISTENERS =====
        if (ttsToggleBtn) {
            ttsToggleBtn.addEventListener('click', () => {
                ttsEnabled = !ttsEnabled;
                localStorage.setItem('mikkan_tts_enabled', ttsEnabled);
                updateTTSBadge();
                console.log('[TTS] Toggled:', ttsEnabled ? 'ON' : 'OFF');
            });
        }

        // ===== MODEL SWITCHING FUNCTIONS =====
        function confirmSwitchToMisan() {
            setModelDebugStatus('tombol Misan ditekan, mengganti model...', 'success');
            window.switchToMisan();
        }

        function confirmSwitchToYachio() {
            setModelDebugStatus('tombol Yachio ditekan, mengganti model...', 'success');
            window.switchToYachio();
        }

        function confirmSwitchToHuohuo() {
            setModelDebugStatus('tombol Huohuo ditekan, mengganti model...', 'success');
            window.switchToHuohuo();
        }

        if (switchMisanBtn) {
            switchMisanBtn.addEventListener('click', () => {
                console.log('[MODEL DEBUG] Tombol Misan menerima click event.');
                confirmSwitchToMisan();
            });
        }

        if (switchYachioBtn) {
            switchYachioBtn.addEventListener('click', () => {
                console.log('[MODEL DEBUG] Tombol Yachio menerima click event.');
                confirmSwitchToYachio();
            });
        }

        if (switchHuohuoBtn) {
            switchHuohuoBtn.addEventListener('click', () => {
                console.log('[MODEL DEBUG] Tombol Huohuo menerima click event.');
                confirmSwitchToHuohuo();
            });
        }

        window.addEventListener('mikkan:model-changed', (event) => {
            console.log('[MODEL DEBUG] Event model berubah:', event.detail);
            updateActiveModelHighlight();
        });
    </script>
</body>

</html>