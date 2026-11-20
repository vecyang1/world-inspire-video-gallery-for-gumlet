#!/usr/bin/env python3
"""Contract & compliance tests for World Inspire Video Gallery for Gumlet."""

import re
import subprocess
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
MAIN_PHP = REPO_ROOT / "world-inspire-video-gallery-for-gumlet.php"
UNINSTALL_PHP = REPO_ROOT / "uninstall.php"
ADMIN_JS = REPO_ROOT / "admin" / "js" / "admin.js"
ADMIN_CSS = REPO_ROOT / "admin" / "css" / "admin.css"
FRONTEND_CSS = REPO_ROOT / "public" / "css" / "frontend.css"


class TestPluginContract(unittest.TestCase):
    def setUp(self):
        self.assertTrue(MAIN_PHP.is_file())
        self.php = MAIN_PHP.read_text(encoding="utf-8")

    def test_php_syntax(self):
        for path in (MAIN_PHP, UNINSTALL_PHP, REPO_ROOT / "tests" / "test_mock_e2e.php"):
            with self.subTest(file=path.name):
                res = subprocess.run(["php", "-l", str(path)], capture_output=True, text=True)
                self.assertEqual(res.returncode, 0, res.stderr)
                self.assertIn("No syntax errors detected", res.stdout)

    def test_plugin_headers_and_no_tested_up_to_in_php(self):
        self.assertIn("Plugin Name:       World Inspire Video Gallery for Gumlet", self.php)
        self.assertIn("Text Domain:       world-inspire-video-gallery-for-gumlet", self.php)
        self.assertIn("License:           GPL-2.0-or-later", self.php)
        self.assertNotIn("Tested up to:", self.php, "Tested up to belongs exclusively in readme.txt")

    def test_zero_inline_script_or_style_in_php(self):
        self.assertNotRegex(self.php, r"<\s*script\b", "No inline <script> tags allowed in PHP")
        self.assertNotRegex(self.php, r"<\s*style\b", "No inline <style> tags allowed in PHP")
        self.assertTrue(ADMIN_JS.is_file())
        self.assertTrue(ADMIN_CSS.is_file())
        self.assertTrue(FRONTEND_CSS.is_file())

    def test_guideline_7_no_third_party_updaters(self):
        for token in (
            "site_transient_update_plugins",
            "pre_set_site_transient_update_plugins",
            "auto_update_plugin",
            "_site_transient_update_plugins",
        ):
            self.assertNotIn(token, self.php)

    def test_zero_private_tenant_or_pii_leaks(self):
        forbidden = (
            "glintmuse",
            "x-stepon",
            "xomoair",
            "xomo",
            "6772bb444ee6c592aae2bc53",
            "gm_extvid",
            "error_log(",
            "$_GET",
            "$_POST",
        )
        all_files = [MAIN_PHP, ADMIN_JS, ADMIN_CSS, FRONTEND_CSS, UNINSTALL_PHP, REPO_ROOT / "readme.txt"]
        for fpath in all_files:
            content = fpath.read_text(encoding="utf-8")
            for token in forbidden:
                with self.subTest(file=fpath.name, token=token):
                    self.assertNotIn(token, content.lower())

    def test_readme_and_distignore_compliance(self):
        readme = (REPO_ROOT / "readme.txt").read_text(encoding="utf-8")
        self.assertIn("=== World Inspire Video Gallery for Gumlet ===", readme)
        self.assertIn("Contributors: hxsmyxh", readme)
        self.assertIn("Tested up to: 7.1", readme)
        self.assertIn("Stable tag: 1.0.1", readme)
        self.assertIn("Version:           1.0.1", self.php)
        self.assertIn("== External Services ==", readme)
        self.assertIn("youtube-nocookie.com", readme)

        # Ensure REST routes do not downgrade permission_callback to upload_files
        rest_block = self.php.split("public static function register_rest_routes()")[1].split("public static function render_admin_page()")[0]
        self.assertNotIn("current_user_can( 'upload_files' )", rest_block)

        distignore = (REPO_ROOT / ".distignore").read_text(encoding="utf-8")
        for pat in (".git/", ".github/", "tests/", ".pytest_cache/", "assets/", ".env*"):
            self.assertIn(pat, distignore)

    def test_prefix_length_and_translators_comments(self):
        self.assertNotIn("evg_", self.php)
        self.assertNotIn("_svh_", self.php)
        self.assertIn("class World_Inspire_Video_Gallery", self.php)
        # Ensure every multi-placeholder gettext call has a translators comment nearby
        for m in re.finditer(r"__\([^)]*%[0-9]+\$", self.php):
            window = self.php[max(0, m.start() - 160):m.start()]
            self.assertIn("/* translators:", window, f"Missing translators comment before {m.group(0)}")

    def test_mock_e2e_suite(self):
        res = subprocess.run(
            ["php", str(REPO_ROOT / "tests" / "test_mock_e2e.php")],
            capture_output=True,
            text=True,
        )
        self.assertEqual(res.returncode, 0, res.stderr + "\n" + res.stdout)
        self.assertIn("mock E2E assertions passed!", res.stdout)


if __name__ == "__main__":
    unittest.main()
