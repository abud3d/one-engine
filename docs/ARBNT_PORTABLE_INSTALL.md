# ARBNT portable community install — any subfolder

Source: `abud3d/arbnt` pinned commit `1b5c44b21bc328fef7bc4645ce3140dba0ed13d1`. Build: `scripts/package-arbnt-new2.sh`. This is an isolated deployable **ARBNT PHP/JSON community** in a ZIP, not the entire ONE Engine general-purpose runtime. ARBNT's existing screens, account system, posts, comments, communities, favorites, notifications and administration remain as originally implemented.

## Install without any shell or database

1. Download the CI artifact named **arbnt-portable-json-install**; extract `arbnt-portable-json.zip`.
2. Upload the **contents** (including hidden `.htaccess` files and `.private-app/`) into a **new empty folder of your choice**: `/new2/`, `/new2000/`, or `/anything/`. Never overwrite or merge with a previous ARBNT installation. The package does not need a configured path.
3. Open `https://YOURDOMAIN/YOURFOLDER/setup.php`. Choose a new site name, administrator username and a new strong password (12+ characters). No hard-coded shared credentials.
4. The installer automatically proves that `.private-data/` is inaccessible over HTTPS: a generated random probe must return **HTTP 403**, else installation refuses to write accounts. If it refuses, do **not** remove protections or use this package on a stack that ignores `.htaccess`.
5. After success: open `https://YOURDOMAIN/YOURFOLDER/login.html` and `.../admin.html`. **Delete `install.php` and `setup.php`** from the installed folder once configured, with ordinary file manager. Do not delete either hidden private directory or `.arbnt-*.php` pointer files.

## Important protected files and their roles

| Path (relative to the uploaded folder) | Purpose | Access |
|---|---|---|
| `index.html`, `community.html`, `admin.html` | Main UI, discussions and admin dashboard | Public UI; admin actions require login |
| `login.html`, `register.html`, `profile.html`, `publish.html` | Accounts / profile / publishing | Public pages; backend enforces membership |
| `api/community.php` | Existing ARBNT JSON HTTP API | Public endpoint with authentication/CSRF/HTTPS checks |
| `assets/*.css`, `assets/*.js` | Original styles and scripts | Public |
| `.private-data/*.json` | Accounts, communities, posts, comments, favorites, settings | **Hidden and access denied** |
| `.private-app/core.php` | Runtime PHP JSON storage/locking code moved from `app/core.php` at install | **Hidden and access denied** |
| `.private-data/.htaccess` and `.private-app/.htaccess` | Apache deny-all protection | **Keep these files** |
| `.htaccess` | Root deny-rules, no directory indexes and noindex | **Keep this file** |
| `.arbnt-app.php` and `.arbnt-storage.php` | Generated pointers tied to the current folder | Hidden and access denied |
| `setup.php`, `install.php` | One-time installer; both must be deleted after setup | Admin should delete after success |

No MySQL, Node, or Composer required. PHP 8.3+ and working Apache `AllowOverride` / `.htaccess` required for this inside-webroot storage model. **Apache + Nginx** is supported only if nginx denies or forwards these protected requests to Apache; a direct nginx static file handler ignoring `.htaccess` is unsafe and causes the installer to fail its HTTP 403 check. Never bypass this check.

## Isolation

The storage path resolves via the installation folder itself: `__DIR__/.private-data/`. The private runtime similarly lives in `.private-app/`. This avoids touching `/new`, a site-root `arbnt-private` or a sibling installation. There is no credential or real user dataset in the ZIP. The installer rejects a populated private storage directory rather than overwriting it.

## Known limitations

- This package is the **original ARBNT community** with path/isolation packaging; it does not include ONE Engine's central builder, shared permission engine or full modular CMS.
- Email activation/recovery depends on the hosting site's mail/SMTP setup; not verified on the target server.
- Site-specific browser testing, Apache/Nginx behavior, HTTPS and upload size cannot be validated without real deployment; the installer itself verifies the most important denial before storing JSON.
- Existing UI/business logic is reused, not rewritten; the package should be treated as a separate experimental site and not promoted into production until its real server behavior is verified.

The build follows the accessible `abud3d/talal-prompts` playbooks: secure-by-default, reuse before rewrite, genuine tests, a PR and verified CI evidence. No public `abud3d/talal` repository was available to inspect; the available coding reference was `abud3d/talal-prompts`.

## إصلاح رسالة «لا يمكن فحص حماية البيانات»

الإصدارات السابقة كانت تتضمن مجلد `.private-data/` داخل ZIP؛ أحياناً يرفع مدير الملفات المجلد بملكية أو صلاحيات لا تسمح لـPHP بكتابة ملف الاختبار. **هذه النسخة لا تشحن المجلد مسبقاً**، بل ينشئه المثبت بنفس مستخدم PHP وبصلاحية 0700، ويضيف ملف الحماية ثم يتحقق عبر طلب HTTPS أن الوصول المباشر يعطي **403**. إذا كان لديك مجلد قديم غير قابل للكتابة ولا يحوي سوى `.htaccess`، يحاول المثبت الاحتفاظ به باسم مخفي احتياطي ثم ينشئ مجلداً جديداً، دون مسح حسابات أو منشورات. إذا تعذر ذلك، تظهر رسالة صريحة بدلاً من إكمال تثبيت غير آمن.

ارفع ملفات ZIP الجديدة **فوق ملفات الكود الخاصة بنسختك التجريبية فقط**؛ لا تحذف `.private-data/` الموجود إذا به بيانات أو ملفات أخرى. يمكنك أيضاً استبدال `install.php` وحده إذا كنت لم تبدأ تثبيتاً ناجحاً. لا تتجاوز فحص 403.
