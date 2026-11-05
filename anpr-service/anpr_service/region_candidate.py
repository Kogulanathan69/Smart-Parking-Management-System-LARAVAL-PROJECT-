"""Experimental region OCR candidate selection.

Produces review-only candidates; not automatic vehicle identification.
"""

from .region_ocr import combine_plate_regions


def select_region_candidate(
    province_readings: list[tuple[str, float]],
    registration_readings: list[tuple[str, float]],
    province_min_confidence: float = 0.50,
    registration_min_confidence: float = 0.50,
) -> dict | None:
    provinces = [
        (text, conf)
        for text, conf in province_readings
        if conf >= province_min_confidence
    ]

    registrations = [
        (text, conf)
        for text, conf in registration_readings
        if conf >= registration_min_confidence
    ]

    # Require matching readings from two different crop variants.
    province_counts = {}
    for text, _ in provinces:
        province_counts[text] = province_counts.get(text, 0) + 1

    registration_counts = {}
    for text, _ in registrations:
        registration_counts[text] = registration_counts.get(text, 0) + 1

    candidates = []

    for province, province_count in province_counts.items():
        if province_count < 2:
            continue

        for registration, registration_count in registration_counts.items():
            if registration_count < 2:
                continue

            plate = combine_plate_regions(province, registration)

            if plate is None:
                continue

            province_conf = min(
                conf for text, conf in provinces if text == province
            )
            registration_conf = min(
                conf for text, conf in registrations if text == registration
            )

            candidates.append({
                "text": plate,
                "confidence": round(
                    min(province_conf, registration_conf), 4
                ),
                "review_required": True,
            })

    if not candidates:
        return None

    return max(candidates, key=lambda item: item["confidence"])
