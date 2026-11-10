"""HTTP adapter for the replaceable LMS risk model."""
import hmac
import json
import os
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

try:
    from .risk_model import load_model
except ImportError:
    from risk_model import load_model

MODEL = load_model(Path(__file__).parent / "artifacts" / "risk_model.json")
API_KEY = os.environ.get("ML_SERVICE_API_KEY", "")
REQUIRE_API_KEY = os.environ.get("REQUIRE_API_KEY", "").lower() in ("1", "true", "yes")
MAX_REQUEST_BYTES = 1024 * 1024

if REQUIRE_API_KEY and not API_KEY:
    raise RuntimeError("ML_SERVICE_API_KEY must be configured when REQUIRE_API_KEY is enabled.")


class Handler(BaseHTTPRequestHandler):
    def _json(self, status, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == "/health":
            self._json(200, {"status": "ok", "model_version": "risk-logistic-v1"})
        else:
            self._json(404, {"error": "not_found"})

    def do_POST(self):
        if self.path != "/predict/risk":
            self._json(404, {"error": "not_found"})
            return
        if API_KEY:
            authorization = self.headers.get("Authorization", "")
            expected = "Bearer " + API_KEY
            if not hmac.compare_digest(authorization, expected):
                self._json(401, {"error": "unauthorized"})
                return

        try:
            length = int(self.headers.get("Content-Length", "0"))
            if length < 1 or length > MAX_REQUEST_BYTES:
                self._json(400, {"error": "invalid_payload", "message": "Invalid request size."})
                return
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
            if not isinstance(payload, dict):
                raise ValueError("JSON request body must be an object.")
            prediction = MODEL.predict(payload)
            self._json(200, {"model_version": "risk-logistic-v1", "data": prediction})
        except (ValueError, KeyError, TypeError, json.JSONDecodeError) as error:
            self._json(400, {"error": "invalid_payload", "message": str(error)})


if __name__ == "__main__":
    host = os.environ.get("HOST", "127.0.0.1")
    port = int(os.environ.get("PORT", "8091"))
    ThreadingHTTPServer((host, port), Handler).serve_forever()
