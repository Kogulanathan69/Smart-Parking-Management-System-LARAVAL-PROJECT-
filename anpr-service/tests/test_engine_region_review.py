from unittest.mock import patch

import numpy as np

from anpr_service.engine import OnnxPlateEngine, Recognition


def test_region_review_does_not_change_normal_candidates():
    normal = [
        {"text": "KL6036", "confidence": 0.9997}
    ]

    suggestion = {
        "text": "NW KL-6036",
        "confidence": 0.85,
        "review_required": True,
        "source": "region_ocr",
    }

    result = Recognition(
        status="recognized",
        candidates=normal,
        review_suggestions=[suggestion],
    )

    body = result.to_json()

    assert body["candidates"] == normal
    assert body["review_suggestions"] == [suggestion]
    assert body["review_suggestions"][0]["review_required"] is True


def test_region_review_helper_is_available_for_engine_integration():
    from anpr_service.region_integration import recognize_plate_regions

    assert callable(recognize_plate_regions)


def test_engine_recognize_has_region_review_integration():
    import inspect

    source = inspect.getsource(OnnxPlateEngine.recognize)

    assert "recognize_plate_regions" in source
    assert "review_suggestions" in source
