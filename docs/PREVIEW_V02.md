# ONE Engine — v0.2 public-pages preview (source-only)

This is the first **real** persisted-content vertical slice, not an admin panel or full migration of arbnt.

## Run locally

Requires PHP 8.3+, an existing writable PRIVATE directory outside `public/` owned by the PHP user, and an explicitly configured `ONE_PRIVATE_DIR` environment variable. This works with JSON and does not create SQLite or any users.

```sh
mkdir -m 700 var/preview-private
export ONE_PRIVATE_DIR="$(pwd)/var/preview-private"
php bin/one-pages publish welcome 'أهلاً في ONE Engine' 'هذه صفحة فعلية محفوظة في JSON.'
php bin/one-pages draft unpublished 'مسودة' 'لن تظهر للزائر.'
php -c config/php.ini -S 127.0.0.1:8080 -t public public/router.php
```

Visit:
- `http://127.0.0.1:8080/` home + new catalog link.
- `http://127.0.0.1:8080/pages` list of published pages only.
- `http://127.0.0.1:8080/pages/welcome` actual persisted page.
- `http://127.0.0.1:8080/pages/unpublished` 404 for draft.

To modify, run `php bin/one-pages publish welcome 'عنوان جديد' 'محتوى جديد'`, then refresh; use `draft` to unpublish. **The CLI is trusted local authoring, NOT an HTTP administrative API.** Do not make this shell command available to site visitors or untrusted tenants.

The preview intentionally has **no browser-based login, editor, migrated posts, or multi-site publisher**. Those belong in the next reviewed slice. There is no fake admin UI. Pages are never served as raw HTML; titles and bodies are escaped.

## Scope and safety

- Uses `DocumentStore` / `JsonStore`, `ContentObject`, and `PermissionEngine`.
- Public pages must be published + public + readable by anonymous users; site IDs are isolated.
- No new public write endpoint. No automatic data directory or SQLite creation. Existing `arbnt` remains unchanged.
- This source-tree preview is opt-in by environment variable; exported Static/Hybrid/Dynamic foundation artifacts remain unchanged and **do not include** the new content engine. This is deliberate, to preserve their security and independence until a reviewed bundler change is made.
- The source demo uses `example.invalid` and `noindex`; catalog URLs are not yet included in sitemap (avoid pretending SEO release readiness).
- Run `php tests/run.php` and `python3 tests/check_structure.py`. Compare to arbnt visually, but do not equate this with a migrated community.

## Next tested milestone

Use existing arbnt account/session code after security review, integrate role-based authoring into ONE Engine, provide a proper authenticated admin screen and a real article/page workflow, and incorporate safe published-page export with per-page sitemap. Do not rewrite arbnt's working UI or expose this CLI over HTTP.
