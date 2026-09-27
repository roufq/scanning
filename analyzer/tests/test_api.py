from pathlib import Path

import pytest
from fastapi.testclient import TestClient
from PIL import Image, ImageDraw

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
