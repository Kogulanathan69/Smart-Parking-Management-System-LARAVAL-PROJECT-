import json
from pathlib import Path

import pytest

from anpr_service import normalizer

CASES = json.loads((Path(__file__).parent / "fixtures" / "plate_cases.json").read_text(encoding="utf-8"))["cases"]


@pytest.mark.parametrize("case", CASES, ids=[c["raw"] for c in CASES])
def test_matches_php_normaliser(case):
    n = normalizer.normalize(case["raw"])
    assert n["normalized"] == case["normalized"]
    assert n["display"] == case["display"]
    assert n["format"] == case["format"]
    assert n["province"] == case["province"]
    assert n["confusion_corrected"] is case["corrected"]


def test_none_is_unreadable():
    assert normalizer.normalize(None)["format"] == "unreadable"


def test_identifier_matches_vehicle_rule():
    assert normalizer.identifier("wp cab-1234") == "WPCAB1234"
