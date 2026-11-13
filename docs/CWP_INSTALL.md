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
   php bin/one-install /home/ACCOUNT/one-engine-data admin 'YOUR-OWN-LONG-PASSWORD'
   ```

   لا تضع كلمة المرور في ملف أو Git أو تذكرة دعم. الأمر لا يحفظ النص الصريح؛ يحفظ hash فقط داخل مجلد البيانات.

6. افتح `https://YOUR-DOMAIN/admin`، وأنشئ قسمًا وصفحة. احفظ الصفحة كمسودة، افتح المعاينة، ثم انشرها وافتح `/p/SLUG` في نافذة خاصة.

## التحقق والأذونات

ينشئ المثبت `config/local.php` بصلاحية `0600` ويحوي مسار البيانات فقط، ومجلد بيانات بصلاحية `0700` خارج `public`. لا توجد بيانات دخول افتراضية في ZIP.

يجب أن يعمل Apache `mod_rewrite` وأن يسمح بقراءة `public/.htaccess`. إذا أعاد CWP خطأ 404 للمسارات النظيفة، راجع Rewrite/AllowOverride للنطاق دون نقل ملفات خاصة إلى الويب.

## فصل الحزمتين

- `one-engine-cms-runtime-php-8.3`: المحرك التفاعلي ولوحة الإدارة.
- `one-engine-static-site-php-8.3`: موقع ثابت للعرض فقط، بلا تسجيل دخول أو لوحة إدارة.
