#!/usr/bin/env bash
set -euo pipefail

archive="${1:?CMS ZIP is required}"
work="${2:?new work directory is required}"
if [[ -e "$work" ]]; then
  echo "Apache acceptance directory already exists" >&2
  exit 1
fi
mkdir -m 700 -p "$work"
work="$(realpath "$work")"
archive="$(realpath "$archive")"
unzip -q "$archive" -d "$work"
root="$work/one-engine-cms"
private="$work/private-data"
ONE_ADMIN_PASSWORD='apache acceptance password' php "$root/bin/one-install" "$private" apache.admin 'https://cms.example.test'

name="one-cms-apache-${RANDOM}-${RANDOM}"
cleanup() {
  docker rm -f "$name" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker run -d --name "$name" -p 127.0.0.1::80 -v "$work:$work" -e "CMS_ROOT=$root" -e "HOST_UID=$(id -u)" -e "HOST_GID=$(id -g)" php:8.3-apache \
  bash -lc 'printf "export APACHE_RUN_USER=#%s\nexport APACHE_RUN_GROUP=#%s\n" "$HOST_UID" "$HOST_GID" >> /etc/apache2/envvars && a2enmod rewrite >/dev/null && sed -ri "s!/var/www/html!${CMS_ROOT}/public!g" /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/docker-php.conf && printf "<Directory %s/public>\nAllowOverride All\nRequire all granted\n</Directory>\n" "$CMS_ROOT" > /etc/apache2/conf-available/one-cms.conf && a2enconf one-cms >/dev/null && apache2-foreground' >/dev/null
port="$(docker port "$name" 80/tcp | sed -n 's/.*://p')"
base="http://localhost:${port}"
for _ in $(seq 1 50); do
  if curl -fsS "$base/admin" -o "$work/login.html" -D "$work/login.headers" -c "$work/cookies.txt"; then break; fi
  sleep 0.2
done
grep -q 'دخول ONE Engine' "$work/login.html"
csrf="$(sed -n 's/.*name="csrf" value="\([a-f0-9]\{64\}\)".*/\1/p' "$work/login.html" | head -n1)"
test -n "$csrf"
status="$(curl -sS -o /dev/null -w '%{http_code}' -b "$work/cookies.txt" -c "$work/cookies.txt" -H 'Origin: https://cms.example.test' --data-urlencode "csrf=$csrf" --data-urlencode 'action=login' --data-urlencode 'username=apache.admin' --data-urlencode 'password=apache acceptance password' "$base/admin")"
test "$status" = '303'
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/config/local.php")" = '404'
test "$(curl -sS -o /dev/null -w '%{http_code}' "$base/p/not-found")" = '404'
echo 'Apache PHP 8.3 package routing, login and private-file isolation passed.'
