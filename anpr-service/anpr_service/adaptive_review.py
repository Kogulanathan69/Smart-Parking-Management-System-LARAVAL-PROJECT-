"""Experimental adaptive staff-review suggestion selection."""

from .staff_review_policy import select_staff_review_suggestion


def select_adaptive_review_suggestions(
    readings: list[dict],
) -> list[dict]:
    """Evaluate each rotation separately using existing safety policy.

    Suggestions remain unverified and require staff confirmation.
    """
    suggestions = []
    angles = sorted({float(r["angle"]) for r in readings})

    for angle in angles:
        group = [
            r for r in readings
            if float(r["angle"]) == angle
        ]

        province = [
            (r["text"], float(r["confidence"]))
            for r in group
            if r["region"] in ("province_a", "province_b")
        ]

        registration = [
            (r["text"], float(r["confidence"]))
            for r in group
            if r["region"] in (
                "registration_a",
                "registration_b",
            )
        ]

        suggestion = select_staff_review_suggestion(
            province,
            registration,
        )

        if suggestion is not None:
            suggestions.append({
                **suggestion,
                "angle": angle,
            })

    return suggestions
