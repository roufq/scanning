"""HTTP API used by the Laravel app. Run with: uvicorn app.main:app --port 8001"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app import compare, features
from app.paths import PathNotAllowedError, resolve_allowed

app = FastAPI(title="Screenshot Analyzer")


class ExtractItem(BaseModel):
    id: int
    path: str
    thumbnail_path: str | None = None


class ExtractRequest(BaseModel):
    items: list[ExtractItem] = Field(max_length=500)


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


class CompareRequest(BaseModel):
    screenshots: list[CompareScreenshot]
    references: list[Reference] = []
    settings: CompareSettings = CompareSettings()


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/extract")
def extract(request: ExtractRequest) -> dict:
    results = []

    for item in request.items:
        try:
            path = resolve_allowed(item.path)
            thumbnail = resolve_allowed(item.thumbnail_path) if item.thumbnail_path else None
            found = features.extract(path, thumbnail)
            results.append({"id": item.id, **found.__dict__, "error": None})
        except PathNotAllowedError as exception:
            raise HTTPException(status_code=422, detail=str(exception)) from exception
        except OSError as exception:
            results.append({"id": item.id, "error": f"Gambar tidak dapat dibaca: {exception}"})

    return {"results": results}


@app.post("/compare")
def compare_screenshots(request: CompareRequest) -> dict:
    try:
        ordered = [(item.id, resolve_allowed(item.path)) for item in request.screenshots]
    except PathNotAllowedError as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception

    settings = request.settings
    changes = compare.consecutive_changes(ordered, settings.pixel_threshold)

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
