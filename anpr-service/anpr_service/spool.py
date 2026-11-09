"""Durable on-disk queue for events the backend could not accept yet (backend restarting, LAN down).

Each entry keeps its original idempotency key, so delivering it twice is harmless. Entries contain plate crops,
so the spool folder must sit on the parking PC's protected disk; delivered entries are deleted immediately and
entries older than max_age are discarded (the backend rejects captures older than one day anyway).
"""

from __future__ import annotations

import base64
import json
import logging
import os
import time
import uuid
from pathlib import Path

log = logging.getLogger("anpr.spool")


class Spool:
    def __init__(self, directory: Path, max_entries: int = 2000, max_age_s: int = 20 * 3600):
        self.dir = Path(directory)
        self.dir.mkdir(parents=True, exist_ok=True)
        self.max_entries = max_entries
        self.max_age_s = max_age_s

    def put(self, fields: dict, files: dict[str, bytes]) -> Path:
        self._enforce_cap(reserve=1)
        entry = {"queued_at": time.time(), "fields": fields, "files": {k: base64.b64encode(v).decode("ascii") for k, v in files.items() if v}}
        name = f"{time.time_ns():020d}-{uuid.uuid4().hex[:8]}.json"
        tmp = self.dir / (name + ".tmp")
        tmp.write_text(json.dumps(entry), encoding="utf-8")
        os.replace(tmp, self.dir / name)  # atomic: a crash never leaves a half-written entry
        return self.dir / name

    def entries(self) -> list[Path]:
        return sorted(p for p in self.dir.glob("*.json"))

    def load(self, path: Path) -> tuple[dict, dict[str, bytes]] | None:
        try:
            entry = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            log.warning("discarding unreadable spool entry %s", path.name)
            self.remove(path)
            return None
        if time.time() - float(entry.get("queued_at", 0)) > self.max_age_s:
            log.warning("discarding expired spool entry %s", path.name)
            self.remove(path)
            return None
        return entry["fields"], {k: base64.b64decode(v) for k, v in entry.get("files", {}).items()}

    def remove(self, path: Path) -> None:
        try:
            path.unlink()
        except FileNotFoundError:
            pass

    def __len__(self) -> int:
        return len(self.entries())

    def _enforce_cap(self, reserve: int = 0) -> None:
        entries = self.entries()
        overflow = len(entries) + reserve - self.max_entries
        for path in entries[: max(0, overflow)]:
            log.warning("spool full; dropping oldest entry %s", path.name)
            self.remove(path)
