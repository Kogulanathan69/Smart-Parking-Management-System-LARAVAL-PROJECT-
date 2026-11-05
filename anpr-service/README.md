# Smart Parking — offline ANPR service

Two processes, both CPU-only and needing no Internet at runtime:

| Process | Command | Role |
|---|---|---|
| Recognition service | `python -m anpr_service.app` | `POST /v1/recognize`: plate detection (YOLOv9 ONNX via open-image-models) followed by OCR (fast-plate-ocr ONNX). Loopback only, bearer token |
| Camera agent (one per camera) | `python -m anpr_service.agent` | Reads RTSP/USB, uses a motion gate, recognises, de-duplicates locally, pushes to Laravel `/api/v1/anpr/device/events` with the camera's device token, and spools to disk while the backend is unreachable |

Other tools:

* `python -m anpr_service.evaluate`: measures accuracy on your own labelled images.
* `scripts/provision_models.py`: offline model install with pinned SHA-256 checks.

Deployment is described step by step in `../docs/ANPR_OFFLINE_DEPLOYMENT.md`.

## What a reading means

The service returns candidates `{text, confidence}`.

* **`confidence`** is the lowest per-character probability from the OCR model, between 0 and 1. It is a model score, **not** a measured accuracy.
* **Laravel** re-normalises every reading, flags low-confidence and confusion-corrected results, and requires staff verification before any entry or exit.
* **Gate.** Nothing here can open a gate.

## Development

```bash
python -m venv .venv && . .venv/bin/activate      # Windows: .venv\Scripts\activate
pip install -r requirements-dev.txt
python -m pytest -q
# optional real-model smoke test (not an accuracy test):
python scripts/provision_models.py download --models-dir /tmp/models
ANPR_TEST_MODELS_DIR=/tmp/models python -m pytest -q tests/test_onnx_smoke.py
```

## Configuration

See `.env.example`. The service refuses to start in these cases:

* `ANPR_SERVICE_TOKEN` is shorter than 32 characters.
* `ANPR_BIND_HOST` is not a loopback address and `ANPR_ALLOW_LAN_BIND=true` is not set.
* The model files are missing or fail their checksum. In that case the process starts, `/v1/health` reports `degraded` with status 503, and `/v1/recognize` returns `engine_not_ready`.

## Licences (verify before production)

| Component | Licence |
|---|---|
| fastapi, uvicorn, httpx, numpy, onnxruntime, fast-plate-ocr, open-image-models, PyYAML | MIT / BSD |
| opencv-python-headless | Apache-2.0 |
| OCR weights `cct_xs_v2_global` | Published with fast-plate-ocr; confirm the terms with the author |
| Detector weights `yolo-v9-t-384` | YOLOv9-derived; **confirm the weight licence for commercial use** |

Ultralytics (AGPL-3.0) is deliberately **not** used.
