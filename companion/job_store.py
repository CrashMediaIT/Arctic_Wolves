"""
Companion Job Store — SQLite-backed persistence for transcoding jobs.

Replaces the previous JSON-file approach with a proper SQLite database
stored at ``/config/companion_jobs.db``.  The database has two tables:

- **jobs** — one row per job with all scalar metadata
- **job_logs** — one row per timestamped log entry, FK → jobs.id

On first start the module automatically migrates any existing
``companion_jobs.json`` data into the database, then renames the JSON
file to ``companion_jobs.json.migrated`` so it is not re-imported.
"""

import json
import os
import sqlite3
import threading
import time
import logging

logger = logging.getLogger("companion.job_store")

# ---------------------------------------------------------------------------
# Schema
# ---------------------------------------------------------------------------

_SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS jobs (
    id               TEXT PRIMARY KEY,
    status           TEXT NOT NULL DEFAULT 'queued',
    description      TEXT DEFAULT '',
    source_key       TEXT DEFAULT '',
    output_prefix    TEXT DEFAULT '',
    output           TEXT DEFAULT '',
    hls_manifest     TEXT,
    dash_manifest    TEXT,
    segments_path    TEXT DEFAULT '',
    variants         TEXT DEFAULT '[]',
    video_id         TEXT,
    source_id        TEXT,
    callback_url     TEXT DEFAULT '',
    delete_original  INTEGER DEFAULT 1,
    callback_ok      INTEGER,
    callback_confirmed INTEGER,
    original_deleted INTEGER,
    retry_of         TEXT,
    created_at       REAL,
    started_at       REAL,
    finished_at      REAL,
    error            TEXT
);

CREATE TABLE IF NOT EXISTS job_logs (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id   TEXT NOT NULL,
    ts       REAL NOT NULL,
    level    TEXT NOT NULL DEFAULT 'info',
    msg      TEXT NOT NULL DEFAULT '',
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_job_logs_job_id ON job_logs(job_id);
CREATE INDEX IF NOT EXISTS idx_jobs_created_at ON jobs(created_at);
"""


class JobStore:
    """Thread-safe SQLite-backed store for companion transcoding jobs."""

    # Columns that live in the *jobs* table (everything except ``log``).
    _JOB_COLUMNS = (
        "id", "status", "description", "source_key", "output_prefix",
        "output", "hls_manifest", "dash_manifest", "segments_path",
        "variants", "video_id", "source_id",
        "callback_url", "delete_original", "callback_ok",
        "callback_confirmed", "original_deleted", "retry_of",
        "created_at", "started_at", "finished_at", "error",
    )

    def __init__(self, db_path: str, max_jobs: int = 500):
        self._db_path = db_path
        self._max_jobs = max_jobs
        self._local = threading.local()
        # Ensure the parent directory exists so sqlite3.connect() can
        # create the database file on first run.
        db_dir = os.path.dirname(db_path) or "."
        os.makedirs(db_dir, exist_ok=True)
        if not os.access(db_dir, os.W_OK):
            msg = (
                f"Database directory {db_dir} is not writable (uid={os.getuid()}). "
                "Check volume mount permissions."
            )
            logger.error(msg)
            raise PermissionError(msg)
        # Initialise the schema (uses a temporary connection)
        self._init_schema()

    # -- connection management ------------------------------------------------

    def _get_conn(self) -> sqlite3.Connection:
        """Return a per-thread SQLite connection (created lazily)."""
        conn = getattr(self._local, "conn", None)
        if conn is None:
            conn = sqlite3.connect(self._db_path, timeout=10)
            conn.execute("PRAGMA journal_mode=WAL")
            conn.execute("PRAGMA foreign_keys=ON")
            conn.execute("PRAGMA busy_timeout=5000")
            conn.row_factory = sqlite3.Row
            self._local.conn = conn
        return conn

    def _init_schema(self):
        conn = self._get_conn()
        conn.executescript(_SCHEMA_SQL)
        conn.commit()
        # Migrate existing tables: add columns that may not exist yet.
        # Note: col/coldef are from a hardcoded tuple — not user input.
        for col, coldef in (
            ("dash_manifest", "TEXT"),
            ("segments_path", "TEXT DEFAULT ''"),
        ):
            try:
                conn.execute(f"ALTER TABLE jobs ADD COLUMN {col} {coldef}")
                conn.commit()
            except sqlite3.OperationalError:
                pass  # column already exists

    # -- public API -----------------------------------------------------------

    def upsert_job(self, job: dict):
        """Insert or fully replace a job row (excluding logs)."""
        cols = self._JOB_COLUMNS
        values = tuple(self._serialise_field(k, job.get(k)) for k in cols)
        placeholders = ", ".join("?" for _ in cols)
        col_names = ", ".join(cols)
        conn = self._get_conn()
        conn.execute(
            f"INSERT OR REPLACE INTO jobs ({col_names}) VALUES ({placeholders})",
            values,
        )
        conn.commit()

    def append_log(self, job_id: str, ts: float, level: str, msg: str):
        """Append a single log entry for a job."""
        conn = self._get_conn()
        conn.execute(
            "INSERT INTO job_logs (job_id, ts, level, msg) VALUES (?, ?, ?, ?)",
            (job_id, ts, level, msg),
        )
        conn.commit()

    def get_job(self, job_id: str) -> dict | None:
        """Return a full job dict (with embedded ``log`` list), or None."""
        conn = self._get_conn()
        row = conn.execute("SELECT * FROM jobs WHERE id = ?", (job_id,)).fetchone()
        if row is None:
            return None
        job = self._row_to_dict(row)
        job["log"] = self._get_logs(conn, job_id)
        return job

    def get_all_jobs(self, *, limit: int | None = None) -> list[dict]:
        """Return jobs newest-first, each with its ``log`` list attached."""
        conn = self._get_conn()
        sql = "SELECT * FROM jobs ORDER BY created_at DESC"
        if limit:
            sql += f" LIMIT {int(limit)}"
        rows = conn.execute(sql).fetchall()
        result = []
        for r in rows:
            job = self._row_to_dict(r)
            job["log"] = self._get_logs(conn, job["id"])
            result.append(job)
        return result

    def get_all_jobs_summary(self) -> list[dict]:
        """Return jobs newest-first **without** log entries (fast list)."""
        conn = self._get_conn()
        rows = conn.execute(
            "SELECT * FROM jobs ORDER BY created_at DESC"
        ).fetchall()
        return [self._row_to_dict(r) for r in rows]

    def delete_job(self, job_id: str) -> bool:
        """Remove a job and its logs.  Returns True if a row was deleted."""
        conn = self._get_conn()
        cur = conn.execute("DELETE FROM jobs WHERE id = ?", (job_id,))
        conn.commit()
        return cur.rowcount > 0

    def prune(self):
        """Delete the oldest jobs beyond ``_max_jobs``, including their logs."""
        conn = self._get_conn()
        conn.execute(
            """DELETE FROM jobs WHERE id NOT IN (
                   SELECT id FROM jobs ORDER BY created_at DESC LIMIT ?
               )""",
            (self._max_jobs,),
        )
        conn.commit()

    def mark_interrupted(self):
        """Mark all in-progress jobs as failed (called on startup)."""
        conn = self._get_conn()
        now = time.time()
        conn.execute(
            """UPDATE jobs
               SET status = 'failed',
                   error  = COALESCE(NULLIF(error, ''), 'Interrupted by restart'),
                   finished_at = COALESCE(finished_at, ?)
               WHERE status IN ('running','queued','downloading',
                                'transcoding','uploading')""",
            (now,),
        )
        conn.commit()

    def load_all_to_dict(self) -> dict:
        """Load every job (with logs) into a ``{id: dict}`` mapping.

        Used at startup to seed the in-memory ``jobs`` cache.
        """
        all_jobs = self.get_all_jobs()
        return {j["id"]: j for j in all_jobs}

    # -- migration from legacy JSON file --------------------------------------

    def migrate_from_json(self, json_path: str):
        """Import jobs from the old ``companion_jobs.json`` file.

        After a successful import the JSON file is renamed to
        ``*.migrated`` so data is not re-imported on the next restart.
        """
        if not os.path.isfile(json_path):
            return
        try:
            with open(json_path, "r") as f:
                job_list = json.load(f)
            if not isinstance(job_list, list):
                logger.warning("JSON migration: expected a list, got %s", type(job_list).__name__)
                return
            count = 0
            for j in job_list:
                if not isinstance(j, dict) or "id" not in j:
                    continue
                self.upsert_job(j)
                # Import log entries
                for entry in j.get("log", []):
                    if isinstance(entry, dict):
                        self.append_log(
                            j["id"],
                            entry.get("ts", 0.0),
                            entry.get("level", "info"),
                            entry.get("msg", ""),
                        )
                count += 1
            migrated_path = json_path + ".migrated"
            os.rename(json_path, migrated_path)
            logger.info("Migrated %d jobs from JSON → SQLite; old file renamed to %s", count, migrated_path)
        except Exception as exc:
            logger.warning("JSON→SQLite migration failed: %s", exc)

    # -- internal helpers -----------------------------------------------------

    @staticmethod
    def _serialise_field(key: str, value):
        """Convert Python values to SQLite-friendly types."""
        if key == "variants":
            return json.dumps(value) if isinstance(value, (list, dict)) else (value or "[]")
        if key in ("delete_original", "callback_ok", "callback_confirmed", "original_deleted"):
            if value is None:
                return None
            return 1 if value else 0
        if key in ("video_id", "source_id"):
            return str(value) if value is not None else None
        return value

    def _row_to_dict(self, row: sqlite3.Row) -> dict:
        """Convert a sqlite3.Row into a plain dict with Python types."""
        d = dict(row)
        # Deserialise JSON fields
        if "variants" in d:
            try:
                d["variants"] = json.loads(d["variants"]) if d["variants"] else []
            except (json.JSONDecodeError, TypeError):
                d["variants"] = []
        # Convert integer booleans back to Python bool / None
        for key in ("delete_original", "callback_ok", "callback_confirmed", "original_deleted"):
            v = d.get(key)
            if v is not None:
                d[key] = bool(v)
        return d

    @staticmethod
    def _get_logs(conn: sqlite3.Connection, job_id: str) -> list[dict]:
        """Fetch all log entries for a job, oldest first."""
        rows = conn.execute(
            "SELECT ts, level, msg FROM job_logs WHERE job_id = ? ORDER BY id ASC",
            (job_id,),
        ).fetchall()
        return [dict(r) for r in rows]
