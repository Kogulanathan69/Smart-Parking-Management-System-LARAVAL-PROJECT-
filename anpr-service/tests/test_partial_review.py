from anpr_service.partial_review import collect_partial_review_clues


def reading(region, text, confidence, angle=30):
    return {
        "region": region,
        "text": text,
        "confidence": confidence,
        "angle": angle,
    }


def test_valid_partial_clues():
    result = collect_partial_review_clues([
        reading("province_a", "SP", 0.87),
        reading("registration_a", "XJ2284", 0.95),
    ])

    assert result["province_clues"][0]["text"] == "SP"
    assert result["registration_clues"][0]["text"] == "XJ2284"
    assert result["review_required"] is True
    assert result["verified"] is False


def test_low_confidence_province_rejected():
    result = collect_partial_review_clues([
        reading("province_a", "SP", 0.30),
    ])

    assert result["province_clues"] == []


def test_invalid_province_rejected():
    result = collect_partial_review_clues([
        reading("province_a", "ZZ", 0.99),
    ])

    assert result["province_clues"] == []


def test_no_automatic_plate_combination():
    result = collect_partial_review_clues([
        reading("province_a", "SP", 0.90),
        reading("registration_a", "XJ2284", 0.99),
    ])

    assert "text" not in result
    assert "plate" not in result
    assert result["verified"] is False
