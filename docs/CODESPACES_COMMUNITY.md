# Codespaces preview — main navigation and community (v0.2)

The user observed `{"status":"ok"}` at `/health` but `Not found` at `/pages`. Root cause in prior source: `public/index.php` instantiated `Application` **without** passing the `PageCatalog`. Both `/pages` and preview assets were guarded by that missing dependency. The fix now wires opt-in storage and keeps `/pages` and `/community` accessible with an empty state even without storage.

## Codespaces quick start (PHP 8.3+)
From the repository directory, **pull the newest main branch after merge** and restart the PHP server (Ctrl+C):

```bash
cd /workspaces/one-engine
git pull --ff-only origin main
mkdir -p var/preview-private
chmod 700 var/preview-private
export ONE_PRIVATE_DIR="$(pwd)/var/preview-private"
php bin/one-pages publish welcome 'أهلاً في ONE Engine' 'هذه صفحة محفوظة.'
php bin/one-posts publish first 'أول موضوع في المجتمع' 'مرحباً بكم. هذا منشور عام محفوظ في JSON.'
php -c config/php.ini -S 0.0.0.0:8080 -t public public/router.php
```

Use Codespaces' forwarded port 8080 in your browser:
- `/` — home header with **الرئيسية / الصفحات / المجتمع**.
- `/pages` — published page list (even when there are none).
- `/pages/welcome` — actual saved page, after running the CLI.
- `/community` — community feed with public posts.
- `/community/first` — real saved published post.
- `/health` — JSON `{"status":"ok"}`.

If the server is already running, restart it **in the same terminal with the environment variable**, or change the process launch environment. Codespaces often retains an old server process or an old checkout when the branch was updated remotely.

## Scope & safety
This is **not** ARBNT's full authenticated community. It is a functioning read-only community stream backed by PRIVATE JSON and `PermissionEngine`. Writing is possible only via trusted local CLI `bin/one-posts`, not a visitor-facing endpoint. Drafts, foreign-site posts and raw HTML are not displayed. The session, registration, posts submission through the browser, comments and full administration are still not implemented.

Preview routes depend on source-only `public/assets/preview-shell.css/js`; they are not added to independent exported release bundles. The old builder outputs remain unchanged. No modification to `abud3d/arbnt`, no production deployment.

## Verification
```bash
php tests/run.php
python3 tests/check_structure.py
curl -i http://127.0.0.1:8080/pages
curl -i http://127.0.0.1:8080/community
```

**Next milestone**: secure account/session + site-scoped permissions for authenticated browser authoring, then a real admin dashboard and comments.
