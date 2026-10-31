# دليل نشر جوز كاليري على استضافة cPanel

هذا الدليل يأخذك من جهازك إلى موقع يعمل على الإنترنت. نُفّذت خطواته فعلياً على نسخة تجريبية قبل كتابته، عدا ما يخص cPanel نفسه لأنه يعتمد على استضافتك.

في الأوامر: استبدل `USER` باسم حسابك في cPanel، و`example.com` بنطاقك.

---

## 1. المتطلبات

من cPanel افتح **MultiPHP Manager** واختر **PHP 8.4** لنطاقك. الأقل هو 8.4.1، وأي إصدار أقدم لن يعمل.

ثم افتح **Select PHP Version** أو **MultiPHP INI Editor** وتأكد من تفعيل هذه الإضافات:

| الإضافة | لماذا |
|---|---|
| `fileinfo` | كشف نوع الملف الحقيقي عند الرفع |
| `gd` أو `imagick` | تحويل الصور، ويجب أن تدعم WebP |
| `intl` | لوحة التحكم |
| `mbstring` | النصوص العربية |
| `pdo_mysql` | قاعدة البيانات |
| `zip` | النسخ الاحتياطي وملفات Excel |
| `bcmath` | عمليات Laravel الحسابية |
| `exif` | قراءة بيانات الصور |

واضبط هذه القيم:

| الإعداد | القيمة |
|---|---|
| `memory_limit` | `256M` أو أكثر |
| `upload_max_filesize` | `12M` |
| `post_max_size` | `64M` |
| `max_execution_time` | `120` |

تحويل الصور يجري أثناء الحفظ، لذلك رفع 10 صور كبيرة دفعة واحدة يحتاج ذاكرة ووقتاً كافيين.

تحتاج أيضاً **Terminal** في cPanel أو دخول SSH لتنفيذ الأوامر. إن لم يتوفر عندك، اطلب تفعيله من الاستضافة.

### أي PHP يستخدمه الطرفية؟

قد يكون `php` في Terminal إصداراً مختلفاً عن إصدار الموقع. نفّذ:

```bash
php -v
```

إن لم يظهر 8.4، ابحث عن المسار الصحيح:

```bash
ls /opt/cpanel/ea-php*/root/usr/bin/php /opt/alt/php*/usr/bin/php 2>/dev/null
```

ثم استخدم المسار الكامل، مثل `/opt/cpanel/ea-php84/root/usr/bin/php`، بدل `php` في كل أوامر هذا الدليل، وفي سطر cron أيضاً.

للتأكد من دعم WebP:

```bash
php -r "var_dump(gd_info()['WebP Support']);"
```

يجب أن تظهر `bool(true)`.

---

## 2. قاعدة البيانات

من cPanel افتح **MySQL Databases**:

1. أنشئ قاعدة بيانات، مثل `USER_joz`.
2. أنشئ مستخدماً بكلمة مرور قوية، مثل `USER_jozapp`.
3. أضف المستخدم إلى القاعدة وامنحه **All Privileges**.

احتفظ بالأسماء الثلاثة لخطوة `.env`.

---

## 3. البناء على جهازك

ابنِ من نسخة نظيفة حتى لا ترفع ملفات التطوير. من PowerShell:

```bash
cd C:\dev
```

```bash
git clone joz-gallery joz-release
```

```bash
cd joz-release
```

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci
```

```bash
npm run build
```

ثم اضغط المشروع، واستثنِ ما لا يلزم:

```bash
C:\Windows\System32\tar.exe -a -c -f ..\joz-release.zip --exclude=.git --exclude=node_modules --exclude=tests --exclude=.env .
```

> استخدم المسار الكامل كما هو. كلمة `tar` وحدها تستدعي نسخة Git على جهازك، فتُنتج ملفاً امتداده zip لكنه ليس zip، ويرفض cPanel فتحه.

**يُرفع:** `app`، `bootstrap`، `config`، `database`، `lang`، `public`، `resources`، `routes`، `storage`، `vendor`، `artisan`، `composer.json`، `composer.lock`، `.env.example`.

**لا يُرفع:** `.git`، `node_modules`، `tests`، `.env` الخاص بجهازك.

الناتج ملف حجمه نحو 24 ميغابايت. تأكد أنه يحتوي ملف حماية مجلد الصور:

```bash
C:\Windows\System32\tar.exe -tf ..\joz-release.zip ./storage/app/public/.htaccess
```

---

## 4. الرفع وربط النطاق

1. من **File Manager** أنشئ المجلد `/home/USER/joz`. يجب أن يكون **خارج** `public_html`.
2. ارفع `joz-release.zip` إليه ثم اضغط **Extract**.
3. من **Domains** اضغط **Manage** بجانب نطاقك، وغيّر **Document Root** إلى `joz/public`.

### إن لم تسمح الاستضافة بتغيير Document Root

بعض الاستضافات تمنع ذلك للنطاق الرئيسي. في هذه الحالة انقل محتوى `public` إلى `public_html`، ثم اجعل `joz/public` يشير إليه. من Terminal:

```bash
cp -a ~/joz/public/. ~/public_html/
```

```bash
rm -rf ~/joz/public
```

```bash
ln -s ~/public_html ~/joz/public
```

ثم عدّل `~/public_html/index.php` ليشير إلى المسار الجديد:

```php
if (file_exists($maintenance = __DIR__.'/../joz/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../joz/vendor/autoload.php';

$app = require_once __DIR__.'/../joz/bootstrap/app.php';
```

السطر الأخير في الملف `$app->handleRequest(...)` يبقى كما هو.

---

## 5. الإعداد الأول

```bash
cd ~/joz
```

```bash
cp .env.example .env
```

افتح `.env` من File Manager واملأ على الأقل:

| المتغير | القيمة |
|---|---|
| `APP_URL` | `https://example.com` |
| `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD` | من الخطوة 2 |
| `MAIL_HOST`، `MAIL_USERNAME`، `MAIL_PASSWORD`، `MAIL_FROM_ADDRESS` | من **Email Accounts** ثم **Connect Devices** |
| `TELEGRAM_BOT_TOKEN` | من @BotFather على تيليغرام |
| `ADMIN_EMAIL`، `ADMIN_PASSWORD` | حساب المالك، وكلمة المرور 12 حرفاً على الأقل مع أحرف كبيرة وصغيرة وأرقام ورموز |
| `BACKUP_NOTIFICATION_EMAIL` | بريد يصله تنبيه عند فشل النسخ الاحتياطي |

اترك `APP_DEBUG=false` دائماً. القيمة `true` تكشف كلمات المرور والكود للزوار.

إن أردت البدء ببيانات الديمو لتجربة الموقع، اجعل `SEED_DEMO=true` مؤقتاً. وإلا يبدأ المتجر فارغاً.

ثم نفّذ بالترتيب:

```bash
php artisan key:generate --force
```

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --force
```

```bash
php artisan storage:link
```

```bash
php artisan sitemap:generate
```

```bash
php artisan optimize
```

```bash
php artisan filament:optimize
```

```bash
chmod -R 775 storage bootstrap/cache
```

بعد أول دخول ناجح، احذف قيمة `ADMIN_PASSWORD` من `.env`، ثم أعد `php artisan optimize`.

### إن كانت الروابط الرمزية معطلة

إن فشل `storage:link`، خزّن الصور مباشرة داخل المجلد العام. أضف إلى `.env`:

```
PUBLIC_DISK_ROOT=/home/USER/joz/public/storage
```

(أو `/home/USER/public_html/storage` إن استخدمت الطريقة البديلة في الخطوة 4.) ثم أنشئ المجلد وانسخ ملف الحماية:

```bash
mkdir -p ~/joz/public/storage
```

```bash
cp ~/joz/storage/app/public/.htaccess ~/joz/public/storage/
```

```bash
php artisan optimize
```

---

## 6. المهام المجدولة (cron)

بدون هذا السطر لن تصل إشعارات الطلبات، ولن يكتمل تصدير Excel، ولن تُؤخذ نسخ احتياطية.

من cPanel افتح **Cron Jobs** وأضف مهمة كل دقيقة (Once Per Minute):

```
* * * * * /usr/local/bin/php /home/USER/joz/artisan schedule:run >> /dev/null 2>&1
```

استبدل `/usr/local/bin/php` بمسار PHP 8.4 الذي وجدته في الخطوة 1 إن كان مختلفاً. للتأكد:

```bash
/usr/local/bin/php -v
```

يتولى هذا السطر الواحد كل شيء:

| المهمة | الوقت |
|---|---|
| طابور الإشعارات والتصدير | كل دقيقة |
| تحديث خريطة الموقع | يومياً |
| حذف ملفات Excel الأقدم من 30 يوماً | يومياً |
| تنظيف النسخ الاحتياطية القديمة | 01:00 |
| النسخ الاحتياطي | 01:30 |

---

## 7. شهادة SSL وفرض HTTPS

1. افتح **SSL/TLS Status** واضغط **Run AutoSSL**، وانتظر ظهور القفل الأخضر للنطاق.
2. من **Domains** فعّل **Force HTTPS Redirect** بجانب نطاقك.

إن لم يتوفر الخيار، أضف هذين السطرين في `public/.htaccess` بعد `RewriteEngine On`:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

تأكد أن `APP_URL` يبدأ بـ `https://` وأن `SESSION_SECURE_COOKIE=true`.

### إن كنت تستخدم Cloudflare

أضف `TRUSTED_PROXIES=*` إلى `.env`، ثم `php artisan optimize`. بدونها يظهر كل الزوار كعنوان واحد، فيحظر حد المحاولات الزبائن الحقيقيين معاً. واضبط SSL في Cloudflare على **Full** لا **Flexible**.

---

## 8. التحديثات والنسخ الاحتياطي

### نشر تحديث

1. ابنِ نسخة جديدة كما في الخطوة 3.
2. على الخادم، ضع الموقع في وضع الصيانة:

```bash
php artisan down
```

3. ارفع الملفات واستبدل القديمة، **عدا** `.env` و`storage/`. لا تحذف `storage/` أبداً لأنها تحتوي صور المنتجات والنسخ الاحتياطية.
4. ثم:

```bash
php artisan migrate --force
```

```bash
php artisan optimize
```

```bash
php artisan filament:optimize
```

```bash
php artisan up
```

### النسخ الاحتياطي

كل ليلة تُحفظ نسخة تضم قاعدة البيانات وصور `storage/app/public` في `storage/app/private/joz-gallery/`، وتُحفظ 7 أيام ثم تُحذف. يصلك بريد عند الفشل فقط.

**هذه النسخ موجودة على الخادم نفسه**، فلا تحميك إن تعطل الخادم أو الحساب. نزّل أحدث ملف إلى جهازك أسبوعياً من File Manager، وفعّل نسخ cPanel إن توفر.

لأخذ نسخة يدوية قبل أي تغيير كبير:

```bash
php artisan backup:run
```

إن فشلت برسالة `mysqldump not found`، ضع في `.env`:

```
DB_DUMP_BINARY_PATH=/usr/bin/
```

### الاسترجاع

1. فك ضغط الملف.
2. من **phpMyAdmin** استورد `db-dumps/mysql-*.sql` إلى قاعدة البيانات.
3. انسخ محتوى مجلد `public` داخل الملف إلى `~/joz/storage/app/public/`.
4. نفّذ `php artisan optimize:clear` ثم `php artisan optimize`.

### نسيت كلمة مرور المالك

```bash
php artisan tinker --execute="App\Models\User::where('email', 'you@example.com')->first()->update(['password' => 'New-Str0ng_Password!']);"
```

---

## 9. قائمة الفحص قبل الإطلاق

### اختبارات على الموقع الحي

- [ ] الصفحة الرئيسية تفتح بـ `https` والقفل ظاهر.
- [ ] `https://example.com/en` يفتح بالإنجليزية.
- [ ] الصور تظهر. إن لم تظهر فراجع `APP_URL` و`storage:link`.
- [ ] `https://example.com/admin` يفتح، والدخول بحساب المالك يعمل.
- [ ] رفع منتج بصورة يعمل، وتظهر الصورة في المتجر.
- [ ] طلب تجريبي كامل يصل إلى صفحة الشكر.
- [ ] خلال دقيقتين تصل رسالة تيليغرام وبريد الطلب.
- [ ] تصدير Excel من الطلبات يعطي إشعاراً بالتنزيل.
- [ ] بعد ليلة واحدة يظهر ملف في `storage/app/private/joz-gallery/`.
- [ ] `https://example.com/sitemap.xml` و`https://example.com/robots.txt` يفتحان.
- [ ] رابط منتج في واتساب يعرض صورة المنتج.

### فحص أمني

- [ ] ارفع ملف `test.php` إلى `storage/app/public/` من File Manager، وافتح `https://example.com/storage/test.php`. يجب أن يعطي **403**. احذف الملف بعدها.
- [ ] افتح `https://example.com/storage/`. يجب ألا تظهر قائمة ملفات.
- [ ] `https://example.com/.env` يجب ألا يفتح.
- [ ] رابط خاطئ يعرض صفحة الخطأ العربية، لا تفاصيل تقنية.
- [ ] `APP_DEBUG=false` و`APP_ENV=production` في `.env`.
- [ ] `ADMIN_PASSWORD` محذوفة من `.env`.

### المحتوى

- [ ] أجور التوصيل الحقيقية لكل محافظة.
- [ ] صور المنتجات الحقيقية بدل صور الديمو، وهي من Unsplash.
- [ ] أوصاف المنتجات وأبعادها ومخزونها.
- [ ] نصوص صفحات من نحن والتوصيل والإرجاع والخصوصية.
- [ ] رقم الهاتف وواتساب وانستغرام وبريد التنبيهات ومعرّف تيليغرام في **الإعدادات**.
- [ ] صور انستغرام وقسم «من نحن» في الرئيسية، وهي ملفات في `public/assets/img/demo/`.
- [ ] حذف كوبون `JOZ10` التجريبي إن حمّلت بيانات الديمو.

---

## حل المشاكل الشائعة

| المشكلة | الحل |
|---|---|
| صفحة بيضاء أو خطأ 500 | راجع آخر ملف في `storage/logs/`، وتأكد من صلاحيات `storage` و`bootstrap/cache`، ومن PHP 8.4 |
| لا تظهر الصور | تأكد أن `APP_URL` مطابق للنطاق، ثم `php artisan storage:link` و`php artisan optimize` |
| لوحة التحكم بلا تنسيق | `php artisan filament:assets` ثم `php artisan optimize` |
| لا تصل الإشعارات | تأكد من سطر cron، ومعرّف تيليغرام في الإعدادات، ثم راجع `storage/logs/` |
| تعديل `.env` لا يؤثر | نفّذ `php artisan optimize` بعد كل تعديل، لأن الإعدادات محفوظة في ذاكرة مؤقتة |
| تعذر الدخول إلى لوحة التحكم عبر http | `SESSION_SECURE_COOKIE=true` يتطلب https، ففعّل SSL أولاً |
| فشل رفع الصور الكبيرة | ارفع `memory_limit` و`max_execution_time`، أو ارفع الصور على دفعات |
