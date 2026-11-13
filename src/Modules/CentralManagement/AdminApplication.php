<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Core\Action;
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{Access, IdentityService, LoginThrottle, SessionGuard};
use OneEngine\Modules\Runtime\Renderer;

final readonly class AdminApplication
{
    public function __construct(
        private string $siteId,
        private IdentityService $identity,
        private SiteContent $content,
        private ?AuditLog $audit = null,
        private ?BackupService $backups = null,
        private ?OperationLock $lock = null,
        private array $siteDefaults = [],
    ) {}

    public function run(string $method, string $path, array $post, array $query = []): string
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
                    $this->audit?->record($this->siteId, 'anonymous', 'login.failed', substr(hash('sha256', (string) ($post['username'] ?? '')), 0, 24));
                    $message = 'بيانات الدخول غير صحيحة.';
                } else {
                    LoginThrottle::success();
                    SessionGuard::login($user, $this->siteId);
                    $this->audit?->record($this->siteId, (string) $user['id'], 'login.success', 'admin');
                    header('Location: /admin', true, 303);
                    exit;
                }
            } elseif ($action === 'logout') {
                SessionGuard::logout();
                header('Location: /admin', true, 303);
                exit;
            } else {
                $actor = $this->actor();
                if ($action === 'backup_create') {
                    $this->admin($actor);
                    if ($this->backups === null) throw new \RuntimeException('Backup service unavailable.');
                    $name = $this->backups->create($this->siteId);
                    $this->audit?->record($this->siteId, (string) $actor['id'], 'backup.created', $name);
                    $message = 'تم إنشاء النسخة الاحتياطية.';
                } elseif ($action === 'backup_restore') {
                    $this->admin($actor);
                    $name = (string) ($post['name'] ?? '');
                    if ($this->backups === null) throw new \RuntimeException('Backup service unavailable.');
                    $this->backups->restore($this->siteId, $name);
                    $this->audit?->record($this->siteId, (string) $actor['id'], 'backup.restored', $name);
                    $message = 'تمت الاستعادة والتحقق من سلامة النسخة.';
                } else {
                    $work = function () use ($action, $actor, $post): void {
                        match ($action) {
                            'section' => $this->content->saveSection($this->siteId, $actor, $post),
                            'page' => $this->content->savePage($this->siteId, $actor, $post + ['blocks' => $this->blocks($post)]),
                            'menu' => $this->content->saveMenu($this->siteId, $actor, $post),
                            'template' => $this->content->saveTemplate($this->siteId, $actor, $post),
                            'media' => $this->content->saveMedia($this->siteId, $actor, $post),
                            'settings' => $this->content->saveSettings($this->siteId, $actor, $post),
                            'user' => $this->createUser($actor, $post),
                            'user_update' => $this->updateUser($actor, $post),
                            'delete' => $this->content->delete($this->siteId, $actor, (string) ($post['kind'] ?? ''), (string) ($post['id'] ?? '')),
                            default => throw new \InvalidArgumentException('Unknown action.'),
                        };
                        $target = (string) ($post['id'] ?? $post['kind'] ?? 'site');
                        $this->audit?->record($this->siteId, (string) $actor['id'], 'content.' . $action, substr($target, 0, 160));
                    };
                    if ($this->lock === null) $work(); else $this->lock->shared($work);
                    $message = 'تم الحفظ بنجاح.';
                }
            }
        }
        $actor = SessionGuard::actor($this->siteId);
        return $actor === null ? $this->login($message) : $this->dashboard($actor, $message, $query);
    }

    private function actor(): array
    {
        return SessionGuard::actor($this->siteId) ?? throw new \DomainException('Authentication required.');
    }

    private function createUser(array $actor, array $post): void
    {
        $this->admin($actor);
        $this->identity->create($this->siteId, (string) $post['id'], (string) $post['username'], (string) $post['password'], (string) $post['role']);
    }

    private function updateUser(array $actor, array $post): void
    {
        $this->admin($actor);
        $this->identity->update($this->siteId, (string) $post['id'], (string) $post['role'], ($post['active'] ?? '') === '1', (string) ($post['password'] ?? ''));
    }

    private function admin(array $actor): void
    {
        if (!Access::allows($actor, Action::Delete, null, $this->siteId)) {
            throw new \DomainException('Forbidden.');
        }
    }

    private function login(string $message): string
    {
        return $this->shell('دخول الإدارة', '<main class="login"><h1>دخول ONE Engine</h1>' . self::notice($message)
            . '<form method="post"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="login">'
            . '<label>اسم المستخدم<input name="username" required autocomplete="username"></label><label>كلمة المرور<input type="password" name="password" required autocomplete="current-password"></label><button>دخول آمن</button></form></main>');
    }

    private function dashboard(array $actor, string $message, array $query): string
    {
        if (($actor['role'] ?? '') === 'member') {
            return $this->shell('حساب العضو', $this->header($actor) . '<main><h1>حساب العضو</h1><p>يمكنك فتح الصفحات العامة أو المخصصة لمعرفك.</p></main>');
        }
        $view = is_string($query['view'] ?? null) ? $query['view'] : 'overview';
        $allowed = ['overview', 'sections', 'pages', 'templates', 'menus', 'media', 'users', 'settings', 'audit', 'backups'];
        if (!in_array($view, $allowed, true) || (in_array($view, ['users', 'settings', 'audit', 'backups'], true) && $actor['role'] !== 'admin')) {
            $view = 'overview';
        }
        $content = match ($view) {
            'sections' => $this->sections($query),
            'pages' => $this->pages($query),
            'templates' => $this->templates($query),
            'menus' => $this->menus($query),
            'media' => $this->media($query),
            'users' => $this->users(),
            'settings' => $this->settingsView(),
            'audit' => $this->auditView(),
            'backups' => $this->backupsView(),
            default => $this->overview(),
        };
        return $this->shell('لوحة الإدارة', $this->header($actor) . '<main><div class="page-title"><div><p class="eyebrow">إدارة الموقع</p><h1>لوحة الموقع</h1></div><a class="secondary" href="/" target="_blank" rel="noopener">فتح الموقع</a></div>' . self::notice($message) . $content . '</main>');
    }

    private function header(array $actor): string
    {
        $links = ['overview' => 'الرئيسية', 'sections' => 'الأقسام', 'pages' => 'الصفحات', 'templates' => 'القوالب', 'menus' => 'القوائم', 'media' => 'الوسائط'];
        if ($actor['role'] === 'admin') {
            $links['users'] = 'الأعضاء والصلاحيات';
            $links['settings'] = 'إعدادات الموقع';
            $links['audit'] = 'سجل التدقيق';
            $links['backups'] = 'النسخ الاحتياطي';
        }
        $nav = '';
        foreach ($links as $view => $label) $nav .= '<a href="/admin?view=' . $view . '">' . $label . '</a>';
        return '<header class="admin-header"><a class="logo" href="/admin">ONE Engine</a><nav>' . $nav . '</nav><div class="account"><span>' . self::e((string) $actor['username']) . ' · ' . self::e((string) $actor['role']) . '</span><form method="post"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="logout"><button class="secondary">خروج</button></form></div></header>';
    }

    private function overview(): string
    {
        $sections = $this->content->list($this->siteId, 'sections');
        $pages = $this->content->list($this->siteId, 'cms_pages');
        $published = count(array_filter($pages, static fn (array $p): bool => ($p['state'] ?? '') === 'published'));
        return '<section class="stats"><article><span>الأقسام</span><b>' . count($sections) . '</b></article><article><span>الصفحات</span><b>' . count($pages) . '</b></article><article><span>المنشور</span><b>' . $published . '</b></article><article><span>المسودات</span><b>' . (count($pages) - $published) . '</b></article></section><section><h2>ابدأ العمل</h2><div class="quick-actions"><a class="button" href="/admin?view=pages">إنشاء صفحة</a><a class="secondary" href="/admin?view=sections">إدارة الأقسام</a><a class="secondary" href="/admin?view=templates">إدارة القوالب</a></div></section>';
    }

    private function sections(array $query): string
    {
        $rows = $this->content->list($this->siteId, 'sections');
        $edit = $this->selected($rows, $query['edit'] ?? null);
        $fields = $this->input('id', 'الرابط', $edit['id'] ?? '', true) . $this->input('title', 'العنوان', $edit['title'] ?? '', true) . $this->textarea('description', 'الوصف', $edit['description'] ?? '') . $this->layoutFields($edit);
        return $this->listing('الأقسام', $rows, 'sections', 'sections') . '<section><h2>' . ($edit ? 'تعديل القسم' : 'قسم جديد') . '</h2>' . $this->form('section', $fields) . '</section>';
    }

    private function pages(array $query): string
    {
        $rows = $this->content->list($this->siteId, 'cms_pages');
        $edit = $this->selected($rows, $query['edit'] ?? null);
        $users = $this->identity->all($this->siteId);
        $blocks = is_array($edit['blocks'] ?? null) && $edit['blocks'] !== [] ? $edit['blocks'] : [['type' => 'text', 'content' => '', 'visibility' => 'public', 'column' => 1]];
        $fields = $this->input('id', 'الرابط', $edit['id'] ?? '', true) . $this->input('title', 'العنوان', $edit['title'] ?? '', true)
            . '<label>القسم<select name="section_id">' . $this->options($this->content->list($this->siteId, 'sections'), $edit['section_id'] ?? '', true) . '</select></label>'
            . '<label>القالب<select name="template_id">' . $this->options($this->content->list($this->siteId, 'templates'), $edit['template_id'] ?? '', true) . '</select></label>'
            . $this->textarea('description', 'وصف SEO', $edit['description'] ?? '') . $this->textarea('body', 'مقدمة الصفحة', $edit['body'] ?? '')
            . '<fieldset class="wide"><legend>البلوكات</legend><p class="help">أضف البلوكات دون كتابة JSON.</p><div id="block-list">';
        foreach ($blocks as $block) $fields .= $this->block($block);
        $fields .= '</div><button type="button" class="secondary" data-add-block>إضافة بلوك</button></fieldset>'
            . '<fieldset class="wide"><legend>الصلاحيات</legend><div class="permission-grid"><div><b>القراء المحددون</b>' . $this->checks('allowed_users', $users, $edit['allowed_users'] ?? []) . '</div><div><b>المحررون المحددون</b><p class="help">فارغ يعني جميع المحررين.</p>' . $this->checks('allowed_editors', array_values(array_filter($users, static fn (array $u): bool => $u['role'] === 'editor')), $edit['allowed_editors'] ?? []) . '</div></div></fieldset>' . $this->layoutFields($edit);
        return $this->listing('الصفحات', $rows, 'cms_pages', 'pages', true) . '<section><h2>' . ($edit ? 'تحرير الصفحة' : 'صفحة جديدة') . '</h2>' . $this->mediaDatalist() . $this->form('page', $fields, 'page-editor') . '</section>';
    }

    private function templates(array $query): string
    {
        $rows = $this->content->list($this->siteId, 'templates');
        $edit = $this->selected($rows, $query['edit'] ?? null);
        $fields = $this->input('id', 'معرف القالب', $edit['id'] ?? '', true) . $this->input('title', 'اسم القالب', $edit['title'] ?? '', true) . $this->textarea('description', 'الوصف', $edit['description'] ?? '') . '<label>التخطيط<select name="layout">' . self::choices(['one' => 'عمود واحد', 'two' => 'عمودان', 'three' => 'ثلاثة أعمدة'], (string) ($edit['layout'] ?? 'one')) . '</select></label><label class="check"><input type="checkbox" name="sidebar" value="1"' . (!empty($edit['sidebar']) ? ' checked' : '') . '> شريط جانبي</label>';
        return $this->listing('القوالب والتخطيطات', $rows, 'templates', 'templates') . '<section><h2>' . ($edit ? 'تعديل القالب' : 'قالب جديد') . '</h2>' . $this->form('template', $fields) . '</section>';
    }

    private function menus(array $query): string
    {
        $rows = $this->content->list($this->siteId, 'menus');
        $edit = $this->selected($rows, $query['edit'] ?? null);
        $fields = $this->input('id', 'المعرف', $edit['id'] ?? '', true) . $this->input('label', 'النص', $edit['label'] ?? '', true) . $this->input('url', 'الرابط المحلي', $edit['url'] ?? '/', true) . '<label>الموضع<select name="position">' . self::choices(['header' => 'أعلى الصفحة', 'sidebar' => 'الجانب', 'footer' => 'التذييل'], (string) ($edit['position'] ?? 'header')) . '</select></label>' . $this->visibility((string) ($edit['visibility'] ?? 'public'));
        return $this->listing('القوائم والروابط', $rows, 'menus', 'menus') . '<section><h2>' . ($edit ? 'تعديل الرابط' : 'رابط جديد') . '</h2>' . $this->form('menu', $fields) . '</section>';
    }

    private function media(array $query): string
    {
        $rows = $this->content->list($this->siteId, 'media');
        $edit = $this->selected($rows, $query['edit'] ?? null);
        $fields = $this->input('id', 'المعرف', $edit['id'] ?? '', true) . $this->input('title', 'اسم الوسيط', $edit['title'] ?? '', true) . $this->input('url', 'رابط الصورة HTTPS', $edit['url'] ?? '', true) . $this->input('alt', 'النص البديل', $edit['alt'] ?? '', false);
        return $this->listing('مكتبة الوسائط', $rows, 'media', 'media', false, 'تدير هذه المرحلة مراجع HTTPS. رفع الملفات مؤجل حتى يكتمل فحص المحتوى والتخزين.') . '<section><h2>' . ($edit ? 'تعديل الوسيط' : 'إضافة وسيط') . '</h2>' . $this->form('media', $fields) . '</section>';
    }

    private function users(): string
    {
        $rows = '';
        foreach ($this->identity->all($this->siteId) as $user) {
            $rows .= '<tr><td><b>' . self::e($user['username']) . '</b><br><code>' . self::e($user['id']) . '</code></td><td><form method="post" class="row-form"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="user_update"><input type="hidden" name="id" value="' . self::e($user['id']) . '"><select name="role">' . self::choices(['admin' => 'مدير', 'editor' => 'محرر', 'member' => 'عضو'], $user['role']) . '</select><label class="check"><input type="checkbox" name="active" value="1"' . ($user['active'] ? ' checked' : '') . '> نشط</label><input type="password" name="password" minlength="12" placeholder="كلمة مرور جديدة (اختياري)"><button>تحديث</button></form></td></tr>';
        }
        $matrix = '';
        foreach (IdentityService::roleCapabilities() as $role => $actions) $matrix .= '<tr><th>' . self::e($role) . '</th><td>' . self::e(implode('، ', $actions)) . '</td></tr>';
        $new = $this->input('id', 'معرف العضو', '', true) . $this->input('username', 'اسم الدخول', '', true) . '<label>كلمة المرور<input type="password" name="password" minlength="12" required></label><label>الدور<select name="role">' . self::choices(['editor' => 'محرر', 'member' => 'عضو', 'admin' => 'مدير'], 'member') . '</select></label>';
        return '<section><h2>الأعضاء والأدوار</h2><div class="table-wrap"><table><tbody>' . $rows . '</tbody></table></div></section><section><h2>مصفوفة الأدوار</h2><table><tbody>' . $matrix . '</tbody></table></section><section><h2>عضو جديد</h2>' . $this->form('user', $new) . '</section>';
    }

    private function auditView(): string
    {
        if ($this->audit === null) return '<section><h2>سجل التدقيق</h2><p class="empty">الخدمة غير مهيأة.</p></section>';
        $rows = '';
        foreach ($this->audit->recent($this->siteId) as $event) {
            $rows .= '<tr><td>' . self::e((string) ($event['created_at'] ?? '')) . '</td><td><code>' . self::e((string) ($event['actor_id'] ?? '')) . '</code></td><td>' . self::e((string) ($event['action'] ?? '')) . '</td><td>' . self::e((string) ($event['target'] ?? '')) . '</td></tr>';
        }
        return '<section><h2>سجل التدقيق الإداري</h2><p class="help">آخر 100 عملية. لا تعرض كلمات المرور أو محتوى الصفحات.</p><div class="table-wrap"><table><thead><tr><th>الوقت UTC</th><th>الفاعل</th><th>العملية</th><th>الهدف</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
    }

    private function settingsView(): string
    {
        $settings = array_merge($this->siteDefaults, $this->content->settings($this->siteId));
        $fields = $this->input('title', 'اسم الموقع', $settings['title'] ?? '', true)
            . $this->input('base_url', 'النطاق الأساسي HTTPS', $settings['base_url'] ?? '', true)
            . $this->textarea('description', 'وصف الموقع وSEO', $settings['description'] ?? '')
            . '<label class="check"><input type="checkbox" name="indexable" value="1"' . (!empty($settings['indexable']) ? ' checked' : '') . '> السماح لمحركات البحث بالفهرسة</label>';
        return '<section><h2>إعدادات الموقع وSEO</h2><p class="help">تُستخدم هذه القيم في العرض العام وcanonical وrobots وملفات التصدير.</p>' . $this->form('settings', $fields) . '</section>';
    }

    private function backupsView(): string
    {
        if ($this->backups === null) return '<section><h2>النسخ الاحتياطي</h2><p class="empty">الخدمة غير مهيأة.</p></section>';
        $rows = '';
        foreach ($this->backups->all() as $backup) {
            $rows .= '<tr><td><code>' . self::e($backup['name']) . '</code></td><td>' . number_format($backup['size']) . ' bytes</td><td>' . self::e($backup['modified']) . '</td><td><form method="post" class="inline" data-confirm="ستستبدل الاستعادة بيانات الموقع الحالية. هل أنت متأكد؟"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="backup_restore"><input type="hidden" name="name" value="' . self::e($backup['name']) . '"><button class="danger">استعادة</button></form></td></tr>';
        }
        if ($rows === '') $rows = '<tr><td colspan="4" class="empty">لا توجد نسخ بعد.</td></tr>';
        return '<section><div class="section-title"><div><h2>النسخ الاحتياطي والاستعادة</h2><p class="help">تُحفظ النسخ بصلاحية خاصة خارج public وتُفحص قبل الاستعادة.</p></div><form method="post"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="backup_create"><button>إنشاء نسخة الآن</button></form></div><div class="table-wrap"><table><thead><tr><th>الملف</th><th>الحجم</th><th>التاريخ</th><th>الإجراء</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
    }

    private function listing(string $title, array $records, string $kind, string $view, bool $preview = false, string $help = ''): string
    {
        $rows = '';
        foreach ($records as $record) {
            $name = (string) ($record['title'] ?? $record['label'] ?? $record['id']);
            $status = (string) ($record['state'] ?? $record['position'] ?? $record['layout'] ?? '');
            $open = $preview ? '<a href="/preview/' . self::e((string) $record['id']) . '" target="_blank" rel="noopener">معاينة</a>' : '';
            $rows .= '<tr><td><b>' . self::e($name) . '</b><br><code>' . self::e((string) $record['id']) . '</code></td><td>' . self::e($status) . '</td><td class="actions"><a href="/admin?view=' . $view . '&amp;edit=' . rawurlencode((string) $record['id']) . '">تعديل</a>' . $open . $this->deleteForm($kind, (string) $record['id']) . '</td></tr>';
        }
        $table = $rows === '' ? '<p class="empty">لا توجد عناصر بعد.</p>' : '<div class="table-wrap"><table><thead><tr><th>العنصر</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        return '<section><div class="section-title"><h2>' . self::e($title) . '</h2><a class="button" href="/admin?view=' . $view . '">إضافة جديد</a></div>' . ($help === '' ? '' : '<p class="help">' . self::e($help) . '</p>') . $table . '</section>';
    }

    private function deleteForm(string $kind, string $id): string
    {
        $actor = SessionGuard::actor($this->siteId);
        if (($actor['role'] ?? '') !== 'admin') return '';
        return '<form method="post" class="inline" data-confirm="هل تريد حذف هذا العنصر؟"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="kind" value="' . self::e($kind) . '"><input type="hidden" name="id" value="' . self::e($id) . '"><button class="danger">حذف</button></form>';
    }

    private function form(string $action, string $fields, string $class = ''): string
    {
        return '<form method="post" class="editor-form ' . self::e($class) . '"><input type="hidden" name="csrf" value="' . self::e(SessionGuard::csrf()) . '"><input type="hidden" name="action" value="' . self::e($action) . '">' . $fields . '<div class="form-actions wide"><button>حفظ التغييرات</button></div></form>';
    }

    private function layoutFields(?array $record): string
    {
        return '<label>التخطيط<select name="layout">' . self::choices(['one' => 'عمود', 'two' => 'عمودان', 'three' => 'ثلاثة أعمدة'], (string) ($record['layout'] ?? 'one')) . '</select></label>' . $this->visibility((string) ($record['visibility'] ?? 'public')) . '<label>الحالة<select name="state">' . self::choices(['draft' => 'مسودة', 'published' => 'منشورة'], (string) ($record['state'] ?? 'draft')) . '</select></label>';
    }

    private function block(array $block): string
    {
        return '<div class="block-row" data-block><label>النوع<select name="block_type[]">' . self::choices(['text' => 'نص', 'heading' => 'عنوان', 'image' => 'صورة', 'links' => 'رابط', 'section' => 'محتوى قسم'], (string) ($block['type'] ?? 'text')) . '</select></label><label class="block-content">المحتوى<textarea name="block_content[]" list="media-library">' . self::e((string) ($block['content'] ?? '')) . '</textarea></label><label>الظهور<select name="block_visibility[]">' . self::choices(['public' => 'عام', 'members' => 'للأعضاء', 'private' => 'خاص'], (string) ($block['visibility'] ?? 'public')) . '</select></label><label>العمود<select name="block_column[]">' . self::choices(['1' => '1', '2' => '2', '3' => '3'], (string) ($block['column'] ?? '1')) . '</select></label><button type="button" class="danger remove-block">إزالة</button></div>';
    }

    private function blocks(array $post): mixed
    {
        if (!isset($post['block_type'])) return $post['blocks'] ?? [];
        foreach (['block_type', 'block_content', 'block_visibility', 'block_column'] as $key) {
            if (!isset($post[$key]) || !is_array($post[$key]) || !array_is_list($post[$key])) throw new \InvalidArgumentException('Invalid block editor data.');
        }
        $count = count($post['block_type']);
        if ($count !== count($post['block_content']) || $count !== count($post['block_visibility']) || $count !== count($post['block_column'])) throw new \InvalidArgumentException('Incomplete block editor data.');
        $blocks = [];
        for ($i = 0; $i < $count; $i++) $blocks[] = ['type' => $post['block_type'][$i], 'content' => $post['block_content'][$i], 'visibility' => $post['block_visibility'][$i], 'column' => $post['block_column'][$i]];
        return $blocks;
    }

    private function selected(array $records, mixed $id): ?array
    {
        if (!is_string($id) || $id === '') return null;
        foreach ($records as $record) if (($record['id'] ?? null) === $id) return $record;
        return null;
    }

    private function options(array $records, mixed $selected, bool $empty): string
    {
        $html = $empty ? '<option value="">بدون</option>' : '';
        foreach ($records as $record) {
            $id = (string) $record['id'];
            $html .= '<option value="' . self::e($id) . '"' . ($id === $selected ? ' selected' : '') . '>' . self::e((string) ($record['title'] ?? $id)) . '</option>';
        }
        return $html;
    }

    private function checks(string $name, array $users, mixed $selected): string
    {
        $selected = is_array($selected) ? $selected : [];
        if ($users === []) return '<p class="empty">لا توجد حسابات مناسبة.</p>';
        $html = '<div class="check-list">';
        foreach ($users as $user) {
            $id = (string) $user['id'];
            $html .= '<label class="check"><input type="checkbox" name="' . self::e($name) . '[]" value="' . self::e($id) . '"' . (in_array($id, $selected, true) ? ' checked' : '') . '> ' . self::e((string) $user['username']) . ' <small>(' . self::e((string) $user['role']) . ')</small></label>';
        }
        return $html . '</div>';
    }

    private function mediaDatalist(): string
    {
        $html = '<datalist id="media-library">';
        foreach ($this->content->list($this->siteId, 'media') as $medium) $html .= '<option value="' . self::e((string) $medium['url']) . '">' . self::e((string) $medium['title']) . '</option>';
        return $html . '</datalist>';
    }

    private function input(string $name, string $label, mixed $value, bool $required): string
    {
        return '<label>' . self::e($label) . '<input name="' . self::e($name) . '" value="' . self::e((string) $value) . '"' . ($required ? ' required' : '') . '></label>';
    }

    private function textarea(string $name, string $label, mixed $value): string
    {
        return '<label class="wide">' . self::e($label) . '<textarea name="' . self::e($name) . '">' . self::e((string) $value) . '</textarea></label>';
    }

    private function visibility(string $selected): string
    {
        return '<label>الظهور<select name="visibility">' . self::choices(['public' => 'عام', 'members' => 'للأعضاء', 'private' => 'خاص'], $selected) . '</select></label>';
    }

    private static function choices(array $options, string $selected): string
    {
        $html = '';
        foreach ($options as $value => $label) $html .= '<option value="' . self::e((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . self::e((string) $label) . '</option>';
        return $html;
    }

    private function shell(string $title, string $body): string
    {
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::e($title) . '</title><link rel="stylesheet" href="/assets/admin.css"><script src="/assets/admin.js" defer></script></head><body>' . $body . '</body></html>';
    }

    private static function notice(string $message): string { return $message === '' ? '' : '<p class="notice" role="status">' . self::e($message) . '</p>'; }
    private static function e(string $value): string { return Renderer::escape($value); }
}
