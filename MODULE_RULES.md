# 📘 راهنمای توسعه ماژول‌های خودکفا و قوانین معماری (MODULE_RULES.md)
## 📌 Singo Tools (جعبه ابزار سینگو) - معماری ماژولار مبتنی بر Optionino

این سند استانداردهای قطعی، ساختار پوشه‌بندی، نحوه یکپارچه‌سازی با دیزاین سیستم **Optionino** و گاردریل‌های امنیتی/کارایی را برای ساخت ماژول‌های ۱۰۰٪ خودکفا (**Self-Contained Plug & Play**) در افزونه Singo Tools تعریف می‌کند.

---

## 🏗️ ۱. اصول بنیادین ماژول‌های خودکفا (Core Principles)

هر ماژول در مسیر `modules/<module-slug>/` یک واحد **کاملاً مستقل و خودکفا** است:
1. **مسیریابی محلی فایل‌ها (`$this->get_path()`):** کلاس‌های داخلی، ویوها و فایل‌های کمکی مستقیماً از داخل پوشه ماژول خوانده می‌شوند.
2. **مسیریابی محلی دارایی‌ها (`$this->get_url()`):** استایل‌های CSS، اسکریپت‌های JS و تصاویر منحصراً از داخل پوشه `assets/` ماژول فراخوانی می‌شوند.
3. **ثبت خودکار دارایی‌ها (Lifecycle Hooks):** با ارث‌بری از `SingoTools\Core\AbstractModule`، متدهای `enqueue_admin_assets()` و `enqueue_frontend_assets()` به طور خودکار به هوک‌های وردپرس متصل می‌شوند.
4. **رابط کاربری ۱۰۰٪ بومی Optionino:** تمامی فرم‌ها، سوئیچرها و تنظیمات ماژول‌ها منحصراً از امکانات نیتیو Optionino استفاده می‌کنند (عدم استفاده از سیستم‌های متفرقه مانند WPDS).
5. **نصب آسان و بدون دستکاری هسته (Drop-in Plug & Play):** برای اضافه کردن یک ماژول جدید، صرفاً کافی است پوشه آن داخل `modules/` قرار گیرد. `ManifestManager` آن را کشف کرده و بدون اسکن مداوم دیسک، آن را مدیریت می‌کند.

---

## 📁 ۲. ساختار استاندارد پوشه ماژول

```text
modules/
└── <module-slug>/
    ├── <ModuleName>Module.php    # کلاس اصلی ماژول که از AbstractModule ارث‌بری می‌کند
    ├── includes/                 # کلاس‌های کمکی و منطق داخلی ماژول (اختیاری)
    │   └── class-module-helper.php
    ├── views/                    # ویوهای اختصاصی Optionino با پارامتر 'file' (اختیاری)
    │   └── admin-view.php
    └── assets/                   # دارایی‌های لوکال ماژول (اختیاری)
        ├── css/
        │   └── style.css
        └── js/
            └── script.js
```

> **قرارداد نام‌گذاری فایل اصلی:**
> نام فایل اصلی ماژول باید به صورت CamelCase با پسوند `Module.php` باشد (مثال: `CurrencyConverterModule.php` یا `ProductPriorityModule.php`).

---

## 📜 ۳. متدهای کلاس پایه `AbstractModule`

کلاس `SingoTools\Core\AbstractModule` در `includes/Core/AbstractModule.php` متدهای استاندارد زیر را ارائه می‌دهد:

| متد | نوع خروجی | شرح |
| :--- | :--- | :--- |
| `get_id()` | `string` (الزامی) | شناسه منحصر‌به‌فرد ماژول (Slug) |
| `get_name()` | `string` (الزامی) | عنوان فارسی و نمایشی ماژول |
| `get_icon()` | `string` | آیکون دش‌ایکون ماژول (پیش‌فرض: `dashicons-admin-generic`) |
| `get_order()` | `int` | اولویت نمایش تب در منوی Optionino (عددی مانند 10, 20, 30) |
| `is_enabled()` | `bool` (الزامی) | بررسی وضعیت فعال بودن ماژول در دیتابیس |
| `is_context_valid()` | `bool` | بررسی پیش‌نیازها (مثلاً وجود ووکامرس با `class_exists('WooCommerce')`) |
| `get_optionino_tab()` | `?array` | تعریف کامل یک تب اختصاصی در Optionino |
| `get_optionino_fields()` | `array` | افزودن فیلد/سوئیچر به تب مشترک «سایر امکانات» |
| `$this->get_path($subpath)` | `string` | آدرس مطلق دیسک فایل‌های ماژول |
| `$this->get_url($subpath)` | `string` | آدرس URL فایل‌های ماژول برای Enqueue |
| `init()` | `void` | متد اصلی اجرای ماژول (هنگام فعال بودن) |
| `enqueue_admin_assets()` | `void` | بارگذاری استایل‌ها و اسکریپت‌های ادمین |
| `enqueue_frontend_assets()` | `void` | بارگذاری استایل‌ها و اسکریپت‌های فرانت‌اند |

---

## 🎨 ۴. استانداردهای رابط کاربری نیتیو Optionino

تمام ماژول‌ها برای نمایش تنظیمات خود باید از ساختار رسمی **Optionino** پیروی کنند:

### الف) ماژول‌های با تب اختصاصی (`get_optionino_tab`)
برای ماژول‌های بزرگ، متد `get_optionino_tab()` یک آرایه استاندارد Optionino برمی‌گرداند:
```php
public function get_optionino_tab(): ?array {
    return array(
        'id'     => 'my_module_settings',
        'title'  => self::get_name(),
        'desc'   => 'توضیحات عملکرد ماژول در بالای تب.',
        'icon'   => self::get_icon(),
        'fields' => array(
            array(
                'id'      => 'enable_my_module',
                'type'    => 'switcher',
                'title'   => 'فعال‌سازی ماژول',
                'default' => false,
            ),
            // سایر فیلدهای استاندارد Optionino (text, number, select, ...)
        ),
    );
}
```

### ب) ماژول‌های دارای قالب سفارشی (`file`)
ماژول‌هایی که نیاز به جدول یا فرم سفارشی دارند (مانند گروه‌بندی ویژگی‌ها یا لیست قیمت)، فایل ویو خود را با کلید `'file'` معرفی می‌کنند:
```php
public function get_optionino_tab(): ?array {
    return array(
        'id'    => 'my_complex_tool',
        'title' => self::get_name(),
        'desc'  => 'مدیریت پیشرفته ابزار.',
        'icon'  => self::get_icon(),
        'file'  => $this->get_path( 'views/admin-view.php' ),
    );
}
```

### ج) ماژول‌های سبک و بدون تنظیمات پیچیده («سایر امکانات»)
اگر یک ماژول تنظیمات زیادی ندارد و صرفاً یک سوئیچر روشن/خاموش یا چند فیلد ساده دارد، توسعه‌دهنده به راحتی می‌تواند آن را در بخش **«سایر امکانات»** قرار دهد. برای این کار دو حالت وجود دارد:

#### ۱. قرارگیری خودکار تنها با یک خط کد (Zero Configuration):
توسعه‌دهنده صرفاً متد `get_placement()` را برابر `'other_tools'` قرار می‌دهد:
```php
public static function get_placement(): string {
    return 'other_tools';
}

public static function get_description(): string {
    return 'توضیحات عملکرد این قابلیت در بخش سایر امکانات.';
}
```
در این حالت هسته افزونه به صورت کاملاً خودکار یک سوئیچر روشن/خاموش با آی‌دی `enable_<module_id>`، عنوان ماژول و توضیحات آن در تب «سایر امکانات» می‌سازد و هیچ نیازی به نوشتن کدهای فیلد Optionino نیست!

#### ۲. فیلدهای سفارشی در «سایر امکانات»:
اگر ماژول علاوه بر سوئیچ روشن/خاموش، فیلدهای دیگری (مانند متن یا عدد) در بخش سایر امکانات نیاز داشته باشد، متد `get_optionino_fields()` را بازنویسی می‌کند:
```php
public static function get_placement(): string {
    return 'other_tools';
}

public function get_optionino_fields(): array {
    return array(
        array(
            'id'      => 'enable_my_feature',
            'type'    => 'switcher',
            'title'   => self::get_name(),
            'desc'    => 'فعال‌سازی این ابزار.',
            'default' => false,
        ),
        array(
            'id'      => 'my_feature_custom_val',
            'type'    => 'number',
            'title'   => 'مقدار سفارشی',
            'default' => '10',
            'require' => array( array( 'enable_my_feature', '==', true ) ),
        ),
    );
}
```

> **📌 قوانین حاکم بر تب «سایر امکانات»:**
> 1. **اولویت قطعی در انتهای لیست (Always Last):** تب «سایر امکانات» همواره دارای اولویت آخر (`PHP_INT_MAX`) است و هر ماژول یا تب جدیدی که با هر اولویتی اضافه شود، حتماً بالاتر از سایر امکانات قرار می‌گیرد.
> 2. **حذف خودکار در صورت نبود ماژول (Dynamic Visibility):** اگر هیچ ماژولی برای قرارگیری در «سایر امکانات» تعریف نشده باشد، این تب به طور ۱۰۰٪ از منو و رابط کاربری Optionino حذف می‌گردد و هیچ تب خالی نمایش داده نخواهد شد.

---

## ⚡ ۵. استراتژی کارایی و عدم اسکن دیسک (Zero Disk Scanning)

1. **کشف خودکار با کش دیتابیس:** کلاس `SingoTools\Core\ManifestManager` مسئول کشف ماژول‌ها است. مانیفست ماژول‌ها در آپشن `singo_tools_modules_manifest` در دیتابیس ذخیره می‌شود.
2. **ران‌تایم سریع و بدون اسکن:** در ریکوئست‌های عادی سایت (فرانت‌اند و ادمین)، هیچ اسکن دیسکی (`scandir`) انجام نمی‌شود و ماژول‌ها مستقیماً از روی کش در دیتابیس اجرا می‌گردند.
3. **بازسازی دقیق کش (Manifest Rebuild Triggers):** مانیفست دیسک صرفاً در ۲ رویداد زیر بازسازی و ذخیره می‌گردد:
   - **هنگام آپدیت و جایگزینی افزونه در وردپرس:** با زدن دکمه «بارگذاری شده را جایگزین فعلی نمایید» یا هوک `upgrader_process_complete` و بررسی تغییر نسخه در `check_version_upgrade`، مانیفست فقط **یک‌بار** اجرا و ثبت می‌شود.
   - **هنگام نصب یا فعال‌سازی افزونه:** هوک `register_activation_hook`.
   - **توجه:** با زدن دکمه «ذخیره تغییرات» در تنظیمات، هیچ اسکن فایلی انجام نمی‌شود تا سرعت ذخیره ۱۰۰٪ آنی و بدون کوئری دیسک باشد.

---

## 🔒 ۶. گاردریل‌های امنیتی و کدنویسی PHP 8+

1. **جلوگیری از دسترسی مستقیم:** خط اول تمامی فایل‌های PHP باید به شکل زیر باشد:
   ```php
   defined( 'ABSPATH' ) || exit;
   ```
2. **تعیین سخت‌گیرانه تایپ‌ها:** ابتدای تمامی فایل‌ها باید `declare(strict_types=1);` قرار گیرد و تمامی پارامترها و بازگشتی‌ها دارای Type Hint صریح باشند.
3. **امنیت داده‌ها:** پاکسازی ورودی‌ها (`sanitize_text_field`, `absint`) و خروجی‌ها (`esc_html`, `esc_attr`, `esc_url`).
4. **اعتبارسنجی AJAX:** بررسی حتمی Nonce (`check_ajax_referer`) و دسترسی کاربر (`current_user_can('manage_woocommerce')`).
5. **ارث‌بری فونت:** در تمام استایل‌های سفارشی، فونت پیشخوان باید با `font-family: inherit !important;` رعایت شود.

---

## 📝 ۷. تمپلیت ماژول استاندارد (Boilerplate)

```php
<?php
declare(strict_types=1);

namespace SingoTools\Modules\SampleFeature;

use SingoTools\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

class SampleFeatureModule extends AbstractModule {

	public static function get_id(): string {
		return 'sample-feature';
	}

	public static function get_name(): string {
		return 'قابلیت نمونه';
	}

	public static function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	public static function get_order(): int {
		return 60;
	}

	public static function is_enabled(): bool {
		return (bool) singo_tools_get_option( 'enable_sample_feature', false );
	}

	public static function is_context_valid(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function get_optionino_tab(): ?array {
		return array(
			'id'     => 'sample_feature_settings',
			'title'  => self::get_name(),
			'desc'   => 'توضیحات قابلیت نمونه.',
			'icon'   => self::get_icon(),
			'fields' => array(
				array(
					'id'      => 'enable_sample_feature',
					'type'    => 'switcher',
					'title'   => 'فعال‌سازی این قابلیت',
					'default' => false,
				),
			),
		);
	}

	public function init(): void {
		parent::init();

		// لاجیک ماژول
	}
}
```
