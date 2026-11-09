"""Experimental partial OCR clues for human staff review."""

from .region_ocr import PROVINCES


def collect_partial_review_clues(
    readings: list[dict],
) -> dict:
    """Return unverified OCR observations without combining a plate."""

    province_clues = []
    registration_clues = []

    for reading in readings:
        text = str(reading.get("text", "")).strip().upper()
        confidence = float(reading.get("confidence", 0.0))
        region = str(reading.get("region", ""))
        angle = float(reading.get("angle", 0.0))

        if not text:
            continue

        if (
            region.startswith("province_")
            and text in PROVINCES
            and confidence >= 0.50
        ):
            province_clues.append({
                "text": text,
                "confidence": confidence,
                "angle": angle,
            })

        if (
            region.startswith("registration_")
            and confidence >= 0.15
            and 5 <= len(text) <= 8
            and text.isalnum()
        ):
            registration_clues.append({
                "text": text,
                "confidence": confidence,
                "angle": angle,
            })

    return {
        "province_clues": province_clues,
        "registration_clues": registration_clues,
        "review_required": True,
        "verified": False,
    }
