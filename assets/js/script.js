// ========================================
// Live2D PIXI Configuration & Initialization
// ========================================

// SUPPORT MULTIPLE MODELS
const MODELS = {
    misan: "assets/l2d/Misan/IceGirl.model3.json",
    yachio: "assets/l2d/Yachio/八千代辉夜姬.model3.json",
    huohuo: "assets/l2d/huohuo/huohuo.model3.json"
};

const MODEL_STORAGE_KEY = 'mikkan_live2d_model';

function getSavedModelName() {
    const savedModelName = localStorage.getItem(MODEL_STORAGE_KEY);
    return MODELS[savedModelName] ? savedModelName : 'misan';
}

// Default model
let activeModelName = getSavedModelName();
let modelUrl = MODELS[activeModelName];
let app = null;
let model = null;
let placeholderSprite = null;
let isModelLoading = false;
let hasInitializedModel = false;

// Setup & Error Tracking
const DEBUG = true;

function log(msg, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const color = type === 'error' ? 'color:red;font-weight:bold' : type === 'success' ? 'color:green;font-weight:bold' : 'color:cyan';
    console.log(`%c[${timestamp}] ${msg}`, color);
}

window.getActiveModelName = function() {
    return activeModelName;
};

// ========================================
// Set Active Model (Misan atau Yachio)
// ========================================
window.setModel = function(modelName) {
    if (MODELS[modelName]) {
        activeModelName = modelName;
        modelUrl = MODELS[modelName];
        localStorage.setItem(MODEL_STORAGE_KEY, modelName);
        log(`Model diubah ke: ${modelName}`, 'success');
        window.dispatchEvent(new CustomEvent('mikkan:model-changed', {
            detail: {
                activeModelName,
                modelUrl
            }
        }));
        location.reload(); // Reload untuk apply perubahan
    } else {
        log(`Model '${modelName}' tidak ditemukan. Gunakan 'misan' atau 'yachio'`, 'error');
    }
};

// ========================================
// Inisialisasi PIXI Application
// ========================================
async function initializeApp() {
    try {
        const canvas = document.getElementById('live2d-canvas');
        if (!canvas) {
            throw new Error('Canvas element #live2d-canvas tidak ditemukan');
        }

        const container = canvas.parentElement;
        if (!container) {
            throw new Error('Parent container dari canvas tidak ditemukan');
        }

        log('Menginisialisasi PIXI Application...', 'info');

        app = new PIXI.Application({
            view: canvas,
            autoStart: true,
            backgroundAlpha: 0,
            resizeTo: container,
            antialias: true,
            powerPreference: 'high-performance'
        });

        log('PIXI Application berhasil dibuat', 'success');
        
        // Pastikan canvas visible
        canvas.style.visibility = 'visible';
        canvas.style.opacity = '1';
        
        return true;

    } catch (error) {
        log(`Error inisialisasi PIXI: ${error.message}`, 'error');
        console.error(error);
        return false;
    }
}

// ========================================
// Load Live2D Model
// ========================================
async function loadLive2D() {
    if (isModelLoading) {
        log('Load model sedang berjalan, melewati permintaan duplikat.', 'info');
        return false;
    }

    try {
        if (!app) {
            throw new Error('PIXI Application belum diinisialisasi');
        }

        isModelLoading = true;
        log(`Memuat Live2D model dari: ${modelUrl}`, 'info');

        // Check apakah file tersedia dengan fetch
        const checkResponse = await fetch(modelUrl, { method: 'HEAD' });
        if (!checkResponse.ok) {
            throw new Error(`Model file tidak ditemukan (${checkResponse.status})`);
        }

        log('File model ditemukan, memproses...', 'success');

        // Load model
        model = await PIXI.live2d.Live2DModel.from(modelUrl);
        
        if (!model) {
            throw new Error('Model tidak berhasil dimuat');
        }

        app.stage.addChild(model);
        log('Model ditambahkan ke stage', 'success');

        // ========== Penyesuaian Transformasi ==========
        // Scale otomatis berdasarkan model
        const scale = modelUrl.includes('Misan') ? 0.12 : 0.09;
        model.scale.set(scale);
        model.anchor.set(0.5, 0.5);
        model.x = app.screen.width / 2
        model.y = app.screen.height / 2

        log(`Model berhasil dimuat!`, 'success');
        log(`Skala: ${model.scale.x}, Posisi: (${model.x}, ${model.y})`, 'info');

        // ========== Setup Mouse Tracking ==========
        setupMouseTracking();

        // ========== Setup Resizing ==========
        setupResizeListener();

        hasInitializedModel = true;

        return true;

    } catch (error) {
        log(`❌ Gagal memuat Live2D: ${error.message}`, 'error');
        console.error('Full error:', error);
        
        // Fallback ke placeholder
        log('Menggunakan placeholder sprite sebagai gantinya...', 'info');
        createPlaceholder();
        
        return false;
    } finally {
        isModelLoading = false;
    }
}

// ========================================
// Placeholder Avatar (Jika Model Gagal)
// ========================================
async function createPlaceholder() {
    try {
        if (!app) return;

        log('Membuat placeholder avatar...', 'info');

        // Buat container placeholder
        const container = new PIXI.Container();
        container.x = app.screen.width / 2;
        container.y = app.screen.height / 2;
        app.stage.addChild(container);

        // Lingkaran kepala (biru/turquoise)
        const head = new PIXI.Graphics();
        head.beginFill(0x40E0D0, 0.8);
        head.drawCircle(0, -40, 80);
        head.endFill();
        container.addChild(head);

        // Mata kiri
        const eyeLeft = new PIXI.Graphics();
        eyeLeft.beginFill(0xFFFFFF);
        eyeLeft.drawCircle(-30, -60, 15);
        eyeLeft.endFill();
        eyeLeft.beginFill(0x000000);
        eyeLeft.drawCircle(-30, -60, 8);
        eyeLeft.endFill();
        container.addChild(eyeLeft);

        // Mata kanan
        const eyeRight = new PIXI.Graphics();
        eyeRight.beginFill(0xFFFFFF);
        eyeRight.drawCircle(30, -60, 15);
        eyeRight.endFill();
        eyeRight.beginFill(0x000000);
        eyeRight.drawCircle(30, -60, 8);
        eyeRight.endFill();
        container.addChild(eyeRight);

        // Mulut
        const mouth = new PIXI.Graphics();
        mouth.lineStyle(2, 0xFFFFFF);
        mouth.arc(0, -20, 25, Math.PI * 0.3, Math.PI * 0.7, false);
        container.addChild(mouth);

        // Body lingkaran
        const body = new PIXI.Graphics();
        body.beginFill(0x40E0D0, 0.6);
        body.drawCircle(0, 60, 100);
        body.endFill();
        container.addChild(body);

        placeholderSprite = container;

        // Setup mouse tracking untuk placeholder
        setupMouseTracking(true);
        setupResizeListener();

        log('Placeholder avatar siap!', 'success');

    } catch (error) {
        log(`Error membuat placeholder: ${error.message}`, 'error');
    }
}

// ========================================
// Mouse Tracking
// ========================================
function setupMouseTracking(isPlaceholder = false) {
    document.addEventListener('mousemove', (e) => {
        if (!app) return;

        const rect = app.view.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Normalize coordinates (0-1)
        const normalizedX = (x / rect.width) - 0.5;
        const normalizedY = (y / rect.height) - 0.5;

        if (isPlaceholder && placeholderSprite) {
            // Placeholder mengikuti mouse dengan efek parallax
            placeholderSprite.x = app.screen.width / 2 + normalizedX * 50;
            placeholderSprite.y = app.screen.height / 2 + normalizedY * 50;
            
            // Rotate sedikit mengikuti mouse
            placeholderSprite.rotation = normalizedX * 0.2;
        } else if (model) {
            // Live2D model tracking
            model.pointerX = normalizedX + 0.5;
            model.pointerY = normalizedY + 0.5;
        }
    });

    log('Mouse tracking diaktifkan', 'success');
}

// ========================================
// Handle Window Resize
// ========================================
function setupResizeListener() {
    window.addEventListener('resize', () => {
        if (!app) return;

        // Re-center sprite/model setelah resize
        if (placeholderSprite) {
            placeholderSprite.x = app.screen.width / 2;
            placeholderSprite.y = app.screen.height / 2;
        } else if (model) {
            model.x = app.screen.width / 2;
            model.y = app.screen.height / 2;
        }

        log(`Resize detected. Avatar recentered`, 'info');
    });
}

// ========================================
// Show Error Message on Canvas
// ========================================
function showErrorOnCanvas(errorMsg) {
    const canvas = document.getElementById('live2d-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.fillStyle = 'rgba(11, 15, 25, 0.9)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = '#ef4444';
    ctx.font = 'bold 24px Arial';
    ctx.textAlign = 'center';
    ctx.fillText('❌ Live2D Loading Error', canvas.width / 2, canvas.height / 2 - 40);

    ctx.fillStyle = '#fca5a5';
    ctx.font = '16px Arial';
    ctx.fillText(errorMsg, canvas.width / 2, canvas.height / 2 + 20);

    ctx.fillStyle = '#9ca3af';
    ctx.font = '14px Arial';
    ctx.fillText('Check console (F12) for details', canvas.width / 2, canvas.height / 2 + 60);
}

// ========================================
// Main Initialization on DOMContentLoaded
// ========================================
document.addEventListener('DOMContentLoaded', async () => {
    log('=== MIKKAN LIVE2D INITIALIZATION START ===', 'success');
    log('DOM siap. Memulai inisialisasi...', 'success');

    // Check jika PIXI sudah loaded
    if (typeof PIXI === 'undefined') {
        log('ERROR: PIXI tidak ditemukan. Pastikan pixi.js dimuat!', 'error');
        return;
    }
    log('✓ PIXI library loaded', 'success');

    // 1. Tunggu PIXI siap
    if (!await initializeApp()) {
        log('Gagal menginisialisasi PIXI', 'error');
        return;
    }

    // 2. Tunggu PIXI.live2d plugin tersedia
    if (typeof PIXI.live2d === 'undefined') {
        log('PIXI.live2d plugin belum dimuat. Menggunakan placeholder...', 'info');
        await createPlaceholder();
        
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
            log('Lucide icons diinisialisasi', 'success');
        }
        log('=== MIKKAN LIVE2D INITIALIZATION COMPLETE (PLACEHOLDER) ===', 'success');
        return;
    }

    log('✓ PIXI.live2d plugin loaded', 'success');

    // 3. Load Live2D
    await loadLive2D();

    // 4. Initialize Lucide Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        log('Lucide icons diinisialisasi', 'success');
    } else {
        log('WARNING: Lucide library tidak ditemukan', 'error');
    }

    log('=== MIKKAN LIVE2D INITIALIZATION COMPLETE ===', 'success');
});

// ========================================
// Emergency Re-initialization
// ========================================
window.addEventListener('load', async () => {
    // Pastikan Live2D dimuat meskipun ada delay
    if (!hasInitializedModel && !model && !placeholderSprite && app && !isModelLoading) {
        log('Attempting emergency Live2D reload...', 'error');
        await loadLive2D();
    }
});

// ========================================
// Global Debug Functions (Bisa dijalankan dari console)
// ========================================
window.debugInfo = function() {
    console.log('=== DEBUG INFO ===');
    console.log('PIXI:', typeof PIXI);
    console.log('PIXI.Application:', typeof PIXI.Application);
    console.log('PIXI.live2d:', typeof PIXI.live2d);
    console.log('App initialized:', !!app);
    console.log('Model loaded:', !!model);
    console.log('Placeholder sprite:', !!placeholderSprite);
    console.log('Active model name:', activeModelName);
    console.log('Current model URL:', modelUrl);
    console.log('Available models:', Object.keys(MODELS));
};

window.switchToMisan = function() {
    log('Switching to Misan model...', 'info');
    window.setModel('misan');
};

window.switchToYachio = function() {
    log('Switching to Yachio model...', 'info');
    window.setModel('yachio');
};

window.switchToHuohuo = function() {
    log('Switching to Huohuo model...', 'info');
    window.setModel('huohuo');
};
