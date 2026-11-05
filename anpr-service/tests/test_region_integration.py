from threading import Lock
from types import SimpleNamespace

import numpy as np

from anpr_service.region_integration import recognize_plate_regions


class FakeOCR:
    def __init__(self, readings):
        self.readings = iter(readings)
        self.calls = []

    def run(self, image, return_confidence=False):
        self.calls.append(image.shape)
        text, confidence = next(self.readings)

        return [
            SimpleNamespace(
                plate=text,
                char_probs=np.full(len(text), confidence),
            )
        ]


def test_region_ocr_returns_review_only_candidate():
    crop = np.zeros((60, 200, 3), dtype=np.uint8)

    ocr = FakeOCR([
        ("NW", 0.90),
        ("NW", 0.85),
        ("KL6036", 0.92),
        ("KL6036", 0.88),
    ])

    result = recognize_plate_regions(ocr, Lock(), crop)

    assert result is not None
    assert result["text"] == "NW KL-6036"
    assert result["review_required"] is True
    assert result["confidence"] == 0.85
    assert len(ocr.calls) == 4


def test_region_ocr_rejects_invalid_province():
    crop = np.zeros((60, 200, 3), dtype=np.uint8)

    ocr = FakeOCR([
        ("ZZ", 0.90),
        ("ZZ", 0.90),
        ("KL6036", 0.95),
        ("KL6036", 0.95),
    ])

    assert recognize_plate_regions(ocr, Lock(), crop) is None


def test_region_ocr_rejects_small_crop():
    crop = np.zeros((5, 10, 3), dtype=np.uint8)

    ocr = FakeOCR([])

    assert recognize_plate_regions(ocr, Lock(), crop) is None
    assert ocr.calls == []
