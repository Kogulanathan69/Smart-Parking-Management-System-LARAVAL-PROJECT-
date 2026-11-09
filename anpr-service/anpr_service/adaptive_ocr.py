"""Experimental adaptive OCR diagnostics; not connected to production."""

import cv2
import numpy as np

from .adaptive_preprocessing import crop_region, rotate_expanded


def collect_adaptive_readings(
    ocr,
    lock,
    image: np.ndarray,
    angles: tuple[float, ...] = (0, 10, 20, 30),
) -> list[dict]:
    """Collect OCR readings from rotation and region variants.

    Results are diagnostic observations, not verified plate numbers.
    """
    if image is None or image.size == 0:
        raise ValueError("Image must not be empty")

    readings = []

    regions = (
        ("full", (0.0, 0.0, 1.0, 1.0)),
        ("province_a", (0.0, 0.10, 0.30, 0.65)),
        ("province_b", (0.0, 0.20, 0.35, 0.65)),
        ("registration_a", (0.12, 0.0, 1.0, 1.0)),
        ("registration_b", (0.15, 0.0, 1.0, 1.0)),
    )

    for angle in angles:
        rotated = rotate_expanded(image, angle)

        for region_name, coordinates in regions:
            region = crop_region(rotated, *coordinates)
            rgb = cv2.cvtColor(region, cv2.COLOR_BGR2RGB)

            with lock:
                prediction = ocr.run(
                    rgb,
                    return_confidence=True,
                )[0]

            text = (
                (prediction.plate or "")
                .strip()
                .replace("_", "")
                .upper()
            )

            probabilities = np.asarray(
                prediction.char_probs
                if prediction.char_probs is not None
                else [],
                dtype=float,
            ).ravel()

            probabilities = probabilities[:len(text)]

            confidence = (
                float(np.clip(probabilities.min(), 0, 1))
                if text and probabilities.size
                else 0.0
            )

            readings.append({
                "angle": float(angle),
                "region": region_name,
                "text": text,
                "confidence": round(confidence, 4),
            })

    return readings
