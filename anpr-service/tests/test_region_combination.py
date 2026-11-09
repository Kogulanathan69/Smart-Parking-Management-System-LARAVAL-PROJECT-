from anpr_service.normalizer import PROVINCES, normalize


def combine_plate_regions(province: str, registration: str):
    province = province.strip().upper()
    registration = registration.strip().upper()

    if province not in PROVINCES:
        return None

    result = normalize(f"{province} {registration}")

    if not result["valid"]:
        return None

    if result["format"] != "modern_province":
        return None

    if result["province"] != province:
        return None

    if result["confusion_corrected"]:
        return None

    return result["display"]


def test_nw_province_combination():
    assert combine_plate_regions("NW", "KL6036") == "NW KL-6036"


def test_wp_province_combination():
    assert combine_plate_regions("WP", "NC9024") == "WP NC-9024"


def test_invalid_province_rejected():
    assert combine_plate_regions("XX", "KL6036") is None


def test_invalid_registration_rejected():
    assert combine_plate_regions("NW", "LL633") is None


def test_empty_province_rejected():
    assert combine_plate_regions("", "KL6036") is None


def test_confused_registration_rejected():
    assert combine_plate_regions("NW", "KL6O36") is None
