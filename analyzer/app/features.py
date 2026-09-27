"""Per-screenshot feature extraction: exact hash, perceptual hash, size, on-screen clock and thumbnail."""

import hashlib
from dataclasses import dataclass
from pathlib import Path

import imagehash
from PIL import Image, ImageOps

from app.clock import read_clock

THUMBNAIL_WIDTH = 480


@dataclass
class Features:
    md5: str
    phash: str
    width: int
    height: int
    file_size: int
    clock: dict | None


def md5_of(path: Path) -> str:
    digest = hashlib.md5()

    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)

    return digest.hexdigest()


def extract(path: Path, thumbnail_path: Path | None = None, with_clock: bool = True) -> Features:
    with Image.open(path) as image:
        image = ImageOps.exif_transpose(image).convert("RGB")
        width, height = image.size
        phash = str(imagehash.phash(image))
        clock = read_clock(image) if with_clock else None

        if thumbnail_path is not None:
            thumbnail_path.parent.mkdir(parents=True, exist_ok=True)
            thumbnail = image.copy()
            thumbnail.thumbnail((THUMBNAIL_WIDTH, THUMBNAIL_WIDTH * 4))
            thumbnail.save(thumbnail_path, "JPEG", quality=80, optimize=True)

    return Features(
        md5=md5_of(path),
        phash=phash,
        width=width,
        height=height,
        file_size=path.stat().st_size,
        clock=clock,
    )
