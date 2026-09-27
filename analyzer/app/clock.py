"""Read the clock shown on screen (taskbar / menu bar) with Tesseract OCR."""

import os
import re
import shutil
from functools import lru_cache
from pathlib import Path

from PIL import Image, ImageOps

# Normalized [x0, y0, x1, y1] areas where an OS usually draws its clock.
CLOCK_REGIONS = [
    (0.70, 0.90, 1.0, 1.0),  # Windows taskbar, bottom right
    (0.60, 0.0, 1.0, 0.06),  # macOS / Linux menu bar, top right
]

TIME_PATTERN = re.compile(
    r"(?<![\d:.])(?P<hour>[01]?\d|2[0-3])\s?[:.]\s?(?P<minute>[0-5]\d)(?:[:.][0-5]\d)?(?!\d)\s*(?P<meridiem>[AaPp])?\.?\s?[Mm]?"
)


@lru_cache(maxsize=1)
def tesseract_command() -> str | None:
    candidates = [
        os.environ.get("TESSERACT_CMD"),
        shutil.which("tesseract"),
        r"C:\Program Files\Tesseract-OCR\tesseract.exe",
        r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
    ]

    return next((candidate for candidate in candidates if candidate and Path(candidate).is_file()), None)


def parse_time(text: str) -> dict | None:
    """Pick the first HH:MM out of OCR text and normalize it to 24-hour time."""
    match = TIME_PATTERN.search(text)

    if not match:
        return None

    hour, minute = int(match["hour"]), int(match["minute"])
    meridiem = (match["meridiem"] or "").upper()

    if meridiem and hour > 12:
        return None

    if meridiem == "P" and hour != 12:
        hour += 12
    elif meridiem == "A" and hour == 12:
        hour = 0

    return {
        "time": f"{hour:02d}:{minute:02d}",
        # Without AM/PM a 12-hour clock cannot be told apart from a 24-hour one.
        "ambiguous": not meridiem and hour <= 12,
        "text": match.group(0).strip(),
    }


def prepare(region: Image.Image) -> Image.Image:
    """Upscale and binarize so small taskbar text is readable for Tesseract."""
    gray = ImageOps.grayscale(region)
    gray = gray.resize((gray.width * 3, gray.height * 3), Image.Resampling.LANCZOS)

    # Tesseract expects dark text on a light background.
    if sum(gray.getdata()) / (gray.width * gray.height) < 128:
        gray = ImageOps.invert(gray)

    return ImageOps.autocontrast(gray).point(lambda value: 255 if value > 140 else 0)


def read_clock(image: Image.Image) -> dict | None:
    command = tesseract_command()

    if command is None:
        return None

    import pytesseract

    pytesseract.pytesseract.tesseract_cmd = command
    width, height = image.size

    for x0, y0, x1, y1 in CLOCK_REGIONS:
        region = image.crop((int(x0 * width), int(y0 * height), int(x1 * width), int(y1 * height)))
        text = pytesseract.image_to_string(
            prepare(region),
            config="--psm 6 -c tessedit_char_whitelist=0123456789:.APMapm/- ",
        )
        found = parse_time(text)

        if found is not None:
            return found

    return None
