from pathlib import Path

import pytest
from fastapi.testclient import TestClient
from PIL import Image, ImageDraw, ImageFont

from app.ocr import parse_time, tesseract_command
from app import classifier
from app.main import app

client = TestClient(app)


def screen(path: Path, text: str = "", cursor: tuple[int, int] | None = None, color: str = "white") -> Path:
    """Draw a fake 1280x720 'screenshot' with a window, some text and an optional cursor."""
    image = Image.new("RGB", (1280, 720), color)
    draw = ImageDraw.Draw(image)
    draw.rectangle((100, 80, 1180, 640), outline="black", width=4)

    for line, content in enumerate(text.splitlines()):
        draw.text((130, 110 + line * 30), content, fill="black")

    if cursor:
        draw.rectangle((cursor[0], cursor[1], cursor[0] + 10, cursor[1] + 16), fill="black")

    image.save(path)

    return path


@pytest.fixture(autouse=True)
def storage_root(tmp_path, monkeypatch):
    monkeypatch.setenv("ANALYZER_STORAGE_ROOT", str(tmp_path))


def test_extract_returns_hashes_and_writes_thumbnail(tmp_path):
    image = screen(tmp_path / "a.png", "hello")
    thumbnail = tmp_path / "thumbs" / "a.jpg"

    response = client.post("/extract", json={"items": [{"id": 1, "path": str(image), "thumbnail_path": str(thumbnail)}]})

    result = response.json()["results"][0]
    assert response.status_code == 200
    assert result["error"] is None
    assert len(result["md5"]) == 32
    assert len(result["phash"]) == 16
    assert (result["width"], result["height"]) == (1280, 720)
    assert thumbnail.exists()


def test_extract_reports_unreadable_files_without_failing_the_request(tmp_path):
    broken = tmp_path / "broken.png"
    broken.write_bytes(b"not an image")

    response = client.post("/extract", json={"items": [{"id": 7, "path": str(broken)}]})

    assert response.status_code == 200
    assert response.json()["results"][0]["id"] == 7
    assert response.json()["results"][0]["error"]


def test_extract_rejects_paths_outside_storage_root(tmp_path, monkeypatch):
    monkeypatch.setenv("ANALYZER_STORAGE_ROOT", str(tmp_path / "private"))
    image = screen(tmp_path / "outside.png")

    response = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}]})

    assert response.status_code == 422


def extract_all(paths: list[Path]) -> list[dict]:
    items = [{"id": index + 1, "path": str(path)} for index, path in enumerate(paths)]
    results = client.post("/extract", json={"items": items}).json()["results"]

    return [{"id": r["id"], "path": str(paths[r["id"] - 1]), "md5": r["md5"], "phash": r["phash"]} for r in results]


def test_compare_measures_change_between_consecutive_screenshots(tmp_path):
    screenshots = extract_all([
        screen(tmp_path / "1.png", "line one"),
        screen(tmp_path / "2.png", "line one", cursor=(600, 300)),
        screen(tmp_path / "3.png", "\n".join(f"new content {i} " * 8 for i in range(15))),
    ])

    response = client.post("/compare", json={"screenshots": screenshots})
    changes = {change["id"]: change for change in response.json()["changes"]}

    assert set(changes) == {2, 3}
    assert changes[2]["previous_id"] == 1
    assert 0 < changes[2]["change_ratio"] < 0.003
    assert changes[2]["bbox"][0] == pytest.approx(600 / 1280, abs=0.01)
    assert changes[3]["change_ratio"] > 0.02


def test_compare_finds_exact_duplicates_and_recycled_screenshots(tmp_path):
    first = screen(tmp_path / "1.png", "report")
    copy = tmp_path / "copy.png"
    copy.write_bytes(first.read_bytes())
    screenshots = extract_all([first, screen(tmp_path / "2.png", "other", color="lightblue"), copy])
    old = screenshots[1]

    response = client.post("/compare", json={
        "screenshots": [screenshots[0], screenshots[2]],
        "references": [{"id": 99, "md5": old["md5"], "phash": old["phash"]}, {"id": 98, "md5": screenshots[0]["md5"], "phash": "0" * 16}],
    }).json()

    assert response["duplicates"] == [{"id": 3, "original_id": 1}]
    assert {match["id"] for match in response["recycled"]} == {1, 3}
    assert all(match["reference_id"] == 98 and match["exact"] for match in response["recycled"])


def test_compare_groups_visually_similar_screenshots(tmp_path):
    screenshots = extract_all([
        screen(tmp_path / "1.png", "editor"),
        screen(tmp_path / "2.png", "editor!"),
        screen(tmp_path / "3.png", "x", color="black"),
    ])

    groups = client.post("/compare", json={"screenshots": screenshots}).json()["groups"]

    assert groups == [[1, 2]]


def desktop(path: Path, clock: str, cursor: tuple[int, int] | None = None) -> Path:
    """A 1366x768 desktop with a dark taskbar that shows the clock in the bottom-right corner."""
    image = Image.new("RGB", (1366, 768), "#2b5797")
    draw = ImageDraw.Draw(image)
    draw.rectangle((0, 728, 1366, 768), fill="#101010")
    draw.text((1280, 738), clock, fill="white", font=ImageFont.load_default(size=16))
    draw.rectangle((200, 100, 1000, 600), fill="white")

    if cursor:
        draw.polygon([cursor, (cursor[0], cursor[1] + 18), (cursor[0] + 12, cursor[1] + 13)], fill="black")

    image.save(path)

    return path


@pytest.mark.skipif(tesseract_command() is None, reason="Tesseract is not installed")
def test_extract_reads_the_taskbar_clock(tmp_path):
    image = desktop(tmp_path / "desk.png", "14:05")

    result = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}]}).json()["results"][0]

    assert result["clock"]["time"] == "14:05"
    assert result["clock"]["ambiguous"] is False


def test_extract_can_skip_the_clock(tmp_path):
    image = desktop(tmp_path / "desk.png", "14:05")

    result = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}], "read_clock": False}).json()

    assert result["results"][0]["clock"] is None


@pytest.mark.parametrize(("text", "expected"), [
    ("14:05", {"time": "14:05", "ambiguous": False}),
    ("8.15", {"time": "08:15", "ambiguous": True}),
    ("8:15 PM", {"time": "20:15", "ambiguous": False}),
    ("12:05 AM", {"time": "00:05", "ambiguous": False}),
    ("27/09/2026 09:41", {"time": "09:41", "ambiguous": True}),
    ("25:10", None),
    ("", None),
])
def test_parse_time_normalizes_clock_text(text, expected):
    parsed = parse_time(text)

    assert (parsed and {"time": parsed["time"], "ambiguous": parsed["ambiguous"]}) == expected


def test_compare_ignores_changes_inside_ignore_regions(tmp_path):
    screenshots = extract_all([desktop(tmp_path / "1.png", "09:00"), desktop(tmp_path / "2.png", "09:05")])

    plain = client.post("/compare", json={"screenshots": screenshots}).json()["changes"][0]
    ignored = client.post("/compare", json={
        "screenshots": screenshots,
        "settings": {"ignore_regions": [[0.7, 0.93, 1.0, 1.0]]},
    }).json()["changes"][0]

    assert plain["change_ratio"] > 0
    assert ignored["change_ratio"] == 0
    assert ignored["regions"] == []


def test_compare_reports_separate_change_regions_for_a_moved_cursor(tmp_path):
    screenshots = extract_all([
        desktop(tmp_path / "1.png", "09:00", cursor=(300, 200)),
        desktop(tmp_path / "2.png", "09:00", cursor=(700, 450)),
    ])

    change = client.post("/compare", json={"screenshots": screenshots}).json()["changes"][0]

    assert len(change["regions"]) == 2
    for region in change["regions"]:
        x0, y0, x1, y1 = region["bbox"]
        assert x1 - x0 < 0.03 and y1 - y0 < 0.05


def browser(path: Path, tab: str) -> Path:
    """A browser window with the page title in the tab strip."""
    image = Image.new("RGB", (1366, 768), "white")
    draw = ImageDraw.Draw(image)
    draw.rectangle((0, 0, 1366, 40), fill="#dee1e6")
    draw.rectangle((10, 6, 360, 40), fill="white")
    draw.text((24, 12), tab, fill="black", font=ImageFont.load_default(size=18))
    image.save(path)

    return path


def spreadsheet(path: Path) -> Path:
    image = Image.new("RGB", (1366, 768), "white")
    draw = ImageDraw.Draw(image)

    for row in range(28):
        for column in range(10):
            draw.rectangle((40 + column * 128, 60 + row * 24, 168 + column * 128, 84 + row * 24), outline="#c0c0c0")
            draw.text((46 + column * 128, 64 + row * 24), str((row + 3) * (column + 7) * 113), fill="black")

    image.save(path)

    return path


@pytest.mark.skipif(tesseract_command() is None, reason="Tesseract is not installed")
def test_extract_reads_the_title_bar_when_asked(tmp_path):
    image = browser(tmp_path / "tab.png", "Lagu santai - YouTube")

    without = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}]}).json()["results"][0]
    with_title = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}], "read_title": True}).json()["results"][0]

    assert without["title"] is None
    assert "youtube" in with_title["title"].lower()


@pytest.mark.skipif(not classifier.is_installed(), reason="torch / open_clip are not installed")
def test_extract_ranks_categories_with_the_ai_classifier(tmp_path):
    image = spreadsheet(tmp_path / "sheet.png")
    labels = [
        {"id": 10, "prompt": "a screenshot of a spreadsheet"},
        {"id": 20, "prompt": "a screenshot of a video game"},
        {"id": 30, "prompt": "a screenshot of a social media feed"},
    ]

    result = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}], "labels": labels}).json()["results"][0]

    assert [category["id"] for category in result["categories"]][0] == 10
    assert result["categories"][0]["score"] > 0.5


def test_extract_skips_classification_without_labels(tmp_path):
    image = spreadsheet(tmp_path / "sheet.png")

    result = client.post("/extract", json={"items": [{"id": 1, "path": str(image)}]}).json()["results"][0]

    assert result["categories"] is None


def test_health_reports_whether_the_classifier_is_installed():
    assert client.get("/health").json() == {"status": "ok", "classifier": classifier.is_installed()}


def sheet(seed: int, clock: str | None = "08:00") -> Image.Image:
    """A spreadsheet-like work screen with a taskbar clock in the bottom-right corner."""
    image = Image.new("RGB", (1366, 768), "white")
    draw = ImageDraw.Draw(image)
    font = ImageFont.load_default(size=15)
    draw.rectangle((0, 0, 1366, 36), fill="#217346")

    for row in range(26):
        for column in range(9):
            x, y = 30 + column * 145, 50 + row * 25
            draw.rectangle((x, y, x + 145, y + 25), outline="#d4d4d4")
            draw.text((x + 6, y + 5), str((seed * 7919 + row * 131 + column * 17) % 99991), fill="black", font=font)

    draw.rectangle((0, 720, 1366, 768), fill="#202020")

    if clock:
        draw.text((1290, 732), clock, fill="white", font=font)

    return image


def compare_frames(tmp_path, frames, references=(), settings=None) -> dict:
    """Save (image, format) frames in order, extract them and run /compare."""
    paths = []

    for index, (image, fmt) in enumerate(frames, start=1):
        path = tmp_path / f"{index}.{'jpg' if fmt == 'JPEG' else 'png'}"
        image.save(path, fmt, **({"quality": 88} if fmt == "JPEG" else {}))
        paths.append(path)

    screenshots = extract_all(paths)
    payload = {"screenshots": screenshots, "references": list(references), "settings": {"ignore_regions": [[0.7, 0.93, 1, 1]], **(settings or {})}}

    return client.post("/compare", json=payload).json()


def test_compare_finds_resaved_and_edited_copies_of_an_earlier_screenshot(tmp_path):
    edited = sheet(2, "08:15")
    edited.putpixel((5, 5), (250, 250, 250))

    result = compare_frames(tmp_path, [
        (sheet(1, "08:00"), "PNG"),
        (sheet(2, "08:05"), "PNG"),
        (sheet(3, "08:10"), "PNG"),
        (edited, "JPEG"),
    ])

    assert [(match["id"], match["original_id"]) for match in result["near_duplicates"]] == [(4, 2)]
    assert result["duplicates"] == []


def test_compare_finds_screens_rotated_back_and_forth(tmp_path):
    result = compare_frames(tmp_path, [(sheet(seed, f"08:{minute:02d}"), "PNG") for minute, seed in zip(range(0, 30, 5), [1, 2, 1, 2, 1, 2])])

    assert sorted(match["id"] for match in result["near_duplicates"]) == [3, 4, 5, 6]


def test_compare_leaves_idle_stretches_and_normal_work_to_the_other_checks(tmp_path):
    (tmp_path / "idle").mkdir()
    (tmp_path / "work").mkdir()

    idle = compare_frames(tmp_path / "idle", [(sheet(1, f"08:{minute:02d}"), "PNG") for minute in (0, 5, 10, 15)])
    work = compare_frames(tmp_path / "work", [(sheet(seed, "08:00"), "PNG") for seed in (1, 2, 3, 4, 5)])

    assert idle["near_duplicates"] == []
    assert work["near_duplicates"] == []


def test_compare_finds_screenshots_recycled_from_earlier_uploads_even_when_resaved(tmp_path):
    old = tmp_path / "old.png"
    sheet(42, "14:30").save(old)
    reference = extract_all([old])[0]
    disguised = sheet(42, "08:05")

    (tmp_path / "new").mkdir()

    result = compare_frames(tmp_path / "new", [
        (sheet(1, "08:00"), "PNG"),
        (disguised, "JPEG"),
    ], references=[{"id": 900, "md5": reference["md5"], "phash": reference["phash"], "path": str(old)}])

    assert [(match["id"], match["reference_id"], match["exact"]) for match in result["recycled"]] == [(2, 900, False)]
    assert result["recycled"][0]["change_ratio"] < 0.005
