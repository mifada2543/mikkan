# 🤖 MIKKAN AI - Interactive Live2D Chatbot System

A sophisticated **local AI assistant** with animated Live2D character interface. Combines server-side LLM inference with client-side 3D character animation.

**Status**: Production Ready | **Last Updated**: May 11, 2026

---

## 📋 Table of Contents

1. [System Overview](#system-overview)
2. [Quick Start](#quick-start)
3. [Architecture](#architecture)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Usage](#usage)
7. [File Structure](#file-structure)
8. [API Documentation](#api-documentation)
9. [Database Schema](#database-schema)
10. [Troubleshooting](#troubleshooting)
11. [Performance Notes](#performance-notes)

---

## 🎯 System Overview

### Purpose
MIKKAN is an **interactive AI chatbot** that provides intelligent conversation through an animated Live2D character. Designed for Indonesian users, featuring real-time context-aware responses and character animations.

### Key Features

✅ **Local LLM Inference** - No cloud dependencies, run everything locally  
✅ **Live2D Animation** - 3D character models with eye-tracking and expressions  
✅ **Context-Aware Chat** - Maintains conversation history (last 6-20 messages)  
✅ **Text-to-Speech (TTS)** - Offline speech synthesis using Piper TTS + pyttsx3 fallback  
✅ **Role-Based Access** - Admin, Member, User roles with different rate limits  
✅ **Session Management** - Secure authentication with bcrypt hashing  
✅ **Multi-Model Support** - Switch between 3 different character models  
✅ **Real-Time Health Checks** - Auto-verify backend status before sending messages  
✅ **Graceful Fallback** - Placeholder avatar if Live2D fails to load  

---

## 🚀 Quick Start

### Prerequisites
- Python 3.8+
- PHP 7.4+
- MySQL 5.7+
- XAMPP/LAMP Stack
- 4GB+ RAM
- 5GB+ disk space

### 1. Activate Python Environment

```bash
cd /opt/lampp/htdocs/mikkan
source mikkan/bin/activate
```

**Note**: Keyboard shortcut to open terminal: `Ctrl+Shift+` (backtick key left of '1')

### 2. Start Backend Server

```bash
python logic/main.py
```

You'll see a model selection menu:
```
============================================================
🤖 MIKKAN AI - MODEL SELECTION
============================================================

Model tersedia di: /opt/lampp/htdocs/mikkan/logic/../assets/llm

  1. gemma-3-1b-low.gguf                 (0.93 GB)
  2. gemma-3-4b-mid.gguf                 (3.85 GB)
  3. gemma-3-4b-high.gguf                (7.23 GB)

------------------------------------------------------------

Pilih model (1-3): 
```

**Recommendation**: Choose `1` (gemma-3-1b-low) for Intel i3-1220P

### 3. Access Web Interface

Open browser and navigate to:
```
http://localhost/mikkan/
```

Or from your network:
```
http://192.168.30.130/mikkan/
```

---

## 🏗️ Architecture

### 3-Tier System Architecture

```
┌─────────────────────────────────────┐
│   Frontend Layer (Browser)          │
│  ├─ HTML/CSS/JavaScript             │
│  ├─ PIXI.js Canvas                  │
│  ├─ Live2D Rendering                │
│  └─ Chat UI                         │
└────────────┬────────────────────────┘
             │ AJAX/Fetch (JSON)
┌────────────▼────────────────────────┐
│   PHP Proxy Layer                   │
│  ├─ /api/chat.php                   │
│  ├─ /api/health.php                 │
│  ├─ /api/chat-reset.php             │
│  └─ cURL → Flask                    │
└────────────┬────────────────────────┘
             │ HTTP cURL (JSON)
┌────────────▼────────────────────────┐
│   Flask Backend (localhost:5000)    │
│  ├─ Gemma GGUF LLM Model            │
│  ├─ Conversation Memory (RAM)       │
│  ├─ Rate Limiting                   │
│  └─ Prompt Engineering              │
└────────────┬────────────────────────┘
             │ SQL Queries
┌────────────▼────────────────────────┐
│   MySQL Database                    │
│  ├─ Users Table                     │
│  ├─ Sessions                        │
│  └─ User Preferences                │
└─────────────────────────────────────┘
```

### Data Flow: Chat Message

```
1. User types message in web UI
   ↓
2. JavaScript: fetch('/api/chat.php', POST)
   ↓
3. PHP Proxy: cURL to http://127.0.0.1:5000/chat
   ↓
4. Flask Backend:
   ├─ Retrieve last 6 messages from chat_memories[user_id]
   ├─ Build system context based on user role
   ├─ Construct prompt: system_context + history + user_input
   ├─ Run Gemma LLM (max_tokens=120, temp=0.4)
   ├─ Sanitize output (remove markdown, filter echo)
   └─ Store in chat_memories[user_id]
   ↓
5. Response JSON → PHP → JavaScript
   ↓
6. Display in chat UI
   ↓
7. Optional: Trigger Live2D expression animation
```

### Components Breakdown

| Component | Location | Purpose | Tech Stack |
|-----------|----------|---------|-----------|
| **Frontend** | `index.php` + `assets/js/script.js` | Chat UI + Live2D | HTML5, CSS (Tailwind), Vanilla JS |
| **Live2D** | `assets/js/script.js` | Character animation | PIXI.js, pixi-live2d-cubism4 |
| **Proxy** | `api/*.php` | Request routing | PHP 7.4+ |
| **Backend** | `logic/main.py` | Flask app + routes | Flask |
| **Chat Logic** | `logic/chat.py` | Prompt building, memory, sanitization | Python |
| **Model Runtime** | `logic/model.py` | LLM loading & inference | llama-cpp-python |
| **Voice Runtime** | `logic/voice.py` | TTS loading & synthesis | Piper, pyttsx3 |
| **LLM** | `assets/llm/*.gguf` | AI model | Gemma GGUF (quantized) |
| **Auth** | `auth/*.php` | User management | PHP, MySQL, bcrypt |
| **Admin** | `admin/index.php` | User control panel | PHP, JavaScript |
| **Database** | MySQL | Persistence | users, sessions tables |

---

## 📦 Installation

### Step 1: Clone/Download Repository
```bash
cd /opt/lampp/htdocs/
# Already at: mikkan/
```

### Step 2: Create Python Virtual Environment (Already Done)
```bash
python3 -m venv mikkan
source mikkan/bin/activate
```

### Step 3: Install Python Dependencies
```bash
pip install -r requirements.txt
```

**Dependencies**:
- flask==2.3.2
- flask-cors==4.0.0
- llama-cpp-python==0.2.36
- flask-limiter==3.3.1
- psutil==5.9.5

### Step 4: Database Setup
```sql
-- Create database
CREATE DATABASE mikkan;
USE mikkan;

-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member', 'user') DEFAULT 'user',
    is_active TINYINT DEFAULT 2,  -- 1=active, 2=pending, 0=inactive
    model_name VARCHAR(100),
    model_desc VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP,
    last_session_id VARCHAR(255),
    last_page VARCHAR(255),
    user_agent TEXT,
    access_via VARCHAR(50),
    ip_address VARCHAR(45)
);

-- Create default admin
INSERT INTO users (username, password, role, is_active, model_name, model_desc)
VALUES ('admin', '$2y$10$...hash...', 'admin', 1, 'Mikkan AI', 'Admin Assistant');
```

### Step 5: Verify Setup
```bash
# Check Python environment
which python
python --version

# Check packages
pip list | grep -E "flask|llama|psutil"

# Test backend startup
python logic/main.py
```

---

## ⚙️ Configuration

### Backend Configuration (main.py)

#### Model Loading
```python
# Auto-detects available .gguf files in assets/llm/
# User selects model at startup (interactive menu)
```

#### Hardware Optimization (Intel Core i3-1220P)

```python
n_threads = 8              # Use 8 of 10 cores (reserve 2 for OS)
n_ctx = 3000               # Context window (tokens)
n_batch = 128              # Batch size (memory efficient)
n_gpu_layers = 0           # CPU-only (iGPU disabled)
f16_kv = True              # FP16 KV cache (save RAM)
```

#### LLM Inference Parameters

```python
max_tokens = 120           # Max output tokens
temperature = 0.4          # 0-1, lower = deterministic
top_p = 0.85              # Nucleus sampling threshold
top_k = 30                # Keep top-30 tokens
repeat_penalty = 1.18     # Prevent repetition
```

#### Rate Limiting (by role)

```python
admin   → Unlimited
member  → 6 requests/minute
user    → 2 requests/minute
```

#### Text-to-Speech (TTS) Configuration

```python
# TTS Engine Selection (automatic with fallback)
# Priority: Piper TTS (female voice) > pyttsx3 (female voice) > Disabled

# Piper TTS Settings (if available)
model_name = "id_ID/google_medium"      # Indonesian model (female voice by default)
fallback_models = [                     # Fallback models in order
    "en_US/libritts-high",              # English female (high quality)
    "en_US/libritts",                   # English fallback
]
length_scale = 0.95                     # Speed: 0.5=fast, 1.0=normal, 2.0=slow
gpu = False                             # CPU-only for i3

# pyttsx3 Settings (fallback) - Female Voice Preference
rate = 160                              # Words per minute (slightly faster = feminine tone)
volume = 1.0                            # 0.0-1.0 (max for clarity)
voice_id = "poz/id"                     # Indonesian voice (gender-neutral but appropriate)
prefer_female = True                    # Look for female-attributed voices first

# Audio Caching
cache_limit = 100                       # Max cached audio entries
cache_key = MD5(text)                   # Hash-based cache
```

**Voice Feature**:
- 🎤 **Female Voice Priority** - Both engines configured for feminine tone
  - Piper TTS: Indonesian female model (native speaker quality)
  - pyttsx3: Indonesian language voice (espeak-ng engine)
- 🗣️ **Speed Tuned** - 160 WPM for natural, engaging speech
- 🔊 **Volume Maximized** - 1.0 for clear, audible output
- 📁 **Smart Caching** - Repeated phrases play instantly

**Performance Notes**:
- First TTS generation: 2-5 seconds (model loading)
- Subsequent generations: <1 second (cached)
- Audio file size: ~50-100 KB per response
- Memory: ~20-50 MB for audio cache

### Frontend Configuration (index.php)

```php
// Session timeout
ini_set('session.gc_maxlifetime', 43200);  // 12 hours

// Model storage
$MODEL_STORAGE_KEY = 'mikkan_live2d_model';  // localStorage key

// Chat history storage
$storageKey = `chat_history_${userId}`;     // localStorage
```

#### Timeout Settings (JavaScript)

```javascript
// Chat request timeout: 60 seconds (60000 ms)
// Health check timeout: 5 seconds (5000 ms)
```

---

## 🎮 Usage

### User Registration & Login

1. **Register**
   - Go to `/auth/register.php`
   - Create username (4+ chars) + password (8+ chars)
   - Auto-marked as `is_active=2` (pending approval)

2. **Admin Approval**
   - Admin goes to `admin/index.php`
   - Find pending registrations
   - Click "Approve" or "Reject"

3. **Login**
   - Navigate to `/auth/login.php`
   - Enter credentials
   - Session created for 12 hours

### Chat Features

#### Send Message
- Type in chat input box
- Press Enter or click Send button
- AI responds in 1-5 seconds (depending on model)

#### Clear Chat History
- Click Menu (hamburger icon)
- Select "Hapus Chat" (Clear Chat)
- Confirms with system message

#### Switch Live2D Model
- Click Menu (hamburger icon)
- Select from available models:
  - Misan (IceGirl)
  - Yachio (八千代辉夜姬)
  - Huohuo
- Page reloads with new model

#### View Debug Info
- Menu shows "Debug tombol model:" status
- Shows current active model
- Shows backend connection status

#### Enable Text-to-Speech (TTS) 🔊
- Click Menu (hamburger icon)
- Click "Suara AI" toggle button
- Badge shows ON/OFF status (green/gray)
- When ON: AI responses automatically play as audio
- Uses **female voice** for engaging, feminine tone
- Primary: Piper TTS Indonesian model (female speaker)
- Fallback: pyttsx3 Indonesian voice (espeak-ng)
- Audio is cached for repeated phrases (faster second time)

**TTS Features**:
- ✅ Fully offline - no internet required
- ✅ **Female voice** - feminine, natural-sounding speech
- ✅ Auto-play responses when enabled
- ✅ Configurable speed & volume (edit in `logic/config.py`)
- ✅ Multi-language support
- ✅ Smart caching (MD5-based)

### Admin Panel

Accessible at `/admin/index.php` for admin-role users

**Features**:
- View all registered users
- Approve/Reject pending registrations
- Delete user accounts
- Kick active sessions (force logout)
- View user statistics

---

## 📁 File Structure

```
/opt/lampp/htdocs/mikkan/
│
├── 📄 index.php                    # Main authenticated interface
├── 📄 README.md                    # This file
├── 📄 requirements.txt             # Python dependencies
├── 📄 readme.md                    # Original setup notes
│
├── 📁 admin/
│   └── index.php                   # Admin control panel
│
├── 📁 api/
│   ├── _proxy.php                  # Core cURL proxy function
│   ├── chat.php                    # Chat endpoint wrapper
│   ├── chat-reset.php              # Reset conversation
│   └── health.php                  # Backend health check
│
├── 📁 auth/
│   ├── db.php                      # Database & session config
│   ├── login.php                   # Login form & handler
│   ├── register.php                # Registration form & handler
│   ├── logout.php                  # Session termination
│   └── auth.php                    # Auth helper functions
│
├── 📁 assets/
│   ├── css/
│   │   └── tailwind.css            # Tailwind CSS output
│   ├── js/
│   │   ├── script.js               # Main frontend logic (CRITICAL)
│   │   ├── pixi.js                 # PIXI v7 rendering engine
│   │   ├── pixi-live2d-cubism4.js  # Live2D plugin
│   │   ├── live2dcubismcore.min.js # Cubism core runtime
│   │   ├── lucide.js               # Icon library
│   │   └── tailwind.js             # Tailwind CDN
│   ├── l2d/
│   │   ├── Misan/                  # IceGirl model files
│   │   │   └── IceGirl.model3.json
│   │   ├── Yachio/                 # 八千代辉夜姬 model files
│   │   │   └── 八千代辉夜姬.model3.json
│   │   └── huohuo/                 # Huohuo model files
│   │       └── huohuo.model3.json
│   └── llm/
│       ├── gemma-3-1b-low.gguf     # 0.93 GB (RECOMMENDED)
│       ├── gemma-3-4b-mid.gguf     # 3.85 GB (OK)
│       └── gemma-3-4b-high.gguf    # 7.23 GB (NOT RECOMMENDED)
│
├── 📁 logic/
│   ├── main.py                     # Flask app + routes only
│   ├── config.py                   # Shared configuration
│   ├── chat.py                     # Prompt building + chat memory
│   ├── model.py                    # LLM loading + inference
│   ├── voice.py                    # TTS management
│   ├── middleware/
│   │   ├── __init__.py             # Middleware exports
│   │   ├── cors.py                 # CORS setup
│   │   └── limiter.py              # Rate-limiter setup
│   └── utils/
│       ├── __init__.py             # Utility exports
│       ├── cache.py                # Simple in-memory cache
│       └── validators.py           # Request/input validators
│
└── 📁 mikkan/                      # Python virtual environment
    ├── bin/
    │   ├── python, python3         # Python executables
    │   ├── pip, pip3               # Package manager
    │   └── activate                # Activation script
    └── lib/
        └── python3.12/
            └── site-packages/      # Installed packages
```

---

## 🔌 API Documentation

### Frontend → PHP Endpoints

#### 1. **POST /api/chat.php**
Send chat message to AI

**Request**:
```json
{
  "message": "Halo, apa kabar?",
  "role": "user",
  "username": "muhammaddaffa",
  "user_id": 1,
  "csrf_token": "hex32chars..."
}
```

**Response (Success)**:
```json
{
  "response": "Baik-baik saja! Ada yang bisa saya bantu?",
  "status": "success"
}
```

**Response (Error)**:
```json
{
  "response": "Error message...",
  "status": "error"
}
```

**Status Codes**:
- `200` - Success
- `400` - Bad request (empty message)
- `429` - Rate limit exceeded
- `503` - Backend offline

---

#### 2. **GET /api/health.php**
Check backend LLM status

**Request**:
```
GET /api/health.php
```

**Response**:
```json
{
  "status": "ok",
  "model_loaded": true
}
```

---

#### 3. **POST /api/chat-reset.php**
Clear conversation history

**Request**:
```json
{
  "user_id": 1
}
```

**Response**:
```json
{
  "response": "Riwayat chat berhasil dihapus.",
  "status": "success"
}
```

---

### PHP → Flask Endpoints (Internal)

#### 1. **POST http://127.0.0.1:5000/chat**
Process message with LLM

**Request**:
```json
{
  "message": "User input",
  "role": "admin|member|user",
  "username": "username",
  "user_id": 1
}
```

**Processing**:
1. Fetch last 6 messages from `chat_memories[user_id]`
2. Build system context based on role
3. Construct prompt: system_context + history + input
4. Run LLM inference
5. Sanitize response
6. Store in memory

**Response**:
```json
{
  "response": "AI generated response",
  "status": "success"
}
```

---

#### 2. **GET http://127.0.0.1:5000/health**
Health check

**Response**:
```json
{
  "status": "ok",
  "model_loaded": true
}
```

---

#### 3. **POST http://127.0.0.1:5000/chat/reset**
Reset user memory

**Request**:
```json
{
  "user_id": 1
}
```

**Response**:
```json
{
  "response": "Riwayat chat berhasil dihapus.",
  "status": "success"
}
```

---

#### 4. **POST http://127.0.0.1:5000/tts**
Convert text to speech (audio generation)

**Request**:
```json
{
  "text": "Halo, selamat datang di Mikkan AI!",
  "user_id": 1
}
```

**Response** (Audio/WAV file):
```
[Binary WAV audio data]
```

**Features**:
- Dual-engine support: Piper TTS (better) + pyttsx3 (fallback)
- Caching: Identical texts reuse cached audio
- Max text length: 500 characters (auto-truncate)
- Cache limit: 100 entries (LRU cleanup)
- Offline: No internet required

**Supported Languages**:
- **Piper**: Indonesian (id_ID) + English (en_US)
- **pyttsx3**: System default TTS voice

---

## 🗄️ Database Schema

### Users Table

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Credentials
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    
    -- Roles & Status
    role ENUM('admin', 'member', 'user') DEFAULT 'user',
    is_active TINYINT DEFAULT 2,  -- 1=active, 2=pending, 0=inactive
    
    -- User Preferences
    model_name VARCHAR(100),       -- Character name (e.g., "Misan")
    model_desc VARCHAR(255),       -- Character description
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP,
    
    -- Session Info
    last_session_id VARCHAR(255),
    last_page VARCHAR(255),
    user_agent TEXT,
    access_via VARCHAR(50),
    ip_address VARCHAR(45)
);
```

### Sample Data

```sql
-- Admin user
INSERT INTO users (username, password, role, is_active, model_name, model_desc)
VALUES (
    'admin',
    '$2y$10$Ye...bcrypt_hash....',
    'admin',
    1,
    'Mikkan AI',
    'Admin Assistant'
);

-- Member user
INSERT INTO users (username, password, role, is_active, model_name, model_desc)
VALUES (
    'member1',
    '$2y$10$member_hash...',
    'member',
    1,
    'Misan',
    'IceGirl - Premium Member'
);

-- Pending user
INSERT INTO users (username, password, role, is_active, model_name, model_desc)
VALUES (
    'newuser',
    '$2y$10$user_hash...',
    'user',
    2,
    'Yachio',
    'Awaiting admin approval'
);
```

---

## 🐛 Troubleshooting

### Backend Won't Start

**Problem**: `[ERROR] Failed to load model: ...`

**Solutions**:
1. Verify model file exists: `ls assets/llm/*.gguf`
2. Check disk space: `df -h`
3. Check RAM available: `free -h`
4. Verify GGUF file integrity: `file assets/llm/gemma-3-1b-low.gguf`

---

### HTTP Error 503 (Service Unavailable)

**Problem**: "Model AI belum dimuat"

**Causes & Fixes**:
1. **Backend not running**
   - Start it: `python logic/main.py`
   
2. **Model loading failed**
   - Check error in terminal
   - Verify model file size/integrity
   - Try smaller model (gemma-3-1b-low)

3. **Context too long**
   - Auto-reduces history if prompt > 3500 chars
   - Or manually: "Hapus Chat"

---

### TTS Not Working (No Sound)

**Problem**: Toggle "Suara AI" is ON but no audio plays

**Causes & Fixes**:
1. **TTS Engine failed to initialize**
   - Check terminal for `[INFO] Piper TTS (female voice)` or `[INFO] pyttsx3`
   - If both failed: install `pip install piper-tts pyttsx3`
   - pyttsx3 always available as fallback (pure Python)

2. **Voice not set to female**
   - Check `logic/config.py` TTS_CONFIG
   - Verify `pyttsx3_voice_id: "poz/id"` (Indonesian)
   - Restart backend: `python logic/main.py`

3. **Browser audio permission**
   - Check browser console (F12) for errors
   - Grant audio permissions if prompted
   - Try different browser

4. **Audio file not generated**
   - Check Flask backend `/tts` responses in console
   - Verify text is not empty
   - Try shorter text first

5. **Audio plays but sounds wrong**
   - Voice too fast/slow: adjust `pyttsx3_rate` in config (150-200 WPM)
   - Volume too low: increase `pyttsx3_volume` to 1.0
   - Language issue: verify response text is correct

6. **Piper TTS dependency error**
   - If `ModuleNotFoundError: No module named 'piper'`
   - Use pyttsx3 fallback (automatically enabled)
   - Or install: `pip install piper-tts`
   - Check system volume/speaker
   - Try headphones
   - Restart browser

**Debug**:
```bash
# Check if pyttsx3 works
cd /opt/lampp/htdocs/mikkan && source mikkan/bin/activate
python -c "import pyttsx3; e = pyttsx3.init(); e.save_to_file('Test', 'test.wav'); e.runAndWait()"
```

---

### Chat Timeout (60 seconds)

**Problem**: "⏱️ Timeout: AI tidak merespons dalam 60 detik"

**Causes & Fixes**:
1. **CPU bottleneck** (i3 full load)
   - Close other apps
   - Use smaller model
   - Increase timeout in `index.php` (max 120000 ms)

2. **Network issue**
   - Check: `curl http://127.0.0.1:5000/health`
   - Verify proxy is running

3. **LLM crash**
   - Check terminal for errors
   - Restart backend: `python logic/main.py`

---

### Live2D Not Loading

**Problem**: Placeholder avatar shows instead of Live2D

**Causes & Fixes**:
1. **PIXI.js not loaded**
   - Check browser console (F12)
   - Verify `assets/js/pixi.js` exists

2. **Model file missing**
   - Verify at `assets/l2d/Misan/IceGirl.model3.json`
   - Check file permissions: `chmod 644 assets/l2d/**/*.json`

3. **Browser GPU issue**
   - Disable hardware acceleration
   - Try different browser

---

### Rate Limit 429

**Problem**: "Mohon tunggu sebentar (Rate Limit aktif)"

**Solutions**:
1. Wait 1 minute (rate limit reset)
2. Ask admin to upgrade to `member` role
3. Configure in `main.py`:
   ```python
   # Change rate limits
   "member": "12 per minute",  # vs 6
   ```

---

### Database Connection Error

**Problem**: "Failed to connect to database"

**Fixes**:
1. Verify MySQL running: `service mysql status`
2. Check credentials in `auth/db.php`
3. Create database: `mysql < schema.sql`
4. Verify user permissions

---

### High CPU Usage (i3 Stuck)

**Problem**: System becomes unresponsive

**Solutions**:
1. **Switch to smaller model**: Use `gemma-3-1b-low.gguf`
2. **Reduce context**: Clear chat history
3. **Lower inference params** in `main.py`:
   ```python
   max_tokens = 80      # vs 120
   n_threads = 6        # vs 8
   ```

---

## 📊 Performance Notes

### Hardware Profiles

#### Intel Core i3-1220P (Your System)
| Metric | Value |
|--------|-------|
| Cores | 10 (2P + 8E) |
| Threads | 8 (reserved 2 for OS) |
| Model | gemma-3-1b-low.gguf |
| Response Time | ~1-2 seconds |
| Memory (Runtime) | ~2.2 GB |
| Max Context | 3000 tokens |

#### Intel Core i7 or Better
| Metric | Value |
|--------|-------|
| Threads | 12+ |
| Model | gemma-3-4b-mid.gguf |
| Response Time | ~2-4 seconds |
| Memory (Runtime) | ~6-8 GB |
| Max Context | 4096+ tokens |

### Optimization Tips

1. **For Slow Responses**
   - Use `gemma-3-1b-low.gguf` (fastest)
   - Reduce `max_tokens` (80-100)
   - Increase `n_threads` if CPU has more cores

2. **For Better Quality**
   - Use `gemma-3-4b-mid.gguf`
   - Increase `max_tokens` (150-200)
   - Lower `temperature` (0.3-0.4)

3. **For Memory Issues**
   - Reduce `n_ctx` (2048)
   - Reduce `n_batch` (64)
   - Enable `f16_kv` (already on)

---

## 🔐 Security Notes

- **Passwords**: Hashed with bcrypt (PASSWORD_DEFAULT)
- **Sessions**: 12-hour timeout, CSRF tokens on forms
- **SQL**: Prepared statements (no injection)
- **CORS**: Restricted to localhost patterns
- **Rate Limiting**: Per-user, per-role
- **Input Validation**: All user inputs sanitized

---

## 📝 License

Not specified in project files.

---

## 👨‍💻 Development Notes

### For Future Enhancements

- [ ] Voice input/output (TTS/STT)
- [ ] Export chat history (PDF/JSON)
- [ ] Custom system prompts
- [ ] Multi-language support
- [ ] Mobile app version
- [ ] Database persistence of chat history
- [ ] User-configurable model parameters
- [ ] Live2D gesture triggering

### Known Limitations

- Chat history stored in RAM (lost on restart)
- Only supports local LLM (no cloud API fallback)
- Limited to 150 response tokens max
- No user chat export

---

## 📞 Support

For issues:
1. Check this README (Troubleshooting section)
2. Review terminal logs when running `python logic/main.py`
3. Check browser console (F12 → Console tab)
4. Verify all components running:
   - Flask backend (port 5000)
   - Apache/PHP (port 80)
   - MySQL (port 3306)

---

**Built with ❤️ for Indonesian AI enthusiasts**  
**Powered by Gemma LLM + PIXI.js Live2D**  
**Optimized for Intel Core i3-1220P CPU**
