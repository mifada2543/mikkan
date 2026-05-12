"""
Text-to-Speech (TTS) management
Priority: Kokoro TTS (natural female) → Piper TTS → pyttsx3
"""
import os
import io
import tempfile
import traceback

try:
    from .config import TTS_CONFIG
    from .utils import SimpleCache, generate_cache_key
except ImportError:
    from config import TTS_CONFIG
    from utils import SimpleCache, generate_cache_key


class TTSManager:
    """Manage TTS model and synthesis"""

    def __init__(self):
        self.model = None
        self.engine_type = None
        self.cache = SimpleCache(max_size=TTS_CONFIG["tts_cache_limit"])

    def initialize(self):
        """
        Lazy load TTS engine on first use.
        Priority: Kokoro → Piper → pyttsx3
        """
        if self.model is not None or self.engine_type is not None:
            return True

        # ── 1. Kokoro TTS (best quality, natural female voice) ───────────────
        try:
            print("[INFO] Attempting Kokoro TTS initialization (natural female voice)...")
            from kokoro_onnx import Kokoro

            model_path  = TTS_CONFIG["kokoro_model_path"]
            voices_path = TTS_CONFIG["kokoro_voices_path"]

            if not os.path.exists(model_path):
                raise FileNotFoundError(f"Kokoro model not found: {model_path}")
            if not os.path.exists(voices_path):
                raise FileNotFoundError(f"Kokoro voices not found: {voices_path}")

            self.model = Kokoro(model_path, voices_path)
            self.engine_type = "kokoro"
            print(f"[INFO] ✅ Kokoro TTS loaded! Voice: {TTS_CONFIG['kokoro_voice']} (natural female)")
            return True

        except Exception as e:
            print(f"[WARN] Kokoro TTS unavailable ({type(e).__name__}: {e}), trying Piper...")

        # ── 2. Piper TTS (fallback 1) ────────────────────────────────────────
        try:
            print("[INFO] Attempting Piper TTS initialization...")
            from piper.voice import PiperVoice

            model_name = TTS_CONFIG["piper_model_id"]
            try:
                self.model = PiperVoice.load(
                    model_name=model_name,
                    gpu=TTS_CONFIG["piper_gpu"],
                    length_scale=TTS_CONFIG["piper_length_scale"],
                )
                self.engine_type = "piper"
                print(f"[INFO] ✅ Piper TTS '{model_name}' loaded.")
                return True
            except Exception as e1:
                print(f"[WARN] Piper model '{model_name}' failed: {type(e1).__name__}")

                for fb_model in TTS_CONFIG["piper_fallback_ids"]:
                    try:
                        print(f"[INFO] Trying Piper fallback: {fb_model}...")
                        self.model = PiperVoice.load(
                            model_name=fb_model,
                            gpu=TTS_CONFIG["piper_gpu"],
                            length_scale=TTS_CONFIG["piper_length_scale"],
                        )
                        self.engine_type = "piper"
                        print(f"[INFO] ✅ Piper TTS '{fb_model}' loaded (fallback).")
                        return True
                    except Exception:
                        continue

                raise Exception("All Piper models failed")

        except Exception as e:
            print(f"[WARN] Piper TTS unavailable ({type(e).__name__}), trying pyttsx3...")

        # ── 3. pyttsx3 (last resort) ─────────────────────────────────────────
        try:
            import pyttsx3
            self.model = pyttsx3.init()

            voices = self.model.getProperty('voices')
            voice_set = False

            # Try configured voice ID first
            target_id = TTS_CONFIG.get("pyttsx3_voice_id", "")
            if target_id:
                for voice in voices:
                    if voice.id == target_id:
                        self.model.setProperty('voice', voice.id)
                        print(f"[INFO] ✅ pyttsx3 voice: {voice.name}")
                        voice_set = True
                        break

            # Look for female-labelled voice
            if not voice_set and TTS_CONFIG.get("pyttsx3_prefer_female", True):
                for voice in voices:
                    if any(k in voice.name.lower() for k in ('female', 'woman', 'girl')):
                        self.model.setProperty('voice', voice.id)
                        print(f"[INFO] ✅ pyttsx3 female voice: {voice.name}")
                        voice_set = True
                        break

            # Index-1 is often female
            if not voice_set and len(voices) > 1:
                self.model.setProperty('voice', voices[1].id)
                print(f"[INFO] ✅ pyttsx3 voice (index 1): {voices[1].name}")
                voice_set = True

            if not voice_set:
                print(f"[INFO] ℹ️ pyttsx3 default voice: {voices[0].name}")

            self.model.setProperty('rate',   TTS_CONFIG["pyttsx3_rate"])
            self.model.setProperty('volume', TTS_CONFIG["pyttsx3_volume"])
            self.engine_type = "pyttsx3"
            print("[INFO] ✅ pyttsx3 TTS initialized (last-resort fallback).")
            return True

        except ImportError:
            print("[ERROR] pyttsx3 not installed. Run: pip install pyttsx3")
        except Exception as e2:
            print(f"[ERROR] pyttsx3 initialization failed: {e2}")

        print("[INFO] TTS fully disabled — text-only chat mode.")
        self.engine_type = None
        return False

    # ─────────────────────────────────────────────────────────────────────────

    def synthesize(self, text):
        """Synthesize text to speech. Returns WAV bytes or None."""
        if not self.initialize():
            return None

        if len(text) > TTS_CONFIG["tts_max_chars"]:
            text = text[:TTS_CONFIG["tts_max_chars"]]
            print(f"[WARN] TTS text truncated to {TTS_CONFIG['tts_max_chars']} chars")

        # Cache check
        cache_key = generate_cache_key(text)
        if self.cache.has(cache_key):
            print(f"[DEBUG] TTS cache hit: {text[:30]}...")
            buf = self.cache.get(cache_key)
            buf.seek(0)
            return buf.getvalue()

        print(f"[DEBUG] Generating TTS ({self.engine_type}): {text[:50]}...")

        try:
            audio_buffer = io.BytesIO()

            # ── Kokoro ───────────────────────────────────────────────────────
            if self.engine_type == "kokoro":
                import soundfile as sf

                samples, rate = self.model.create(
                    text,
                    voice=TTS_CONFIG["kokoro_voice"],
                    speed=TTS_CONFIG["kokoro_speed"],
                    lang=TTS_CONFIG["kokoro_lang"],
                )
                sf.write(audio_buffer, samples, rate, format="WAV")

            # ── Piper ────────────────────────────────────────────────────────
            elif self.engine_type == "piper":
                self.model.synthesize(text, audio_buffer)

            # ── pyttsx3 ──────────────────────────────────────────────────────
            elif self.engine_type == "pyttsx3":
                tmp = tempfile.NamedTemporaryFile(suffix='.wav', delete=False)
                tmp_path = tmp.name
                tmp.close()
                try:
                    self.model.save_to_file(text, tmp_path)
                    self.model.runAndWait()
                    with open(tmp_path, 'rb') as f:
                        audio_buffer.write(f.read())
                finally:
                    if os.path.exists(tmp_path):
                        os.remove(tmp_path)

            audio_buffer.seek(0)
            audio_data = audio_buffer.getvalue()

            # Store in cache
            self.cache.set(cache_key, io.BytesIO(audio_data))

            print(f"[DEBUG] TTS generated: {len(audio_data)} bytes")
            return audio_data

        except Exception as e:
            print(f"[ERROR] TTS synthesis error: {e}")
            traceback.print_exc()
            return None

    def is_available(self):
        """Check if TTS is available."""
        return self.engine_type is not None


# ── Global instance ───────────────────────────────────────────────────────────

tts_manager = None


def get_tts_manager():
    """Get global TTS manager instance."""
    global tts_manager
    if tts_manager is None:
        tts_manager = TTSManager()
    return tts_manager