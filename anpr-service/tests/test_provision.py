import hashlib
import importlib.util
import io
from pathlib import Path

import pytest

spec = importlib.util.spec_from_file_location("provision", Path(__file__).resolve().parent.parent / "scripts" / "provision_models.py")
provision = importlib.util.module_from_spec(spec)
spec.loader.exec_module(provision)


def fake_models(content: bytes):
    return {"m.onnx": {"url": "https://example.invalid/m.onnx", "sha256": hashlib.sha256(content).hexdigest(), "role": "x", "package": "y"}}


def test_download_verifies_checksum(tmp_path):
    models = fake_models(b"model-bytes")
    provision.download(tmp_path, models, opener=lambda url: io.BytesIO(b"model-bytes"))
    assert provision.verify(tmp_path, models) == []

    bad = fake_models(b"expected")
    with pytest.raises(SystemExit, match="checksum mismatch"):
        provision.download(tmp_path / "b", bad, opener=lambda url: io.BytesIO(b"tampered"))
    assert not (tmp_path / "b" / "m.onnx").exists()


def test_verify_reports_missing_and_mismatch(tmp_path):
    models = fake_models(b"abc")
    assert provision.verify(tmp_path, models) == ["missing: m.onnx"]
    (tmp_path / "m.onnx").write_bytes(b"abd")
    assert provision.verify(tmp_path, models) == ["checksum mismatch: m.onnx"]


def test_pinned_manifest_is_complete():
    for name, m in provision.MODELS.items():
        assert len(m["sha256"]) == 64 and m["url"].startswith("https://")
