# Security and local operations

## Current exposure

The foundation is read-only over HTTP. It has no installer, signup, login,
uploads, mail, admin mutation API, remote deployment or production data import.
`/health` says only that the PHP entrypoint runs; it does not probe a database,
central connectivity or hosting readiness. Do not interpret it as a production
health assessment.

Only `public/` is a DocumentRoot. Never serve the repository root or a whole
release directory. Source, config, manifest, NOTICE and any private data must
stay outside the served directory. The development router uses exact routes
and never exposes arbitrary paths by returning `false`.

## Default HTTP policy

The PHP entrypoint supplies a restrictive CSP, frame denial, nosniff, no-referrer,
disabled camera/microphone/location and `Cache-Control: no-store`. It emits HSTS
only when the web server asserts HTTPS. Forwarded headers are not trusted.
SEO defaults to noindex. Unexpected exceptions become a generic 503 and a log
entry containing only the exception class, without user content or credentials.

Production must terminate TLS correctly, redirect HTTP at the web server and
set HTTPS for PHP through trusted configuration. Load `config/php.ini` or apply
its equivalent in the host's PHP configuration: errors hidden, uploads off,
strict Secure HttpOnly SameSite sessions. Do not assume a checked-in INI file is
loaded automatically. This release does not start sessions.

For static/hybrid files served directly by a server, PHP cannot add headers.
The bundle's `server-headers.json` is a configuration specification, **not an
auto-applied server file**. Apply equivalent web-server headers before hosting.
Example Nginx header snippet, within a TLS server block:

```nginx
add_header Content-Security-Policy "default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" always;
add_header X-Content-Type-Options nosniff always;
add_header X-Frame-Options DENY always;
add_header Referrer-Policy no-referrer always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header Strict-Transport-Security "max-age=31536000" always;
add_header Cache-Control no-store always;
```

These are reference settings, not a deployment performed or a full vhost recipe.
Validate header inheritance, TLS, filesystem ownership, allowed methods and
routing on the actual host. Serve static mode from `public/` without PHP;
hybrid prefers `/index.html` at `/` and sends `/health` to `/index.php`;
dynamic sends application requests to `/index.php`. Only the front controller
should execute under PHP; refuse arbitrary PHP filenames. Site origins are
root domains in this version, not subdirectory mounts.

## Filesystem and builds

The builder creates new private directories/files (0700/0600), so the web server
must run under the provisioned service account or receive narrowly scoped read
permissions before hosting. Do not solve permissions with 0777. Keep private
directories unavailable through aliases, symlinks or other virtual hosts.

The CLI does not modify an existing release. Build into a new local directory;
review `release.json` and the file allowlist, then decide how to host it. Manifests
are not signed. No automatic symlink switch, server restart, upload or SSH action
is present. Runtime bundles contain no Builder, CentralManagement or datastore.
They run without network connectivity to a center; tests verify that boundary
with independently started artifact servers.

Storage is an infrastructure primitive, not exposed by the sample UI. For a
local experiment create a dedicated directory using `mkdir -m 700 var/private`
and instantiate the selected adapter with its path and the actual public root.
Do not initialize it from user-supplied paths. See [STORAGE.md](STORAGE.md) for
backup/restore, local filesystem limits and crash-durability qualifications.

## Authenticated administration

The CMS administration validates identity/session version, configured HTTPS
Origin and CSRF, then applies schema/UTF-8 validation and site authorization.
Login attempts are bounded and successful mutations create audit events. Secure password recovery requires
trusted site URLs, hashed expiring single-use tokens, replay tests and session
revocation. Do not add uploads without a separate safe-storage design.

Private content, management responses and tokens are no-store and never included
in static builds. The administration can create private JSON backups with a
checksum and restore them under an exclusive operation lock; a failed document
write rolls back already applied documents. Stop external/legacy CLI writers
during restore. Off-host backup retention, signed archives, credential rotation
and privacy policy remain deployment responsibilities.

## Validation

Run `php tests/run.php` with PDO SQLite and process spawning enabled. Tests use
temporary synthetic directories, real parallel writers and local HTTP servers;
they clean up after themselves. Run `python3 tests/check_structure.py` and lint
all PHP files plus `bin/one`. GitHub Actions tests PHP 8.3 and 8.4 using pinned
action commits and read-only repository permissions. No CI deployment exists.
