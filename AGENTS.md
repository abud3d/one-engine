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

## Approved phase 2 / cross-agent handoff

**Read [docs/V02_HANDOFF.md](docs/V02_HANDOFF.md) before any ONE Engine v0.2 work.** Resume marker: `ONE-V02-004`. Consult `abud3d/talal-prompts` and TDP-001 as a reusable lessons library, not a blanket override of local rules. Preserve the functioning ARBNT source and UI patterns; migrate incrementally only after code/security review. A real user flow, tests, commit/PR, and honest preview status are required for every slice. Never claim accounts, admin, CMS, or VPS publishing exist until actually implemented. Do not modify ARBNT or deploy to production without explicit approval.

## Full legacy ARBNT mount

Read [docs/FULL_ARBNT_MOUNT.md](docs/FULL_ARBNT_MOUNT.md). Full original ARBNT frontend and API are mounted for **Codespaces preview** under `/community`; `src/LegacyArbnt/core.php` stays outside webroot. Never serve arbitrary files from that tree. The old ARBNT account/authorization logic is an isolated legacy module, not yet ONE Engine native identity or central administration. No production deployment or existing ARBNT data migration. Do not advertise all features as tested; verify login, posts, comments, admin, email and notifications individually.
