import json
from unittest.mock import Mock

import pytest

from anpr_service.agent import CameraAgent


@pytest.mark.parametrize("valid", [True, False])
def test_partial_clue_forwarding(valid):
    agent = object.__new__(CameraAgent)
    agent.stats = {"frames": 0, "recognized": 0}
    agent._last_plate = None
    agent.config = {"recognition": {"send_full_frame": False}}
    agent.push = Mock(return_value="sent")
    agent.should_check = Mock(return_value=True)
    agent.clock = Mock(return_value=1000.0)

    clues = {
        "province_clues": [
            {"text": "SP", "confidence": 0.72, "angle": 10}
        ],
        "registration_clues": [
            {"text": "XJ2284", "confidence": 0.68, "angle": 10}
        ],
        "review_required": True,
        "verified": False,
    }

    if not valid:
        clues["verified"] = True

    agent.recognize = Mock(return_value={
        "status": "recognized",
        "candidates": [{"text": "XJ2284", "confidence": 0.68}],
        "processing_ms": 25,
        "partial_review_clues": clues,
    })

    result = agent.process_frame(
        __import__("numpy").zeros((64, 128, 3), dtype="uint8")
    )

    assert result == "sent"
    fields = agent.push.call_args.args[0]

    if valid:
        assert json.loads(fields["partial_review_clues"]) == clues
    else:
        assert "partial_review_clues" not in fields
