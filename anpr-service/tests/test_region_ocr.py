from anpr_service.region_ocr import combine_plate_regions


def test_nw_combination():
    assert combine_plate_regions("NW", "KL6036") == "NW KL-6036"


def test_wp_combination():
    assert combine_plate_regions("WP", "NC9024") == "WP NC-9024"


def test_invalid_province():
    assert combine_plate_regions("XX", "KL6036") is None


def test_invalid_registration():
    assert combine_plate_regions("NW", "LL633") is None


def test_empty_province():
    assert combine_plate_regions("", "KL6036") is None


def test_confused_registration():
    assert combine_plate_regions("NW", "KL6O36") is None
