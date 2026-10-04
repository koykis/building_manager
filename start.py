#!/usr/bin/env python3
"""Start the local database, Laravel API, and Vue development server."""

import argparse
import fcntl
import os
from pathlib import Path
import shutil
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request


ROOT = Path(__file__).resolve().parent
PROCESSES = []


def say(message):
    print(message, flush=True)


def compatible_node(binary):
    try:
        result = subprocess.run(
            [str(binary), "--version"], capture_output=True, text=True, timeout=5
        )
        version = tuple(int(part) for part in result.stdout.strip().lstrip("v").split("."))
        return result.returncode == 0 and (
            (version[0] == 20 and version >= (20, 19, 0)) or version >= (22, 12, 0)
        )
    except (OSError, ValueError, IndexError, subprocess.TimeoutExpired):
        return False


def preflight():
    for command in ("php", "docker"):
        if not shutil.which(command):
            raise RuntimeError(f"Missing {command}. See Requirements and setup in README.md.")
    for relative in (
        ".local/compose.env", "backend/.env", "backend/vendor/autoload.php",
        "frontend/node_modules/vite/bin/vite.js",
    ):
        if not (ROOT / relative).is_file():
            raise RuntimeError(f"Missing {relative}. Complete Requirements and setup in README.md first.")

    candidates = []
    current = shutil.which("node")
    if current:
        candidates.append(Path(current))
    nvm_dir = Path(os.environ.get("NVM_DIR", str(Path.home() / ".nvm")))
    candidates.extend(sorted(nvm_dir.glob("versions/node/*/bin/node"), reverse=True))
    node = next((candidate for candidate in candidates if compatible_node(candidate)), None)
    if node is None:
        raise RuntimeError("Install Node 20.19+ (20.x) or Node 22.12+ for Vite, then retry.")

    for port in (8000, 5173):
        with socket.socket() as probe:
            # Allow a restart while closed connections remain in TIME_WAIT.
            probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            try:
                probe.bind(("127.0.0.1", port))
            except OSError as error:
                raise RuntimeError(f"Cannot use port {port}: {error}. Stop the existing server first.") from error
    result = subprocess.run(["docker", "compose", "version"], capture_output=True, text=True, timeout=10)
    if result.returncode:
        raise RuntimeError("Docker Compose is unavailable. See README.md for requirements.")
    say(f"Using Node: {node}")
    return node


def launch(command, directory):
    process = subprocess.Popen(command, cwd=directory, start_new_session=True)
    PROCESSES.append(process)
    return process


def check_servers(servers):
    for name, process in servers:
        if process.poll() is not None:
            raise RuntimeError(f"{name} stopped (exit {process.returncode}). See its output above.")


def wait_for_servers(servers):
    pending = {"http://127.0.0.1:8000/up", "http://127.0.0.1:5173/"}
    deadline = time.monotonic() + 45
    # Local health checks should bypass any workstation HTTP proxy.
    http = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    while pending:
        check_servers(servers)
        for url in tuple(pending):
            try:
                with http.open(url, timeout=1) as response:
                    if response.status == 200:
                        pending.remove(url)
            except (OSError, urllib.error.URLError):
                pass
        if time.monotonic() >= deadline and pending:
            raise RuntimeError(f"Startup timed out waiting for: {', '.join(sorted(pending))}")
        time.sleep(0.2)


def signal_group(process, signum):
    try:
        os.killpg(process.pid, signum)
    except ProcessLookupError:
        pass


def cleanup():
    if not PROCESSES:
        return
    say("Stopping local application processes; MySQL remains running.")
    for process in PROCESSES:
        signal_group(process, signal.SIGTERM)
    deadline = time.monotonic() + 5
    for process in PROCESSES:
        try:
            process.wait(timeout=max(0, deadline - time.monotonic()))
        except subprocess.TimeoutExpired:
            pass
    # Include child processes, even when their launcher already exited.
    for process in PROCESSES:
        signal_group(process, signal.SIGKILL)
        process.wait()


def interrupted(signum, frame):
    raise KeyboardInterrupt


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="check prerequisites and ports without starting services")
    args = parser.parse_args()
    lock = None
    signal.signal(signal.SIGINT, interrupted)
    signal.signal(signal.SIGTERM, interrupted)
    try:
        node = preflight()
        if args.check:
            say("Startup prerequisites and application ports are ready.")
            return 0
        lock = (ROOT / ".local/start.lock").open("a")
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as error:
            raise RuntimeError("Another startup command is already running for this project.") from error

        say("Starting MySQL and waiting for its health check…")
        database = launch([
            "docker", "compose", "--project-directory", str(ROOT),
            "--env-file", str(ROOT / ".local/compose.env"),
            "-f", str(ROOT / "compose.yaml"), "up", "-d", "--wait", "--wait-timeout", "180", "db",
        ], ROOT)
        if database.wait(timeout=210):
            raise RuntimeError("MySQL failed to start. See Docker output above.")
        PROCESSES.remove(database)

        say("Starting Laravel and Vue…")
        servers = [
            ("Laravel", launch([
                "php", "artisan", "serve", "--host=127.0.0.1", "--port=8000",
                "--tries=1", "--no-interaction",
            ], ROOT / "backend")),
            ("Vue", launch([
                str(node), "node_modules/vite/bin/vite.js", "--host", "127.0.0.1",
                "--port", "5173", "--strictPort",
            ], ROOT / "frontend")),
        ]
        wait_for_servers(servers)
        say("\nReady: http://127.0.0.1:5173\nAPI:   http://127.0.0.1:8000\nPress Ctrl+C to stop Laravel and Vue.\n")
        while True:
            check_servers(servers)
            time.sleep(0.5)
    except KeyboardInterrupt:
        return 0
    except (RuntimeError, OSError, subprocess.TimeoutExpired) as error:
        print(f"Startup failed: {error}", file=sys.stderr, flush=True)
        return 1
    finally:
        signal.signal(signal.SIGINT, signal.SIG_IGN)
        signal.signal(signal.SIGTERM, signal.SIG_IGN)
        cleanup()
        if lock:
            lock.close()


if __name__ == "__main__":
    sys.exit(main())
