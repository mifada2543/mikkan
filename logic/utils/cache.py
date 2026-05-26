"""
Cache utilities for audio and other resources.
"""
import hashlib


class SimpleCache:
    """Simple in-memory cache with a maximum item count."""

    def __init__(self, max_size=100):
        self.cache = {}
        self.max_size = max_size

    def get(self, key):
        return self.cache.get(key)

    def set(self, key, value):
        self.cache[key] = value
        if len(self.cache) > self.max_size:
            oldest_key = next(iter(self.cache))
            self.cache.pop(oldest_key)

    def has(self, key):
        return key in self.cache

    def clear(self):
        self.cache.clear()

    def size(self):
        return len(self.cache)


def generate_cache_key(text):
    """Generate a stable cache key from text."""
    return hashlib.md5(text.encode()).hexdigest()
