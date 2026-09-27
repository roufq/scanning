"""Cross-screenshot analysis: pixel change between consecutive shots and hash-based matching."""

from dataclasses import dataclass
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps

DIFF_WIDTH = 640


@dataclass
class Change:
    id: int
    previous_id: int
    change_ratio: float
    bbox: list[float] | None


def load_grayscale(path: Path) -> np.ndarray:
    """Load an image as a grayscale array downscaled to DIFF_WIDTH."""
    with Image.open(path) as image:
        image = ImageOps.exif_transpose(image).convert("L")
        size = (DIFF_WIDTH, max(1, round(image.height * DIFF_WIDTH / image.width)))

        return np.asarray(image.resize(size, Image.Resampling.BILINEAR), dtype=np.int16)


def resize_to(array: np.ndarray, shape: tuple[int, ...]) -> np.ndarray:
    image = Image.fromarray(array.astype(np.uint8)).resize((shape[1], shape[0]), Image.Resampling.BILINEAR)

    return np.asarray(image, dtype=np.int16)


def change_between(previous: np.ndarray, current: np.ndarray, pixel_threshold: int) -> tuple[float, list[float] | None]:
    """Fraction of pixels that changed, plus the normalized bounding box [x0, y0, x1, y1] of the change."""
    mask = np.abs(current - previous) > pixel_threshold
    ratio = float(mask.mean())

    if not mask.any():
        return ratio, None

    rows = np.flatnonzero(mask.any(axis=1))
    cols = np.flatnonzero(mask.any(axis=0))
    height, width = mask.shape

    bbox = [
        round(cols[0] / width, 4),
        round(rows[0] / height, 4),
        round((cols[-1] + 1) / width, 4),
        round((rows[-1] + 1) / height, 4),
    ]

    return ratio, bbox


def consecutive_changes(items: list[tuple[int, Path]], pixel_threshold: int) -> list[Change]:
    """Compare each screenshot with the one before it (items must already be in time order)."""
    changes: list[Change] = []
    previous_id: int | None = None
    previous: np.ndarray | None = None

    for screenshot_id, path in items:
        try:
            current = load_grayscale(path)
        except OSError:
            previous, previous_id = None, None
            continue

        if previous is not None and previous_id is not None:
            comparable = current if current.shape == previous.shape else resize_to(current, previous.shape)
            ratio, bbox = change_between(previous, comparable, pixel_threshold)
            changes.append(Change(screenshot_id, previous_id, round(ratio, 6), bbox))

        previous = current
        previous_id = screenshot_id

    return changes


def hashes_to_bits(hashes: list[str]) -> np.ndarray:
    """Turn 64-bit hex perceptual hashes into an (n, 64) bit matrix."""
    if not hashes:
        return np.zeros((0, 64), dtype=np.uint8)

    values = np.array([int(value, 16) for value in hashes], dtype=np.uint64)

    return np.unpackbits(values.byteswap().view(np.uint8).reshape(-1, 8), axis=1)


def hamming_matrix(left: np.ndarray, right: np.ndarray) -> np.ndarray:
    """Pairwise Hamming distances between two bit matrices."""
    left = left.astype(np.int16)
    right = right.astype(np.int16)

    return (left @ (1 - right).T + (1 - left) @ right.T).astype(np.int16)


def exact_duplicates(items: list[tuple[int, str]]) -> list[dict]:
    """Later screenshots whose file hash equals an earlier one in the same set."""
    first_seen: dict[str, int] = {}
    duplicates: list[dict] = []

    for screenshot_id, md5 in items:
        if md5 in first_seen:
            duplicates.append({"id": screenshot_id, "original_id": first_seen[md5]})
        else:
            first_seen[md5] = screenshot_id

    return duplicates


def recycled_matches(
    items: list[tuple[int, str, str]],
    references: list[tuple[int, str, str]],
    max_distance: int,
    chunk_size: int = 2000,
) -> list[dict]:
    """Screenshots that match a screenshot from an earlier upload (same file or near-identical image)."""
    if not items or not references:
        return []

    # Point at the earliest reference with that file hash (references come in id order).
    by_md5: dict[str, int] = {}

    for ref_id, md5, _ in references:
        by_md5.setdefault(md5, ref_id)

    matches: dict[int, dict] = {}

    for screenshot_id, md5, _ in items:
        if md5 in by_md5:
            matches[screenshot_id] = {"id": screenshot_id, "reference_id": by_md5[md5], "distance": 0, "exact": True}

    item_bits = hashes_to_bits([phash for _, _, phash in items])

    for start in range(0, len(references), chunk_size):
        chunk = references[start:start + chunk_size]
        distances = hamming_matrix(item_bits, hashes_to_bits([phash for _, _, phash in chunk]))

        for row, (screenshot_id, _, _) in enumerate(items):
            column = int(distances[row].argmin())
            distance = int(distances[row, column])
            current = matches.get(screenshot_id)

            if distance <= max_distance and (current is None or (not current["exact"] and distance < current["distance"])):
                matches[screenshot_id] = {
                    "id": screenshot_id,
                    "reference_id": chunk[column][0],
                    "distance": distance,
                    "exact": False,
                }

    return list(matches.values())


def similarity_groups(items: list[tuple[int, str]], max_distance: int) -> list[list[int]]:
    """Group screenshots whose perceptual hashes are within max_distance (connected components)."""
    if len(items) < 2:
        return []

    ids = [screenshot_id for screenshot_id, _ in items]
    distances = hamming_matrix(*(2 * [hashes_to_bits([phash for _, phash in items])]))
    parent = list(range(len(ids)))

    def find(index: int) -> int:
        while parent[index] != index:
            parent[index] = parent[parent[index]]
            index = parent[index]

        return index

    left, right = np.nonzero(np.triu(distances <= max_distance, k=1))

    for a, b in zip(left.tolist(), right.tolist()):
        root_a, root_b = find(a), find(b)

        if root_a != root_b:
            parent[max(root_a, root_b)] = min(root_a, root_b)

    groups: dict[int, list[int]] = {}

    for index, screenshot_id in enumerate(ids):
        groups.setdefault(find(index), []).append(screenshot_id)

    return [members for members in groups.values() if len(members) > 1]
