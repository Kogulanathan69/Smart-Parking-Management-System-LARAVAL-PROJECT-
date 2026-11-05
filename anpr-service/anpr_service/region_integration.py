"""Experimental region OCR for staff-review suggestions only."""

import cv2
import numpy as np

from .staff_review_policy import select_staff_review_suggestion


def recognize_plate_regions(ocr, lock, crop: np.ndarray) -> dict | None:
    if crop is None or crop.size == 0:
        return None

    height, width = crop.shape[:2]

    if height < 8 or width < 20:
        return None

    def read_region(region: np.ndarray) -> tuple[str, float]:
        rgb = cv2.cvtColor(region, cv2.COLOR_BGR2RGB)

        with lock:
            pred = ocr.run(rgb, return_confidence=True)[0]

        text = (pred.plate or "").strip().replace("_", "").upper()

        if not text:
            return "", 0.0

        probs = np.asarray(
            pred.char_probs if pred.char_probs is not None else [],
            dtype=float,
        ).ravel()

        probs = probs[:len(text)]

        confidence = (
            float(np.clip(probs.min(), 0, 1))
            if probs.size else 0.0
        )

        return text, confidence

    province_readings = [
        read_region(crop[:, :max(1, int(width * 0.28))]),
        read_region(crop[:, :max(1, int(width * 0.30))]),
    ]

    registration_readings = [
        read_region(crop[:, int(width * 0.12):]),
        read_region(crop[:, int(width * 0.15):]),
    ]

    return select_staff_review_suggestion(
        province_readings,
        registration_readings,
    )
