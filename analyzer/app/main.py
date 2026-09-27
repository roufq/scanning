"""HTTP API used by the Laravel app. Run with: uvicorn app.main:app --port 8001"""

from concurrent.futures import ThreadPoolExecutor

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app import compare, features
from app.paths import PathNotAllowedError, resolve_allowed

app = FastAPI(title="Screenshot Analyzer")

EXTRACT_WORKERS = 4


class ExtractItem(BaseModel):
    id: int
    path: str
    thumbnail_path: str | None = None


class ExtractRequest(BaseModel):
    items: list[ExtractItem] = Field(max_length=500)
    read_clock: bool = True


class CompareScreenshot(BaseModel):
    id: int
    path: str
    md5: str
    phash: str


class Reference(BaseModel):
    id: int
    md5: str
    phash: str


class CompareSettings(BaseModel):
    pixel_threshold: int = 25
    similar_distance: int = 4
    recycled_distance: int = 0
    ignore_regions: list[list[float]] = []


class CompareRequest(BaseModel):
    screenshots: list[CompareScreenshot]
    references: list[Reference] = []
    settings: CompareSettings = CompareSettings()


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/extract")
def extract(request: ExtractRequest) -> dict:
    try:
        jobs = [
            (item.id, resolve_allowed(item.path), resolve_allowed(item.thumbnail_path) if item.thumbnail_path else None)
            for item in request.items
        ]
    except PathNotAllowedError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    def run(job: tuple) -> dict:
        screenshot_id, path, thumbnail = job

        try:
            found = features.extract(path, thumbnail, with_clock=request.read_clock)

            return {"id": screenshot_id, **found.__dict__, "error": None}
        except OSError as exception:
            return {"id": screenshot_id, "error": f"Gambar tidak dapat dibaca: {exception}"}

    # OCR runs Tesseract as a subprocess, so threads give a real speed-up.
    with ThreadPoolExecutor(max_workers=EXTRACT_WORKERS) as pool:
        return {"results": list(pool.map(run, jobs))}


@app.post("/compare")
def compare_screenshots(request: CompareRequest) -> dict:
    try:
        ordered = [(item.id, resolve_allowed(item.path)) for item in request.screenshots]
    except PathNotAllowedError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    settings = request.settings
    changes = compare.consecutive_changes(ordered, settings.pixel_threshold, settings.ignore_regions)

    return {
        "changes": [change.__dict__ for change in changes],
        "duplicates": compare.exact_duplicates([(item.id, item.md5) for item in request.screenshots]),
        "recycled": compare.recycled_matches(
            [(item.id, item.md5, item.phash) for item in request.screenshots],
            [(item.id, item.md5, item.phash) for item in request.references],
            settings.recycled_distance,
        ),
        "groups": compare.similarity_groups(
            [(item.id, item.phash) for item in request.screenshots],
            settings.similar_distance,
        ),
    }
