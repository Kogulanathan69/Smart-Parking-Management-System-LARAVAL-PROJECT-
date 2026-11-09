import base64

from test_agent import Backend, make_agent, frame


def test_review_only_candidate_is_not_automatically_sent(tmp_path):
    backend = Backend()
    clock = {"now": 1000.0}

    result = {
        "status": "recognized",
        "candidates": [
            {
                "text": "NW KL-6036",
                "confidence": 0.7927,
                "review_required": True,
            }
        ],
        "processing_ms": 40,
        "plate_crop_jpeg_base64": base64.b64encode(
            b"\xff\xd8\xffcrop"
        ).decode(),
    }

    agent = make_agent(tmp_path, backend, [result], clock)

    outcome = agent.process_frame(frame())

    assert outcome == "unreadable"
    assert backend.events == []
