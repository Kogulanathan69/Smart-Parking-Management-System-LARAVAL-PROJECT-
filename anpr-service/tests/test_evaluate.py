from anpr_service.evaluate import evaluate, levenshtein


def test_levenshtein():
    assert levenshtein("CAB1234", "CAB1234") == 0
    assert levenshtein("CAB1234", "CA81234") == 1
    assert levenshtein("", "ABC") == 3


def test_metrics():
    results = {
        "a.jpg": {"status": "recognized", "candidates": [{"text": "CAB-1234", "confidence": 0.97}]},   # exact, accepted
        "b.jpg": {"status": "recognized", "candidates": [{"text": "CAB-1284", "confidence": 0.95}]},   # wrong but accepted -> false match
        "c.jpg": {"status": "recognized", "candidates": [{"text": "KX-4455", "confidence": 0.60}]},    # exact, below threshold
        "d.jpg": {"status": "unreadable", "candidates": []},                                           # missed
        "e.jpg": {"status": "recognized", "candidates": [{"text": "QQ-0000", "confidence": 0.99}]},    # phantom on empty image
    }
    samples = [("a.jpg", "WP CAB-1234".replace("WP ", "")), ("b.jpg", "CAB 1234"), ("c.jpg", "KX-4455"), ("d.jpg", "NP AB-1111"), ("e.jpg", None)]
    s = evaluate(samples, lambda p: results[p], threshold=0.90)["summary"]
    assert s["images_with_plate"] == 4 and s["images_without_plate"] == 1
    assert s["detection_rate_pct"] == 75.0
    assert s["exact_match_rate_pct"] == 50.0
    assert s["accepted_rate_pct"] == 50.0
    assert s["accepted_precision_pct"] == 50.0
    assert s["false_match_at_threshold_pct"] == 25.0
    assert s["phantom_reads_on_empty_pct"] == 100.0
    assert s["mean_cer"] == round((0 + 1 / 7 + 0 + 1) / 4, 4)


def test_engine_errors_count_as_misses():
    def boom(_):
        raise RuntimeError("x")
    s = evaluate([("a.jpg", "CAB-1234")], boom, 0.9)["summary"]
    assert s["errors"] == 1 and s["detection_rate_pct"] == 0.0
