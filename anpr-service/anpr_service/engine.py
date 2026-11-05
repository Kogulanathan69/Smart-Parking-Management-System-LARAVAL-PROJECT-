"""Recognition engines.

OnnxPlateEngine runs entirely on the local CPU with ONNX Runtime:
  1. plate detection  Ã¢â‚¬â€ open-image-models YOLOv9 ONNX detector (local file, no download at runtime)
  2. plate OCR        Ã¢â‚¬â€ fast-plate-ocr ONNX recogniser (local file, no download at runtime)

Confidence reported per candidate is the *lowest* per-character probability of the OCR output (the weakest
character decides whether the plate is right). It is a model score, not a measured accuracy: thresholds must be
tuned on locally collected, labelled Sri Lankan images with evaluate.py before relying on them.
"""

from __future__ import annotations

import base64
import hashlib
import json
import threading
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Protocol

import cv2
import numpy as np

from .config import ServiceSettings
from .imaging import encode_jpeg
from .region_integration import recognize_plate_regions
from .adaptive_ocr import collect_adaptive_readings
from .partial_review import collect_partial_review_clues


@dataclass
class Recognition:
    status: str  # recognized | unreadable
    candidates: list[dict] = field(default_factory=list)  # [{"text": str, "confidence": float 0..1, "detector_confidence": float|None}]
    plate_crop_jpeg: bytes | None = None
    processing_ms: int = 0
    detector: str = "none"
    review_suggestions: list[dict] = field(default_factory=list)
    partial_review_clues: dict | None = None

    def to_json(self) -> dict:
        out = {"status": self.status, "candidates": self.candidates, "processing_ms": self.processing_ms, "detector": self.detector}
        if self.plate_crop_jpeg:
            out["plate_crop_jpeg_base64"] = base64.b64encode(self.plate_crop_jpeg).decode("ascii")
        if self.review_suggestions:
            out["review_suggestions"] = self.review_suggestions
        if self.partial_review_clues is not None:
            out["partial_review_clues"] = self.partial_review_clues
        return out


class Engine(Protocol):
    name: str

    def ready(self) -> tuple[bool, str]: ...

    def recognize(self, img_bgr: np.ndarray) -> Recognition: ...


class ModelIntegrityError(RuntimeError):
    pass


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def verify_manifest(models_dir: Path, files: list[str]) -> None:
    manifest_path = models_dir / "manifest.json"
    if not manifest_path.is_file():
        raise ModelIntegrityError("models/manifest.json is missing Ã¢â‚¬â€ run scripts/provision_models.py verify.")
    manifest = json.loads(manifest_path.read_text(encoding="utf-8")).get("files", {})
    for name in files:
        path = models_dir / name
        if not path.is_file():
            raise ModelIntegrityError(f"Model file missing: {name}")
        expected = (manifest.get(name) or {}).get("sha256")
        if not expected or sha256_file(path) != expected:
            raise ModelIntegrityError(f"Model file failed its checksum: {name}")


class OnnxPlateEngine:
    name = "onnx_local"

    def __init__(self, settings: ServiceSettings):
        self.settings = settings
        self._detector = None
        self._ocr = None
        self._error: str | None = None
        self._lock = threading.Lock()

    def load(self) -> None:
        s = self.settings
        try:
            files = [s.detector_model, s.ocr_model, s.ocr_config]
            if s.require_manifest:
                verify_manifest(s.models_dir, files)
            else:
                missing = [f for f in files if not (s.models_dir / f).is_file()]
                if missing:
                    raise ModelIntegrityError("Model files missing: " + ", ".join(missing))

            import onnxruntime as ort
            from fast_plate_ocr import LicensePlateRecognizer
            from open_image_models import create_detector

            opts = ort.SessionOptions()
            opts.intra_op_num_threads = s.threads
            opts.inter_op_num_threads = 1
            providers = ["CPUExecutionProvider"]
            self._detector = create_detector(
                str(s.models_dir / s.detector_model), backend="yolo_v9", class_labels=["License Plate"],
                conf_thresh=s.detector_conf, providers=providers, sess_options=opts,
            )
            self._ocr = LicensePlateRecognizer(
                onnx_model_path=str(s.models_dir / s.ocr_model), plate_config_path=str(s.models_dir / s.ocr_config),
                device="cpu", providers=providers, sess_options=opts,
            )
            self._error = None
        except ModelIntegrityError as e:
            self._error = str(e)
        except Exception as e:  # import/runtime problems: report, never crash the process
            self._error = f"Engine failed to load: {type(e).__name__}"

    def ready(self) -> tuple[bool, str]:
        if self._error:
            return False, self._error
        if self._detector is None or self._ocr is None:
            return False, "Engine not loaded"
        return True, "ok"

    def recognize(self, img_bgr: np.ndarray) -> Recognition:
        started = time.perf_counter()
        ok, why = self.ready()
        if not ok:
            raise RuntimeError(why)

        detections = sorted(self._detector.predict(img_bgr), key=lambda d: d.confidence, reverse=True)
        crops: list[tuple[np.ndarray, float | None]] = []
        h, w = img_bgr.shape[:2]
        for det in detections[: self.settings.max_candidates]:
            b = det.bounding_box
            pad_x, pad_y = int((b.x2 - b.x1) * 0.06), int((b.y2 - b.y1) * 0.10)
            x1, y1 = max(0, b.x1 - pad_x), max(0, b.y1 - pad_y)
            x2, y2 = min(w, b.x2 + pad_x), min(h, b.y2 + pad_y)
            if x2 - x1 >= 8 and y2 - y1 >= 8:
                crops.append((img_bgr[y1:y2, x1:x2], float(det.confidence)))

        detector = "yolo_v9"
        if not crops and self.settings.ocr_full_frame_fallback and _looks_like_plate_crop(img_bgr):
            crops.append((img_bgr, None))  # the client already sent a tight plate crop (e.g. USB booth camera)
            detector = "skipped_crop_input"

        candidates: dict[str, dict] = {}
        review_suggestions: list[dict] = []
        partial_review_clues = None
        best_crop = None
        for crop, det_conf in crops:
            # Try the original plate and a locally contrast-enhanced version.
            lab = cv2.cvtColor(crop, cv2.COLOR_BGR2LAB)
            l_channel, a_channel, b_channel = cv2.split(lab)
            enhanced_l = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(4, 4)).apply(l_channel)
            enhanced = cv2.cvtColor(
                cv2.merge((enhanced_l, a_channel, b_channel)),
                cv2.COLOR_LAB2BGR,
            )

            # Experimental left crop for plates with small side markings.
            # Keep the full crop as an alternative; never discard it.
            left_offset = int(crop.shape[1] * 0.15)
            left_cropped = crop[:, left_offset:]

            for variant in (crop, enhanced, left_cropped):
                rgb = cv2.cvtColor(variant, cv2.COLOR_BGR2RGB)
                with self._lock:
                    pred = self._ocr.run(rgb, return_confidence=True)[0]
                text = (pred.plate or "").strip().replace("_", "")
                if not text:
                    continue
                probs = np.asarray(
                    pred.char_probs if pred.char_probs is not None else [0.0],
                    dtype=float,
                ).ravel()
                probs = probs[: len(text)] if probs.size >= len(text) else probs
                conf = float(np.clip(probs.min() if probs.size else 0.0, 0.0, 1.0))
                if text not in candidates or candidates[text]["confidence"] < conf:
                    candidates[text] = {
                        "text": text,
                        "confidence": round(conf, 4),
                        "detector_confidence": None if det_conf is None else round(det_conf, 4),
                    }

            suggestion = recognize_plate_regions(
                self._ocr, self._lock, crop
            )
            if suggestion is not None:
                review_suggestions.append({
                    **suggestion,
                    "source": "region_ocr",
                })

            if best_crop is None:
                best_crop = crop

        if self.settings.adaptive_review_enabled and best_crop is not None:
            try:
                readings = collect_adaptive_readings(
                    self._ocr, self._lock, best_crop
                )
                partial_review_clues = collect_partial_review_clues(readings)
            except Exception:
                partial_review_clues = None

        ordered = sorted(candidates.values(), key=lambda c: c["confidence"], reverse=True)[: self.settings.max_candidates]
        return Recognition(
            status="recognized" if ordered else "unreadable",
            candidates=ordered,
            review_suggestions=review_suggestions,
            partial_review_clues=partial_review_clues,
            plate_crop_jpeg=encode_jpeg(best_crop) if best_crop is not None else None,
            processing_ms=int((time.perf_counter() - started) * 1000),
            detector=detector if crops else "yolo_v9",
        )


def _looks_like_plate_crop(img: np.ndarray) -> bool:
    h, w = img.shape[:2]
    return h <= 400 and 1.4 <= w / max(h, 1) <= 6.5


class StubEngine:
    """Deterministic engine for automated tests only. Refused unless ANPR_ENGINE=stub is set explicitly."""

    name = "stub"

    def __init__(self, text: str | None = "CAB1234", confidence: float = 0.95):
        self.text, self.confidence = text, confidence
        self.calls = 0

    def ready(self) -> tuple[bool, str]:
        return True, "ok"

    def recognize(self, img_bgr: np.ndarray) -> Recognition:
        self.calls += 1
        if self.text is None:
            return Recognition(status="unreadable", detector="stub")
        return Recognition(status="recognized", candidates=[{"text": self.text, "confidence": self.confidence, "detector_confidence": 0.9}],
                           plate_crop_jpeg=encode_jpeg(img_bgr), processing_ms=1, detector="stub")


def build_engine(settings: ServiceSettings) -> Engine:
    if settings.engine == "stub":
        return StubEngine()
    engine = OnnxPlateEngine(settings)
    engine.load()
    return engine
