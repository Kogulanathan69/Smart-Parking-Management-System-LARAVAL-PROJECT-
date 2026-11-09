"""Conservative staff-review-only OCR suggestion policy."""

from .region_candidate import select_region_candidate


def select_staff_review_suggestion(
    province_readings: list[tuple[str, float]],
    registration_readings: list[tuple[str, float]],
) -> dict | None:
    result = select_region_candidate(
        province_readings,
        registration_readings,
        province_min_confidence=0.50,
        registration_min_confidence=0.15,
    )

    if result is None:
        return None

    return {
        **result,
        "review_required": True,
        "source": "region_ocr",
    }
