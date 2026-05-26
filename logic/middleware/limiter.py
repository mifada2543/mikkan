"""
Rate limiter middleware setup
"""
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from flask import request

try:
    from ..config import RATE_LIMITS
except ImportError:
    from config import RATE_LIMITS

def create_limiter(app=None):
    """Create and configure rate limiter."""
    limiter = Limiter(
        key_func=get_remote_address,
        app=app,
        storage_uri="memory://"
    )
    return limiter


def get_role_limit():
    """Determine rate limit based on user role."""
    if request.method == "OPTIONS":
        return None

    data = request.get_json(silent=True) or {}
    role = data.get("role", "user")
    return RATE_LIMITS.get(role, RATE_LIMITS["user"])
