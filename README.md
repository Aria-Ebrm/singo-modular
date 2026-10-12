# 🧩 افزونه ماژولار سینگو (Singo Modular)
### بویلرپلیت و فریم‌ورک استاندارد توسعه ماژولار برای وردپرس بر پایه Optionino

**افزونه ماژولار سینگو** یک معماری مدرن، خودکفا (Self-Contained) و فوق‌سریع برای توسعه امکانات در وردپرس است. این افزونه به عنوان یک **نمونه مرجع (Reference Implementation)** طراحی شده تا توسعه‌دهندگان بتوانند بدون دستکاری هسته افزونه یا دستکاری پنل تنظیمات، قابلیت‌های جدید را به صورت پلاگ‌اند‌پلی اضافه کنند.

---

## 🌟 ویژگی‌های کلیدی معماری

- ⚡ **سیاست عدم اسکن دیسک در ران‌تایم (Zero Disk Scanning):** تمامی ماژول‌ها توسط `ManifestManager` کش شده و هیچ اسکن فایلی در لود عادی صفحات رخ نمی‌دهد.
- 🎨 **یکپارچه‌سازی ۱۰۰٪ بومی با Optionino:** بدون تغییر حتی یک خط از کد هسته `optionino/`، تب‌ها و فیلدهای هر ماژول به صورت پویا به پنل مدیریت تزریق می‌شوند.
- 🧱 **دو الگوی استقرار ماژول (Dual Placement Strategy):**
  - **تب اختصاصی (Dedicated Tab):** برای ماژول‌های بزرگ و چندبخشی.
  - **سایر امکانات (Other Tools Tab):** برای ماژول‌های سبک و تک‌منظوره بدون شلوغ کردن منو.
- 💾 **ذخیره‌سازی هوشمند تفاضلی (Delta Saving):** در زمان ذخیره تنظیمات، تنها کلیدهای تغییریافته ذخیره شده و از کوئری‌های سنگین به دیتابیس جلوگیری می‌شود.
- 🛡️ **پیش‌فرض غیرفعال (Default Inactive):** تمام قابلیت‌ها در نصب اولیه خاموش هستند تا کنترل ۱۰۰٪ در دست مدیر سایت باشد.
- 🐘 **استاندارد مدرن PHP 8+:** رعایت کامل `strict_types=1`، انواع داده‌ای صریح و امنیت پیشرفته وردپرس.

---

## 📦 ماژول‌های نمونه موجود در این مخزن

این مخزن شامل دو ماژول آماده و بهینه به عنوان نمونه‌های کاربردی است:

| ماژول | مسیر | نوع استقرار | عملکرد |
| :--- | :--- | :--- | :--- |
| **تاریخ شمسی** | `modules/shamsi-date/` | تب اختصاصی (`dedicated_tab`) | تبدیل کامل تاریخ‌های وردپرس، ووکامرس و المنتور به هجری شمسی به همراه ارقام فارسی. |
| **زمان تخمینی مطالعه** | `modules/reading-time/` | تب سایر امکانات (`other_tools`) | محاسبه دقیق زمان مطالعه مقالات بر اساس کلمات فارسی و نیم‌فاصله با کش دو لایه و شورت‌کد `[reading_time]`. |

---

## 🚀 راهنمای سریع: چگونه یک ماژول جدید بسازیم؟

برای اضافه کردن یک ماژول جدید، کافیست یک پوشه در `modules/` بسازید و کلاس آن را تعریف کنید:

### نمونه ۱: ساخت ماژول با تب اختصاصی
در مسیر `modules/sms-notify/SmsNotifyModule.php`:

```php
<?php
declare(strict_types=1);

namespace SingoTools\Modules\SmsNotify;

use SingoTools\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

class SmsNotifyModule extends AbstractModule {

    public static function get_id(): string {
        return 'sms-notify';
    }

    public static function get_name(): string {
        return 'اطلاع‌رسانی پیامکی';
    }

    public static function get_icon(): string {
        return 'dashicons-format-chat';
    }

    public static function get_order(): int {
        return 20; // جایگاه قرارگیری تب در منو
    }

    public static function is_enabled(): bool {
        return (bool) singo_modular_get_option( 'enable_sms_notify', false );
    }

    public function get_optionino_tab(): ?array {
        return array(
            'id'     => self::get_id(),
            'title'  => self::get_name(),
            'icon'   => self::get_icon(),
            'fields' => array(
                array(
                    'id'      => 'enable_sms_notify',
                    'type'    => 'switcher',
                    'title'   => 'فعال‌سازی ماژول',
                    'default' => false,
                ),
            ),
        );
    }

    public function init(): void {
        // هوک‌ها و منطق ماژول هنگام فعال بودن
    }
}
```

### نمونه ۲: ساخت ماژول در تب «سایر امکانات»
در مسیر `modules/copy-protect/CopyProtectModule.php`:

```php
<?php
declare(strict_types=1);

namespace SingoTools\Modules\CopyProtect;

use SingoTools\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

class CopyProtectModule extends AbstractModule {

    public static function get_id(): string {
        return 'copy-protect';
    }

    public static function get_name(): string {
        return 'قفل راست‌کلیک';
    }

    public static function get_placement(): string {
        return 'other_tools'; // ارسال به تب سایر امکانات
    }

    public static function is_enabled(): bool {
        return (bool) singo_modular_get_option( 'enable_copy_protect', false );
    }

    public function get_optionino_fields(): array {
        return array(
            array(
                'id'      => 'enable_copy_protect',
                'type'    => 'switcher',
                'title'   => self::get_name(),
                'desc'    => 'جلوگیری از کپی متن و راست‌کلیک در سایت.',
                'default' => false,
            ),
        );
    }
}
```

---

## 🏗️ ساختار پوشه‌های مخزن

```
singo-modular-plugin/
├── singo-modular.php                   # فایل ریشه و راه‌انداز افزونه
├── README.md                           # راهنمای کلی مخزن
├── MODULE_RULES.md                     # استانداردهای فنی توسعه‌دهندگان
├── .gitignore                          # قوانین نادیده گرفتن فایل‌ها
├── assets/                             # دارایی‌های ادمین و برندینگ
├── includes/
│   ├── helpers.php                     # توابع کمکی امن و کشینگ آپشن‌ها
│   ├── class-singo-modular-core.php    # ارکستراتور و لودر مرکزی
│   ├── class-singo-modular-optionino.php # یکپارچه‌ساز داینامیک Optionino
│   └── Core/
│       ├── AbstractModule.php          # کلاس پایه ماژول‌ها
│       ├── ManifestManager.php         # سیستم کاشف خودکار و کش مانیفست
│       ├── ModuleManager.php           # منیجر لود ماژول‌ها
│       └── DeltaManager.php            # سیستم ذخیره تفاضلی (Delta)
├── modules/                            # پوشه مستقل ماژول‌ها
│   ├── shamsi-date/                    # نمونه تب اختصاصی
│   └── reading-time/                   # نمونه تب سایر امکانات
└── optionino/                          # هسته Optionino (100% دست‌نخورده)
```

---

## 📋 نیازمندی‌های سیستم

- **نسخه PHP:** 8.0 یا بالاتر (تست شده تا PHP 8.3)
- **نسخه وردپرس:** 6.7 یا بالاتر
- **ووکامرس (اختیاری):** جهت امکانات بخش فروشگاه

---

## 📄 لایسنس

این پروژه تحت مجوز [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html) منتشر شده است.
توسعه یافته توسط **Singo Studio**.
