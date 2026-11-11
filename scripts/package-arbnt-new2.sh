#!/usr/bin/env bash
set -euo pipefail
# Standalone ARBNT PHP + JSON, portable to ANY folder under a domain.
# The package never reads, changes, or writes a neighboring installation.
source_dir="${1:?Usage: scripts/package-arbnt-new2.sh /path/to/arbnt-source}"
output_dir="${2:-dist}"
root="$output_dir/portable-site"
for f in install.php app/core.php api/community.php; do
  test -f "$source_dir/$f" || { echo "Missing $f" >&2; exit 1; }
done
rm -rf "$root"
mkdir -p "$root/assets" "$root/api" "$root/app" "$root/.private-data" "$root/.private-app"
for f in index.html community.html communities.html admin.html login.html register.html forgot.html profile.html favorites.html post.html publish.html; do
  test -f "$source_dir/$f" || exit 1
  cp "$source_dir/$f" "$root/$f"
done
cp "$source_dir"/assets/*.css "$root/assets/"
cp "$source_dir"/assets/*.js "$root/assets/"
cp "$source_dir/api/community.php" "$root/api/community.php"
cp "$source_dir/app/core.php" "$root/app/core.php"
cp "$source_dir/install.php" "$root/install.php"
cp "$source_dir/setup.php" "$root/setup.php"

python3 - "$root" <<'PY'
from pathlib import Path
import sys, re
root=Path(sys.argv[1])

# Replace ONLY the legacy cross-installation paths with a path derived from the
# current package directory. Never use DOCUMENT_ROOT for private storage.
p=root/'install.php'
s=p.read_text(encoding='utf-8')
assert "$private=dirname($base).'/arbnt-private';" in s
assert "$appOutside=dirname($base).'/arbnt-app';" in s
s=s.replace("$private=dirname($base).'/arbnt-private';","$private=__DIR__.'/.private-data';")
s=s.replace("$appOutside=dirname($base).'/arbnt-app';","$appOutside=__DIR__.'/.private-app';")
s=s.replace("is_file(dirname($base).'/arbnt-private/installed.lock')","is_file(__DIR__.'/.private-data/installed.lock')")
# Do not accept the previous fallback storage or old absolute pointers.
s=s.replace("$local=__DIR__.'/storage';","$local=__DIR__.'/.private-data';")
s=s.replace("if($existing)$errors[]=", "if(array_diff($existing,['.htaccess']))$errors[]=")
# The protected directory ships with an .htaccess. An actual 403 response is
# mandatory: if Apache rules are ignored (e.g., nginx alone), FAIL CLOSED.
probe=r'''
 if(!$errors){
   $probe='probe-'.bin2hex(random_bytes(12)).'.txt';
   $probePath=$private.'/'.$probe;
   if(!is_dir($private) || is_link($private))$errors[]='مجلد البيانات غير آمن.';
   elseif(file_put_contents($probePath,bin2hex(random_bytes(12)),LOCK_EX)===false)$errors[]='لا يمكن فحص حماية البيانات.';
   else {
     try {
       $host=(string)($_SERVER['HTTP_HOST']??'');
       $requestPath=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/');
       $url='https://'.$host.$requestPath.'/.private-data/'.$probe;
       $status=0;
       if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'
         && preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i',$host)){
         if(function_exists('curl_init')){
           $ch=curl_init($url);
           curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false]);
           curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
         }elseif(ini_get('allow_url_fopen')){
           $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>8,'follow_location'=>0]]);
           @file_get_contents($url,false,$ctx);
           foreach(($http_response_header??[]) as $header)
             if(preg_match('/^HTTP\/\S+ (\d{3})/',$header,$m)){$status=(int)$m[1];break;}
         }
       }
       if($status!==403)$errors[]='توقف التثبيت: تعذر إثبات الحماية (يلزم HTTP 403 لمجلد JSON).';
     }finally{@unlink($probePath);}
   }
 }
'''
needle=" if(!$errors){\n  if(!is_file(__DIR__.'/app/core.php'))"
assert needle in s
s=s.replace(needle,probe+needle,1)
s=s.replace("if(session_status()!==PHP_SESSION_ACTIVE)session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);",
"""$cookiePath=rtrim(str_replace('\\\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/').'/';
session_name('ARBNT_'.substr(hash('sha256',__DIR__),0,16));
if(session_status()!==PHP_SESSION_ACTIVE)session_start(['cookie_path'=>$cookiePath,'cookie_httponly'=>true,'cookie_secure'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);""")
assert "session_name('ARBNT_'" in s
# All archives are self-contained: no fallback outside /current-folder.
assert "dirname($base).'/arbnt-private'" not in s
assert "dirname($base).'/arbnt-app'" not in s
s=s.replace("يحفظ المثبت محرك PHP خارج المجلد العام، ويفضل تخزين البيانات خارج الموقع.", "يضع المثبت التخزين والمحرك داخل مجلدات مخفية محمية في مجلد هذه النسخة، بعد التأكد من منع وصول الويب إليها.")
s=s.replace("موقع التخزين:","موقع التخزين لهذه النسخة فقط:")
p.write_text(s,encoding='utf-8')

p=root/'app/core.php'
s=p.read_text(encoding='utf-8')
assert "dirname($base).'/arbnt-private'" in s
s=s.replace("dirname($base).'/arbnt-private'", "$root.'/.private-data'")
s=s.replace("$root.'/storage'", "$root.'/.private-data'")
p.write_text(s,encoding='utf-8')

p=root/'api/community.php'
s=p.read_text(encoding='utf-8')
old="session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);"
assert old in s, 'Expected original ARBNT session initialization'
s=s.replace(old,
"""$sessionBase=rtrim(str_replace('\\\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/');
$sessionBase=preg_replace('~/api$~','',$sessionBase);
$cookiePath=rtrim($sessionBase,'/').'/';
session_name('ARBNT_'.substr(hash('sha256',$publicRoot),0,16));
session_start(['cookie_path'=>$cookiePath,'cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);""")
assert "session_name('ARBNT_'" in s
p.write_text(s,encoding='utf-8')

# All app links are relative to this install folder; remove production canonical
# and other hardcoded production domain metadata from the test copy.
for path in root.glob('*.html'):
    data=path.read_text(encoding='utf-8')
    data=re.sub(r'<meta\s+name="robots"[^>]*>', '', data, flags=re.I)
    data=re.sub(r'<link\s+rel="canonical"[^>]*>', '', data, flags=re.I)
    data=data.replace('<head>','<head><meta name="robots" content="noindex,nofollow">',1)
    data=data.replace('https://arbnt.com/', './')
    path.write_text(data,encoding='utf-8')

# Installer path is always based on __DIR__, not on new2/new2000 or hostname.
(root/'BUILD.txt').write_text(
    'Portable ARBNT community (Native PHP + JSON)\n'
    'Install from https://YOUR-DOMAIN/ANY-SUBFOLDER/setup.php\n'
    'Protected private data: .private-data/*.json\n'
    'Protected private PHP engine: .private-app/core.php\n'
    'Both paths are relative to THIS directory only.\n'
    'Delete setup.php and install.php after installation.\n',
    encoding='utf-8')
PY

# Apache with AllowOverride enabled. The installer tests an actual HTTP 403 and
# refuses to create users if the hosting stack ignores .htaccess.
cat > "$root/.htaccess" <<'HT'
Options -Indexes
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^(?:\.private-data|\.private-app|app|storage)(?:/|$) - [F,L,NC]
RewriteRule (^|/)\. - [F,L]
RewriteRule ^BUILD\.txt$ - [F,L,NC]
</IfModule>
<FilesMatch "^(?:\.arbnt-.*|BUILD\.txt)$">
Require all denied
</FilesMatch>
<IfModule mod_headers.c>
Header always set X-Robots-Tag "noindex, nofollow"
Header always set X-Content-Type-Options "nosniff"
</IfModule>
HT
for d in .private-data .private-app app; do
cat > "$root/$d/.htaccess" <<'HT'
<IfModule mod_authz_core.c>
Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
Deny from all
</IfModule>
HT
done
test -f "$root/.private-data/.htaccess"
test -f "$root/.private-app/.htaccess"
test -f "$root/app/.htaccess"
! grep -q "dirname(\$base).'/arbnt-private'" "$root/install.php"
! grep -q "dirname(\$base).'/arbnt-private'" "$root/app/core.php"
for f in "$root"/api/*.php "$root"/app/*.php "$root"/install.php "$root"/setup.php; do php -l "$f" >/dev/null; done

mkdir -p "$output_dir"
(cd "$root" && zip -q -r -X "../arbnt-portable-json.zip" .)
(cd "$output_dir" && sha256sum arbnt-portable-json.zip > arbnt-portable-json.zip.sha256)
echo "CREATED: $output_dir/arbnt-portable-json.zip"
du -h "$output_dir/arbnt-portable-json.zip"
