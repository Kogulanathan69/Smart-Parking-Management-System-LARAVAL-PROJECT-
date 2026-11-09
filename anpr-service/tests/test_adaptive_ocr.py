from threading import Lock
from types import SimpleNamespace

import numpy as np
import pytest

from anpr_service.adaptive_ocr import collect_adaptive_readings


class FakeOCR:
    def __init__(self):
        self.calls = 0

    def run(self, image, return_confidence=False):
        self.calls += 1
        return [
            SimpleNamespace(
                plate="SPXJ2284",
                char_probs=np.full(8, 0.9),
            )
        ]


def test_collects_all_rotation_region_readings():
    ocr = FakeOCR()
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    readings = collect_adaptive_readings(
        ocr, Lock(), image
    )

    assert len(readings) == 20
    assert ocr.calls == 20
    assert all(r["text"] == "SPXJ2284" for r in readings)
    assert all(r["confidence"] == 0.9 for r in readings)


def test_custom_angles():
    ocr = FakeOCR()
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    readings = collect_adaptive_readings(
        ocr, Lock(), image, angles=(0, 30)
    )

    assert len(readings) == 10
    assert {r["angle"] for r in readings} == {0.0, 30.0}


def test_readings_include_region_names():
    ocr = FakeOCR()
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    readings = collect_adaptive_readings(
        ocr, Lock(), image, angles=(0,)
    )

    assert {r["region"] for r in readings} == {
        "full",
        "province_a",
        "province_b",
        "registration_a",
        "registration_b",
    }


def test_empty_image_rejected():
    ocr = FakeOCR()
    image = np.empty((0, 0, 3), dtype=np.uint8)

    with pytest.raises(ValueError):
        collect_adaptive_readings(ocr, Lock(), image)

    assert ocr.calls == 0
