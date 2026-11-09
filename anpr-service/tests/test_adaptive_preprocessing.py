import numpy as np
import pytest

from anpr_service.adaptive_preprocessing import (
    rotate_expanded,
    crop_region,
)


def test_zero_rotation_preserves_image():
    image = np.zeros((100, 200, 3), dtype=np.uint8)
    image[20:80, 40:160] = 255

    result = rotate_expanded(image, 0)

    assert result.shape == image.shape
    assert np.array_equal(result, image)


def test_expanded_rotation_increases_canvas():
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    result = rotate_expanded(image, 30)

    assert result.shape[0] > image.shape[0]
    assert result.shape[1] > image.shape[1]


def test_crop_region_dimensions():
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    result = crop_region(image, 0.0, 0.1, 0.3, 0.65)

    assert result.shape == (55, 60, 3)


def test_crop_region_is_independent_copy():
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    result = crop_region(image, 0.0, 0.0, 0.5, 0.5)
    result[:] = 255

    assert np.all(image == 0)


@pytest.mark.parametrize(
    "coordinates",
    [
        (-0.1, 0, 0.5, 1),
        (0.5, 0, 0.3, 1),
        (0, 0.8, 1, 0.2),
        (0, 0, 1.1, 1),
    ],
)
def test_invalid_crop_coordinates(coordinates):
    image = np.zeros((100, 200, 3), dtype=np.uint8)

    with pytest.raises(ValueError):
        crop_region(image, *coordinates)


def test_empty_images_rejected():
    empty = np.empty((0, 0, 3), dtype=np.uint8)

    with pytest.raises(ValueError):
        rotate_expanded(empty, 30)

    with pytest.raises(ValueError):
        crop_region(empty, 0, 0, 1, 1)
