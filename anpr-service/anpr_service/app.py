"""HTTP API used by the Laravel backend (HttpAnprEngine) and the camera agent.

    GET  /v1/live       no auth; {"status":"ok"} only — for process supervisors
    GET  /v1/health     bearer token; engine readiness
    POST /v1/recognize  bearer token; multipart field "image" (JPEG/PNG/WebP)

Security: binds to loopback by default, constant-time token check, size/type/pixel limits, bounded concurrency,
and nothing about images, plates or tokens is written to logs. The service never stores images.
"""

from __future__ import annotations

import hmac
import logging
import threading
import time
import uuid

from fastapi import Depends, FastAPI, File, Header, HTTPException, Request, UploadFile
from fastapi.responses import JSONResponse

from . import __version__
from .config import ServiceSettings
from .engine import Engine, build_engine
from .imaging import ImageRejected, decode

log = logging.getLogger("anpr.service")


def create_app(settings: ServiceSettings, engine: Engine | None = None) -> FastAPI:
    app = FastAPI(title="Smart Parking ANPR service", version=__version__, docs_url=None, redoc_url=None, openapi_url=None)
    app.state.settings = settings
    app.state.engine = engine or build_engine(settings)
    slots = threading.BoundedSemaphore(settings.max_concurrency)

    def require_token(authorization: str | None = Header(default=None)) -> None:
        supplied = authorization[7:] if authorization and authorization.startswith("Bearer ") else ""
        if not supplied or not hmac.compare_digest(supplied.encode(), settings.token.encode()):
            raise HTTPException(status_code=401, detail={"error_code": "unauthenticated"})

    @app.exception_handler(HTTPException)
    async def http_error(_: Request, exc: HTTPException):
        detail = exc.detail if isinstance(exc.detail, dict) else {"error_code": "error", "message": str(exc.detail)}
        return JSONResponse(status_code=exc.status_code, content=detail)

    @app.get("/v1/live")
    def live():
        return {"status": "ok"}

    @app.get("/v1/health", dependencies=[Depends(require_token)])
    def health():
        ok, message = app.state.engine.ready()
        body = {"status": "ok" if ok else "degraded", "engine": app.state.engine.name, "version": __version__,
                "detector": "yolo_v9_onnx", "ocr": "fast_plate_ocr_onnx"}
        if not ok:
            body["message"] = message
        return JSONResponse(status_code=200 if ok else 503, content=body)

    @app.post("/v1/recognize", dependencies=[Depends(require_token)])
    def recognize(image: UploadFile = File(...)):
        request_id = uuid.uuid4().hex[:12]
        started = time.perf_counter()
        ok, _ = app.state.engine.ready()
        if not ok:
            raise HTTPException(status_code=503, detail={"error_code": "engine_not_ready"})
        data = image.file.read(settings.max_image_bytes + 1)
        try:
            img = decode(data, settings.max_image_bytes, settings.max_image_pixels)
        except ImageRejected as e:
            log.info("recognize rejected id=%s code=%s", request_id, e.code)
            raise HTTPException(status_code=e.http_status, detail={"error_code": e.code, "message": str(e)})

        if not slots.acquire(timeout=settings.queue_timeout_s):
            raise HTTPException(status_code=503, detail={"error_code": "engine_busy"})
        try:
            result = app.state.engine.recognize(img)
        except Exception as e:
            log.error("recognize failed id=%s error=%s", request_id, type(e).__name__)
            raise HTTPException(status_code=500, detail={"error_code": "engine_failure"})
        finally:
            slots.release()

        body = result.to_json()
        body["processing_ms"] = int((time.perf_counter() - started) * 1000)
        log.info("recognize id=%s status=%s candidates=%d ms=%d", request_id, result.status, len(result.candidates), body["processing_ms"])
        return body

    return app


def main() -> None:  # pragma: no cover - process entry point
    import uvicorn

    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
    settings = ServiceSettings.from_env()
    app = create_app(settings)
    ok, message = app.state.engine.ready()
    log.info("ANPR service starting on %s:%d engine=%s ready=%s %s", settings.bind_host, settings.port, app.state.engine.name, ok, "" if ok else message)
    uvicorn.run(app, host=settings.bind_host, port=settings.port, log_level="info", access_log=False, workers=1)


if __name__ == "__main__":  # pragma: no cover
    main()
