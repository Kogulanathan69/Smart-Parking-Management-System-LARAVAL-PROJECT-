import numpy as np
import pytest

from anpr_service.rotation_preprocessing import generate_rotation_variants


def test_rotation_preserves_original_crop():
    crop = np.zeros((60, 200, 3), dtype=np.uint8)
    crop[20:40, 50:150] = 255

    variants = generate_rotation_variants(crop)

    assert len(variants) >= 1
    assert np.array_equal(variants[0], crop)


def test_rotation_variants_preserve_dimensions():
    crop = np.zeros((60, 200, 3), dtype=np.uint8)

    variants = generate_rotation_variants(
        crop,
        angles=(-10, 0, 10),
    )

    assert len(variants) == 3
    assert all(v.shape == crop.shape for v in variants)


def test_rotation_rejects_empty_crop():
    crop = np.empty((0, 0, 3), dtype=np.uint8)

    with pytest.raises(ValueError):
        generate_rotation_variants(crop)
