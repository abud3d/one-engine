# Full ARBNT community mount — Codespaces preview

**Implemented in:** `feature/full-arbnt-community-mount` (subject to PR review and CI).  
**ARBNT source:** `abud3d/arbnt` main SHA `1b5c44b21bc328fef7bc4645ce3140dba0ed13d1`.

## What was really transferred

The actual original ARBNT front-end HTML/CSS/JS, accounts screens, posts, comments, favorites, communities, notifications, reporting and existing administrative dashboard are copied into `public/community/`. The genuine legacy `api/community.php` endpoint is mounted; its private PHP core is in `src/LegacyArbnt/core.php`, not in the webroot.

This is a **full original feature set as a quarantined legacy application module**, not yet re-implemented as native ONE Engine permissions, objects, schemas, control plane or universal CMS. Do not describe the original ARBNT admin panel as ONE Engine's central administration.

Files copied: 28 (26 public app + API + private core). All user's original ARBNT content/data is left untouched. No real accounts/passwords were copied.

## Start Codespaces preview

Run from the `one-engine` repository, after checking out the branch/merged main containing this module. One-time installation of a **new, empty, standalone** community:

```bash
cd /workspaces/one-engine
# Pull newest main after this PR is merged:
git pull --ff-only origin main

read -rsp 'New test admin password (12+ chars): ' ONE_ARBNT_ADMIN_PASSWORD
echo
export ONE_ARBNT_ADMIN_PASSWORD
php bin/one-arbnt-install 'مجتمع ONE التجريبي' testadmin
unset ONE_ARBNT_ADMIN_PASSWORD
```

The installer refuses to overwrite `arbnt-private/` if already populated. It uses strong password hashing and a separate site-specific private dataset. Do not delete any existing data directory casually.

Start/restart PHP server (Ctrl+C to stop previous server). The `ONE_CODESPACES_PREVIEW=1` flag **only** enables the legacy API's explicit HTTPS-forwarded proxy check on a loopback Codespaces proxy host matching `*-8080.app.github.dev`. Do not set this flag on a production server:

```bash
export ONE_CODESPACES_PREVIEW=1
php -c config/php.ini -S 0.0.0.0:8080 -t public public/router.php
```

Open the forwarded GitHub Codespaces port 8080 using your existing private Codespaces URL:

- **Actual ARBNT discussions:** `/community/community.html` (or `/community` redirect)
- **Administrator login:** `/community/login.html`
- **Real ARBNT administrator panel:** `/community/admin.html`
- **Registration:** `/community/register.html`
- **Communities:** `/community/communities.html`
- **New post:** `/community/publish.html`
- **Post view:** `/community/post.html`
- **Profiles / favorites:** `/community/profile.html`, `/community/favorites.html`
- **API:** `/community/api/community.php?action=session`
- **ONE Engine's original home / pages:** `/`, `/pages` — remain separate.

Once logged in as `testadmin`, create a test post and community using ARBNT's existing administrative/user UI. The public gallery starts empty; this is not a migration of anyone's production posts.

## Security and important limitations

- **Codespaces/dev preview only.** The new files are not automatically exported to Static/Hybrid/Dynamic release packages, and production Apache/Nginx routing and proxy TLS trust are not configured. No VPS deployment was made.
- User data is outside the webroot in `arbnt-private/`, ignored by Git. No production data imported.
- Only a narrow allowlist of legacy HTML, CSS, JS and the PHP API is served by the Codespaces router; the original `install.php`, `setup.php`, `recover.php` and private core are **not** published.
- Legacy API is still the original ARBNT permission model, not yet ONE Engine's `PermissionEngine`. Its full functional/security audit and per-site adaptation are a separate phase.
- Email activation/recovery depends on SMTP/mail configuration and has **not** been end-to-end validated in Codespaces. The test admin is locally provisioned and marked verified.
- The original ARBNT homepage carries its own brand/canonical and is not linked as ONE Engine's main home. The UX is deliberately isolated to `/community` while we integrate shared layout and core permissions.
- Do not say all ARBNT features were verified until each workflow is tested. CI currently validates source/asset access, private routing and a synthetic session→admin interaction; browser-level tests remain necessary.

## Tests

```bash
php tests/run.php
python3 tests/check_structure.py
php -l public/community/api/community.php
php -l src/LegacyArbnt/core.php
```

See `docs/V02_HANDOFF.md`, `docs/ARBNT_AUTH_REVIEW.md` and issue #4 for the remaining native integration and risk controls.
