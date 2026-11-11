# خطة الترحيل وسجل النسب

المصدر مثبت على `abud3d/arbnt@1b5c44b21bc328fef7bc4645ce3140dba0ed13d1`.
المستودع الهدف مستقل؛ بدأ العمل من commit الهدف
`2a6b801b8a8840b993d68eb1b1256026e152b261` على فرع `foundation/one-engine`.
لم يُنسخ تاريخ arbnt أو تُنسب commits القديمة إلى مؤلفين جدد.

## ما تغير في هذا الإصدار

| المكوّن | المصدر الفكري | القرار والتنفيذ الجديد | الحالة |
| --- | --- | --- | --- |
| التخزين JSON | `app/core.php:arbnt_read/arbnt_write` | إعادة كتابة `src/Storage/JsonStore.php` بعقد site/key، مهلة، رفض التلف، كتابة كاملة وfsync | منفذ ومختبر؛ لا نقل حرفي |
| الكائنات/العلاقات/القدرات | `docs/ARCHITECTURE.md` القديم | أنواع مستقلة تحت Core، وفصل الملكية عن الإذن وعزل المواقع | تنفيذ جديد؛ القديم كان تصورًا |
| التفويض | فحص المالك/admin في `api/community.php` | PermissionEngine بمنع افتراضي وgrants وdeny precedence، ودور موقع موثوق يتحول إلى grants | إعادة تصميم واختبارات عزل |
| حسابات ARBNT | `users.json` و`password_hash` في API القديم | مستورد offline اختياري يعيد استخدام المعرف والاسم وhash المتوافق فقط، ويتجاهل المحظور وغير الموثق | تكييف تدريجي؛ لا قراءة إنتاج أو نقل تلقائي |
| حماية العرض وCSRF | escaping وhash_equals في المصدر | Renderer وHeaders وCsrf صغيرة مستقلة | إعادة كتابة؛ لا مسارات كتابة أو جلسات بعد |
| فحوص الجودة | فحوص syntax المتفرقة في workflows | workflow موحد مع اختبارات سلوك وتزامن وHTTP وحدود وحدات | كتابة جديدة |
| SQLite، builder، artifact manifest، catalog، SEO | لا مقابل تنفيذي مستقل في المصدر | تنفيذ تأسيسي جديد محدود ببيانات تجريبية | منفذ ضمن الحدود المعلنة |
| تصميم واجهة التشغيل | لا نقل للقوالب | HTML/CSS جديد بسيط يحمل ONE Engine | منفذ |

**عدد ملفات المصدر المنسوخة حرفيًا: صفر. عدد سجلات المستخدمين وقواعد بيانات
الإنتاج المنقولة: صفر.** لم يُنقل ملف إعداد قد يحتوي SMTP أو tokens أو أسرار.
المراجع أعلاه تثبت النسب الفكري، ولا تدّعي ترخيصًا غير موجود.

## ما بقي في arbnt

التحقق بالبريد واستعادة كلمة المرور؛ المنشورات والردود والمجتمعات؛
المفضلة والإشعارات والبلاغات؛ لوحة الإدارة وإحصاءاتها وإعدادات SMTP؛ الهوية
والصفحات والقوالب؛ المثبت والاستعادة وترحيل التخزين؛ أي بيانات تشغيل خارج Git.
لا تعمل هذه الوظائف في ONE Engine 0.1.0 بعد.

## التسلسل التالي

| المرحلة | المكون المرشح | شرط القبول قبل النقل |
| --- | --- | --- |
| 0 — الحالية | أساس مستقل وفحص المصدر وعقود واختبارات | PR قابل للمراجعة وCI ناجح، دون نشر |
| 1 | Identity: جلسات وحسابات وتحقق/استعادة | سياسة كلمات مرور واضحة، secure cookies، إعادة توليد الجلسات، CSRF/origin، rate limits، اختبارات expiry/replay/session invalidation وتفرد الحساب داخل transaction |
| 2 | Content + Communities | خدمات نطاق بدل dispatcher، قيود علاقات، أذونات لكل كائن، سياسة حذف متسقة، pagination، اختبارات تنافس وإصلاح العلاقات |
| 3 | Favorites / Notifications / Reports / Moderation | سياسة احتفاظ، إغلاق البلاغ، حدود المستخدم والموقع، قراءة خاصة غير قابلة للتخزين العام |
| 4 | Themes + Pages/Blocks + Builder | فصل tokens والمكونات عن العلامة، سجل قوالب موثوق، تصدير منشور/عام فقط، SEO لكل صفحة |
| 5 | Central Management | هوية مركزية منفصلة، صلاحيات تفويض محدودة، versioned releases وتوقيع تحديثات وrollback، اختبار انقطاع المركز |
| 6 | MariaDB + أدوات ترحيل اختيارية | اختبارات العقد نفسها، locking/transactions، dry-run/ID mapping/restore، لا استيراد تلقائي للإنتاج |

لا يُجمع العمل كله في PR واحد لاحق. لكل مرحلة تصميم قصير، مكونات محددة،
اختبارات قبول، وحدود معلنة. ليست هناك موافقة ضمنية على نشر VPS.

## إجراءات حفظ النسب والأمان

عند نقل كود فعلًا لاحقًا، سجل المستودع والمسار وSHA المصدر، وأسماء المؤلفين من
تاريخ الملف، ونطاق الاقتباس/التعديل، والترخيص ونصوصه، وcommit الوجهة والاختبارات.
احتفظ بالترويسات والإشعارات المطلوبة. استخدم commit رسالة واضحة أو cherry-pick
لمكون معزول عند ملاءمته؛ لا تستورد تاريخًا يحتوي بيانات أو تغييرات غير مطلوبة.
غياب الترخيص يحتاج قرارًا من صاحب الحقوق قبل توزيع كود مشتق؛ لا يُعين ترخيص
جديد ضمن هذه المرحلة.

لا تبنِ importer يتصل بالإنتاج تلقائيًا. إذا طُلب ترحيل بيانات مستقبلًا: نسخة
احتياطية معزولة ومصرح بها، إزالة الأسرار والبيانات غير المطلوبة، dry-run،
تقرير تعارضات ومطابقة IDs والعلاقات، تحقق counts/checksums، وتجربة rollback
قبل التفعيل. لا تُدخل قواعد الإنتاج أو مستخدميها إلى Git أو fixtures الاختبارات.

## Imported ARBNT provenance — source-preserving mount

ARBNT main commit `1b5c44b21bc328fef7bc4645ce3140dba0ed13d1`. The complete existing ARBNT user-facing application screens, front-end assets and `api/community.php` were copied into `public/community/`; `app/core.php` was moved to `src/LegacyArbnt/core.php` outside document root. The legacy core now retains its public function contract while adding strict JSON decoding, bounded stable locks and checked atomic replacement so corrupt data is never treated as an empty collection. Other adaptations cover the API core path and HTTPS forwarded Codespaces preview/session cookie scope; `install.php`/`recover.php` intentionally were not exposed. Original source repository remains unchanged. The module still uses ARBNT's own identity and permissions; integration into native ONE Engine Core is unfinished. See `docs/FULL_ARBNT_MOUNT.md`. No production users or records were imported.
