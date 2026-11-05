"""Experimental plate preprocessing; not connected to production recognition."""

import cv2
import numpy as np


def rotate_expanded(image: np.ndarray, angle: float) -> np.ndarray:
    """Rotate an image while expanding the canvas to reduce clipping."""
    if image is None or image.size == 0:
        raise ValueError("Image must not be empty")

    height, width = image.shape[:2]
    center = (width / 2.0, height / 2.0)

    matrix = cv2.getRotationMatrix2D(center, angle, 1.0)

    cosine = abs(matrix[0, 0])
    sine = abs(matrix[0, 1])

    new_width = int(np.ceil(height * sine + width * cosine))
    new_height = int(np.ceil(height * cosine + width * sine))

    matrix[0, 2] += new_width / 2.0 - center[0]
    matrix[1, 2] += new_height / 2.0 - center[1]

    return cv2.warpAffine(
        image,
        matrix,
        (new_width, new_height),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_REPLICATE,
    )


def crop_region(
    image: np.ndarray,
    left: float,
    top: float,
    right: float,
    bottom: float,
) -> np.ndarray:
    """Extract a region using normalized coordinates between 0 and 1."""
    if image is None or image.size == 0:
        raise ValueError("Image must not be empty")

    if not (0 <= left < right <= 1 and 0 <= top < bottom <= 1):
        raise ValueError("Invalid normalized crop coordinates")

    height, width = image.shape[:2]

    x1 = int(width * left)
    x2 = max(x1 + 1, int(width * right))
    y1 = int(height * top)
    y2 = max(y1 + 1, int(height * bottom))

    return image[y1:y2, x1:x2].copy()
