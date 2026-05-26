"""
Chat domain logic and in-memory conversation state.
Support untuk Thinking Mode (adaptive inference profiles).
"""
from __future__ import annotations

import re

try:
    from .config import (
        CHAT_CONFIG,
        SYSTEM_CONTEXTS,
        LLM_INFERENCE_NORMAL,
        LLM_INFERENCE_THINKING,
        THINKING_MODE_KEYWORDS,
    )
except ImportError:
    from config import (
        CHAT_CONFIG,
        SYSTEM_CONTEXTS,
        LLM_INFERENCE_NORMAL,
        LLM_INFERENCE_THINKING,
        THINKING_MODE_KEYWORDS,
    )


chat_memories = {}
thinking_mode_users = set()  # Track users dengan thinking mode aktif


def sanitize_response(text):
    """Clean and normalize model output before returning it."""
    if not text:
        return ""

    cleaned = text.replace("```", " ")
    cleaned = re.sub(r"`+", "", cleaned)
    cleaned = re.sub(r"^(Assistant|Asisten|AI)\s*:\s*", "", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"^(User|Pengguna)\s*:\s*.*$", "", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"\s+", " ", cleaned).strip()
    return cleaned


def build_prompt(system_context, history, user_input):
    """Build a structured prompt from system context, history, and input."""
    prompt_parts = [
        "Instruksi Sistem:",
        system_context,
        "",
        "Aturan Jawaban:",
        "- Jawab sebagai asisten, bukan sebagai pengguna.",
        "- Jangan menyalin atau mengulang pesan pengguna.",
        "- Jawab dengan lengkap, natural, dan informatif sesuai pertanyaan.",
        "",
        "Riwayat Percakapan:",
    ]

    if history:
        for msg in history:
            speaker = "Pengguna" if msg["role"] == "User" else "Asisten"
            prompt_parts.append(f"{speaker}: {msg['content']}")
    else:
        prompt_parts.append("Belum ada riwayat.")

    prompt_parts.extend([
        "",
        f"Pengguna: {user_input}",
        "Asisten:",
    ])

    return "\n".join(prompt_parts)


def looks_like_user_echo(response_text, user_input):
    """Detect whether the model only echoed the user input."""
    normalized_response = response_text.casefold().strip(" .,!?\n\t")
    normalized_input = user_input.casefold().strip(" .,!?\n\t")

    if not normalized_response or not normalized_input:
        return False

    if normalized_response == normalized_input:
        return True

    if normalized_response.startswith(normalized_input) and len(normalized_response) <= len(normalized_input) + 12:
        return True

    return False


def detect_thinking_mode(user_input: str, explicit_mode: bool = False) -> bool:
    """
    Detect apakah user request memerlukan Thinking Mode.
    explicit_mode=True berarti user sudah toggle tombol thinking.
    """
    if explicit_mode:
        return True

    # Auto-detect dari keyword
    input_lower = user_input.lower()
    for keyword in THINKING_MODE_KEYWORDS:
        if keyword in input_lower:
            return True

    return False


def get_system_context(role, username=""):
    """Resolve the system prompt by role."""
    context = SYSTEM_CONTEXTS.get(role, SYSTEM_CONTEXTS["user"])
    if role == "member" and username:
        context = context.format(username=username)
    return context


def get_history(user_id):
    """Return the conversation history for a user, creating it if needed."""
    return chat_memories.setdefault(user_id, [])


def append_history(user_id, user_input, response_text):
    """Append a new exchange and keep memory bounded."""
    history = get_history(user_id)
    history.append({"role": "User", "content": user_input})
    history.append({"role": "Assistant", "content": response_text})

    max_history = CHAT_CONFIG["max_history"]
    if len(history) > max_history:
        chat_memories[user_id] = history[-max_history:]


def reset_history(user_id):
    """Clear one user's chat history."""
    chat_memories.pop(user_id, None)
    thinking_mode_users.discard(user_id)


def build_prompt_with_trim(system_context, history, user_input, max_chars):
    """
    Build prompt, progressively trimming history until it fits within max_chars.
    Returns (prompt, history_used).
    """
    full_history = list(history)
    window = len(full_history)

    while window >= 0:
        candidate_history = full_history[-window:] if window > 0 else []
        prompt = build_prompt(system_context, candidate_history, user_input)

        if len(prompt) <= max_chars:
            if window < len(full_history):
                print(f"[WARN] History dipotong dari {len(full_history)} → {window} pesan")
            return prompt, window

        window -= 2

    prompt = build_prompt(system_context, [], user_input)
    print(f"[WARN] Semua history dihapus dari prompt.")
    return prompt, 0


def get_inference_config(thinking_mode: bool):
    """
    Return inference config berdasarkan mode.
    thinking_mode=True → deep analysis, lebih tokens & kreatif
    thinking_mode=False → balanced, cepat & sedang
    """
    if thinking_mode:
        return LLM_INFERENCE_THINKING
    else:
        return LLM_INFERENCE_NORMAL


def generate_chat_reply(
    llm_manager,
    user_input,
    role="user",
    username="Guest",
    user_id="Guest",
    thinking_mode_explicit=False,
):
    """
    Generate a reply with adaptive inference profile.

    Args:
        thinking_mode_explicit: User explicitly toggled thinking mode
    """
    history = get_history(user_id)
    system_context = get_system_context(role, username)

    # Detect thinking mode dari explicit flag atau keyword
    is_thinking = detect_thinking_mode(user_input, explicit_mode=thinking_mode_explicit)

    if is_thinking:
        thinking_mode_users.add(user_id)
        print(f"[INFO] 🧠 Thinking Mode ON for user {user_id}")
    else:
        thinking_mode_users.discard(user_id)
        print(f"[INFO] 💬 Normal Mode for user {user_id}")

    # Get inference config sesuai mode
    inference_cfg = get_inference_config(is_thinking)

    max_window = CHAT_CONFIG["max_history_window"]
    max_chars = CHAT_CONFIG["max_prompt_chars"]

    windowed_history = history[-max_window:] if history else []
    prompt, used_window = build_prompt_with_trim(system_context, windowed_history, user_input, max_chars)

    print(
        f"[DEBUG] User {user_id} | Mode: {'🧠 THINKING' if is_thinking else '💬 NORMAL'} | "
        f"Input: {user_input[:50]}... | Prompt: {len(prompt)} chars | History: {used_window} msgs"
    )

    # Inference dengan config yang dipilih
    try:
        # Override max_tokens dan temperature sesuai mode
        inference_params = dict(inference_cfg)
        output = llm_manager.infer(prompt, **inference_params)
    except RuntimeError as e:
        err_msg = str(e).lower()
        if any(k in err_msg for k in ["context", "kv", "exceed", "token"]):
            print(f"[WARN] Context overflow di mode {'THINKING' if is_thinking else 'NORMAL'}, retry tanpa history...")
            fallback_prompt = build_prompt(system_context, [], user_input)
            try:
                output = llm_manager.infer(fallback_prompt, **get_inference_config(is_thinking))
                print("[INFO] Retry tanpa history berhasil.")
            except RuntimeError as e2:
                raise RuntimeError(f"LLM gagal bahkan tanpa history: {e2}") from e2
        else:
            raise

    response_text = sanitize_response(output["choices"][0]["text"].strip())

    if looks_like_user_echo(response_text, user_input):
        response_text = "Saya paham. Bisa jelaskan lebih spesifik apa yang ingin Anda lakukan atau tanyakan?"

    if not response_text or len(response_text) < CHAT_CONFIG["min_response_length"]:
        response_text = "Maaf, saya tidak dapat memproses permintaan itu. Coba lagi dengan pertanyaan yang berbeda."

    print(f"[DEBUG] Response: {response_text[:100]}... ({len(response_text)} chars)")
    append_history(user_id, user_input, response_text)
    return response_text, is_thinking  # Return flag thinking mode


def get_thinking_mode_status(user_id) -> bool:
    """Check apakah user sedang dalam thinking mode."""
    return user_id in thinking_mode_users


def toggle_thinking_mode(user_id, enable: bool = None):
    """
    Toggle thinking mode untuk user.
    enable=True: aktifkan
    enable=False: matikan
    enable=None: toggle (kebalikan status sekarang)
    """
    current = user_id in thinking_mode_users

    if enable is None:
        enable = not current

    if enable:
        thinking_mode_users.add(user_id)
        print(f"[INFO] Thinking Mode diaktifkan untuk user {user_id}")
    else:
        thinking_mode_users.discard(user_id)
        print(f"[INFO] Thinking Mode dimatikan untuk user {user_id}")

    return enable