"""Zero-shot screenshot classification with CLIP (open_clip, CPU).

Each category is described by a short English prompt, e.g. "a screenshot of a YouTube video".
The model is loaded lazily on first use; when torch/open_clip are not installed, classification
is simply unavailable and the rest of the analyzer keeps working.
"""

import importlib.util
import os
import threading
from functools import lru_cache

from PIL import Image

MODEL_NAME = os.environ.get("CLIP_MODEL", "ViT-B-32")
PRETRAINED = os.environ.get("CLIP_PRETRAINED", "openai")
BATCH_SIZE = 16

_lock = threading.Lock()
_state: dict = {}


@lru_cache(maxsize=1)
def is_installed() -> bool:
    # find_spec avoids importing torch, which takes several seconds.
    return all(importlib.util.find_spec(name) is not None for name in ("torch", "open_clip"))


def _load() -> dict:
    with _lock:
        if not _state:
            import open_clip
            import torch

            torch.set_num_threads(max(1, (os.cpu_count() or 2) - 1))
            model, _, preprocess = open_clip.create_model_and_transforms(MODEL_NAME, pretrained=PRETRAINED)
            model.eval()
            _state.update(model=model, preprocess=preprocess, tokenizer=open_clip.get_tokenizer(MODEL_NAME))

    return _state


@lru_cache(maxsize=8)
def _text_features(prompts: tuple[str, ...]):
    import torch

    state = _load()

    with torch.no_grad():
        features = state["model"].encode_text(state["tokenizer"](list(prompts)))

    return features / features.norm(dim=-1, keepdim=True)


def classify(images: list[Image.Image], labels: list[dict], top: int = 3) -> list[list[dict]]:
    """Return, per image, the `top` most likely labels as [{"id": ..., "score": 0-1}]."""
    if not images or not labels:
        return [[] for _ in images]

    import torch

    state = _load()
    text = _text_features(tuple(label["prompt"] for label in labels))
    results: list[list[dict]] = []

    for start in range(0, len(images), BATCH_SIZE):
        batch = torch.stack([state["preprocess"](image) for image in images[start:start + BATCH_SIZE]])

        with torch.no_grad():
            features = state["model"].encode_image(batch)
            features = features / features.norm(dim=-1, keepdim=True)
            probabilities = (100.0 * features @ text.T).softmax(dim=-1)

        for row in probabilities.tolist():
            ranked = sorted(zip(labels, row), key=lambda pair: pair[1], reverse=True)[:top]
            results.append([{"id": label["id"], "score": round(score, 4)} for label, score in ranked])

    return results
