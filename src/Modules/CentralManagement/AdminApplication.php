<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{Access, IdentityService, LoginThrottle, SessionGuard};
use OneEngine\Core\Action;
use OneEngine\Modules\Runtime\Renderer;

final readonly class AdminApplication
{
    public function __construct(private string $siteId, private IdentityService $identity, private SiteContent $content)
    {
    }

    public function run(string $method, string $path, array $post): string
    {
        $message = '';
        if ($method === 'POST') {
            SessionGuard::requireCsrf((string) ($post['csrf'] ?? ''));
            $action = (string) ($post['action'] ?? '');
            if ($action === 'login') {
                LoginThrottle::check();
                $user = $this->identity->authenticate($this->siteId, (string) ($post['username'] ?? ''), (string) ($post['password'] ?? ''));
                if ($user === null) {
                    LoginThrottle::failure();
                    $message = 'بيانات الدخول غير صحيحة.';
                } else {
                    LoginThrottle::success();
                    SessionGuard::login($user, $this->siteId);
                    header('Location: /admin', true, 303);
                    exit;
                }
            } elseif ($action === 'logout') {
                SessionGuard::logout();
                header('Location: /admin', true, 303);
                exit;
            } else {
                $actor = $this->requireActor();
                if ($action === 'section') {
                    $this->content->saveSection($this->siteId, $actor, $post);
                } elseif ($action === 'page') {
                    $this->content->savePage($this->siteId, $actor, $post);
                } elseif ($action === 'menu') {
                    $this->content->saveMenu($this->siteId, $actor, $post);
                } elseif ($action === 'user') {
                    if (!Access::allows($actor, Action::Delete, null, $this->siteId)) {
                        throw new \DomainException('Forbidden.');
                    }
                    $this->identity->create($this->siteId, (string) $post['id'], (string) $post['username'], (string) $post['password'], (string) $post['role']);
                } elseif ($action === 'delete') {
                    $this->content->delete($this->siteId, $actor, (string) ($post['kind'] ?? ''), (string) ($post['id'] ?? ''));
                } else {
                    throw new \InvalidArgumentException('Unknown action.');
                }
                $message = 'تم الحفظ بنجاح.';
            }
        }
        $actor = SessionGuard::actor($this->siteId);
        return $actor === null ? $this->login($message) : $this->dashboard($actor, $message);
    }

    private function requireActor(): array
    {
        $actor = SessionGuard::actor($this->siteId);
        if ($actor === null) {
            throw new \DomainException('Authentication required.');
        }
        return $actor;
    }

    private function login(string $message): string
    {
        return $this->shell('دخول الإدارة', '<main class="login"><h1>دخول ONE Engine</h1>' . self::notice($message)
            . '<form method="post"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="login">'
            . '<label>اسم المستخدم<input name="username" required autocomplete="username"></label><label>كلمة المرور<input type="password" name="password" required autocomplete="current-password"></label><button>دخول آمن</button></form></main>');
    }

    private function dashboard(array $actor, string $message): string
    {
        if (($actor['role'] ?? '') === 'member') {
            return $this->shell('حساب العضو', '<header><b>ONE Engine</b><a href="/">الموقع</a><form method="post"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="logout"><button>خروج</button></form></header><main><h1>حساب العضو</h1><p>تم تسجيل الدخول. يمكنك الآن فتح الصفحات المتاحة للأعضاء.</p></main>');
        }
        $sections = $this->content->list($this->siteId, 'sections');
        $pages = $this->content->list($this->siteId, 'cms_pages');
        $menus = $this->content->list($this->siteId, 'menus');
        $token = self::e(SessionGuard::csrf());
        $sectionOptions = '<option value="">بلا قسم</option>';
        foreach ($sections as $section) {
            $sectionOptions .= '<option value="' . self::e((string) $section['id']) . '">' . self::e((string) $section['title']) . '</option>';
        }
        $list = static function (array $records, string $preview = '', string $kind = '') use ($token, $actor): string {
            $html = '<ul>';
            foreach ($records as $record) {
                $link = $preview === '' ? '' : ' <a href="' . $preview . self::e((string) $record['id']) . '">معاينة</a>';
                $delete = ($actor['role'] ?? '') === 'admin' && $kind !== '' ? '<form class="inline" method="post"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="kind" value="' . self::e($kind) . '"><input type="hidden" name="id" value="' . self::e((string) $record['id']) . '"><button>حذف</button></form>' : '';
                $html .= '<li><b>' . self::e((string) ($record['title'] ?? $record['label'] ?? $record['id'])) . '</b> <code>' . self::e((string) $record['id']) . '</code> ' . self::e((string) ($record['state'] ?? $record['position'] ?? '')) . $link . $delete . '</li>';
            }
            return $html . '</ul>';
        };
        $blocksExample = self::e('[{"type":"heading","content":"عنوان","visibility":"public","column":1},{"type":"text","content":"نص البلوك","visibility":"public","column":1}]');
        $body = '<header><b>ONE Engine</b><nav><a href="/">الموقع</a><a href="/admin">الإدارة</a></nav><form method="post"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="action" value="logout"><button>خروج</button></form></header><main>'
            . '<h1>لوحة الموقع</h1><p>المستخدم: ' . self::e((string) $actor['username']) . ' — ' . self::e((string) $actor['role']) . '</p>' . self::notice($message)
            . '<section class="stats"><article>الأقسام <b>' . count($sections) . '</b></article><article>الصفحات <b>' . count($pages) . '</b></article><article>الروابط <b>' . count($menus) . '</b></article></section>'
            . '<section><h2>الأقسام</h2>' . $list($sections, '', 'sections') . self::form($token, 'section', '<label>الرابط<input name="id" required pattern="[a-z0-9][a-z0-9_-]{0,63}"></label><label>العنوان<input name="title" required></label><label>الوصف<textarea name="description"></textarea></label>' . self::choices(true)) . '</section>'
            . '<section><h2>الصفحات</h2>' . $list($pages, '/preview/', 'cms_pages') . self::form($token, 'page', '<label>الرابط<input name="id" required pattern="[a-z0-9][a-z0-9_-]{0,63}"></label><label>العنوان<input name="title" required></label><label>القسم<select name="section_id">' . $sectionOptions . '</select></label><label>المحتوى<textarea name="body"></textarea></label><label>البلوكات JSON<textarea name="blocks" placeholder="' . $blocksExample . '"></textarea></label><label>قراء محددون (معرفات مفصولة بفاصلة)<input name="allowed_users"></label><label>محررون محددون (فارغ = كل المحررين)<input name="allowed_editors"></label>' . self::choices(true)) . '</section>'
            . '<section><h2>القوائم</h2>' . $list($menus, '', 'menus') . self::form($token, 'menu', '<label>المعرف<input name="id" required></label><label>النص<input name="label" required></label><label>الرابط المحلي<input name="url" value="/" required></label><label>الموضع<select name="position"><option>header</option><option>sidebar</option><option>footer</option></select></label>' . self::visibility()) . '</section>';
        if (($actor['role'] ?? '') === 'admin') {
            $body .= '<section><h2>المستخدمون</h2>' . $list($this->identity->all($this->siteId)) . self::form($token, 'user', '<label>المعرف<input name="id" required></label><label>اسم الدخول<input name="username" required></label><label>كلمة المرور<input type="password" name="password" minlength="12" required></label><label>الدور<select name="role"><option>editor</option><option>member</option><option>admin</option></select></label>') . '</section>';
        }
        return $this->shell('لوحة الإدارة', $body . '</main>');
    }

    private static function form(string $token, string $action, string $fields): string
    {
        return '<form method="post"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="action" value="' . $action . '">' . $fields . '<button>حفظ</button></form>';
    }

    private static function choices(bool $state): string
    {
        return '<label>التخطيط<select name="layout"><option value="one">عمود</option><option value="two">عمودان</option><option value="three">ثلاثة أعمدة</option></select></label>' . self::visibility()
            . ($state ? '<label>الحالة<select name="state"><option value="draft">مسودة</option><option value="published">منشورة</option></select></label>' : '');
    }

    private static function visibility(): string
    {
        return '<label>الظهور<select name="visibility"><option value="public">عام</option><option value="members">للأعضاء</option><option value="private">خاص</option></select></label>';
    }

    private function shell(string $title, string $body): string
    {
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::e($title) . '</title><link rel="stylesheet" href="/assets/admin.css"></head><body>' . $body . '</body></html>';
    }

    private static function notice(string $message): string
    {
        return $message === '' ? '' : '<p class="notice">' . self::e($message) . '</p>';
    }

    private static function e(string $value): string
    {
        return Renderer::escape($value);
    }
}
