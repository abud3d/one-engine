# تشغيل ONE Engine CMS

هذه معاينة تطوير محلية، وليست نشرًا إنتاجيًا. تتطلب PHP 8.3+ ودليل بيانات خاصًا خارج `public/` بصلاحية `0700`.

```sh
mkdir -m 700 var/demo
php bin/one-admin var/demo admin 'change-this-long-password'
ONE_PRIVATE_DIR="$PWD/var/demo" php -c config/php.ini -S 127.0.0.1:8080 -t public public/router.php
```

افتح `/admin`، ثم أنشئ قسمًا وصفحة وقائمة. الصفحة المنشورة العامة تظهر عند `/p/SLUG`. المسودة لا تظهر للزائر. دور `editor` ينشئ ويعدل وينشر، ودور `member` يقرأ المنشور العام أو المخصص للأعضاء ولا يرى أدوات التحرير. كل البيانات معزولة بمفتاح الموقع من `config/site.php`.

لاستيراد حسابات ARBNT، جهّز يدويًا نسخة JSON مراجعة ومعزولة من `users.json` ثم نفّذ:

```sh
php bin/one-admin import-arbnt var/demo REVIEWED_USERS.json
```

لا يقرأ الأمر تثبيت ARBNT أو الإنتاج تلقائيًا. يعيد استخدام معرف الحساب واسم الدخول و`password_hash` المتوافق مع PHP، ويتجاهل المحظور وغير الموثق. لا تُحفظ النسخة المراجعة في Git.

لإنشاء موقع ثابت مستقل وحزمة ZIP فعلية، يجب أن يتوفر امتداد PHP `zip`:

```sh
mkdir -p build
php bin/one export var/demo build/site build/site.zip
```

يصدر الأمر الصفحات المنشورة العامة فقط. لا يصدر المسودات أو صفحات الأعضاء أو الحسابات أو التخزين. الحزمة تستخدم روابط أصول نسبية كي تعمل داخل مجلد فرعي، وتحتوي `release.json` و`robots.txt` و`sitemap.xml`. لا ينفذ الأمر أي نشر.
