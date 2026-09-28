"""Per-screenshot feature extraction: hashes, size, on-screen clock, title-bar text and thumbnail."""

import hashlib
from dataclasses import asdict, dataclass, field
from pathlib import Path

import imagehash
from PIL import Image, ImageOps

from app.ocr import read_clock, read_title

THUMBNAIL_WIDTH = 480
CLASSIFIER_SIZE = 448


@dataclass
class Features:
    md5: str
    phash: str
    width: int
    height: int
    file_size: int
    clock: dict | None
    title: str | None
    # Downscaled copy kept in memory for the classifier; never serialized.
    preview: Image.Image | None = field(default=None, repr=False)

    def to_dict(self) -> dict:
        data = asdict(self)
        data.pop("preview")

        return data


def md5_of(path: Path) -> str:
    digest = hashlib.md5()

    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)

    return digest.hexdigest()


def extract(
    path: Path,
    thumbnail_path: Path | None = None,
    with_clock: bool = True,
    with_title: bool = False,
    with_preview: bool = False,
) -> Features:
    with Image.open(path) as image:
        image = ImageOps.exif_transpose(image).convert("RGB")
        width, height = image.size
        phash = str(imagehash.phash(image))
        clock = read_clock(image) if with_clock else None
        title = read_title(image) if with_title else None
        preview = None

        if thumbnail_path is not None:
            thumbnail_path.parent.mkdir(parents=True, exist_ok=True)
            thumbnail = image.copy()
            thumbnail.thumbnail((THUMBNAIL_WIDTH, THUMBNAIL_WIDTH * 4))
            thumbnail.save(thumbnail_path, "JPEG", quality=80, optimize=True)

        if with_preview:
            preview = image.copy()
            preview.thumbnail((CLASSIFIER_SIZE, CLASSIFIER_SIZE))

    return Features(
        md5=md5_of(path),
        phash=phash,
        width=width,
        height=height,
        file_size=path.stat().st_size,
        clock=clock,
        title=title,
        preview=preview,
    )
