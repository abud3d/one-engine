<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\{Action, ContentObject, EngineVersion, PermissionEngine, Principal};

final class Renderer
{
    public function home(Site $site, ContentObject $page): string
    {
        if (!(new PermissionEngine())->allows(new Principal(), Action::Read, $page, $site->id)) {
            throw new \DomainException('Only public, published pages can be rendered.');
        }
        $e = self::escape(...);
        $robots = $site->indexable ? 'index,follow' : 'noindex,nofollow';
        $health = $site->mode === Mode::Static ? '' : '<a class="button" href="/health">فحص التشغيل</a>';
        return '<!doctype html>
<html lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $e($page->title) . ' · ' . $e($site->title) . '</title>
<meta name="description" content="' . $e($site->description) . '">
<meta name="robots" content="' . $robots . '">
<link rel="canonical" href="' . $e($site->canonical()) . '">
<link rel="stylesheet" href="/assets/app.css">
</head><body><header><a class="brand" href="/" aria-label="ONE Engine الرئيسية"><span class="mark">1</span><b dir="ltr">ONE Engine</b></a><span class="badge">الإصدار التأسيسي</span></header>
<main id="main"><p class="eyebrow">أساس واحد. مواقع مستقلة.</p>
<h1>' . $e($page->title) . '</h1><p class="intro">' . $e($page->body) . '</p>
<div class="status"><span class="dot" aria-hidden="true"></span>واجهة التشغيل جاهزة <span dir="ltr">' . $e($site->mode->value) . '</span></div>
<section class="grid" aria-label="وحدات المحرك">
<article><span class="number">01</span><h2>البناء</h2><p>إعداد الموقع وإنشاء حزمة مستقلة قابلة للمراجعة.</p></article>
<article><span class="number">02</span><h2>التشغيل</h2><p>عرض صفحات الموقع دون اتصال إلزامي بالمركز.</p></article>
<article><span class="number">03</span><h2>الإدارة المركزية</h2><p>حدود معمارية وسجل تجريبي؛ إدارة النشر تأتي في مرحلة لاحقة.</p></article>
</section><div class="actions">' . $health . '<span>نموذج تأسيسي ببيانات تجريبية فقط.</span></div>
</main><footer><span dir="ltr">' . $e(EngineVersion::current()->label()) . '</span><span>PHP أصلي · HTML/CSS</span></footer></body></html>';
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function robots(Site $site): string
    {
        return $site->indexable
            ? "User-agent: *\nAllow: /\nSitemap: " . rtrim($site->baseUrl, '/') . "/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";
    }

    public function sitemap(Site $site): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . ($site->indexable ? '<url><loc>' . self::escape($site->canonical()) . '</loc></url>' : '') . '</urlset>';
    }
}
