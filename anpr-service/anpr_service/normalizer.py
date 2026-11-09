"""Sri Lankan plate normalisation — a line-for-line port of app/Support/Anpr/SriLankaPlateNormalizer.php.

The Laravel backend re-normalises every reading itself; this copy exists so the camera agent can
de-duplicate locally and so evaluation reports use the same rules. Parity is enforced by the shared
fixture tests/fixtures/plate_cases.json, which both test suites read.
"""

from __future__ import annotations

import re
from typing import Optional, TypedDict

PROVINCES = ("WP", "CP", "SP", "NP", "EP", "NW", "NC", "UP", "SG")
LETTER_FOR_DIGIT = str.maketrans({"0": "O", "1": "I", "8": "B", "5": "S", "2": "Z", "6": "G"})
DIGIT_FOR_LETTER = str.maketrans({"O": "0", "Q": "0", "D": "0", "I": "1", "L": "1", "B": "8", "S": "5", "Z": "2", "G": "6"})
_SRI_SEPARATORS = ("ශ්‍රී", "ශ්රී", "ශ්‍ර", "ශ්‍")
_TOKEN_SPLIT = re.compile(r"[^A-Z0-9]+")


class Normalized(TypedDict):
    normalized: Optional[str]
    display: Optional[str]
    format: str
    province: Optional[str]
    confusion_corrected: bool
    valid: bool


def _empty() -> Normalized:
    return {"normalized": None, "display": None, "format": "unreadable", "province": None, "confusion_corrected": False, "valid": False}


def normalize(raw: Optional[str]) -> Normalized:
    if raw is None or raw.strip() == "":
        return _empty()
    text = raw.strip().upper()
    for sep in _SRI_SEPARATORS:
        text = text.replace(sep, "-")
    tokens = [t for t in _TOKEN_SPLIT.split(text) if t]
    compact = "".join(tokens)
    if compact == "" or len(compact) < 3 or len(compact) > 12:
        return _empty()

    for allow_confusion in (False, True):
        result = _match(compact, tokens, allow_confusion)
        if result:
            return result

    return {
        "normalized": compact,
        "display": "-".join(tokens),
        "format": "unknown",
        "province": None,
        "confusion_corrected": False,
        "valid": 4 <= len(compact) <= 10,
    }


def _match(compact: str, tokens: list[str], allow_confusion: bool) -> Optional[Normalized]:
    province = None
    body = compact
    first = tokens[0] if tokens else ""
    if compact[:2] in PROVINCES and (first in PROVINCES or len(compact) >= 8):
        province = compact[:2]
        body = compact[2:]

    for letters in (3, 2):
        if len(body) != letters + 4:
            continue
        l, d = body[:letters], body[letters:]
        l2, d2, changed = l, d, False
        if allow_confusion:
            l2 = l.translate(LETTER_FOR_DIGIT)
            d2 = d.translate(DIGIT_FOR_LETTER)
            changed = l2 != l or d2 != d
        if re.fullmatch(r"[A-Z]+", l2) and re.fullmatch(r"\d{4}", d2) and (changed if allow_confusion else True):
            return {
                "normalized": (province or "") + l2 + d2,
                "display": ((province + " ") if province else "") + l2 + "-" + d2,
                "format": "modern_province" if province else "modern",
                "province": province,
                "confusion_corrected": changed,
                "valid": True,
            }

    if not province and len(tokens) == 2:
        series = tokens[0].translate(DIGIT_FOR_LETTER) if allow_confusion else tokens[0]
        if re.fullmatch(r"\d{1,3}", series):
            number = tokens[1].translate(DIGIT_FOR_LETTER) if allow_confusion else tokens[1]
            changed = series != tokens[0] or number != tokens[1]
            if re.fullmatch(r"\d{4}", number) and (changed if allow_confusion else True):
                return {
                    "normalized": series + number,
                    "display": series + "-" + number,
                    "format": "legacy_numeric",
                    "province": None,
                    "confusion_corrected": changed,
                    "valid": True,
                }
    return None


def identifier(plate: str) -> str:
    """Same rule as Vehicle::normalizePlate on the backend (exact matching only)."""
    return re.sub(r"[^A-Za-z0-9]", "", plate).upper()
