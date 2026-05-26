from __future__ import annotations

import io
import traceback

from flask import Flask, jsonify, request, send_file

try:
    from .chat import generate_chat_reply, reset_history, toggle_thinking_mode, get_thinking_mode_status
    from .middleware import create_limiter, get_role_limit, setup_cors
    from .model import get_llm_manager, initialize_llm
    from .voice import get_tts_manager
    from .config import TTS_CONFIG
    from .utils import normalize_user_id, parse_json_payload, truncate_text, validate_required_text
except ImportError:
    from chat import generate_chat_reply, reset_history, toggle_thinking_mode, get_thinking_mode_status
    from middleware import create_limiter, get_role_limit, setup_cors
    from model import get_llm_manager, initialize_llm
    from voice import get_tts_manager
    from config import TTS_CONFIG
    from utils import normalize_user_id, parse_json_payload, truncate_text, validate_required_text


app = Flask(__name__)
setup_cors(app)
limiter = create_limiter(app)

llm_manager = get_llm_manager()
initialize_llm(interactive=True)
tts_manager = get_tts_manager()


@app.route("/chat", methods=["POST", "OPTIONS"])
@limiter.limit(get_role_limit)
def chat():
    if request.method == "OPTIONS":
        return "", 204

    try:
        if not llm_manager.is_loaded():
            return jsonify({
                "response": "Model AI belum dimuat. Silakan restart server.",
                "status": "error",
            }), 503

        data = parse_json_payload(request)
        user_input = validate_required_text(data.get("message"), "message", "Pesan kosong. Coba lagi.")
        role = str(data.get("role", "user")).strip() or "user"
        username = str(data.get("username", "Guest")).strip() or "Guest"
        user_id = normalize_user_id(data.get("user_id"), fallback=username)
        
        # New: Thinking mode dari client
        thinking_mode_explicit = data.get("thinking_mode", False)

        try:
            response_text, is_thinking = generate_chat_reply(
                llm_manager=llm_manager,
                user_input=user_input,
                role=role,
                username=username,
                user_id=user_id,
                thinking_mode_explicit=thinking_mode_explicit,
            )
        except RuntimeError as err:
            print(f"[ERROR] LLM inference error on i3-1220P: {err}")
            return jsonify({
                "response": "Model overload. Coba kirim input lebih singkat atau reset chat untuk mengurangi context. (i3 CPU load tinggi)",
                "status": "error",
            }), 503

        return jsonify({
            "response": response_text,
            "status": "success",
            "thinking_mode": is_thinking,
        })

    except ValueError as err:
        return jsonify({
            "response": str(err),
            "status": "error",
        }), 400
    except Exception as err:
        print(f"[ERROR] Chat error: {err}")
        traceback.print_exc()
        return jsonify({
            "response": "Terjadi kesalahan server. Cek log untuk detail.",
            "status": "error",
        }), 500


@app.route("/chat/reset", methods=["POST", "OPTIONS"])
def reset_chat():
    if request.method == "OPTIONS":
        return "", 204

    try:
        data = parse_json_payload(request)
        user_id = validate_required_text(data.get("user_id"), "user_id", "user_id wajib diisi.")
        reset_history(user_id)

        return jsonify({
            "response": "Riwayat chat berhasil dihapus.",
            "status": "success",
        })
    except ValueError as err:
        return jsonify({
            "response": str(err),
            "status": "error",
        }), 400
    except Exception as err:
        print(f"[ERROR] Reset chat error: {err}")
        traceback.print_exc()
        return jsonify({
            "response": "Terjadi kesalahan saat menghapus riwayat chat.",
            "status": "error",
        }), 500


@app.route("/thinking/toggle", methods=["POST", "OPTIONS"])
def toggle_thinking():
    """Toggle thinking mode untuk user."""
    if request.method == "OPTIONS":
        return "", 204

    try:
        data = parse_json_payload(request)
        user_id = validate_required_text(data.get("user_id"), "user_id", "user_id wajib diisi.")
        
        # enable=None berarti toggle (kebalikan status sekarang)
        enable = data.get("enable", None)
        if enable is not None:
            enable = bool(enable)

        new_status = toggle_thinking_mode(user_id, enable=enable)

        return jsonify({
            "status": "success",
            "thinking_mode": new_status,
            "message": "🧠 Thinking Mode " + ("diaktifkan" if new_status else "dimatikan"),
        })
    except ValueError as err:
        return jsonify({
            "response": str(err),
            "status": "error",
        }), 400
    except Exception as err:
        print(f"[ERROR] Toggle thinking error: {err}")
        traceback.print_exc()
        return jsonify({
            "response": "Terjadi kesalahan saat toggle thinking mode.",
            "status": "error",
        }), 500


@app.route("/thinking/status", methods=["GET", "POST", "OPTIONS"])
def thinking_status():
    """Get thinking mode status untuk user."""
    if request.method == "OPTIONS":
        return "", 204

    try:
        if request.method == "GET":
            user_id = request.args.get("user_id", "Guest")
        else:
            data = parse_json_payload(request)
            user_id = data.get("user_id", "Guest")

        is_thinking = get_thinking_mode_status(user_id)

        return jsonify({
            "status": "success",
            "thinking_mode": is_thinking,
            "user_id": user_id,
        })
    except Exception as err:
        print(f"[ERROR] Get thinking status error: {err}")
        return jsonify({
            "response": "Terjadi kesalahan saat mengecek status thinking mode.",
            "status": "error",
        }), 500


@app.route("/tts", methods=["POST", "OPTIONS"])
def text_to_speech():
    """Convert text to speech using Kokoro TTS."""
    if request.method == "OPTIONS":
        return "", 204

    try:
        data = parse_json_payload(request)
        text = validate_required_text(data.get("text"), "text", "Text kosong")
        user_id = normalize_user_id(data.get("user_id"), fallback="unknown")

        text, was_truncated = truncate_text(text, TTS_CONFIG["tts_max_chars"])
        if was_truncated:
            print(f"[WARN] TTS text truncated to {TTS_CONFIG['tts_max_chars']} chars")

        print(f"[DEBUG] TTS request from user {user_id}: {text[:50]}...")
        audio_data = tts_manager.synthesize(text)
        if not audio_data:
            return jsonify({
                "error": "TTS not available",
            }), 503

        audio_buffer = io.BytesIO(audio_data)
        audio_buffer.seek(0)

        return send_file(
            audio_buffer,
            mimetype="audio/wav",
            as_attachment=True,
            download_name="response.wav",
        )
    except ValueError as err:
        return jsonify({
            "error": str(err),
        }), 400
    except Exception as err:
        print(f"[ERROR] TTS error: {err}")
        traceback.print_exc()
        return jsonify({
            "error": "Terjadi kesalahan TTS",
        }), 500


@app.errorhandler(429)
def ratelimit_handler(_error):
    return jsonify({
        "response": "Sistem: Mohon tunggu sebentar (Rate Limit aktif).",
        "status": "rate_limited",
    }), 429


@app.route("/health", methods=["GET", "OPTIONS"])
def health():
    if request.method == "OPTIONS":
        return "", 204
    return jsonify({
        "status": "ok",
        "model_loaded": llm_manager.is_loaded(),
    })


if __name__ == "__main__":
    print("[INFO] Starting Flask server with CORS enabled...")
    print("[INFO] Allowed origin: http://localhost")
    print("[INFO] CORS headers enabled for all routes")
    print("[INFO] New endpoints: /thinking/toggle, /thinking/status")
    app.run(host="0.0.0.0", port=5000, debug=False)