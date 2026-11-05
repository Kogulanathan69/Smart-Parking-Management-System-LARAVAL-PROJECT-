import base64
import json

import httpx
import numpy as np
import pytest

from anpr_service.agent import AgentSettings, CameraAgent, is_insecure_remote, redact, stream_source

CONFIG = {"data": {"camera": {"code": "CAM-IN-1", "direction": "entry"}, "stream": {"url": "rtsp://192.168.1.20:554/s1", "username": "admin", "password": "p@ss:w/rd"},
                   "recognition": {"min_confidence": 0.9, "heartbeat_interval_seconds": 60, "send_full_frame": False}}}


class Backend:
    def __init__(self):
        self.mode = "ok"
        self.events = []
        self.heartbeats = []

    def __call__(self, request: httpx.Request) -> httpx.Response:
        assert request.headers["X-Anpr-Camera"] == "CAM-IN-1"
        assert request.headers["Authorization"] == "Bearer devtoken"
        if self.mode == "down":
            raise httpx.ConnectError("refused")
        if request.url.path.endswith("/config"):
            return httpx.Response(200, json=CONFIG)
        if request.url.path.endswith("/heartbeat"):
            self.heartbeats.append(json.loads(request.content))
            return httpx.Response(200, json={"status": "online"})
        if self.mode in ("422", "401", "503"):
            return httpx.Response(int(self.mode), json={})
        self.events.append(request)
        return httpx.Response(201, json={"event_uuid": "x"})


def recognizer_for(results):
    it = iter(results)

    def handle(request):
        assert request.headers["Authorization"] == "Bearer svc"
        return httpx.Response(200, json=next(it))
    return httpx.Client(transport=httpx.MockTransport(handle))


def reading(text="CAB-1234", conf=0.95):
    return {"status": "recognized", "candidates": [{"text": text, "confidence": conf}], "processing_ms": 40,
            "plate_crop_jpeg_base64": base64.b64encode(b"\xff\xd8\xffcrop").decode()}


@pytest.fixture
def clock():
    t = {"now": 1000.0}
    return t


def make_agent(tmp_path, backend, results, clock, **over):
    s = AgentSettings(backend_url="http://127.0.0.1", camera_code="CAM-IN-1", camera_token="devtoken", service_token="svc",
                      spool_dir=tmp_path / "spool", **over)
    agent = CameraAgent(s, httpx.Client(transport=httpx.MockTransport(backend)), recognizer_for(results), clock=lambda: clock["now"])
    agent.fetch_config()
    return agent


def frame(seed=0):
    return np.random.default_rng(seed).integers(0, 255, (120, 200, 3), dtype=np.uint8)


def form(request):
    """Decode the multipart body enough to read simple fields."""
    body = request.content.decode("latin-1")
    out = {}
    for part in body.split("--" + request.headers["content-type"].split("boundary=")[1]):
        if 'name="' in part and "filename=" not in part:
            name = part.split('name="')[1].split('"')[0]
            out[name] = part.split("\r\n\r\n", 1)[1].rsplit("\r\n", 1)[0]
        elif "filename=" in part:
            out.setdefault("_files", []).append(part.split('name="')[1].split('"')[0])
    return out


def test_pushes_a_reading_with_device_auth_and_crop_only(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading()], clock)
    assert agent.process_frame(frame()) == "sent"
    f = form(b.events[0])
    assert json.loads(f["candidates"]) == [{"text": "CAB-1234", "confidence": 0.95}]
    assert f["status"] == "recognized" and len(f["idempotency_key"]) == 36
    assert f["_files"] == ["plate_crop"]  # full frame only when the backend setting allows it


def test_full_frame_sent_only_when_enabled(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading()], clock)
    agent.config["recognition"]["send_full_frame"] = True
    agent.process_frame(frame())
    assert sorted(form(b.events[0])["_files"]) == ["image", "plate_crop"]


def test_unreadable_frames_are_not_sent(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [{"status": "unreadable", "candidates": []}], clock)
    assert agent.process_frame(frame()) == "unreadable" and b.events == []


def test_local_dedup_window(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading("CAB-1234"), reading("cab 1234"), reading("CAB-1234")], clock, local_dedup_seconds=15)
    assert agent.process_frame(frame(1)) == "sent"
    clock["now"] += 5
    assert agent.process_frame(frame(2)) == "duplicate_local"
    clock["now"] += 20
    assert agent.process_frame(frame(3)) == "sent"
    assert len(b.events) == 2


def test_motion_gate_skips_static_scene(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading(), reading()], clock, idle_recheck_seconds=5)
    f = frame(7)
    assert agent.process_frame(f) == "sent"
    clock["now"] += 1
    assert agent.process_frame(f.copy()) == "skipped"
    clock["now"] += 10
    assert agent.process_frame(f.copy()) == "duplicate_local"


def test_spools_while_backend_down_and_replays_with_same_key(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading()], clock)
    b.mode = "down"
    assert agent.process_frame(frame()) == "spooled"
    assert len(agent.spool) == 1
    queued_key = json.loads(agent.spool.entries()[0].read_text())["fields"]["idempotency_key"]

    assert agent.flush_spool() == 0 and len(agent.spool) == 1  # still down: kept, in order
    b.mode = "ok"
    assert agent.flush_spool() == 1 and len(agent.spool) == 0
    assert form(b.events[0])["idempotency_key"] == queued_key
    assert form(b.events[0])["_files"] == ["plate_crop"]


@pytest.mark.parametrize("mode,outcome,spooled", [("503", "spooled", 1), ("401", "spooled", 1), ("422", "dropped", 0)])
def test_backend_error_handling(tmp_path, clock, mode, outcome, spooled):
    b = Backend()
    agent = make_agent(tmp_path, b, [reading()], clock)
    b.mode = mode
    assert agent.process_frame(frame()) == outcome
    assert len(agent.spool) == spooled
    assert agent._auth_failed is (mode == "401")


def test_heartbeat(tmp_path, clock):
    b = Backend()
    agent = make_agent(tmp_path, b, [], clock)
    assert agent.heartbeat("error", "x" * 900, 12)
    assert len(b.heartbeats[0]["message"]) == 480 and b.heartbeats[0]["latency_ms"] == 12


def test_stream_source_inserts_credentials_without_leaking_them_in_logs():
    src = stream_source(CONFIG["data"]["stream"], None)
    assert src == "rtsp://admin:p%40ss%3Aw%2Frd@192.168.1.20:554/s1"
    assert "admin" not in redact(src) and "p%40ss" not in redact(src)
    assert redact(src) == "rtsp://192.168.1.20:554/…"
    assert stream_source({"url": None}, "usb:1") == 1
    with pytest.raises(ValueError):
        stream_source({"url": None}, None)


def test_insecure_remote_detection():
    assert not is_insecure_remote("http://127.0.0.1")
    assert not is_insecure_remote("https://parking.local")
    assert is_insecure_remote("http://192.168.1.10")


class FakeCapture:
    def __init__(self, frames):
        self.frames = list(frames)
        self.released = False

    def isOpened(self):
        return True

    def read(self):
        if not self.frames:
            return False, None
        return True, self.frames.pop(0)

    def release(self):
        self.released = True


def test_run_loop_with_fake_camera_reconnects_and_pushes(tmp_path):
    b = Backend()
    opened = []

    def factory(source):
        opened.append(source)
        return FakeCapture([frame(len(opened))])  # one frame, then a "dropped stream" forcing a reconnect

    results = [reading("CAB-1234"), reading("KX-4455"), {"status": "unreadable", "candidates": []}]
    s = AgentSettings(backend_url="http://127.0.0.1", camera_code="CAM-IN-1", camera_token="devtoken", service_token="svc",
                      spool_dir=tmp_path / "spool", sample_interval_ms=0)

    def recog(request):
        if request.url.path == "/v1/health":
            return httpx.Response(200, json={"status": "ok"})
        return httpx.Response(200, json=results.pop(0) if results else {"status": "unreadable", "candidates": []})

    agent = CameraAgent(s, httpx.Client(transport=httpx.MockTransport(b)), httpx.Client(transport=httpx.MockTransport(recog)), capture_factory=factory)
    agent.run(stop=lambda: len(opened) >= 4)

    assert opened[0] == "rtsp://admin:p%40ss%3Aw%2Frd@192.168.1.20:554/s1"
    assert len(b.events) == 2 and agent.stats["pushed"] == 2
    assert b.heartbeats and b.heartbeats[0]["status"] == "ok"


def test_detector_confidence_is_forwarded_separately(tmp_path, clock):
    b = Backend()
    r = reading()
    r["candidates"][0]["detector_confidence"] = 0.88
    agent = make_agent(tmp_path, b, [r], clock)
    agent.process_frame(frame())
    assert json.loads(form(b.events[0])["candidates"]) == [{"text": "CAB-1234", "confidence": 0.95, "detector_confidence": 0.88}]
