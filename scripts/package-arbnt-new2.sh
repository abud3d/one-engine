#!/usr/bin/env bash
set -euo pipefail

# Build a standalone ARBNT community for https://arbnt.com/new2/.
# No current server, production data, legacy /new folder, or VPS is touched.
source_dir="${1:?Usage: scripts/package-arbnt-new2.sh /path/to/arbnt-source}"
output_dir="${2:-dist}"
root="$output_dir/new2"
if [[ ! -f "$source_dir/install.php" || ! -f "$source_dir/app/core.php" || ! -f "$source_dir/api/community.php" ]]; then
  echo "Missing original ARBNT source. Build aborted." >&2
  exit 1
fi

rm -rf "$root"
mkdir -p "$root/assets" "$root/api" "$root/app"
for f in index.html community.html communities.html admin.html login.html register.html forgot.html profile.html favorites.html post.html publish.html; do
  test -f "$source_dir/$f" || { echo "Missing $f" >&2; exit 1; }
  cp "$source_dir/$f" "$root/$f"
done
cp "$source_dir"/assets/*.css "$root/assets/"
cp "$source_dir"/assets/*.js "$root/assets/"
cp "$source_dir/api/community.php" "$root/api/community.php"
cp "$source_dir/app/core.php" "$root/app/core.php"
cp "$source_dir/install.php" "$root/install.php"
cp "$source_dir/setup.php" "$root/setup.php"

# All changes below happen to the packaged COPY only.
python3 - "$root" <<'PY'
from pathlib import Path
import sys, re

root = Path(sys.argv[1])
private = 'arbnt-new2-private'
app = 'arbnt-new2-app'

# The stock ARBNT installer/store uses shared names arbnt-private and
# arbnt-app relative to the hosting account; these MUST be unique to /new2
# or they could read/write an existing application's JSON/account files.
for rel in ('install.php', 'app/core.php'):
    path = root / rel
    data = path.read_text(encoding='utf-8')
    assert 'arbnt-private' in data, rel
    data = data.replace('arbnt-private', private)
    data = data.replace("'/arbnt-app'", "'/arbnt-new2-app'")
    path.write_text(data, encoding='utf-8')

# The existing ARBNT URL handling is relative to the installation folder.
# Public search indexing is disabled for this experimental second installation.
for html in root.glob('*.html'):
    content = html.read_text(encoding='utf-8')
    content = re.sub(r'<meta\s+name="robots"[^>]*>', '', content, flags=re.I)
    content = content.replace('<head>', '<head><meta name="robots" content="noindex,nofollow">', 1)
    if html.name == 'index.html':
        content = content.replace('https://arbnt.com/', 'https://arbnt.com/new2/')
    html.write_text(content, encoding='utf-8')

# Prevent accidentally linking to the live ARBNT domain from test email URLs.
api = root / 'api/community.php'
data = api.read_text(encoding='utf-8')
# No rewriting of third-party destinations or permissions; the new2 scope is
# determined by the current request's host+script path and local private store.
api.write_text(data, encoding='utf-8')

# Add a version marker for deployment inspection; it contains no secrets.
(root / 'BUILD-NEW2.txt').write_text(
    'ARBNT JSON community /new2/\n'
    'Source: abud3d/arbnt (pinned by GitHub Actions)\n'
    'Isolated private directory: arbnt-new2-private\n'
    'Isolated PHP engine directory: arbnt-new2-app\n'
    'Install URL: https://arbnt.com/new2/setup.php\n'
    'Remove setup.php and install.php after successful installation.\n',
    encoding='utf-8'
)
PY

# Apache/CWP shared hosting: block private, source, hidden files, directory
# indexes and the status marker; no global htaccess is modified.
cat > "$root/.htaccess" <<'HT'
Options -Indexes
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^(?:app|storage)(?:/|$) - [F,L,NC]
RewriteRule (^|/)\. - [F,L]
RewriteRule ^BUILD-NEW2\.txt$ - [F,L,NC]
</IfModule>
<FilesMatch "^(?:core\.php|\.arbnt-.*|BUILD-NEW2\.txt)$">
  Require all denied
</FilesMatch>
<IfModule mod_headers.c>
Header always set X-Robots-Tag "noindex, nofollow"
Header always set X-Content-Type-Options "nosniff"
</IfModule>
HT
cat > "$root/app/.htaccess" <<'HT'
Require all denied
HT

# Validate the sandbox structure and that no original shared names remain in
# the core and installer. The newer app points to its own outside-webroot
# storage while keeping original API, UI, comments, admin and login logic.
! grep -q 'arbnt-private' "$root/install.php"
! grep -q 'arbnt-private' "$root/app/core.php"
! grep -q "'/arbnt-app'" "$root/install.php"
test -f "$root/.htaccess"
test -f "$root/api/community.php"
test -f "$root/admin.html"

for f in "$root"/api/*.php "$root"/app/*.php "$root"/install.php "$root"/setup.php; do php -l "$f" >/dev/null; done

# Small standalone zip: contents are uploaded INTO /new2, not a new2 folder
# nested inside /new2.
mkdir -p "$output_dir"
(cd "$root" && zip -q -r -X "../arbnt-new2-json.zip" .)
(cd "$output_dir" && sha256sum arbnt-new2-json.zip > arbnt-new2-json.zip.sha256)
echo "CREATED: $output_dir/arbnt-new2-json.zip"
du -h "$output_dir/arbnt-new2-json.zip"
