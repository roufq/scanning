"""Guards file access so the analyzer only touches files under the configured storage root."""

import os
from pathlib import Path


class PathNotAllowedError(ValueError):
    pass


def storage_root() -> Path | None:
    root = os.environ.get("ANALYZER_STORAGE_ROOT")

    return Path(root).resolve() if root else None


def resolve_allowed(path: str) -> Path:
    """Resolve a path and make sure it lives inside ANALYZER_STORAGE_ROOT (when configured)."""
    resolved = Path(path).resolve()
    root = storage_root()

    if root is not None and not resolved.is_relative_to(root):
        raise PathNotAllowedError(f"Path is outside the storage root: {path}")

    return resolved
