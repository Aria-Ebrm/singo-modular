# 🧩 Singo Modular WordPress Plugin Framework
### Reference Architecture & Standard Boilerplate for Modular WordPress Development Powered by Optionino

**Singo Modular** is a modern, self-contained, lightning-fast architecture for developing WordPress features and plugins. Designed as a **Reference Implementation**, it enables developers to build and plug-in new features seamlessly without ever touching core plugin files or altering settings panel configurations.

---

## 🌟 Key Architectural Features

- ⚡ **Zero Disk Scanning at Runtime:** All modules are cached in the database via `ManifestManager`. Zero filesystem scanning (`glob` or `scandir`) occurs during standard page loads.
- 🎨 **100% Native Optionino Integration:** Seamlessly injects module tabs and setting fields into the Optionino panel dynamically without modifying a single line of core `optionino/` code.
- 🧱 **Dual Module Placement Strategy:**
  - **Dedicated Tab (`dedicated_tab`):** For expansive, multi-feature modules with custom order and icons.
  - **Other Tools Tab (`other_tools`):** For compact, single-purpose utilities to prevent admin menu clutter.
- 💾 **Intelligent Delta Saving:** When saving admin options, only modified keys are written to the database, preventing heavy I/O overhead and redundant SQL queries.
- 🛡️ **Default-Inactive Rule:** All new features and modules are disabled by default (`false`) upon installation, giving administrators complete control.
- 🐘 **Modern PHP 8+ Standards:** Full compliance with `declare(strict_types=1);`, explicit type hinting, and strict WordPress security best practices.

---

## 📦 Bundled Sample Modules

This repository includes two production-grade sample modules illustrating both placement paradigms:

| Module | Location | Placement Strategy | Description |
| :--- | :--- | :--- | :--- |
| **Solar Hijri (Shamsi) Date** | `modules/shamsi-date/` | Dedicated Tab (`dedicated_tab`) | Complete Persian/Solar Hijri date conversion for WordPress core, WooCommerce, and Elementor with Persian numerals and datepickers. |
| **Estimated Reading Time** | `modules/reading-time/` | Other Tools Tab (`other_tools`) | Accurate reading time calculation for posts with two-layer caching, Persian word counting, and `[reading_time]` shortcode support. |

---

## 🚀 Quick Start: How to Build a New Module

To add a new module, simply create a folder in `modules/` and define its module class inheriting from `AbstractModule`:

### Pattern 1: Dedicated Tab Module
At `modules/sms-notify/SmsNotifyModule.php`:

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
        return 'SMS Notifications';
    }

    public static function get_icon(): string {
        return 'dashicons-format-chat';
    }

    public static function get_order(): int {
        return 20; // Tab positioning priority in the admin panel
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
                    'title'   => 'Enable Module',
                    'default' => false,
                ),
            ),
        );
    }

    public function init(): void {
        // Module hooks and runtime logic when enabled
    }
}
```

### Pattern 2: Lightweight Module in "Other Tools" Tab
At `modules/copy-protect/CopyProtectModule.php`:

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
        return 'Content Protection';
    }

    public static function get_placement(): string {
        return 'other_tools'; // Routes fields to the shared "Other Tools" tab
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
                'desc'    => 'Prevents text selection and right-click copying on the frontend.',
                'default' => false,
            ),
        );
    }

    public function init(): void {
        // Module hooks and frontend scripts
    }
}
```

---

## 🏗️ Project Directory Structure

```
singo-modular/
├── singo-modular.php                   # Plugin bootstrap and entry file
├── README.md                           # Project documentation
├── MODULE_RULES.md                     # Technical architecture & developer standards
├── .gitignore                          # Git ignore specifications
├── assets/                             # Admin UI styling & branding assets
├── includes/
│   ├── helpers.php                     # Secure helpers & static cached option getters
│   ├── class-singo-modular-core.php    # Central lifecycle orchestrator
│   ├── class-singo-modular-optionino.php # Dynamic Optionino panel integrator
│   └── Core/
│       ├── AbstractModule.php          # Base contract for all modules
│       ├── ManifestManager.php         # Zero Disk Scanning manifest builder & cache
│       ├── ModuleManager.php           # Runtime module instantiator & lifecycle manager
│       └── DeltaManager.php            # Differential Delta saving mechanism
├── modules/                            # Self-contained modules directory
│   ├── shamsi-date/                    # Reference implementation: Dedicated Tab
│   └── reading-time/                   # Reference implementation: Other Tools Tab
└── optionino/                          # Optionino UI Framework (100% untouched)
```

---

## 📋 System Requirements

- **PHP Version:** 8.0 or higher (tested up to PHP 8.3)
- **WordPress Version:** 6.7 or higher
- **WooCommerce (Optional):** Supported for e-commerce features

---

## 📄 License

This project is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).  
Developed by **Singo Studio**.
