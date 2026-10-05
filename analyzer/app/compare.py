"""Cross-screenshot analysis: pixel change between consecutive shots and hash-based matching."""

from dataclasses import dataclass
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps

DIFF_WIDTH = 640

# Grid cell size (in DIFF_WIDTH pixels) used to group changed pixels into regions.
REGION_BLOCK = 8

# Change regions are only grouped when less than this share of the screen changed.
REGION_MAX_RATIO = 0.05

# Width of the cached copies used to confirm near-duplicates (small keeps 1000+ images in memory).
CONFIRM_WIDTH = 320


@dataclass
class Change:
    id: int
    previous_id: int
    change_ratio: float
    bbox: list[float] | None
    regions: list[dict]


def load_grayscale(path: Path) -> np.ndarray:
    """Load an image as a grayscale array downscaled to DIFF_WIDTH."""
    with Image.open(path) as image:
        image = ImageOps.exif_transpose(image).convert("L")
        size = (DIFF_WIDTH, max(1, round(image.height * DIFF_WIDTH / image.width)))

        return np.asarray(image.resize(size, Image.Resampling.BILINEAR), dtype=np.int16)


def resize_to(array: np.ndarray, shape: tuple[int, ...]) -> np.ndarray:
    image = Image.fromarray(array.astype(np.uint8)).resize((shape[1], shape[0]), Image.Resampling.BILINEAR)

    return np.asarray(image, dtype=np.int16)


def normalized_bbox(rows: np.ndarray, cols: np.ndarray, height: int, width: int) -> list[float]:
    return [
        round(cols.min() / width, 4),
        round(rows.min() / height, 4),
        round((cols.max() + 1) / width, 4),
        round((rows.max() + 1) / height, 4),
    ]


def ignore_mask(shape: tuple[int, ...], ignore_regions: list[list[float]]) -> np.ndarray:
    """True for pixels inside regions (e.g. the taskbar clock) whose changes should not count."""
    height, width = shape
    mask = np.zeros(shape, dtype=bool)

    for x0, y0, x1, y1 in ignore_regions:
        mask[int(y0 * height):int(np.ceil(y1 * height)), int(x0 * width):int(np.ceil(x1 * width))] = True

    return mask


def change_regions(mask: np.ndarray, max_regions: int = 10) -> list[dict]:
    """Group changed pixels into connected regions on a coarse grid (8-connected), largest first."""
    height, width = mask.shape
    rows_count, cols_count = -(-height // REGION_BLOCK), -(-width // REGION_BLOCK)
    padded = np.zeros((rows_count * REGION_BLOCK, cols_count * REGION_BLOCK), dtype=bool)
    padded[:height, :width] = mask
    counts = padded.reshape(rows_count, REGION_BLOCK, cols_count, REGION_BLOCK).sum(axis=(1, 3))

    seen = np.zeros_like(counts, dtype=bool)
    regions = []

    for start in zip(*np.nonzero(counts)):
        if seen[start]:
            continue

        stack, cells = [start], []
        seen[start] = True

        while stack:
            row, col = stack.pop()
            cells.append((row, col))

            for d_row in (-1, 0, 1):
                for d_col in (-1, 0, 1):
                    neighbour = (row + d_row, col + d_col)

                    if (0 <= neighbour[0] < rows_count and 0 <= neighbour[1] < cols_count
                            and counts[neighbour] and not seen[neighbour]):
                        seen[neighbour] = True
                        stack.append(neighbour)

        # Only look at the region's own cells instead of building a full-size mask per region.
        pixel_rows, pixel_cols = [], []

        for row, col in cells:
            top, left = row * REGION_BLOCK, col * REGION_BLOCK
            ys, xs = np.nonzero(mask[top:top + REGION_BLOCK, left:left + REGION_BLOCK])
            pixel_rows.append(ys + top)
            pixel_cols.append(xs + left)

        rows_found, cols_found = np.concatenate(pixel_rows), np.concatenate(pixel_cols)

        regions.append({
            "bbox": normalized_bbox(rows_found, cols_found, height, width),
            "ratio": round(len(rows_found) / (height * width), 6),
        })

    return sorted(regions, key=lambda region: region["ratio"], reverse=True)[:max_regions]


def change_between(
    previous: np.ndarray,
    current: np.ndarray,
    pixel_threshold: int,
    ignore_regions: list[list[float]] | None = None,
) -> tuple[float, list[float] | None, list[dict]]:
    """Fraction of pixels that changed, the normalized bounding box [x0, y0, x1, y1] and the separate change regions."""
    mask = np.abs(current - previous) > pixel_threshold

    if ignore_regions:
        mask &= ~ignore_mask(mask.shape, ignore_regions)

    ratio = float(mask.mean())

    if not mask.any():
        return ratio, None, []

    rows, cols = np.nonzero(mask)
    # Regions only matter for small changes (cursor-only / mouse jiggler); skip the work otherwise.
    regions = change_regions(mask) if ratio < REGION_MAX_RATIO else []

    return ratio, normalized_bbox(rows, cols, *mask.shape), regions


def consecutive_changes(
    items: list[tuple[int, Path]],
    pixel_threshold: int,
    ignore_regions: list[list[float]] | None = None,
) -> list[Change]:
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
            ratio, bbox, regions = change_between(previous, comparable, pixel_threshold, ignore_regions)
            changes.append(Change(screenshot_id, previous_id, round(ratio, 6), bbox, regions))

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


class SmallImages:
    """Lazily loaded, cached small grayscale copies used to confirm near-duplicates pixel by pixel."""

    def __init__(self) -> None:
        self.cache: dict[Path, np.ndarray | None] = {}

    def get(self, path: Path) -> np.ndarray | None:
        if path not in self.cache:
            try:
                with Image.open(path) as image:
                    image = ImageOps.exif_transpose(image).convert("L")
                    size = (CONFIRM_WIDTH, max(1, round(image.height * CONFIRM_WIDTH / image.width)))
                    self.cache[path] = np.asarray(image.resize(size, Image.Resampling.BILINEAR), dtype=np.uint8)
            except OSError:
                self.cache[path] = None

        return self.cache[path]


def pixel_change(
    first: np.ndarray,
    second: np.ndarray,
    pixel_threshold: int,
    ignore_regions: list[list[float]] | None,
) -> float:
    """Fraction of pixels that differ between two small grayscale images, outside the ignored regions."""
    if first.shape != second.shape:
        second = np.asarray(Image.fromarray(second).resize((first.shape[1], first.shape[0]), Image.Resampling.BILINEAR))

    mask = np.abs(first.astype(np.int16) - second.astype(np.int16)) > pixel_threshold

    if ignore_regions:
        mask &= ~ignore_mask(mask.shape, ignore_regions)

    return float(mask.mean())


def near_duplicates(
    items: list[tuple[int, Path, str, str]],
    change_ratios: dict[int, float],
    candidate_distance: int,
    max_change: float,
    pixel_threshold: int,
    ignore_regions: list[list[float]] | None = None,
    max_candidates: int = 20,
) -> list[dict]:
    """Later screenshots that are (almost) pixel-identical to an earlier, non-adjacent one of the same set.

    Catches copies that were re-saved, slightly edited or had their clock changed, and screens
    that are rotated (A-B-A-B). An unchanged screen over consecutive shots is an idle screen and
    is left to the idle check: a match only counts when the screen changed somewhere in between.
    Items must be in time order: (id, path, md5, phash); change_ratios maps an id to its pixel
    change against the previous screenshot (see consecutive_changes).
    """
    if len(items) < 3:
        return []

    distances = hamming_matrix(*(2 * [hashes_to_bits([phash for *_, phash in items])]))
    changed = np.array([change_ratios.get(item[0], 0.0) > max_change for item in items])
    # changed_before[k] = number of real screen changes among items[1..k].
    changed_before = np.cumsum(changed)
    images = SmallImages()
    found: list[dict] = []

    for later in range(2, len(items)):
        later_id, later_path, later_md5, _ = items[later]
        candidates = [
            earlier for earlier in range(later - 1)
            if distances[later, earlier] <= candidate_distance and items[earlier][2] != later_md5
            # The screen must have changed in between, otherwise this is just an idle stretch.
            and changed_before[later] - changed_before[earlier] > 0
        ]
        candidates.sort(key=lambda earlier: (distances[later, earlier], earlier))

        for earlier in candidates[:max_candidates]:
            first, second = images.get(items[earlier][1]), images.get(later_path)

            if first is None or second is None:
                continue

            ratio = pixel_change(first, second, pixel_threshold, ignore_regions)

            if ratio <= max_change:
                found.append({
                    "id": later_id,
                    "original_id": items[earlier][0],
                    "distance": int(distances[later, earlier]),
                    "change_ratio": round(ratio, 6),
                })
                break

    return found


def recycled_matches(
    items: list[tuple[int, Path, str, str]],
    references: list[tuple[int, Path | None, str, str]],
    candidate_distance: int,
    max_change: float,
    pixel_threshold: int,
    ignore_regions: list[list[float]] | None = None,
    max_candidates: int = 10,
    chunk_size: int = 2000,
) -> list[dict]:
    """Screenshots that reappear from earlier uploads: the same file, or an image that is almost
    pixel-identical after re-saving, resizing or editing the clock. (id, path, md5, phash) tuples."""
    if not items or not references:
        return []

    # Point at the earliest reference with that file hash (references come in id order).
    by_md5: dict[str, int] = {}

    for ref_id, _, md5, _ in references:
        by_md5.setdefault(md5, ref_id)

    matches: dict[int, dict] = {}

    for screenshot_id, _, md5, _ in items:
        if md5 in by_md5:
            matches[screenshot_id] = {"id": screenshot_id, "reference_id": by_md5[md5], "distance": 0, "exact": True, "change_ratio": 0.0}

    pending = [index for index, item in enumerate(items) if item[0] not in matches]

    if not pending:
        return list(matches.values())

    item_bits = hashes_to_bits([items[index][3] for index in pending])
    candidates: dict[int, list[tuple[int, int]]] = {index: [] for index in pending}

    for start in range(0, len(references), chunk_size):
        chunk = references[start:start + chunk_size]
        distances = hamming_matrix(item_bits, hashes_to_bits([phash for *_, phash in chunk]))

        for row, column in zip(*np.nonzero(distances <= candidate_distance)):
            candidates[pending[row]].append((int(distances[row, column]), start + int(column)))

    images = SmallImages()

    for index, found in candidates.items():
        screenshot_id, path, _, _ = items[index]

        for distance, reference_index in sorted(found)[:max_candidates]:
            reference_id, reference_path, _, _ = references[reference_index]
            first = images.get(reference_path) if reference_path else None
            second = images.get(path)

            if first is None or second is None:
                continue

            ratio = pixel_change(first, second, pixel_threshold, ignore_regions)

            if ratio <= max_change:
                matches[screenshot_id] = {
                    "id": screenshot_id,
                    "reference_id": reference_id,
                    "distance": distance,
                    "exact": False,
                    "change_ratio": round(ratio, 6),
                }
                break

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
