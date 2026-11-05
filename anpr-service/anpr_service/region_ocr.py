"""Validate independently recognized Sri Lankan plate regions."""

from .normalizer import PROVINCES, normalize


def combine_plate_regions(province: str, registration: str) -> str | None:
    province = province.strip().upper()
    registration = registration.strip().upper()

    if province not in PROVINCES:
        return None

    result = normalize(f"{province} {registration}")

    if (
        not result["valid"]
        or result["format"] != "modern_province"
        or result["province"] != province
        or result["confusion_corrected"]
    ):
        return None

    return result["display"]
