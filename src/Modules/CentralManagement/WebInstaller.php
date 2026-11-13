<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Core\EngineVersion;
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\IdentityService;
use OneEngine\Modules\Runtime\Site;
use OneEngine\Security\OriginGuard;
use OneEngine\Storage\JsonStore;

final readonly class WebInstaller
{
    public function __construct(private string $root) {}

    public function run(string $method, array $post, array $server): string
    {
        $local = $this->root . '/config/local.php';
        if (is_file($local)) return $this->page('التثبيت مكتمل', '<section class="card"><h1>ONE Engine مثبت بالفعل</h1><p>أُغلق معالج الإعداد لحماية الموقع.</p><a class="button" href="/admin">الدخول إلى الإدارة</a></section>');
        $https = isset($server['HTTPS']) && $server['HTTPS'] !== '' && $server['HTTPS'] !== 'off';
        $this->session($https);
        $message = '';
        if ($method === 'POST') {
            if (!hash_equals((string) ($_SESSION['setup_csrf'] ?? ''), (string) ($post['csrf'] ?? ''))) throw new \DomainException('انتهت صلاحية الطلب. أعد تحميل الصفحة.');
            $origin = OriginGuard::validate((string) ($post['site_url'] ?? ''));
            if (!hash_equals($origin, (string) ($server['HTTP_ORIGIN'] ?? ''))) throw new \DomainException('مصدر الطلب غير موثوق.');
            if ((string) ($post['password'] ?? '') !== (string) ($post['password_confirm'] ?? '')) throw new \InvalidArgumentException('كلمتا المرور غير متطابقتين.');
            $this->install($post, $origin);
            session_regenerate_id(true);
            return $this->page('اكتمل التثبيت', '<section class="card success"><h1>تم تثبيت ONE Engine بنجاح</h1><p>تم إنشاء التخزين الخاص وحساب المدير وقفل المثبّت.</p><a class="button" href="/admin">الدخول إلى لوحة الإدارة</a></section>');
        }
        $checks = $this->checks($https);
        $ready = !in_array(false, array_column($checks, 'ok'), true);
        $rows = '';
        foreach ($checks as $check) $rows .= '<li class="' . ($check['ok'] ? 'ok' : 'bad') . '"><b>' . self::e($check['label']) . '</b><span>' . self::e($check['detail']) . '</span></li>';
        $engine = EngineVersion::current();
        $csrf = bin2hex(random_bytes(32));
        $_SESSION['setup_csrf'] = $csrf;
        $form = '<section class="card"><h2>1. فحص البيئة</h2><ul class="checks">' . $rows . '</ul></section><section class="card"><h2>2. إعداد الموقع والمدير</h2>'
            . ($ready ? '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><label>اسم الموقع<input name="site_name" required maxlength="120"></label><label>رابط الموقع HTTPS<input type="url" name="site_url" required placeholder="https://example.com"></label><label>اللغة الافتراضية<select name="language"><option value="ar">العربية</option><option value="en">English</option></select></label><label>اسم المدير<input name="username" required pattern="[a-z0-9][a-z0-9_.-]{2,31}" dir="ltr"></label><label>البريد الإلكتروني (اختياري)<input type="email" name="email" maxlength="254"></label><label>كلمة المرور<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>تأكيد كلمة المرور<input type="password" name="password_confirm" required minlength="12" autocomplete="new-password"></label><button>تثبيت JSON وقفل المعالج</button></form>' : '<p class="bad">صحح المتطلبات أعلاه ثم أعد تحميل الصفحة.</p>') . '</section>';
        return $this->page('تثبيت ONE Engine', '<header><b>ONE Engine</b><span dir="ltr">v' . self::e($engine->version) . ' · ' . self::e($engine->build) . '</span></header><main><section class="hero"><p>معالج تثبيت عربي آمن دون SSH أو قاعدة بيانات مسبقة.</p></section>' . $form . '</main>');
    }

    private function install(array $post, string $origin): void
    {
        $lockPath = $this->root . '/config/setup.lock';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false || !chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) throw new \RuntimeException('تعذر قفل المثبّت.');
        try {
            if (is_file($this->root . '/config/local.php')) throw new \DomainException('الموقع مثبت بالفعل.');
            $private = dirname($this->root) . '/one-engine-private-' . substr(hash('sha256', $this->root), 0, 12);
            if (!is_dir($private) && !mkdir($private, 0700)) throw new \RuntimeException('تعذر إنشاء مجلد البيانات خارج مجلد الويب.');
            if (is_link($private) || !chmod($private, 0700)) throw new \RuntimeException('تعذر حماية مجلد البيانات.');
            $site = Site::fromArray(require $this->root . '/config/site.php');
            $store = new JsonStore($private, $this->root . '/public');
            $id = 'admin-' . bin2hex(random_bytes(8));
            (new IdentityService($store))->create($site->id, $id, (string) ($post['username'] ?? ''), (string) ($post['password'] ?? ''), 'admin');
            $actor = ['id' => $id, 'username' => (string) $post['username'], 'role' => 'admin'];
            (new SiteContent($store))->saveSettings($site->id, $actor, ['title' => (string) ($post['site_name'] ?? ''), 'base_url' => $origin, 'description' => '', 'indexable' => '']);
            $this->writeLocal(['private_dir' => realpath($private), 'trusted_origin' => $origin, 'storage' => 'json', 'language' => in_array(($post['language'] ?? ''), ['ar', 'en'], true) ? $post['language'] : 'ar']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeLocal(array $config): void
    {
        $path = $this->root . '/config/local.php';
        $content = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = fopen($temp, 'x+b');
        if ($handle === false) throw new \RuntimeException('تعذر إنشاء إعدادات الموقع.');
        try {
            if (!chmod($temp, 0600) || fwrite($handle, $content) !== strlen($content) || !fflush($handle) || !fsync($handle) || !rename($temp, $path)) throw new \RuntimeException('تعذر حفظ إعدادات الموقع.');
        } finally {
            fclose($handle);
            if (is_file($temp)) unlink($temp);
        }
    }

    private function checks(bool $https): array
    {
        return [
            ['label' => 'PHP 8.3+', 'ok' => PHP_VERSION_ID >= 80300, 'detail' => PHP_VERSION],
            ['label' => 'JSON والجلسات', 'ok' => extension_loaded('json') && function_exists('session_start'), 'detail' => 'مطلوبة للتثبيت الافتراضي'],
            ['label' => 'الكتابة في config', 'ok' => is_writable($this->root . '/config'), 'detail' => 'لحفظ إعداد محمي وقفل التثبيت'],
            ['label' => 'مسار البيانات الخاص', 'ok' => is_writable(dirname($this->root)), 'detail' => 'سيُنشأ خارج public تلقائيًا'],
            ['label' => 'HTTPS', 'ok' => $https, 'detail' => $https ? 'الاتصال آمن' : 'فعّل شهادة SSL وافتح /setup عبر HTTPS'],
            ['label' => 'SQLite الاختياري', 'ok' => true, 'detail' => extension_loaded('pdo_sqlite') ? 'متاح للترقية لاحقًا' : 'غير متاح حاليًا؛ JSON سيعمل بصورة طبيعية'],
        ];
    }

    private function session(bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name('ONESETUP');
        session_set_cookie_params(['secure' => $secure, 'httponly' => true, 'samesite' => 'Strict', 'path' => '/setup']);
        if (!session_start()) throw new \RuntimeException('تعذر بدء جلسة التثبيت.');
    }

    private function page(string $title, string $body): string
    {
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::e($title) . '</title><link rel="stylesheet" href="/assets/setup.css"></head><body>' . $body . '</body></html>';
    }

    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
