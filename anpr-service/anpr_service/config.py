"""Settings from environment variables (or a local .env file next to the service). No Internet is needed at runtime."""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent


def _load_dotenv(path: Path) -> None:
    """Minimal KEY=VALUE loader so Windows deployments need no extra package. Existing env vars win."""
    if not path.is_file():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def _bool(name: str, default: bool) -> bool:
    return os.environ.get(name, str(default)).strip().lower() in ("1", "true", "yes", "on")


class ConfigError(RuntimeError):
    pass


@dataclass(frozen=True)
class ServiceSettings:
    token: str
    bind_host: str = "127.0.0.1"
    port: int = 8765
    engine: str = "onnx"
    models_dir: Path = BASE_DIR / "models"
    detector_model: str = "yolo-v9-t-384-license-plates-end2end.onnx"
    detector_conf: float = 0.35
    ocr_model: str = "cct_xs_v2_global.onnx"
    ocr_config: str = "cct_xs_v2_global_plate_config.yaml"
    require_manifest: bool = True
    ocr_full_frame_fallback: bool = True
    adaptive_review_enabled: bool = False
    max_image_bytes: int = 4 * 1024 * 1024
    max_image_pixels: int = 25_000_000
    max_candidates: int = 3
    max_concurrency: int = 2
    queue_timeout_s: float = 5.0
    threads: int = 2
    allow_lan_bind: bool = False
    extra: dict = field(default_factory=dict)

    @classmethod
    def from_env(cls) -> "ServiceSettings":
        _load_dotenv(BASE_DIR / ".env")
        token = os.environ.get("ANPR_SERVICE_TOKEN", "")
        if len(token) < 32:
            raise ConfigError("ANPR_SERVICE_TOKEN must be set to a random value of at least 32 characters.")
        s = cls(
            token=token,
            bind_host=os.environ.get("ANPR_BIND_HOST", "127.0.0.1"),
            port=int(os.environ.get("ANPR_PORT", "8765")),
            engine=os.environ.get("ANPR_ENGINE", "onnx"),
            models_dir=Path(os.environ.get("ANPR_MODELS_DIR", str(BASE_DIR / "models"))),
            detector_model=os.environ.get("ANPR_DETECTOR_MODEL", cls.detector_model),
            detector_conf=float(os.environ.get("ANPR_DETECTOR_CONF", "0.35")),
            ocr_model=os.environ.get("ANPR_OCR_MODEL", cls.ocr_model),
            ocr_config=os.environ.get("ANPR_OCR_CONFIG", cls.ocr_config),
            require_manifest=_bool("ANPR_REQUIRE_MANIFEST", True),
            ocr_full_frame_fallback=_bool("ANPR_OCR_FULL_FRAME_FALLBACK", True),
            adaptive_review_enabled=_bool("ANPR_ADAPTIVE_REVIEW_ENABLED", False),
            max_image_bytes=int(os.environ.get("ANPR_MAX_IMAGE_BYTES", str(4 * 1024 * 1024))),
            max_candidates=max(1, min(5, int(os.environ.get("ANPR_MAX_CANDIDATES", "3")))),
            max_concurrency=max(1, int(os.environ.get("ANPR_MAX_CONCURRENCY", "2"))),
            threads=max(1, int(os.environ.get("ANPR_THREADS", "2"))),
            allow_lan_bind=_bool("ANPR_ALLOW_LAN_BIND", False),
        )
        if s.engine not in ("onnx", "stub"):
            raise ConfigError("ANPR_ENGINE must be 'onnx' (or 'stub' for tests only).")
        if s.bind_host not in ("127.0.0.1", "::1", "localhost") and not s.allow_lan_bind:
            raise ConfigError("The recognition service binds to loopback only. Set ANPR_ALLOW_LAN_BIND=true deliberately to expose it on the LAN.")
        return s
