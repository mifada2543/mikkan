import os
import sys
import time
from pathlib import Path

try:
    import readline
except ImportError:
    pass

from llama_cpp import Llama


# =========================================================
# CONFIG
# =========================================================

MODEL_DIR = "/media/muhammaddaffa/KB/llm"

N_CTX = 16 * 1024
N_THREADS = 8
MAX_HISTORY = 8

TEMPERATURE = 0.3
TOP_P = 0.9
REPEAT_PENALTY = 1.1
MAX_TOKENS = 2048


# =========================================================
# TERMINAL COLORS
# =========================================================

COLOR_USER = "\033[92m"
COLOR_AI = "\033[96m"
COLOR_SYS = "\033[93m"
COLOR_ERR = "\033[91m"

RESET = "\033[0m"


# =========================================================
# UTILITIES
# =========================================================

def typing_effect(text, color=COLOR_SYS, delay=0.005):

    for char in text:
        sys.stdout.write(f"{color}{char}{RESET}")
        sys.stdout.flush()
        time.sleep(delay)

    print()


def clear():
    os.system("clear")


def print_error(text):
    print(f"{COLOR_ERR}[ERROR]{RESET} {text}")


# =========================================================
# MULTILINE INPUT
# =========================================================

def multiline_input():

    print(
        f"\n{COLOR_SYS}"
        "Paste code di bawah ini.\n"
        "Ketik EOF pada baris baru untuk selesai.\n"
        f"{RESET}"
    )

    lines = []

    while True:

        try:
            line = input()

            if line.strip() == "EOF":
                break

            lines.append(line)

        except EOFError:
            break

    code = "\n".join(lines)

    # Bungkus otomatis dengan markdown
    wrapped = (
        "Tolong analisa code berikut:\n\n"
        f"```python\n{code}\n```"
    )

    return wrapped


# =========================================================
# MODEL SELECTOR
# =========================================================

def select_model(directory):

    path = Path(directory)

    if not path.exists():
        print_error(f"Folder tidak ditemukan: {directory}")
        sys.exit(1)

    models = sorted(path.glob("*.gguf"))

    if not models:
        print_error("Tidak ada file GGUF.")
        sys.exit(1)

    typing_effect("\n=== MODEL YANG TERSEDIA ===\n")

    for idx, model in enumerate(models, 1):

        size_gb = model.stat().st_size / (1024**3)

        print(
            f"{idx}. "
            f"{model.name} "
            f"({size_gb:.2f} GB)"
        )

    while True:

        try:

            choice = int(
                input(
                    f"\n{COLOR_SYS}Pilih model: {RESET}"
                )
            )

            if 1 <= choice <= len(models):
                return str(models[choice - 1])

            print_error("Pilihan tidak valid.")

        except ValueError:
            print_error("Masukkan angka.")


# =========================================================
# LOAD MODEL
# =========================================================

clear()

model_path = select_model(MODEL_DIR)

typing_effect(
    f"\nMemuat model:\n"
    f"{os.path.basename(model_path)}\n"
)

load_start = time.time()

llm = Llama(
    model_path=model_path,
    n_ctx=N_CTX,
    n_threads=N_THREADS,
    use_mmap=True,
    use_mlock=False,
    verbose=False
)

load_time = time.time() - load_start

typing_effect(
    f"Model loaded dalam "
    f"{load_time:.2f} detik.\n"
)

# =========================================================
# SYSTEM PROMPT
# =========================================================

SYSTEM_PROMPT = (
    #"Kamu adalah FikaAI, asisten AI lokal "
    #"yang membantu coding dan penjelasan teknis.\n\n"
     ""
    #"Jawab langsung ke inti.\n"
    #"JANGAN tampilkan proses berpikir.\n"
    #"JANGAN tampilkan chain-of-thought.\n"
    #"JANGAN gunakan tag seperti <think>.\n"
    #"Berikan hanya jawaban final.\n"
    #"Ringkas dan efisien.\n"
    #"Jika diminta jelaskan proses, buat penjelasan terpisah tanpa tag.\n"
)

messages = [
    {
        "role": "system",
        "content": SYSTEM_PROMPT
    }
]


# =========================================================
# HELP
# =========================================================

def show_help():

    print(
        f"""
{COLOR_SYS}
Commands:
------------------------------------------------
/help       : tampilkan bantuan
/paste      : paste code multiline
/clear      : reset history chat
/exit       : keluar
------------------------------------------------
{RESET}
"""
    )


# =========================================================
# CHAT LOOP
# =========================================================

typing_effect(
    "=== FikaAI Local Ready ===\n"
)

show_help()

while True:

    try:

        user_input = input(
            f"\n{COLOR_USER}Kamu > {RESET}"
        ).strip()

        # =============================================
        # COMMANDS
        # =============================================

        if user_input == "/help":
            show_help()
            continue

        if user_input == "/clear":

            messages = [
                {
                    "role": "system",
                    "content": SYSTEM_PROMPT
                }
            ]

            typing_effect(
                "History dibersihkan."
            )

            continue

        if user_input == "/paste":
            user_input = multiline_input()

        # =============================================
        # EXIT
        # =============================================

        if user_input.lower() in [
            "exit",
            "quit",
            "keluar",
            "/exit"
        ]:

            typing_effect(
                "Sampai jumpa!"
            )

            break

        if not user_input:
            continue

        # =============================================
        # ADD USER MESSAGE
        # =============================================

        messages.append({
            "role": "user",
            "content": user_input
        })

        # =============================================
        # HISTORY LIMIT
        # =============================================

        if len(messages) > MAX_HISTORY + 1:

            messages = (
                [messages[0]] +
                messages[-MAX_HISTORY:]
            )

        # =============================================
        # GENERATION
        # =============================================

        print(
            f"\n{COLOR_AI}FikaAI > {RESET}",
            end=""
        )

        response_text = ""

        token_counter = 0

        start_time = time.time()

        stream = llm.create_chat_completion(
            messages=messages,
            temperature=TEMPERATURE,
            top_p=TOP_P,
            repeat_penalty=REPEAT_PENALTY,
            max_tokens=MAX_TOKENS,
            stream=True
        )

        # =============================================
        # STREAM OUTPUT
        # =============================================

        inside_think = False

        for output in stream:

            try:

                delta = output["choices"][0]["delta"]
                token = delta.get("content", "")

                if not token:
                    continue

                if "<think>" in token:
                    inside_think = True
                    continue

                if "</think>" in token:
                    inside_think = False
                    continue

                if inside_think:
                    continue

                token_counter += 1

                sys.stdout.write(
                    f"{COLOR_AI}{token}{RESET}"
                )

                sys.stdout.flush()

                response_text += token

            except Exception:
                pass

        elapsed = time.time() - start_time

        speed = (
            token_counter / elapsed
            if elapsed > 0
            else 0
        )

        print(
            f"\n\n{COLOR_SYS}"
            f"[{elapsed:.2f}s | "
            f"{speed:.1f} tok/s | "
            f"{token_counter} tokens]"
            f"{RESET}"
        )

        # =============================================
        # SAVE RESPONSE
        # =============================================

        messages.append({
            "role": "assistant",
            "content": response_text.strip()
        })

    except KeyboardInterrupt:

        print()

        typing_effect(
            "Program dihentikan."
        )

        sys.exit(0)

    except Exception as e:

        print_error(str(e))
