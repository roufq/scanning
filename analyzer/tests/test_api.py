from pathlib import Path

import pytest
from fastapi.testclient import TestClient
from PIL import Image, ImageDraw, ImageFont

from app.clock import parse_time, tesseract_command
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
