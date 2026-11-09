from anpr_service.staff_review_policy import (
    select_staff_review_suggestion,
)


def test_low_confidence_plate_is_review_only():
    result = select_staff_review_suggestion(
        [("NW", 0.7927), ("NW", 0.5101)],
        [("KL6036", 0.1911), ("KL6036", 0.9997)],
    )

    assert result is not None
    assert result["text"] == "NW KL-6036"
    assert result["review_required"] is True
    assert result["source"] == "region_ocr"
    assert result["confidence"] == 0.1911


def test_invalid_province_is_rejected():
    result = select_staff_review_suggestion(
        [("ZZ", 0.90), ("ZZ", 0.85)],
        [("KL6036", 0.90), ("KL6036", 0.85)],
    )

    assert result is None


def test_single_province_reading_is_rejected():
    result = select_staff_review_suggestion(
        [("NW", 0.90), ("", 0.0)],
        [("KL6036", 0.90), ("KL6036", 0.85)],
    )

    assert result is None


def test_very_weak_registration_is_rejected():
    result = select_staff_review_suggestion(
        [("NW", 0.90), ("NW", 0.85)],
        [("KL6036", 0.10), ("KL6036", 0.99)],
    )

    assert result is None
