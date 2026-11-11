# ONE Engine

محرك مستقل لإنشاء المواقع والتطبيقات وإدارتها ونشرها مركزيًا، مع استقلال تشغيل كل موقع عن المركز.
**0.2.0 preview:** أساس قابل للاختبار مع تدفق CMS تشغيلي، وليس إصدارًا جاهزًا للإنتاج.

## التشغيل المحلي

يتطلب PHP **8.3+**. لا يتطلب Composer أو Node أو إطار عمل. تحتاج الاختبارات إلى `PDO_SQLite`، وإلى Python 3 لفحص حدود الوحدات فقط.

```sh
php -c config/php.ini -S 127.0.0.1:8080 -t public public/router.php
```

افتح `http://127.0.0.1:8080/`، أو `/health` لفحص التشغيل.
الخادم المدمج للتطوير فقط. إعداد الموقع التجريبي في `config/site.php`، ودليل الويب هو `public/` حصريًا.
المثال غير قابل للفهرسة افتراضيًا، ويستخدم `example.invalid`، ولا ينشئ حسابات أو بيانات فعلية.

للتجربة الكاملة من تسجيل الدخول إلى التصدير راجع [تشغيل CMS](docs/OPERATIONAL_CMS.md). تستخدم الإدارة `/admin`، وتتطلب `ONE_PRIVATE_DIR` وحسابًا ينشأ محليًا بأمر `bin/one-admin`؛ لا توجد كلمة مرور افتراضية في المستودع.

## ما تنفذه النسخة

- نواة كائنات وعلاقات وقدرات، وصلاحيات محددة بالموقع والكائن، مع منع افتراضي.
- واجهة HTML/CSS عربية متجاوبة، مع مسارات قراءة فقط ورؤوس حماية.
- محولا تخزين JSON وSQLite بعقد واحد، مع اختبارات تلف البيانات والتزامن والتراجع.
- فصل وحدات Builder وRuntime وCentral Management؛ سجل المركز تجريبي في الذاكرة فقط.
- بناء حزمة **Static** بلا PHP، أو **Hybrid** بصفحة ثابتة وفحص تشغيل PHP، أو **Dynamic** بعرض الصفحة من PHP.
- إعداد عنوان ووصف وcanonical وrobots وsitemap لكل موقع؛ لا يُستنتج النطاق من ترويسة Host.
- هوية محلية مع تكييف اختياري لحسابات ARBNT المراجعة، وجلسات وCSRF وتحديد لمحاولات الدخول.
- لوحة للأقسام والصفحات والتخطيطات والبلوكات والقوائم والمستخدمين، بصلاحيات خادمية وعزل المواقع.
- تصدير الصفحات العامة المنشورة إلى دليل ثابت مستقل وZIP اختياري، دون حسابات أو اتصال بالمركز.

لا توجد مزامنة مركزية أو نشر بعيد. **MariaDB امتداد مخطط، ولم يُنفّذ محوله بعد.** راجع [الحدود المعروفة](docs/KNOWN_ISSUES.md).

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

## معاينة Codespaces الجديدة

- [حل خطأ Not found وروابط الهيدر وصفحة المجتمع](docs/CODESPACES_COMMUNITY.md)
- `/pages` و`/community` متاحان في معاينة المصدر حتى بدون تخزين؛ بيانات المنشورات تتطلب تفعيل `ONE_PRIVATE_DIR`.
- المجتمع حاليًا **للقراءة فقط** عبر المتصفح، وإضافة المواضيع تتم محليًا بأمر `bin/one-posts`. لا حسابات أو تعليقات أو لوحة إدارة متكاملة بعد.

## استئناف التطوير المعتمد

- [ONE-V02-004 — خطة نقل ARBNT وتسليم الوكلاء](docs/V02_HANDOFF.md)
- [TDP — مكتبة برومبت طلال](https://github.com/abud3d/talal-prompts)

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

## المجتمع الأصلي المنقول

**[واجهة ARBNT الفعلية والحسابات ولوحة الإدارة داخل ONE Engine — شرح التشغيل](docs/FULL_ARBNT_MOUNT.md)**. النسخة الأصلية من التطبيق مُدرجة في `public/community/`، ومحرك التخزين في `src/LegacyArbnt/`. هذه وحدة Legacy مستقلة، وليست بعد وحدة ONE Engine أصلية موحّدة الصلاحيات. لا توجد بيانات مستخدمين حقيقية منقولة.
