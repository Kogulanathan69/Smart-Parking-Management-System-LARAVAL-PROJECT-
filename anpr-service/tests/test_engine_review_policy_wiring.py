"""Review-only integration safety tests."""

import inspect

from anpr_service.engine import OnnxPlateEngine, Recognition


def test_review_suggestion_stays_out_of_normal_candidates():
    result = Recognition(
        status="recognized",
        candidates=[
            {"text": "KL6036", "confidence": 0.9997}
        ],
        review_suggestions=[
            {
                "text": "NW KL-6036",
                "confidence": 0.1911,
                "review_required": True,
                "source": "region_ocr",
            }
        ],
    )

    body = result.to_json()

    assert len(body["candidates"]) == 1
    assert body["candidates"][0]["text"] == "KL6036"
    assert body["review_suggestions"][0]["text"] == "NW KL-6036"
    assert body["review_suggestions"][0]["review_required"] is True


def test_engine_uses_staff_review_policy():
    from anpr_service.staff_review_policy import (
        select_staff_review_suggestion,
    )

    assert callable(select_staff_review_suggestion)

    source = inspect.getsource(OnnxPlateEngine.recognize)

    assert "recognize_plate_regions" in source


def test_engine_region_helper_wires_review_policy():
    import inspect
    from anpr_service.region_integration import recognize_plate_regions

    source = inspect.getsource(recognize_plate_regions)

    assert "select_staff_review_suggestion" in source
