# ONE Engine

محرك مستقل لإنشاء المواقع والتطبيقات وإدارتها ونشرها مركزيًا، مع استقلال تشغيل كل موقع عن المركز.
**0.1.0 — Foundation:** أساس قابل للاختبار، وليس نظام إدارة محتوى مكتملًا أو إصدارًا جاهزًا للإنتاج.

## التشغيل المحلي

يتطلب PHP **8.3+**. لا يتطلب Composer أو Node أو إطار عمل. تحتاج الاختبارات إلى `PDO_SQLite`، وإلى Python 3 لفحص حدود الوحدات فقط.

```sh
php -c config/php.ini -S 127.0.0.1:8080 -t public public/router.php
```

افتح `http://127.0.0.1:8080/`، أو `/health` لفحص التشغيل.
الخادم المدمج للتطوير فقط. إعداد الموقع التجريبي في `config/site.php`، ودليل الويب هو `public/` حصريًا.
المثال غير قابل للفهرسة افتراضيًا، ويستخدم `example.invalid`، ولا ينشئ حسابات أو بيانات فعلية.

## ما تنفذه النسخة

- نواة كائنات وعلاقات وقدرات، وصلاحيات محددة بالموقع والكائن، مع منع افتراضي.
- واجهة HTML/CSS عربية متجاوبة، مع مسارات قراءة فقط ورؤوس حماية.
- محولا تخزين JSON وSQLite بعقد واحد، مع اختبارات تلف البيانات والتزامن والتراجع.
- فصل وحدات Builder وRuntime وCentral Management؛ سجل المركز تجريبي في الذاكرة فقط.
- بناء حزمة **Static** بلا PHP، أو **Hybrid** بصفحة ثابتة وفحص تشغيل PHP، أو **Dynamic** بعرض الصفحة من PHP.
- إعداد عنوان ووصف وcanonical وrobots وsitemap لكل موقع؛ لا يُستنتج النطاق من ترويسة Host.

الأنماط الثلاثة تثبت الحدود المعمارية بصفحة واحدة. لا توجد بعد صفحات مُدارة من قاعدة البيانات، أو تسجيل دخول، أو لوحة بناء مرئية، أو مزامنة مركزية، أو نشر بعيد. محولا التخزين مختبران لكنهما غير موصولين بالواجهة التجريبية. **MariaDB امتداد مخطط، ولم يُنفّذ محوله بعد.**

## البناء والاختبارات

```sh
php tests/run.php
python3 tests/check_structure.py
mkdir -p build
php bin/one build static build/demo-static
php bin/one build hybrid build/demo-hybrid
php bin/one build dynamic build/demo-dynamic
php bin/one sites
```

يجب أن يكون مجلد الإصدار جديدًا؛ لا تُستبدل حزمة موجودة. هذه الأوامر تبني ملفات محلية ولا تنشرها.
للتجربة المستقلة:

```sh
php -S 127.0.0.1:8081 -t build/demo-static/public
php -S 127.0.0.1:8082 -t build/demo-hybrid/public build/demo-hybrid/public/router.php
php -S 127.0.0.1:8083 -t build/demo-dynamic/public build/demo-dynamic/public/router.php
```

## الوثائق

- [المعمارية وحدود الإصدار](docs/ARCHITECTURE.md)
- [مخطط الصلاحيات](docs/PERMISSIONS.md)
- [عقد التخزين وحدوده](docs/STORAGE.md)
- [مراجعة arbnt والنتائج](docs/SOURCE_AUDIT.md) و[جرد الملفات الـ48](docs/SOURCE_INVENTORY.md)
- [خطة الترحيل وسجل النسب](docs/MIGRATION.md)
- [الأمان والتشغيل](docs/OPERATIONS.md)
- [التسليم والتحقق](docs/DELIVERY.md)

نُفّذت النسخة من أساس جديد بعد مراجعة `abud3d/arbnt`؛ لم يُنسخ المشروع القديم أو بياناته.
راجع [NOTICE.md](NOTICE.md) لنسب الأفكار وحدود الترخيص؛ لم يُعيّن ترخيص جديد.
