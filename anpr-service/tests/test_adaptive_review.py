from anpr_service.adaptive_review import (
    select_adaptive_review_suggestions,
)


def reading(angle, region, text, confidence):
    return {
        "angle": angle,
        "region": region,
        "text": text,
        "confidence": confidence,
    }


def test_valid_staff_review_suggestion():
    readings = [
        reading(30, "province_a", "SP", 0.87),
        reading(30, "province_b", "SP", 0.75),
        reading(30, "registration_a", "XJ2284", 0.99),
        reading(30, "registration_b", "XJ2284", 0.98),
    ]

    result = select_adaptive_review_suggestions(readings)

    assert len(result) == 1
    assert result[0]["text"] == "SP XJ-2284"
    assert result[0]["review_required"] is True
    assert result[0]["angle"] == 30.0


def test_low_confidence_province_rejected():
    readings = [
        reading(30, "province_a", "SP", 0.30),
        reading(30, "province_b", "SP", 0.40),
        reading(30, "registration_a", "XJ2284", 0.99),
        reading(30, "registration_b", "XJ2284", 0.98),
    ]

    assert select_adaptive_review_suggestions(readings) == []


def test_rotations_do_not_mix_readings():
    readings = [
        reading(10, "province_a", "SP", 0.90),
        reading(20, "province_b", "SP", 0.90),
        reading(10, "registration_a", "XJ2284", 0.99),
        reading(20, "registration_b", "XJ2284", 0.99),
    ]

    assert select_adaptive_review_suggestions(readings) == []
