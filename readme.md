# 🚀 QUICK START GUIDE

## Activate Python Environment

Open terminal with `Ctrl+Shift+` (backtick key - left of '1'):

```bash
cd /opt/lampp/htdocs/mikkan
source mikkan/bin/activate
```

## Start Backend Server

```bash
python logic/main.py
```

Select model when prompted (recommended: **1** for gemma-3-1b-low.gguf)

## Access Web Interface

- **Local**: http://localhost/mikkan/
- **Network**: http://192.168.30.130/mikkan/

---

## 📖 Full Documentation

See **README.md** for comprehensive documentation including:
- System architecture
- Installation guide
- Configuration options
- API documentation
- Database schema
- Troubleshooting
- Performance optimization

---

## ⚙️ Optimizations Applied (Intel Core i3-1220P)

- CPU Threads: 8 of 10 cores (reserved 2 for OS)
- Context Window: 3000 tokens (optimized for RAM)
- Batch Size: 128 (memory efficient)
- GPU Offloading: Disabled (CPU-only faster)
- Timeout: 60 seconds (chat requests)