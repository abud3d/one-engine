<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\ContentObject;

final class PagePreview
{
    /** @param list<ContentObject> $pages */
    public function index(Site $site, array $pages): string
    {
        $items = '';
        foreach ($pages as $page) {
            $items .= '<article><h2><a href="/pages/' . Renderer::escape($page->id) . '">'
                . Renderer::escape($page->title) . '</a></h2><p>'
                . Renderer::escape($page->body) . '</p></article>';
        }
        if ($items === '') {
            $items = '<article><h2>لا توجد صفحات منشورة</h2><p>أنشئ صفحة باستخدام أداة سطر الأوامر.</p></article>';
        }
        return $this->layout($site, 'الصفحات المنشورة', '<p class="eyebrow">ONE Engine · معاينة حقيقية</p>'
            . '<h1>الصفحات المنشورة</h1><p class="intro">صفحات محفوظة في JSON وليست بيانات وهمية.</p>'
            . '<section class="grid" aria-label="الصفحات">' . $items . '</section>', '/pages');
    }

    /** @param list<ContentObject> $posts */
    public function community(Site $site, array $posts): string
    {
        $items = '';
        foreach ($posts as $post) {
            $items .= '<article class="community-post"><p class="eyebrow">نقاش عام</p><h2><a href="/community/' . Renderer::escape($post->id) . '">'
                . Renderer::escape($post->title) . '</a></h2><p>'
                . Renderer::escape($post->body) . '</p><a href="/community/' . Renderer::escape($post->id) . '">قراءة الموضوع</a></article>';
        }
        if ($items === '') {
            $items = '<article><h2>مرحباً بك في المجتمع</h2><p>هذا قسم المجتمع الجديد. لا توجد منشورات عامة بعد.</p></article>';
        }
        return $this->layout($site, 'المجتمع',
            '<p class="eyebrow">مساحة النقاشات</p><h1>المجتمع</h1>'
            . '<p class="intro">واجهة أولية للمنشورات العامة المحفوظة في المحرك، دون حسابات أو تعليقات حتى الآن.</p>'
            . '<section class="grid" aria-label="منشورات المجتمع">' . $items . '</section>', '/community');
    }

    public function post(Site $site, ContentObject $post): string
    {
        return $this->layout($site, $post->title,
            '<p class="eyebrow"><a href="/community">العودة إلى المجتمع</a></p><h1>'
            . Renderer::escape($post->title) . '</h1><article><p class="page-body">'
            . nl2br(Renderer::escape($post->body), false)
            . '</p></article>', '/community/' . $post->id);
    }

    public function page(Site $site, ContentObject $page): string
    {
        return $this->layout($site, $page->title,
            '<p class="eyebrow"><a href="/pages">العودة إلى الصفحات</a></p><h1>'
            . Renderer::escape($page->title) . '</h1><article><p class="page-body">'
            . nl2br(Renderer::escape($page->body), false)
            . '</p></article>', '/pages/' . $page->id);
    }

    private function layout(Site $site, string $title, string $content, string $path): string
    {
        $escape = Renderer::escape(...);
        $robots = $site->indexable ? 'index,follow' : 'noindex,nofollow';
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $escape($title) . ' · ' . $escape($site->title) . '</title>'
            . '<meta name="description" content="' . $escape($site->description) . '">'
            . '<meta name="robots" content="' . $robots . '">'
            . '<link rel="canonical" href="' . $escape(rtrim($site->baseUrl, '/') . $path) . '">'
            . '<link rel="stylesheet" href="/assets/app.css">'
            . '<link rel="stylesheet" href="/assets/preview-shell.css">'
            . '<script src="/assets/preview-shell.js" defer></script></head><body class="one-preview">'
            . '<header class="one-header"><a class="brand" href="/"><span class="mark">1</span><b dir="ltr">ONE Engine</b></a>'
            . '<nav class="one-nav" aria-label="القائمة الرئيسية"><a href="/">الرئيسية</a><a href="/pages">الصفحات</a><a href="/community/community.html">المجتمع</a><a href="/community/login.html">الدخول</a><a href="/community/admin.html">الإدارة</a></nav>'
            . '<button id="one-theme-toggle" class="theme-toggle" type="button" aria-pressed="false">الوضع الليلي</button>'
            . '<span class="badge">معاينة v0.2</span></header>'
            . '<div class="one-workspace"><aside class="one-sidebar" aria-label="أقسام الموقع"><strong>المحتوى</strong>'
             . '<nav><a href="/">الرئيسية</a><a href="/pages">الصفحات المنشورة</a><a href="/community/community.html">المجتمع الكامل</a><a href="/community/admin.html">لوحة الإدارة</a></nav>'
            . '<p class="one-sidebar-note">الصفحات العامة منفصلة عن مجتمع ARBNT. لإدارة المجتمع استخدم رابط لوحة الإدارة بعد تسجيل الدخول.</p></aside>'
            . '<main id="main" class="one-content">' . $content . '</main></div><footer>'
            . '<span>محتوى عام مصرح بنشره فقط</span><span dir="ltr">ONE Engine preview</span>'
            . '</footer></body></html>';
    }
}
