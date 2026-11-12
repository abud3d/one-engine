# تركيب ONE Engine CMS على CWP باستخدام PHP 8.3

## المتطلبات

- نطاق أو نطاق فرعي داخل CWP يعمل عبر HTTPS.
- PHP 8.3 مع JSON وSessions. لا يحتاج التشغيل إلى Node أو Composer أو قاعدة بيانات.
- Terminal/SSH لحظة واحدة لتهيئة المدير الأول.
- إمكانية ضبط Document Root على مجلد `public` داخل الحزمة.

إذا لم تسمح الاستضافة بتغيير Document Root، لا تستخرج الحزمة كاملة داخل `public_html`؛ سيعرض ذلك ملفات المحرك والإعدادات. اطلب من مسؤول الاستضافة ضبط Document Root أو استخدم نطاقًا فرعيًا يسمح به.

## التركيب

1. من CWP اختر PHP-FPM 8.3 للنطاق، وفعّل HTTPS.
2. ارفع ZIP التفاعلية وافتحها في مجلد خارج `public_html`، مثل `/home/ACCOUNT/one-engine-cms/`.
3. اضبط Document Root للنطاق إلى `/home/ACCOUNT/one-engine-cms/public`.
4. افتح Terminal في CWP وتحقق من الإصدار:

   ```sh
   php -v
   ```

   إذا كان CWP يستخدم مسارًا بديلًا، استخدم مسار PHP 8.3 الظاهر في PHP Selector بدل `php`.

5. أنشئ المدير الأول ومجلد البيانات الشقيق خارج `public`:

   ```sh
   cd /home/ACCOUNT/one-engine-cms
   read -rsp 'Admin password: ' ONE_ADMIN_PASSWORD
   echo
   export ONE_ADMIN_PASSWORD
   php bin/one-install /home/ACCOUNT/one-engine-data admin 'https://YOUR-DOMAIN'
   unset ONE_ADMIN_PASSWORD
   ```

   لا تضع كلمة المرور في ملف أو Git أو معاملات الأمر أو تذكرة دعم. الإدخال المخفي لا يسجلها في shell history، والمثبت يحفظ hash فقط.

6. قبل فتح الإدارة شغّل فحصًا محليًا للتركيب (قراءة فقط):\n\n   ```sh\n   php bin/one-doctor\n   ```\n\n   النتيجة PASS تعني نجاح الفحوص المحلية فقط؛ لا يتحقق الأمر من DocumentRoot أو HTTPS أو Rewrite فعليًا في CWP. يلزم اختبارها من المتصفح. لا يعرض الأمر أسرار التهيئة ولا يغيّر الملفات.\n\n7. افتح `https://YOUR-DOMAIN/admin`، وأنشئ قسمًا وصفحة. احفظ الصفحة كمسودة، افتح المعاينة، ثم انشرها وافتح `/p/SLUG` في نافذة خاصة.

8. من «إعدادات الموقع» أدخل الاسم والوصف ونفس نطاق HTTPS، ثم أنشئ أول نسخة من شاشة «النسخ الاحتياطي». توجد النسخ داخل مجلد البيانات الخاص ولا يمكن تنزيلها من الويب.

## التحقق والأذونات

ينشئ المثبت `config/local.php` بصلاحية `0600` ويحوي مسار البيانات وOrigin HTTPS الموثوق، ومجلد بيانات بصلاحية `0700` خارج `public`. تُرفض طلبات POST من Origin مختلف أو مفقود. لا توجد بيانات دخول افتراضية في ZIP.

يجب أن يعمل Apache `mod_rewrite` وأن يسمح بقراءة `public/.htaccess`. إذا أعاد CWP خطأ 404 للمسارات النظيفة، راجع Rewrite/AllowOverride للنطاق دون نقل ملفات خاصة إلى الويب.

## فصل الحزمتين

- `one-engine-cms-runtime-php-8.3`: المحرك التفاعلي ولوحة الإدارة.
- `one-engine-static-site-php-8.3`: موقع ثابت للعرض فقط، بلا تسجيل دخول أو لوحة إدارة.
