from anpr_service.region_candidate import select_region_candidate


def test_default_threshold_rejects_weak_registration():
    result = select_region_candidate(
        [("NW", 0.7927), ("NW", 0.5101)],
        [("KL6036", 0.1911), ("KL6036", 0.9997)],
    )
    assert result is None


def test_experimental_threshold_returns_review_candidate():
    result = select_region_candidate(
        [("NW", 0.7927), ("NW", 0.5101)],
        [("KL6036", 0.1911), ("KL6036", 0.9997)],
        registration_min_confidence=0.15,
    )
    assert result == {
        "text": "NW KL-6036",
        "confidence": 0.1911,
        "review_required": True,
    }


def test_invalid_province_rejected():
    result = select_region_candidate(
        [("XX", 0.99), ("XX", 0.99)],
        [("KL6036", 0.99), ("KL6036", 0.99)],
    )
    assert result is None


def test_single_reading_not_enough():
    result = select_region_candidate(
        [("NW", 0.99)],
        [("KL6036", 0.99), ("KL6036", 0.99)],
    )
    assert result is None


def test_strong_matching_readings():
    result = select_region_candidate(
        [("NW", 0.92), ("NW", 0.90)],
        [("KL6036", 0.98), ("KL6036", 0.96)],
    )
    assert result == {
        "text": "NW KL-6036",
        "confidence": 0.90,
        "review_required": True,
    }
