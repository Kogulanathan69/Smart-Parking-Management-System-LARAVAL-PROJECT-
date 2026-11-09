import json

import pytest
from fastapi.testclient import TestClient

from anpr_service.app import create_app
from anpr_service.config import ConfigError, ServiceSettings
from anpr_service.engine import OnnxPlateEngine, StubEngine, sha256_file
from tests.conftest import TOKEN, jpeg_bytes


@pytest.fixture
def stub():
    return StubEngine(text="CAB1234", confidence=0.93)


@pytest.fixture
def client(settings, stub):
    return TestClient(create_app(settings, stub))


def post_image(client, data, headers, name="f.jpg", mime="image/jpeg"):
    return client.post("/v1/recognize", files={"image": (name, data, mime)}, headers=headers)


def test_requires_bearer_token(client):
    assert client.get("/v1/health").status_code == 401
    assert client.get("/v1/health", headers={"Authorization": "Bearer wrong"}).status_code == 401
    assert post_image(client, jpeg_bytes(), {"Authorization": "Bearer " + "x" * 40}).status_code == 401
    assert client.get("/v1/live").json() == {"status": "ok"}  # liveness reveals nothing


def test_health_and_recognize(client, auth, stub):
    assert client.get("/v1/health", headers=auth).json()["status"] == "ok"
    r = post_image(client, jpeg_bytes(), auth)
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "recognized"
    assert body["candidates"][0] == {"text": "CAB1234", "confidence": 0.93, "detector_confidence": 0.9}
    assert body["plate_crop_jpeg_base64"]
    assert stub.calls == 1


def test_unreadable(settings, auth):
    c = TestClient(create_app(settings, StubEngine(text=None)))
    assert post_image(c, jpeg_bytes(), auth).json() == {"status": "unreadable", "candidates": [], "processing_ms": pytest.approx(0, abs=1000), "detector": "stub"}


@pytest.mark.parametrize("data,status,code", [
    (b"GIF89a....", 415, "image_type_unsupported"),
    (b"\xff\xd8\xff" + b"0" * 100, 422, "image_undecodable"),
    (b"", 422, "image_missing"),
])
def test_rejects_bad_images(client, auth, data, status, code, stub):
    r = post_image(client, data, auth)
    assert r.status_code == status and r.json()["error_code"] == code
    assert stub.calls == 0


def test_rejects_oversized_and_tiny_images(settings, auth):
    small = ServiceSettings(token=TOKEN, engine="stub", max_image_bytes=1000)
    c = TestClient(create_app(small, StubEngine()))
    assert post_image(c, jpeg_bytes(800, 600), auth).status_code == 413
    c2 = TestClient(create_app(settings, StubEngine()))
    assert post_image(c2, jpeg_bytes(10, 10), auth).json()["error_code"] == "image_dimensions_invalid"


def test_engine_failure_is_safe(settings, auth):
    class Boom(StubEngine):
        def recognize(self, img):
            raise RuntimeError("secret internal detail")

    r = post_image(TestClient(create_app(settings, Boom())), jpeg_bytes(), auth)
    assert r.status_code == 500 and r.json() == {"error_code": "engine_failure"}


def test_onnx_engine_refuses_missing_or_tampered_models(tmp_path, auth):
    s = ServiceSettings(token=TOKEN, models_dir=tmp_path)
    eng = OnnxPlateEngine(s)
    eng.load()
    ok, why = eng.ready()
    assert not ok and "manifest" in why
    c = TestClient(create_app(s, eng))
    assert c.get("/v1/health", headers=auth).status_code == 503
    assert post_image(c, jpeg_bytes(), auth).json()["error_code"] == "engine_not_ready"

    for name in (s.detector_model, s.ocr_model, s.ocr_config):
        (tmp_path / name).write_bytes(b"real")
    manifest = {"files": {n: {"sha256": sha256_file(tmp_path / n)} for n in (s.detector_model, s.ocr_model, s.ocr_config)}}
    (tmp_path / s.ocr_model).write_bytes(b"tampered")
    (tmp_path / "manifest.json").write_text(json.dumps(manifest))
    eng.load()
    assert eng.ready() == (False, f"Model file failed its checksum: {s.ocr_model}")


def test_settings_validation(monkeypatch):
    monkeypatch.setenv("ANPR_SERVICE_TOKEN", "short")
    with pytest.raises(ConfigError):
        ServiceSettings.from_env()
    monkeypatch.setenv("ANPR_SERVICE_TOKEN", TOKEN)
    monkeypatch.setenv("ANPR_BIND_HOST", "0.0.0.0")
    with pytest.raises(ConfigError, match="loopback"):
        ServiceSettings.from_env()
    monkeypatch.setenv("ANPR_ALLOW_LAN_BIND", "true")
    assert ServiceSettings.from_env().bind_host == "0.0.0.0"
    monkeypatch.setenv("ANPR_ENGINE", "magic")
    with pytest.raises(ConfigError):
        ServiceSettings.from_env()


def test_configurable_resource_limits(monkeypatch):
    monkeypatch.setenv("ANPR_SERVICE_TOKEN", TOKEN)
    monkeypatch.setenv("ANPR_ENGINE", "stub")
    monkeypatch.setenv("ANPR_MAX_IMAGE_BYTES", "2097152")
    monkeypatch.setenv("ANPR_MAX_IMAGE_PIXELS", "12000000")
    monkeypatch.setenv("ANPR_QUEUE_TIMEOUT_SECONDS", "2.5")
    settings = ServiceSettings.from_env()
    assert settings.max_image_bytes == 2097152
    assert settings.max_image_pixels == 12000000
    assert settings.queue_timeout_s == 2.5
