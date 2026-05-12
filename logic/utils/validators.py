"""
Input validation helpers for API routes.
"""

try:
    from .cache import generate_cache_key
except ImportError:
    from cache import generate_cache_key


def parse_json_payload(request):
    """Safely parse a JSON request body."""
    return request.get_json(silent=True) or {}


def normalize_user_id(value, fallback="Guest"):
    """Normalize user identifiers into a non-empty string."""
    normalized = str(value or fallback).strip()
    return normalized or fallback


def validate_required_text(value, field_name, empty_message):
    """Validate and normalize a required text field."""
    text = str(value or "").strip()
    if not text:
        raise ValueError(empty_message)
    return text


def truncate_text(text, limit):
    """Truncate text to a configured character limit."""
    if len(text) <= limit:
        return text, False
    return text[:limit], True


def build_tts_cache_key(text):
    """Alias kept for semantic readability in TTS routes."""
    return generate_cache_key(text)
