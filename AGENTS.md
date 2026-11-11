# ONE Engine — Foundation

Read README.md and docs/ARCHITECTURE.md before changes. The owner authorized this
foundation runtime and tests. Keep work limited to the current repository and
the requested phase. Do not deploy to a VPS without explicit owner approval.

- Native PHP 8.3+, HTML/CSS, optional vanilla JavaScript. No framework or mandatory package manager.
- Runtime must not depend on Builder or CentralManagement. Core has no infrastructure dependency.
- Central access never implies a site's content permissions. Deny by default and scope every grant to a site.
- Only `public/` may be served. Never copy secrets, user records or production databases.
- JSON uses independent stable locks, bounded waits and atomic replacement; malformed data must throw.
- Do not migrate legacy code wholesale. Record any reuse in docs/MIGRATION.md and retain original notices.
- Run `php tests/run.php`, `python3 tests/check_structure.py` and PHP lint after relevant changes.
- Use a separate branch and a PR; report actual GitHub Actions results. Do not merge unless requested.
- License selection remains an owner decision. Do not assign a license by inference.
