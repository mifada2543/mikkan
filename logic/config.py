"""
Configuration settings for MIKKAN AI system
Dengan support untuk Thinking Mode (deep response) vs Normal Mode (balanced)
"""
import os

# ===== PATH CONFIGURATION =====
BASE_DIR = os.path.dirname(__file__)
ASSETS_DIR = os.path.join(BASE_DIR, "../assets")
LLM_DIR = os.path.join(ASSETS_DIR, "llm")
KOKORO_DIR = os.path.join(ASSETS_DIR, "kokoro")

# ===== CORS CONFIGURATION =====
ALLOWED_ORIGIN_PATTERNS = [
    r"^http://localhost(?::\d+)?$",
    r"^http://127\.0\.0\.1(?::\d+)?$",
    r"^http://192\.168\.\d{1,3}\.\d{1,3}(?::\d+)?$",
    r"^http://10\.\d{1,3}\.\d{1,3}\.\d{1,3}(?::\d+)?$",
    r"^http://172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}(?::\d+)?$",
]

CORS_CONFIG = {
    "resources": {r"/*": {"origins": ALLOWED_ORIGIN_PATTERNS}},
    "methods": ["GET", "POST", "OPTIONS"],
    "allow_headers": ["Content-Type"],
    "supports_credentials": True,
    "max_age": 3600,
}

# ===== LLM CONFIGURATION =====
LLM_CONFIG = {
    "n_ctx": 4096,          # Context window
    "n_batch": 256,         # Batch size untuk 4b-high
    "n_gpu_layers": 0,      # CPU-only
    "f16_kv": True,
    "verbose": False,
}

# ===== INFERENCE PROFILES =====
# NORMAL MODE: balanced speed & quality, jawaban singkat-sedang
LLM_INFERENCE_NORMAL = {
    "max_tokens": 150,              # Sedang (tidak pendek, tidak panjang)
    "temperature": 0.6,             # Sedikit kreatif
    "top_p": 0.90,
    "top_k": 40,
    "repeat_penalty": 1.12,
    "stop": ["\nPengguna:", "\nUser:", "Pengguna:", "User:", "\nAsisten:", "Assistant:", "<end_of_turn>"],
    "echo": False,
}

# THINKING MODE: deep analysis, detailed response, menggunakan seluruh potensi AI
LLM_INFERENCE_THINKING = {
    "max_tokens": 400,              # Panjang & detail (untuk kode, analisis, penjelasan mendalam)
    "temperature": 0.8,             # Lebih kreatif & eksplorasi
    "top_p": 0.95,                  # Lebih banyak variasi vocab
    "top_k": 60,                    # Lebih banyak opsi token
    "repeat_penalty": 1.08,         # Lebih fleksibel dengan pengulangan natural
    "stop": ["\nPengguna:", "\nUser:", "Pengguna:", "User:", "\nAsisten:", "Assistant:", "<end_of_turn>"],
    "echo": False,
}

# Keyword trigger untuk Thinking Mode
THINKING_MODE_KEYWORDS = [
    "berfikir",
    "berpikir",
    "analisis",
    "analisa",
    "jelaskan",
    "detail",
    "buatkan",
    "coding",
    "code",
    "hitung",
    "rumus",
    "terangkan",
    "explain",
    "analyze",
    "code",
]

# ===== TTS CONFIGURATION =====
TTS_CONFIG = {
    # --- Kokoro TTS (PRIORITY - most natural female voice) ---
    "kokoro_model_path": os.path.join(KOKORO_DIR, "kokoro-v1.0.onnx"),
    "kokoro_voices_path": os.path.join(KOKORO_DIR, "voices-v1.0.bin"),
    "kokoro_voice": "af_heart",
    "kokoro_speed": 1.0,
    "kokoro_lang": "en-us",

    # --- Piper TTS (fallback 1) ---
    "piper_model_id": "id_ID-tuti-medium",
    "piper_fallback_ids": [
        "en_US-libritts-high",
        "en_US-lessac-medium",
    ],
    "piper_gpu": False,
    "piper_length_scale": 0.95,

    # --- pyttsx3 (fallback 2) ---
    "pyttsx3_rate": 160,
    "pyttsx3_volume": 1.0,
    "pyttsx3_voice_id": "mb-id1",
    "pyttsx3_prefer_female": True,

    # --- Cache settings ---
    "tts_cache_limit": 100,
    "tts_max_chars": 500,
}

# ===== CHAT CONFIGURATION =====
CHAT_CONFIG = {
    "max_history": 20,
    "max_history_window": 10,
    "min_response_length": 3,
    "max_prompt_chars": 6000,
}

# ===== SYSTEM CONTEXTS =====
SYSTEM_CONTEXTS = {
    "admin": (
        "Kamu adalah asisten pribadi bernama Mikkan, melayani Kuro (Admin sistem). "
        "Kuro adalah developer yang menggunakan Linux Mint dengan Hyprland. "
        "Jawablah secara teknis, akurat, dan mendetail. "
        "Boleh menggunakan istilah teknis. Berikan contoh atau langkah-langkah jika relevan. "
        "Jangan terlalu singkat — jawaban lengkap lebih dihargai."
    ),
    "member": (
        "Kamu adalah asisten AI bernama Mikkan yang melayani {username}, member premium. "
        "Bersikaplah ramah, hangat, dan informatif. "
        "Berikan jawaban yang lengkap dan membantu. "
        "Jika ada beberapa poin penting, jelaskan satu per satu. "
        "Gunakan bahasa yang natural dan mudah dimengerti."
    ),
    "user": (
        "Kamu adalah asisten AI bernama Mikkan. "
        "Jawab dengan jelas, lengkap, dan langsung ke inti. "
        "Jika pertanyaan membutuhkan penjelasan bertahap, berikan langkah-langkahnya. "
        "Jangan meniru atau mengulang ucapan pengguna. "
        "Gunakan bahasa Indonesia yang baik dan natural."
    ),
}

# ===== RATE LIMITING =====
RATE_LIMITS = {
    "admin": None,
    "member": "6 per minute",
    "user": "2 per minute",
}

# ===== SYSTEM INFO =====
SYSTEM_INFO = {
    "name": "Intel Core i3-1220P",
    "gpu": "iGPU only - GPU offloading disabled",
    "description": "Optimized for i3-1220P (iGPU only - GPU offloading disabled for optimal performance)",
}