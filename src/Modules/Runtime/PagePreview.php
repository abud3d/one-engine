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
            . '<link rel="stylesheet" href="/assets/app.css"></head><body>'
            . '<header><a class="brand" href="/"><span class="mark">1</span><b dir="ltr">ONE Engine</b></a>'
            . '<span class="badge">معاينة الصفحات 0.2</span></header>'
            . '<main id="main">' . $content . '</main><footer>'
            . '<span>محتوى عام مصرح بنشره فقط</span><span dir="ltr">ONE Engine preview</span>'
            . '</footer></body></html>';
    }
}
