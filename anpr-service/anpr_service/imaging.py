"""Safe image decoding: size, type (magic bytes) and pixel-count limits before anything reaches the models."""

from __future__ import annotations

import os

# Must be set before cv2 is imported anywhere: caps decompression-bomb images inside OpenCV itself.
os.environ.setdefault("OPENCV_IO_MAX_IMAGE_PIXELS", str(50_000_000))

import cv2  # noqa: E402
import numpy as np  # noqa: E402


class ImageRejected(ValueError):
    def __init__(self, code: str, message: str, http_status: int = 422):
        super().__init__(message)
        self.code = code
        self.http_status = http_status


def sniff(data: bytes) -> str | None:
    if data[:3] == b"\xff\xd8\xff":
        return "image/jpeg"
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return "image/png"
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return "image/webp"
    return None


def decode(data: bytes, max_bytes: int, max_pixels: int) -> np.ndarray:
    """Return a BGR uint8 image or raise ImageRejected."""
    if not data:
        raise ImageRejected("image_missing", "No image was provided.")
    if len(data) > max_bytes:
        raise ImageRejected("image_too_large", "The image exceeds the size limit.", 413)
    if sniff(data) is None:
        raise ImageRejected("image_type_unsupported", "Only JPEG, PNG or WebP images are accepted.", 415)
    img = cv2.imdecode(np.frombuffer(data, dtype=np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        raise ImageRejected("image_undecodable", "The image could not be decoded.")
    h, w = img.shape[:2]
    if h * w > max_pixels or h < 16 or w < 16:
        raise ImageRejected("image_dimensions_invalid", "The image dimensions are outside the accepted range.")
    return img


def encode_jpeg(img: np.ndarray, quality: int = 85) -> bytes:
    ok, buf = cv2.imencode(".jpg", img, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
    if not ok:
        raise RuntimeError("JPEG encoding failed")
    return buf.tobytes()
