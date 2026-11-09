import base64
import json

from test_agent import Backend, make_agent, frame, form


def test_agent_forwards_review_suggestion_separately(tmp_path):
    backend = Backend()
    clock = {"now": 1000.0}

    suggestion = {
        "text": "NW KL-6036",
        "confidence": 0.1911,
        "review_required": True,
        "source": "region_ocr",
    }

    result = {
        "status": "recognized",
        "candidates": [
            {"text": "KL6036", "confidence": 0.9997}
        ],
        "review_suggestions": [suggestion],
        "processing_ms": 40,
        "plate_crop_jpeg_base64": base64.b64encode(
            b"\xff\xd8\xffcrop"
        ).decode(),
    }

    agent = make_agent(tmp_path, backend, [result], clock)

    assert agent.process_frame(frame()) == "sent"
    assert len(backend.events) == 1

    fields = form(backend.events[0])

    assert json.loads(fields["candidates"]) == [
        {"text": "KL6036", "confidence": 0.9997}
    ]

    assert json.loads(fields["review_suggestions"]) == [
        suggestion
    ]

    assert "NW KL-6036" not in fields["candidates"]


def test_agent_omits_review_field_when_no_suggestion(tmp_path):
    backend = Backend()
    clock = {"now": 1000.0}

    result = {
        "status": "recognized",
        "candidates": [
            {"text": "KL6036", "confidence": 0.95}
        ],
        "review_suggestions": [],
        "processing_ms": 40,
        "plate_crop_jpeg_base64": base64.b64encode(
            b"\xff\xd8\xffcrop"
        ).decode(),
    }

    agent = make_agent(tmp_path, backend, [result], clock)

    assert agent.process_frame(frame()) == "sent"

    fields = form(backend.events[0])

    assert "review_suggestions" not in fields
    assert json.loads(fields["candidates"]) == [
        {"text": "KL6036", "confidence": 0.95}
    ]