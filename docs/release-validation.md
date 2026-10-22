# Taggy 0.421.0 release validation

Taggy 0.421.0 was submitted to WordPress.org on 20 October 2026. The automated submission scan passed; its manual review result is not confirmed here. The submitted ZIP has SHA-256 `607a1505c8b34777f78e06596e373989caf558bc7dd26c3cec85801e246a18f5`. Running `python3 scripts/package.py` from this source reproduces that ZIP byte for byte.

Before submission, the release pack recorded 11 WordPress assertions on WordPress 7.1.3/PHP 8.4/SQLite and WordPress 6.0/PHP 7.4.33/MariaDB. The assertions covered permission and nonce checks, input bounds, sanitised service responses, server-side key handling, caching and request quotas. Provider responses were stubbed. Chromium editor checks on both versions used fictional titles and confirmed selecting and saving a native tag. Plugin Check found zero errors and one compatibility-helper naming warning; the release pack's Gitleaks check found no findings.

The ZIP contains `taggy.php`, `js/taggy.js`, `readme.txt` and `LICENSE` only. The screenshot uses fictional content and a stubbed response. A live OMDb search with a dedicated demo key has not been verified; do not describe the mocked tests as live provider proof. Manual WordPress.org approval, SVN publication and deployment are separate states.
