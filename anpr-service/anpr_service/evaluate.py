"""Measure recognition quality on a locally collected, labelled image set.

    python -m anpr_service.evaluate --images D:\\anpr-eval\\images --labels D:\\anpr-eval\\labels.csv --threshold 0.90

labels.csv columns: filename,plate   (plate as written on the vehicle, e.g. "WP CAB-1234"; leave empty for
images that contain no readable plate). Results are only valid for the images evaluated: collect them on site
(day/night, rain, motorbikes, two-line plates, dirty/tilted plates) before tuning the confidence threshold.
"""

from __future__ import annotations

import argparse
import csv
import json
import statistics
import time
from pathlib import Path
from typing import Callable

from . import normalizer


def levenshtein(a: str, b: str) -> int:
    prev = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        cur = [i]
        for j, cb in enumerate(b, 1):
            cur.append(min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (ca != cb)))
        prev = cur
    return prev[-1]


def _key(text: str | None) -> str | None:
    if not text:
        return None
    return normalizer.normalize(text)["normalized"] or normalizer.identifier(text) or None


def evaluate(samples: list[tuple[str, str | None]], recognize: Callable[[str], dict], threshold: float) -> dict:
    """samples: [(image_path, truth_plate_or_None)]; recognize(path) -> service-style result dict."""
    rows, latencies = [], []
    for path, truth in samples:
        t0 = time.perf_counter()
        try:
            result = recognize(path)
            error = None
        except Exception as e:  # count as a miss, keep going
            result, error = {"status": "engine_error", "candidates": []}, type(e).__name__
        latencies.append((time.perf_counter() - t0) * 1000)
        top = (result.get("candidates") or [{}])[0]
        read, conf = _key(top.get("text")), float(top.get("confidence") or 0.0)
        truth_key = _key(truth)
        rows.append({
            "image": Path(path).name, "truth": truth_key, "read": read, "confidence": round(conf, 4),
            "status": result.get("status"), "error": error,
            "exact": bool(truth_key and read == truth_key),
            "accepted": bool(read and conf >= threshold),
            "cer": (levenshtein(read or "", truth_key) / len(truth_key)) if truth_key else None,
        })

    with_plate = [r for r in rows if r["truth"]]
    without_plate = [r for r in rows if not r["truth"]]
    accepted = [r for r in with_plate if r["accepted"]]
    n = len(with_plate) or 1

    def pct(x: float) -> float:
        return round(100 * x, 2)

    summary = {
        "images": len(rows),
        "images_with_plate": len(with_plate),
        "images_without_plate": len(without_plate),
        "threshold": threshold,
        "detection_rate_pct": pct(sum(1 for r in with_plate if r["read"]) / n),
        "exact_match_rate_pct": pct(sum(r["exact"] for r in with_plate) / n),
        "accepted_rate_pct": pct(len(accepted) / n),
        "accepted_precision_pct": pct(sum(r["exact"] for r in accepted) / len(accepted)) if accepted else None,
        # The dangerous case: a wrong plate that would pass the confidence threshold.
        "false_match_at_threshold_pct": pct(sum(1 for r in accepted if not r["exact"]) / n),
        "phantom_reads_on_empty_pct": pct(sum(1 for r in without_plate if r["accepted"]) / len(without_plate)) if without_plate else None,
        "mean_cer": round(statistics.mean(r["cer"] for r in with_plate), 4) if with_plate else None,
        "latency_ms_p50": round(statistics.median(latencies), 1) if latencies else None,
        "latency_ms_p95": round(sorted(latencies)[max(0, int(len(latencies) * 0.95) - 1)], 1) if latencies else None,
        "errors": sum(1 for r in rows if r["error"]),
        "note": "Measured only on the images listed. Not a general accuracy claim.",
    }
    return {"summary": summary, "rows": rows}


def load_labels(images_dir: Path, labels_csv: Path) -> list[tuple[str, str | None]]:
    out = []
    with labels_csv.open(newline="", encoding="utf-8-sig") as fh:
        for row in csv.DictReader(fh):
            path = images_dir / row["filename"]
            if path.is_file():
                out.append((str(path), (row.get("plate") or "").strip() or None))
    return out


def main() -> None:  # pragma: no cover - CLI
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--images", type=Path, required=True)
    p.add_argument("--labels", type=Path, required=True)
    p.add_argument("--threshold", type=float, default=0.90)
    p.add_argument("--out", type=Path, default=Path("evaluation-report.json"))
    args = p.parse_args()

    from .config import ServiceSettings
    from .engine import build_engine
    from .imaging import decode

    settings = ServiceSettings.from_env()
    engine = build_engine(settings)
    ok, why = engine.ready()
    if not ok:
        raise SystemExit(f"Engine not ready: {why}")

    def recognize(path: str) -> dict:
        return engine.recognize(decode(Path(path).read_bytes(), settings.max_image_bytes, settings.max_image_pixels)).to_json()

    report = evaluate(load_labels(args.images, args.labels), recognize, args.threshold)
    for r in report["rows"]:
        r.pop("plate_crop_jpeg_base64", None)
    args.out.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report["summary"], indent=2))


if __name__ == "__main__":  # pragma: no cover
    main()
