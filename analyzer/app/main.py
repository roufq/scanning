"""HTTP API used by the Laravel app. Run with: uvicorn app.main:app --port 8001"""

from concurrent.futures import ThreadPoolExecutor

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app import classifier, compare, features
from app.paths import PathNotAllowedError, resolve_allowed

app = FastAPI(title="Screenshot Analyzer")

EXTRACT_WORKERS = 4


class ExtractItem(BaseModel):
    id: int
    path: str
    thumbnail_path: str | None = None


class Label(BaseModel):
    id: int
    prompt: str


class ExtractRequest(BaseModel):
    items: list[ExtractItem] = Field(max_length=500)
    read_clock: bool = True
    read_title: bool = False
    # Categories for the AI classifier; empty means "do not classify".
    labels: list[Label] = []


class CompareScreenshot(BaseModel):
    id: int
    path: str
    md5: str
    phash: str


class Reference(BaseModel):
    id: int
    md5: str
    phash: str
    # Needed to confirm near-identical (re-saved / edited) copies pixel by pixel.
    path: str | None = None


class CompareSettings(BaseModel):
    pixel_threshold: int = 25
    similar_distance: int = 4
    # Perceptual-hash distance under which two screenshots are compared pixel by pixel...
    duplicate_candidate_distance: int = 10
    # ...and the share of changed pixels under which they count as the same image.
    duplicate_max_change: float = 0.005
    ignore_regions: list[list[float]] = []


class CompareRequest(BaseModel):
    screenshots: list[CompareScreenshot]
    references: list[Reference] = []
    settings: CompareSettings = CompareSettings()


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "classifier": classifier.is_installed()}


@app.post("/extract")
def extract(request: ExtractRequest) -> dict:
    try:
        jobs = [
            (item.id, resolve_allowed(item.path), resolve_allowed(item.thumbnail_path) if item.thumbnail_path else None)
            for item in request.items
        ]
    except PathNotAllowedError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    labels = [label.model_dump() for label in request.labels]
    classify = bool(labels) and classifier.is_installed()

    def run(job: tuple) -> tuple[dict, object]:
        screenshot_id, path, thumbnail = job

        try:
            found = features.extract(
                path,
                thumbnail,
                with_clock=request.read_clock,
                with_title=request.read_title,
                with_preview=classify,
            )

            return {"id": screenshot_id, **found.to_dict(), "categories": None, "error": None}, found.preview
        except OSError as exception:
            return {"id": screenshot_id, "error": f"Gambar tidak dapat dibaca: {exception}"}, None

    # OCR runs Tesseract as a subprocess, so threads give a real speed-up.
    with ThreadPoolExecutor(max_workers=EXTRACT_WORKERS) as pool:
        extracted = list(pool.map(run, jobs))

    classifiable = [(result, preview) for result, preview in extracted if preview is not None]

    if classifiable:
        rankings = classifier.classify([preview for _, preview in classifiable], labels)

        for (result, _), ranking in zip(classifiable, rankings):
            result["categories"] = ranking

    return {"results": [result for result, _ in extracted]}


@app.post("/compare")
def compare_screenshots(request: CompareRequest) -> dict:
    try:
        screenshots = [(item.id, resolve_allowed(item.path), item.md5, item.phash) for item in request.screenshots]
        references = [
            (item.id, resolve_allowed(item.path) if item.path else None, item.md5, item.phash)
            for item in request.references
        ]
    except PathNotAllowedError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    settings = request.settings
    changes = compare.consecutive_changes([(item[0], item[1]) for item in screenshots], settings.pixel_threshold, settings.ignore_regions)
    confirm = {
        "candidate_distance": settings.duplicate_candidate_distance,
        "max_change": settings.duplicate_max_change,
        "pixel_threshold": settings.pixel_threshold,
        "ignore_regions": settings.ignore_regions,
    }

    return {
        "changes": [change.__dict__ for change in changes],
        "duplicates": compare.exact_duplicates([(item.id, item.md5) for item in request.screenshots]),
        "near_duplicates": compare.near_duplicates(screenshots, {change.id: change.change_ratio for change in changes}, **confirm),
        "recycled": compare.recycled_matches(screenshots, references, **confirm),
        "groups": compare.similarity_groups(
            [(item.id, item.phash) for item in request.screenshots],
            settings.similar_distance,
        ),
    }
