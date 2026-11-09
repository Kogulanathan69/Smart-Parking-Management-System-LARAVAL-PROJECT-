from threading import Lock
from types import SimpleNamespace

import numpy as np

from anpr_service.region_integration import recognize_plate_regions


class BoundaryAwareFakeOCR:
    def __init__(self):
        self.calls = []

    def run(self, image, return_confidence=False):
        width = image.shape[1]
        self.calls.append(width)

        # Synthetic crop: width = 200.
        # The expected boundaries are 28%, 30%, 12%, 15%.
        readings = {
            56: ("NW", 0.7882),
            60: ("NW", 0.8116),
            176: ("KL6036", 0.9906),
            170: ("KL6036", 0.9997),
        }

        text, confidence = readings.get(width, ("", 0.0))

        return [
            SimpleNamespace(
                plate=text,
                char_probs=np.full(len(text), confidence),
            )
        ]


def test_region_ocr_uses_verified_crop_boundaries():
    crop = np.zeros((60, 200, 3), dtype=np.uint8)
    ocr = BoundaryAwareFakeOCR()

    result = recognize_plate_regions(ocr, Lock(), crop)

    assert ocr.calls == [56, 60, 176, 170]
    assert result is not None
    assert result["text"] == "NW KL-6036"
    assert result["review_required"] is True
    assert result["source"] == "region_ocr"
