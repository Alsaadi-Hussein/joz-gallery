# نشر جوز كاليري على Laravel Cloud

هذا الدليل يعطيك رابطاً تجريبياً ترسله للعميل، بدون شراء استضافة أو نطاق. للنسخة النهائية على cPanel راجع `DEPLOY.md`.

بعد النشر يكون عندك رابطان:

- الموقع: `https://اسم-التطبيق.laravel.cloud`
- لوحة الأدمن: `https://اسم-التطبيق.laravel.cloud/admin`

---

## 1. إنشاء التطبيق

1. افتح https://cloud.laravel.com وسجّل دخولك بحساب GitHub.
2. اضغط **New application**، واسمح لـ Laravel Cloud بالوصول إلى مستودع `joz-gallery`. المستودع يبقى خاصاً.
3. اختر الفرع `main` والمنطقة الأقرب، مثل **Frankfurt**.

## 2. قاعدة البيانات

من صفحة البيئة (Environment) اضغط **Add resource** ثم **Database**، واختر **Laravel MySQL**. يربطها Laravel Cloud تلقائياً ويضع إعدادات `DB_*` بنفسه.

## 3. تخزين الصور

الملفات على سيرفر Laravel Cloud تُمسح مع كل نشر جديد، لذلك تحتاج الصور مكاناً دائماً:

1. **Add resource** ثم **Object storage**.
2. اختر الخصوصية **Public**، واكتب اسم القرص (Disk name): `media`.

## 4. المتغيرات (Environment variables)

من **Settings** ثم **Environment variables** أضف هذه القيم. لا تضف `DB_*` ولا `AWS_*`، فـ Laravel Cloud يضعها بنفسه.

```
APP_NAME="Joz Gallery"
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

FILESYSTEM_DISK=media
MEDIA_DISK=media
QUEUE_CONVERSIONS_BY_DEFAULT=false
IMAGE_DRIVER=gd

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

TRUSTED_PROXIES=*

ADMIN_EMAIL=بريدك
ADMIN_PASSWORD=كلمة-مرور-قوية

SEED_DEMO=true
MAIL_MAILER=log
```

- `APP_KEY` و`APP_URL`: يضعها Laravel Cloud تلقائياً. إن لم يفعل، أضف `APP_URL` برابط التطبيق مع `https://` وبدون `/` في الآخر.
- `ADMIN_PASSWORD`: لا تقل عن 12 حرفاً، وفيها حروف كبيرة وصغيرة وأرقام ورموز، وإلا يفشل النشر.
- `SEED_DEMO=true`: يضيف منتجات تجريبية حتى يرى العميل متجراً فيه بضاعة. غيّرها إلى `false` قبل إدخال البضاعة الحقيقية.
- `TRUSTED_PROXIES=*`: ضروري، لأن Laravel Cloud يمرر الطلبات عبر خوادم وسيطة. بدونه تفشل الروابط وتسجيل الدخول.

## 5. أوامر البناء والنشر

من **Settings** ثم **Deployments**:

**Build commands**

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

**Deploy commands**

```
php artisan migrate --force
php artisan db:seed --force
```

تشغيل `db:seed` في كل نشر آمن: يضيف الناقص فقط ولا يمس ما عدّلته من اللوحة.

## 6. الإصدار والمهام

- **PHP version**: اختر **8.4**.
- **Scheduler**: فعّله، فهو يولّد خريطة الموقع وينظّف الملفات القديمة.
- **Queue worker**: فعّله إن توفر، لإرسال الإشعارات في الخلفية.

## 7. النشر

اضغط **Deploy**. بعد انتهائه افتح رابط التطبيق، ثم `/admin` وسجّل الدخول بـ `ADMIN_EMAIL` و`ADMIN_PASSWORD`.

بعد أول دخول ناجح، احذف `ADMIN_PASSWORD` من المتغيرات.

إن فشل النشر، افتح سجل النشر (Deployment logs). أكثر سببين شيوعاً: كلمة مرور الأدمن ضعيفة، أو قاعدة البيانات غير مربوطة.
