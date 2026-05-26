"""
CORS middleware setup.
"""
from flask_cors import CORS

try:
    from ..config import CORS_CONFIG
except ImportError:
    from config import CORS_CONFIG


def setup_cors(app):
    """Initialize CORS for Flask app."""
    CORS(app, **CORS_CONFIG)
    return app
