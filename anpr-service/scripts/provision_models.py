"""Provision the ONNX models for fully offline operation.

On a machine WITH Internet (once):
    python scripts/provision_models.py download            # fetch + verify pinned SHA-256, write models/manifest.json
    -> copy the whole models/ folder to the parking PC (USB drive)

On the parking PC (no Internet needed):
    python scripts/provision_models.py verify              # checks every file against the pinned SHA-256
    python scripts/provision_models.py import E:\\models     # copy from a USB folder, then verify

The service refuses to load models that are missing or fail their checksum (ANPR_REQUIRE_MANIFEST=true).
Pinned hashes were recorded on 2026-11-01 from the upstream release files. If upstream replaces a file the
download fails closed — re-pin deliberately after reviewing the new file and its licence.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DIR = ROOT / "models"

MODELS = {
    "yolo-v9-t-384-license-plates-end2end.onnx": {
        "url": "https://github.com/ankandrew/open-image-models/releases/download/assets/yolo-v9-t-384-license-plates-end2end.onnx",
        "sha256": "888397b96d761c89db40bc9c305838e8652660f5e282c2cadebbe8d2951a77a8",
        "role": "plate detector",
        "package": "open-image-models 0.6.0 (MIT). Weights: YOLOv9-based — confirm the weight licence before commercial use.",
    },
    "cct_xs_v2_global.onnx": {
        "url": "https://github.com/ankandrew/cnn-ocr-lp/releases/download/arg-plates/cct_xs_v2_global.onnx",
        "sha256": "8031afb5fdc6b4d80462c9d542f1284ebd2cfddf5dbacd62609848d7e2855f44",
        "role": "plate OCR",
        "package": "fast-plate-ocr 1.1.0 (MIT). Global model; Sri Lanka is not among its listed training regions.",
    },
    "cct_xs_v2_global_plate_config.yaml": {
        "url": "https://github.com/ankandrew/cnn-ocr-lp/releases/download/arg-plates/cct_xs_v2_global_plate_config.yaml",
        "sha256": "0335c74a305173bb6f393efed0fde03cadeaa0b649ed8e19f431016d8232d0a6",
        "role": "plate OCR config",
        "package": "fast-plate-ocr 1.1.0 (MIT)",
    },
}


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def write_manifest(models_dir: Path, models: dict) -> None:
    manifest = {"files": {name: {"sha256": m["sha256"], "role": m["role"], "source": m["url"], "licence_note": m["package"]} for name, m in models.items()}}
    (models_dir / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")


def verify(models_dir: Path, models: dict = MODELS) -> list[str]:
    problems = []
    for name, m in models.items():
        path = models_dir / name
        if not path.is_file():
            problems.append(f"missing: {name}")
        elif sha256(path) != m["sha256"]:
            problems.append(f"checksum mismatch: {name}")
    return problems


def download(models_dir: Path, models: dict = MODELS, opener=urllib.request.urlopen) -> None:
    models_dir.mkdir(parents=True, exist_ok=True)
    for name, m in models.items():
        target = models_dir / name
        if target.is_file() and sha256(target) == m["sha256"]:
            print(f"ok (cached)  {name}")
            continue
        tmp = target.with_suffix(target.suffix + ".part")
        with opener(m["url"]) as resp, tmp.open("wb") as out:
            shutil.copyfileobj(resp, out)
        if sha256(tmp) != m["sha256"]:
            tmp.unlink()
            raise SystemExit(f"checksum mismatch for {name}: refusing to install it")
        tmp.replace(target)
        print(f"downloaded   {name}")


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("action", choices=["download", "verify", "import"])
    p.add_argument("source", nargs="?", type=Path, help="folder to import from (import only)")
    p.add_argument("--models-dir", type=Path, default=DEFAULT_DIR)
    a = p.parse_args(argv)

    if a.action == "download":
        download(a.models_dir)
    elif a.action == "import":
        if not a.source or not a.source.is_dir():
            p.error("import needs the source folder")
        a.models_dir.mkdir(parents=True, exist_ok=True)
        for name in MODELS:
            if (a.source / name).is_file():
                shutil.copy2(a.source / name, a.models_dir / name)

    problems = verify(a.models_dir)
    if problems:
        print("\n".join(problems), file=sys.stderr)
        return 1
    write_manifest(a.models_dir, MODELS)
    print(f"all {len(MODELS)} model files verified; manifest written to {a.models_dir / 'manifest.json'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
