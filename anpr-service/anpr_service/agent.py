"""Camera agent: reads an RTSP/USB camera, recognises plates through the local service and pushes readings to
the Laravel backend (POST /api/v1/anpr/device/events) using the camera's own device token.

A pushed reading is only a *suggestion* for staff: the backend never starts an entry/exit or opens a gate
because of it. Run one agent process per camera, normally on the parking PC itself.
"""

from __future__ import annotations

import argparse
import ipaddress
import json
import logging
import os
import signal
import time
import uuid
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Callable
from urllib.parse import quote, urlsplit, urlunsplit

import httpx
import numpy as np

from . import normalizer
from .config import BASE_DIR, _load_dotenv
from .imaging import encode_jpeg
from .spool import Spool

log = logging.getLogger("anpr.agent")


@dataclass
class AgentSettings:
    backend_url: str
    camera_code: str
    camera_token: str
    service_url: str = "http://127.0.0.1:8765"
    service_token: str = ""
    source_override: str | None = None
    sample_interval_ms: int = 400
    local_dedup_seconds: int = 15
    motion_threshold: float = 4.0
    idle_recheck_seconds: float = 5.0
    spool_dir: Path = BASE_DIR / "spool"
    verify_tls: bool | str = True

    @classmethod
    def from_env(cls) -> "AgentSettings":
        _load_dotenv(BASE_DIR / ".env")
        need = {k: os.environ.get(k, "") for k in ("ANPR_BACKEND_URL", "ANPR_CAMERA_CODE", "ANPR_CAMERA_TOKEN", "ANPR_SERVICE_TOKEN")}
        missing = [k for k, v in need.items() if not v]
        if missing:
            raise SystemExit("Missing settings: " + ", ".join(missing))
        verify = os.environ.get("ANPR_BACKEND_CA_FILE") or os.environ.get("ANPR_BACKEND_VERIFY_TLS", "true").lower() != "false"
        return cls(
            backend_url=need["ANPR_BACKEND_URL"].rstrip("/"),
            camera_code=need["ANPR_CAMERA_CODE"],
            camera_token=need["ANPR_CAMERA_TOKEN"],
            service_url=os.environ.get("ANPR_SERVICE_URL", "http://127.0.0.1:8765").rstrip("/"),
            service_token=need["ANPR_SERVICE_TOKEN"],
            source_override=os.environ.get("ANPR_SOURCE") or None,
            sample_interval_ms=int(os.environ.get("ANPR_SAMPLE_INTERVAL_MS", "400")),
            local_dedup_seconds=int(os.environ.get("ANPR_LOCAL_DEDUP_SECONDS", "15")),
            motion_threshold=float(os.environ.get("ANPR_MOTION_THRESHOLD", "4.0")),
            spool_dir=Path(os.environ.get("ANPR_SPOOL_DIR", str(BASE_DIR / "spool" / need["ANPR_CAMERA_CODE"]))),
            verify_tls=verify,
        )


def stream_source(stream: dict, override: str | None) -> str | int:
    """Build the OpenCV source. Credentials are inserted in memory only and never logged."""
    if override:
        if override.startswith("usb:"):
            return int(override[4:])
        return override
    url = stream.get("url")
    if not url:
        raise ValueError("No stream URL configured for this camera; set ANPR_SOURCE (e.g. usb:0).")
    parts = urlsplit(url)
    if stream.get("username"):
        cred = quote(stream["username"], safe="") + (":" + quote(stream["password"], safe="") if stream.get("password") else "")
        netloc = cred + "@" + parts.netloc.split("@")[-1]
        parts = parts._replace(netloc=netloc)
    return urlunsplit(parts)


def redact(source: str | int) -> str:
    if isinstance(source, int):
        return f"usb:{source}"
    p = urlsplit(source)
    return f"{p.scheme}://{p.hostname or ''}{':' + str(p.port) if p.port else ''}/…"


def is_insecure_remote(url: str) -> bool:
    p = urlsplit(url)
    if p.scheme == "https":
        return False
    host = p.hostname or ""
    if host in ("localhost",):
        return False
    try:
        return not ipaddress.ip_address(host).is_loopback
    except ValueError:
        return True


class CameraAgent:
    def __init__(self, settings: AgentSettings, backend: httpx.Client, recognizer: httpx.Client,
                 capture_factory: Callable[[str | int], object] | None = None, clock: Callable[[], float] = time.monotonic):
        self.s = settings
        self.backend = backend
        self.recognizer = recognizer
        self.capture_factory = capture_factory or _opencv_capture
        self.clock = clock
        self.spool = Spool(settings.spool_dir)
        self.config: dict = {}
        self._last_plate: tuple[str, float] | None = None
        self._prev_small: np.ndarray | None = None
        self._last_check = -1e9
        self._auth_failed = False
        self.stats = {"frames": 0, "recognized": 0, "pushed": 0, "spooled": 0, "dropped": 0, "dedup_skipped": 0}

    # ------------------------------------------------------------------ backend
    def _device_headers(self) -> dict:
        return {"X-Anpr-Camera": self.s.camera_code, "Authorization": f"Bearer {self.s.camera_token}", "Accept": "application/json"}

    def fetch_config(self) -> dict:
        r = self.backend.get(f"{self.s.backend_url}/api/v1/anpr/device/config", headers=self._device_headers())
        if r.status_code in (401, 403):
            self._auth_failed = True
            raise PermissionError(f"Backend refused camera {self.s.camera_code} ({r.status_code}). Check the device token / camera is enabled.")
        r.raise_for_status()
        self.config = r.json()["data"]
        return self.config

    def heartbeat(self, status: str, message: str | None = None, latency_ms: int | None = None) -> bool:
        try:
            r = self.backend.post(f"{self.s.backend_url}/api/v1/anpr/device/heartbeat", headers=self._device_headers(),
                                  json={"status": status, "message": (message or "")[:480] or None, "latency_ms": latency_ms})
            return r.status_code == 200
        except httpx.HTTPError:
            return False

    def push(self, fields: dict, files: dict[str, bytes], from_spool: Path | None = None) -> str:
        """Returns sent | spooled | dropped. Network errors, 429 and 5xx are retried later from the spool."""
        multipart = {k: (f"{k}.jpg", v, "image/jpeg") for k, v in files.items() if v}
        try:
            r = self.backend.post(f"{self.s.backend_url}/api/v1/anpr/device/events", headers=self._device_headers(), data=fields, files=multipart or None)
        except httpx.HTTPError:
            return self._spool(fields, files, from_spool)
        if r.status_code in (200, 201):
            if from_spool:
                self.spool.remove(from_spool)
            self.stats["pushed"] += 1
            return "sent"
        if r.status_code in (401, 403):
            self._auth_failed = True
            log.error("backend rejected camera credentials (%s); readings are spooled until fixed", r.status_code)
            return self._spool(fields, files, from_spool)
        if r.status_code == 429 or r.status_code >= 500:
            return self._spool(fields, files, from_spool)
        # 4xx validation problems will never succeed — drop rather than retry forever.
        log.warning("backend rejected a reading (%s); dropped", r.status_code)
        if from_spool:
            self.spool.remove(from_spool)
        self.stats["dropped"] += 1
        return "dropped"

    def _spool(self, fields: dict, files: dict[str, bytes], existing: Path | None) -> str:
        if existing is None:
            self.spool.put(fields, files)
            self.stats["spooled"] += 1
        return "spooled"

    def flush_spool(self, limit: int = 50) -> int:
        sent = 0
        for path in self.spool.entries()[:limit]:
            loaded = self.spool.load(path)
            if loaded is None:
                continue
            outcome = self.push(*loaded, from_spool=path)
            if outcome == "spooled":
                break  # backend still unavailable; keep order and try later
            sent += outcome == "sent"
        return sent

    # ------------------------------------------------------------------ recognition
    def recognize(self, jpeg: bytes) -> dict:
        r = self.recognizer.post(f"{self.s.service_url}/v1/recognize", headers={"Authorization": f"Bearer {self.s.service_token}"},
                                 files={"image": ("frame.jpg", jpeg, "image/jpeg")})
        r.raise_for_status()
        return r.json()

    def should_check(self, frame: np.ndarray) -> bool:
        """Cheap motion gate so an empty lane does not run the models continuously."""
        import cv2

        small = cv2.resize(cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY), (96, 54))
        moved = self._prev_small is None or float(np.mean(cv2.absdiff(small, self._prev_small))) >= self.s.motion_threshold
        self._prev_small = small
        now = self.clock()
        if moved or now - self._last_check >= self.s.idle_recheck_seconds:
            self._last_check = now
            return True
        return False

    def process_frame(self, frame: np.ndarray, captured_at: datetime | None = None) -> str:
        """Returns skipped | unreadable | duplicate_local | sent | spooled | dropped."""
        self.stats["frames"] += 1
        if not self.should_check(frame):
            return "skipped"
        jpeg = encode_jpeg(frame)
        result = self.recognize(jpeg)
        candidates = [
            c for c in result.get("candidates", [])
            if c.get("text") and c.get("review_required") is not True
        ]
        if result.get("status") != "recognized" or not candidates:
            return "unreadable"  # empty lane / no plate: nothing is sent, keeps the review queue clean
        self.stats["recognized"] += 1

        plate = normalizer.normalize(candidates[0]["text"])["normalized"] or candidates[0]["text"]
        now = self.clock()
        if self._last_plate and self._last_plate[0] == plate and now - self._last_plate[1] < self.s.local_dedup_seconds:
            self.stats["dedup_skipped"] += 1
            return "duplicate_local"
        self._last_plate = (plate, now)

        fields = {
            "idempotency_key": str(uuid.uuid4()),
            "captured_at": (captured_at or datetime.now(timezone.utc)).isoformat(),
            "status": "recognized",
            "candidates": json.dumps([{"text": c["text"], "confidence": float(c["confidence"]), **({"detector_confidence": float(c["detector_confidence"])} if c.get("detector_confidence") is not None else {})} for c in candidates[:5]]),
            "processing_ms": str(int(result.get("processing_ms", 0))),
        }
        review_suggestions = [
            {
                "text": s["text"],
                "confidence": float(s["confidence"]),
                "review_required": True,
                "source": "region_ocr",
            }
            for s in result.get("review_suggestions", [])[:3]
            if isinstance(s, dict)
            and s.get("review_required") is True
            and s.get("source") == "region_ocr"
            and isinstance(s.get("text"), str)
            and isinstance(s.get("confidence"), (int, float))
            and not isinstance(s.get("confidence"), bool)
            and 0 <= s["confidence"] <= 1
        ]
        if review_suggestions:
            fields["review_suggestions"] = json.dumps(review_suggestions)

        partial_clues = result.get("partial_review_clues")
        if isinstance(partial_clues, dict):
            try:
                from .region_ocr import PROVINCES

                def valid_clues(items, province):
                    if not isinstance(items, list) or len(items) > 10:
                        return False
                    for item in items:
                        if not isinstance(item, dict) or set(item) != {"text", "confidence", "angle"}:
                            return False
                        text = item["text"]
                        confidence = item["confidence"]
                        angle = item["angle"]
                        if not isinstance(text, str) or not text.isascii():
                            return False
                        if province:
                            if text not in PROVINCES:
                                return False
                        elif not (5 <= len(text) <= 8 and text.isalnum()):
                            return False
                        if (
                            isinstance(confidence, bool)
                            or not isinstance(confidence, (int, float))
                            or not 0 <= confidence <= 1
                            or isinstance(angle, bool)
                            or not isinstance(angle, (int, float))
                            or not -180 <= angle <= 180
                        ):
                            return False
                    return True

                if (
                    set(partial_clues) == {
                        "province_clues", "registration_clues",
                        "review_required", "verified"
                    }
                    and partial_clues["review_required"] is True
                    and partial_clues["verified"] is False
                    and valid_clues(partial_clues["province_clues"], True)
                    and valid_clues(partial_clues["registration_clues"], False)
                ):
                    fields["partial_review_clues"] = json.dumps(partial_clues)
            except (TypeError, ValueError, ImportError):
                pass

        files = {}
        import base64

        if result.get("plate_crop_jpeg_base64"):
            files["plate_crop"] = base64.b64decode(result["plate_crop_jpeg_base64"])
        if self.config.get("recognition", {}).get("send_full_frame"):
            files["image"] = jpeg
        return self.push(fields, files)

    # ------------------------------------------------------------------ main loop
    def run(self, stop: Callable[[], bool] = lambda: False) -> None:
        backoff = 2.0
        while not stop():
            try:
                self.fetch_config()
                break
            except PermissionError as e:
                log.error("%s", e)
                time.sleep(60)
            except httpx.HTTPError:
                log.warning("backend unreachable; retrying config in %.0fs", backoff)
                time.sleep(backoff)
                backoff = min(backoff * 2, 60)

        rec = self.config.get("recognition", {})
        hb_every = max(15, int(rec.get("heartbeat_interval_seconds", 60)))
        source = stream_source(self.config.get("stream", {}), self.s.source_override)
        log.info("camera %s (%s) source=%s", self.s.camera_code, self.config.get("camera", {}).get("direction"), redact(source))

        cap, last_hb, last_flush, failures = None, 0.0, 0.0, 0
        while not stop():
            now = time.monotonic()
            if cap is None:
                cap = self.capture_factory(source)
                if not cap.isOpened():
                    failures += 1
                    self.heartbeat("error", f"camera stream unavailable (attempt {failures})")
                    cap = None
                    time.sleep(min(30, 2 * failures))
                    continue
                failures = 0
            ok, frame = cap.read()
            if not ok or frame is None:
                cap.release()
                cap = None
                continue
            try:
                self.process_frame(frame)
            except httpx.HTTPError:
                self.heartbeat("error", "recognition service unavailable")
                time.sleep(2)
            if now - last_flush > 10 and len(self.spool):
                self.flush_spool()
                last_flush = now
            if now - last_hb > hb_every:
                t0 = time.perf_counter()
                svc_ok = _service_ok(self.recognizer, self.s)
                self.heartbeat("ok" if svc_ok else "error", None if svc_ok else "recognition service not ready",
                               int((time.perf_counter() - t0) * 1000))
                last_hb = now
            time.sleep(self.s.sample_interval_ms / 1000)
        if cap is not None:
            cap.release()


def _service_ok(client: httpx.Client, s: AgentSettings) -> bool:
    try:
        return client.get(f"{s.service_url}/v1/health", headers={"Authorization": f"Bearer {s.service_token}"}).status_code == 200
    except httpx.HTTPError:
        return False


def _opencv_capture(source: str | int):  # pragma: no cover - hardware
    import cv2

    if isinstance(source, str) and source.startswith("rtsp"):
        os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp|stimeout;5000000")
        cap = cv2.VideoCapture(source, cv2.CAP_FFMPEG)
    else:
        cap = cv2.VideoCapture(source)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    return cap


def main() -> None:  # pragma: no cover - process entry point
    parser = argparse.ArgumentParser(description="Smart Parking ANPR camera agent")
    parser.add_argument("--check", action="store_true", help="fetch config, test the service and exit")
    args = parser.parse_args()
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")
    s = AgentSettings.from_env()
    if is_insecure_remote(s.backend_url):
        log.warning("ANPR_BACKEND_URL uses plain HTTP to another machine; the device token crosses the LAN unencrypted. Prefer HTTPS.")
    timeout = httpx.Timeout(10.0, connect=3.0)
    with httpx.Client(timeout=timeout, verify=s.verify_tls, trust_env=False) as backend, httpx.Client(timeout=timeout, trust_env=False) as recognizer:
        agent = CameraAgent(s, backend, recognizer)
        if args.check:
            cfg = agent.fetch_config()
            print(json.dumps({"camera": cfg.get("camera"), "service_ready": _service_ok(recognizer, s), "spooled": len(agent.spool)}, indent=2))
            return
        stopping = {"flag": False}
        signal.signal(signal.SIGINT, lambda *_: stopping.update(flag=True))
        signal.signal(signal.SIGTERM, lambda *_: stopping.update(flag=True))
        agent.run(stop=lambda: stopping["flag"])


if __name__ == "__main__":  # pragma: no cover
    main()
