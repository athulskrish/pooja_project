"""
Shared E2E Test Harness Utilities for Zippy Food Ordering Platform.

Provides environment-aware DB/URL/secret configuration, database reseeding,
cookie-isolated HTTP client helpers, CSRF extraction, PHP error detection,
and Selenium WebDriver helpers without touching existing legacy test scripts.
"""

from __future__ import annotations

import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from typing import Iterable, Optional

import pymysql
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

if sys.platform == "win32":
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8")
        except Exception:
            pass

TESTS_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(TESTS_DIR)

PHP_BAD_MARKERS = (
    "Fatal error",
    "Parse error",
    "Uncaught Error",
    "Uncaught Exception",
    "<b>Warning</b>:",
    "<b>Notice</b>:",
    "PHP Warning",
    "PHP Notice",
    "SQLSTATE[",
    "Deprecated:",
)


def load_env_config() -> dict:
    """Load runtime config from pooja_project/.env with safe local defaults."""
    env_vars: dict[str, str] = {}
    env_path = os.path.join(PROJECT_ROOT, ".env")
    if os.path.isfile(env_path):
        with open(env_path, "r", encoding="utf-8") as fh:
            for raw in fh:
                line = raw.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                env_vars[k.strip()] = v.strip().strip('"').strip("'")

    base_url = os.environ.get("ZIPPY_BASE_URL", "http://127.0.0.1:8080").rstrip("/")
    webhook_secret = env_vars.get(
        "RAZORPAY_WEBHOOK_SECRET",
        os.environ.get("RAZORPAY_WEBHOOK_SECRET", "zippy_dev_webhook_secret_2026"),
    )
    db_config = {
        "host": os.environ.get("DB_HOST", env_vars.get("DB_HOST", "127.0.0.1")),
        "port": int(os.environ.get("DB_PORT", env_vars.get("DB_PORT", "3306"))),
        "user": os.environ.get("DB_USER", env_vars.get("DB_USER", "root")),
        "password": os.environ.get("DB_PASS", env_vars.get("DB_PASS", "")),
        "database": os.environ.get("DB_NAME", env_vars.get("DB_NAME", "zippy_db")),
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": True,
    }
    return {
        "base_url": base_url,
        "webhook_secret": webhook_secret,
        "db_config": db_config,
    }


CFG = load_env_config()
BASE_URL: str = CFG["base_url"]
WEBHOOK_SECRET: str = CFG["webhook_secret"]
DB_CONFIG: dict = CFG["db_config"]


def db_conn():
    """Return a PyMySQL connection configured for zippy_db."""
    return pymysql.connect(**DB_CONFIG)


def seed_clean_database() -> None:
    """Reset zippy_db cleanly via database/seed.php, migrate_food_moods_table.php, and migrate_v2_features.php."""
    for rel in ("database/seed.php", "database/migrate_food_moods_table.php", "database/migrate_v2_features.php"):
        script_path = os.path.join(PROJECT_ROOT, rel)
        subprocess.run(
            ["php", script_path],
            cwd=PROJECT_ROOT,
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            text=True,
        )


def extract_csrf(html: str) -> str:
    """Extract CSRF token from either <meta name='csrf-token'> or hidden input."""
    meta = re.search(r'<meta\s+name="csrf-token"\s+content="([^"]+)"', html)
    if meta:
        return meta.group(1)
    hidden = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
    return hidden.group(1) if hidden else ""


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class HttpClient:
    """Lightweight cookie-persisting HTTP client matching single-browser jar behavior."""

    def __init__(self, base_url: str = BASE_URL):
        self.base_url = base_url.rstrip("/")
        self.cj = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        self.raw_opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cj),
            _NoRedirect(),
        )

    def get(self, path: str, headers: Optional[dict] = None, follow: bool = True):
        req = urllib.request.Request(self.base_url + path, headers=headers or {})
        op = self.opener if follow else self.raw_opener
        try:
            resp = op.open(req)
            return resp.getcode(), resp.geturl(), resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as err:
            return err.code, err.geturl(), err.read().decode("utf-8", errors="replace")

    def post_form(self, path: str, data: dict, headers: Optional[dict] = None, follow: bool = True):
        body = urllib.parse.urlencode(data, doseq=True).encode("utf-8")
        hdrs = {"Content-Type": "application/x-www-form-urlencoded"}
        if headers:
            hdrs.update(headers)
        req = urllib.request.Request(self.base_url + path, data=body, headers=hdrs)
        op = self.opener if follow else self.raw_opener
        try:
            resp = op.open(req)
            return resp.getcode(), resp.geturl(), resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as err:
            return err.code, err.geturl(), err.read().decode("utf-8", errors="replace")

    def post_json(self, path: str, payload: dict, headers: Optional[dict] = None):
        body = json.dumps(payload).encode("utf-8")
        hdrs = {"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"}
        if headers:
            hdrs.update(headers)
        req = urllib.request.Request(self.base_url + path, data=body, headers=hdrs)
        try:
            resp = self.opener.open(req)
            return resp.getcode(), resp.geturl(), resp.read().decode("utf-8", errors="replace")
        except urllib.error.HTTPError as err:
            return err.code, err.geturl(), err.read().decode("utf-8", errors="replace")


def assert_html_clean(testcase, label: str, code: int, html: str, required_substrings: Iterable[str] = ()) -> None:
    """Assert HTTP 200, absence of PHP/SQL error text, and required markers."""
    for marker in PHP_BAD_MARKERS:
        testcase.assertNotIn(marker, html, f"[{label}] Found PHP/SQL marker '{marker}' (HTTP {code})")
    testcase.assertEqual(code, 200, f"[{label}] Expected HTTP 200, got {code}")
    for expected in required_substrings:
        testcase.assertIn(expected, html, f"[{label}] Missing expected text '{expected}'")


def make_headless_chrome():
    """Instantiate configured headless Chrome WebDriver with browser console logging."""
    opts = Options()
    opts.add_argument("--headless=new")
    opts.add_argument("--window-size=1440,950")
    opts.add_argument("--disable-gpu")
    opts.add_argument("--no-sandbox")
    opts.add_argument("--disable-dev-shm-usage")
    opts.set_capability("goog:loggingPrefs", {"browser": "ALL"})
    driver = webdriver.Chrome(options=opts)
    driver.implicitly_wait(4)
    return driver


def js_click(driver, element) -> None:
    """Scroll element into view cleanly and click via JS to prevent sticky header overlap."""
    driver.execute_script("arguments[0].scrollIntoView({block:'center'}); arguments[0].click();", element)


def accept_alert_if_present(driver, timeout: float = 2.0) -> None:
    """Accept native browser confirm/alert dialog if one appears."""
    try:
        WebDriverWait(driver, timeout).until(EC.alert_is_present())
        driver.switch_to.alert.accept()
    except Exception:
        pass


def assert_page_clean_in_browser(testcase, driver, label: str, required_substrings: Iterable[str] = ()) -> None:
    """Verify current Chrome DOM has no PHP/SQL errors and contains required strings."""
    src = driver.page_source
    for marker in PHP_BAD_MARKERS:
        testcase.assertNotIn(marker, src, f"[{label}] Found '{marker}' on {driver.current_url}")
    for expected in required_substrings:
        testcase.assertIn(expected, src, f"[{label}] Missing '{expected}' on {driver.current_url}")


def assert_no_severe_console_errors(testcase, driver, label: str) -> None:
    """Assert Chrome browser logs recorded zero SEVERE JS/app console errors."""
    severe = [
        entry for entry in driver.get_log("browser")
        if entry.get("level") == "SEVERE"
        and "favicon" not in entry.get("message", "").lower()
        and "tile.openstreetmap" not in entry.get("message", "").lower()
        and "fonts.gstatic" not in entry.get("message", "").lower()
        and "fonts.googleapis" not in entry.get("message", "").lower()
    ]
    testcase.assertEqual(len(severe), 0, f"[{label}] Severe browser JS errors: {severe}")
