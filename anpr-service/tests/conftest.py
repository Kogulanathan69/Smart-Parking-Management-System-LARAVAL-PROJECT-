import sys
from pathlib import Path

import cv2
import numpy as np
import pytest

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from anpr_service.config import ServiceSettings  # noqa: E402

TOKEN = "t" * 40


@pytest.fixture
def settings(tmp_path):
    return ServiceSettings(token=TOKEN, engine="stub", models_dir=tmp_path / "models")


def jpeg_bytes(w=320, h=120, text="CAB 1234") -> bytes:
    img = np.full((h, w, 3), 255, np.uint8)
    cv2.putText(img, text, (10, h // 2 + 15), cv2.FONT_HERSHEY_SIMPLEX, 1.4, (0, 0, 0), 3)
    return cv2.imencode(".jpg", img)[1].tobytes()


@pytest.fixture
def auth():
    return {"Authorization": f"Bearer {TOKEN}"}
