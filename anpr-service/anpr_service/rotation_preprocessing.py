"""Experimental rotation variants for plate OCR.

This helper does not perform verification or parking actions.
"""

import cv2
import numpy as np


def generate_rotation_variants(
    crop: np.ndarray,
    angles: tuple[float, ...] = (0, -10, 10),
) -> list[np.ndarray]:
    """Return the original crop and optional rotated variants."""

    if crop is None or crop.size == 0:
        raise ValueError("Plate crop must not be empty")

    height, width = crop.shape[:2]
    center = (width / 2.0, height / 2.0)

    variants = [crop.copy()]

    for angle in angles:
        if angle == 0:
            continue

        matrix = cv2.getRotationMatrix2D(center, angle, 1.0)

        rotated = cv2.warpAffine(
            crop,
            matrix,
            (width, height),
            flags=cv2.INTER_LINEAR,
            borderMode=cv2.BORDER_REPLICATE,
        )

        variants.append(rotated)

    return variants
