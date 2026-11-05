import base64
import json

from test_agent import Backend, make_agent, frame, form


def test_mixed_candidates_exclude_review_only(tmp_path):
    backend = Backend()
    clock = {"now": 1000.0}

    result = {
        "status": "recognized",
        "candidates": [
            {
                "text": "NW KL-6036",
                "confidence": 0.95,
                "review_required": True,
            },
            {
                "text": "KL6036",
                "confidence": 0.90,
            },
        ],
        "processing_ms": 40,
        "plate_crop_jpeg_base64": base64.b64encode(
            b"\xff\xd8\xffcrop"
        ).decode(),
    }

    agent = make_agent(tmp_path, backend, [result], clock)

    outcome = agent.process_frame(frame())

    assert outcome == "sent"
    assert len(backend.events) == 1

    fields = form(backend.events[0])
    sent_candidates = json.loads(fields["candidates"])

    assert sent_candidates == [
        {"text": "KL6036", "confidence": 0.90}
    ]

    assert all(
        c["text"] != "NW KL-6036"
        for c in sent_candidates
    )
