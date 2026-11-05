"""Optional smoke test with the real ONNX models (ANPR_TEST_MODELS_DIR=<provisioned models dir>).

This only proves the pipeline loads and runs offline end to end. It is NOT an accuracy test.
"""

import os
from pathlib import Path

import pytest

from anpr_service.config import ServiceSettings
from anpr_service.engine import OnnxPlateEngine
from anpr_service.imaging import decode
from tests.conftest import TOKEN, jpeg_bytes

MODELS = os.environ.get("ANPR_TEST_MODELS_DIR")
pytestmark = pytest.mark.skipif(not MODELS, reason="set ANPR_TEST_MODELS_DIR to run the real-model smoke test")


def test_real_models_load_and_run_offline():
    engine = OnnxPlateEngine(ServiceSettings(token=TOKEN, models_dir=Path(MODELS)))
    engine.load()
    assert engine.ready() == (True, "ok")
    result = engine.recognize(decode(jpeg_bytes(320, 110, "CAB 1234"), 4 << 20, 25_000_000))
    assert result.status in ("recognized", "unreadable")
    for c in result.candidates:
        assert 0.0 <= c["confidence"] <= 1.0 and c["text"].isalnum()
    print("smoke result:", result.to_json() | {"plate_crop_jpeg_base64": "..."})
