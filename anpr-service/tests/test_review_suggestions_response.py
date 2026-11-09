from anpr_service.engine import Recognition


def test_existing_recognition_response_unchanged():
    result = Recognition(
        status="recognized",
        candidates=[
            {"text": "KL6036", "confidence": 0.9997}
        ],
        processing_ms=40,
        detector="yolo_v9",
    )

    body = result.to_json()

    assert body["status"] == "recognized"
    assert body["candidates"] == [
        {"text": "KL6036", "confidence": 0.9997}
    ]
    assert "review_suggestions" not in body


def test_review_suggestions_are_separate_from_candidates():
    result = Recognition(
        status="recognized",
        candidates=[
            {"text": "KL6036", "confidence": 0.9997}
        ],
        review_suggestions=[
            {
                "text": "NW KL-6036",
                "confidence": 0.1911,
                "review_required": True,
                "source": "region_ocr",
            }
        ],
    )

    body = result.to_json()

    assert body["candidates"] == [
        {"text": "KL6036", "confidence": 0.9997}
    ]
    assert body["review_suggestions"] == [
        {
            "text": "NW KL-6036",
            "confidence": 0.1911,
            "review_required": True,
            "source": "region_ocr",
        }
    ]
