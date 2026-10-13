<div align="center">

```text
███████╗██╗███╗   ██╗ ██████╗  ██████╗     ███╗   ███╗ ██████╗ ██████╗ ██╗   ██╗██╗      █████╗ ██████╗
██╔════╝██║████╗  ██║██╔════╝ ██╔═══██╗    ████╗ ████║██╔═══██╗██╔══██╗██║   ██║██║     ██╔══██╗██╔══██╗
███████╗██║██╔██╗ ██║██║  ███╗██║   ██║    ██╔████╔██║██║   ██║██║  ██║██║   ██║██║     ███████║██████╔╝
╚════██║██║██║╚██╗██║██║   ██║██║   ██║    ██║╚██╔╝██║██║   ██║██║  ██║██║   ██║██║     ██╔══██║██╔══██╗
███████║██║██║ ╚████║╚██████╔╝╚██████╔╝    ██║ ╚═╝ ██║╚██████╔╝██████╔╝╚██████╔╝███████╗██║  ██║██║  ██║
╚══════╝╚═╝╚═╝  ╚═══╝ ╚═════╝  ╚═════╝     ╚═╝     ╚═╝ ╚═════╝ ╚═════╝  ╚═════╝ ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝
```

# Singo Modular

**High-Performance Modular WordPress Plugin Architecture & Boilerplate Powered by Optionino**  
*A modern reference implementation featuring a Zero Disk Scanning runtime policy*

<p align="center">
  <a href="https://github.com/Aria-Ebrm/singo-modular/archive/refs/heads/main.zip"><img src="https://img.shields.io/badge/Download-ZIP%20Plugin-2563EB?style=for-the-badge&logo=github&logoColor=white" alt="Download ZIP"></a>
  <a href="MODULE_RULES.md"><img src="https://img.shields.io/badge/Standards-MODULE__RULES.md-059669?style=for-the-badge&logo=markdown&logoColor=white" alt="Standards"></a>
  <a href="#-quick-start-how-to-build-a-new-module"><img src="https://img.shields.io/badge/Guide-Quick%20Start-D97706?style=for-the-badge&logo=bookstack&logoColor=white" alt="Quick Start"></a>
</p>

<p align="center">
  <a href="https://github.com/Aria-Ebrm/singo-modular"><img src="https://img.shields.io/badge/Release-v1.0.0-2563EB?style=flat&logo=github" alt="Release"></a>
  <a href="https://wordpress.org"><img src="https://img.shields.io/badge/WordPress-6.7%2B-21759B?style=flat&logo=wordpress&logoColor=white" alt="WordPress"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php&logoColor=white" alt="PHP"></a>
  <a href="https://github.com/shokrino/optionino-framework"><img src="https://img.shields.io/badge/Framework-Optionino-0284C7?style=flat" alt="Optionino Framework"></a>
  <a href="https://www.gnu.org/licenses/gpl-2.0.html"><img src="https://img.shields.io/badge/License-GPL%20v2%2B-F59E0B?style=flat" alt="License"></a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Architecture-Modular%20Self--Contained-10B981?style=flat" alt="Architecture">
  <img src="https://img.shields.io/badge/Disk%20I%2FO-Zero%20Runtime%20Scan-059669?style=flat" alt="Zero Runtime Scan">
  <img src="https://img.shields.io/badge/Storage-Intelligent%20Delta-7C3AED?style=flat" alt="Delta Storage">
  <img src="https://img.shields.io/badge/Type%20Safety-PHP%208%20Strict-3B82F6?style=flat" alt="Strict Types">
  <img src="https://img.shields.io/badge/Default-Inactive%20Safe-6B7280?style=flat" alt="Default Inactive">
  <a href="https://github.com/Aria-Ebrm/singo-modular/pulls"><img src="https://img.shields.io/badge/PRs-Welcome-22C55E?style=flat&logo=git&logoColor=white" alt="PRs Welcome"></a>
</p>

</div>

**Singo Modular** is a modern, self-contained, lightning-fast architecture for developing WordPress features and plugins. Designed as a **Reference Implementation**, it enables developers to build and plug-in new features seamlessly without ever touching core plugin files or altering settings panel configurations.

---

## 🌟 Core Architecture & Framework Features

### 1. ⚡ Zero Disk Scanning Runtime Policy (`ManifestManager`)
- **Zero Runtime Filesystem Overhead:** Normal WordPress page requests (both frontend and dashboard) never run `glob()`, `scandir()`, or filesystem directory scans.
- **Persistent Database Cache:** All discovered modules, class namespaces, paths, and placement configurations are compiled once into the `singo_modular_modules_manifest` database option.
- **Deterministic Cache Invalidation:** The manifest cache is rebuilt strictly during lifecycle events: plugin activation (`register_activation_hook`), plugin update (`upgrader_process_complete`), or explicit settings saves.

### 2. 💾 Intelligent Delta Saving & Dirty Checking Engine (`DeltaManager`)
- **Client-Side Dirty Checking (`assets/js/singo-delta-save.js`):** Takes an initial snapshot of the settings form upon load. Monitors all inputs, switchers, and nested Optionino repeaters, isolating only the keys modified by the user (`dirtyFields`).
- **Sub-Millisecond Payload:** Transmits only the modified keys via AJAX instead of submitting the full, bloated settings form.
- **Server-Side Differential Merge (`includes/Core/DeltaManager.php`):** Compares inbound deltas against stored options via `pre_update_option`, eliminating redundant SQL `UPDATE` queries and preventing database write overhead.

### 3. 🎨 Native Optionino Integration & Dynamic Injection
- **100% Core Immutability:** Seamlessly injects module settings into the [Optionino Framework](https://github.com/shokrino/optionino-framework) by [Matin Shokri](https://github.com/shokrino) ([shokrino.com](https://shokrino.com)) without altering a single line of core `optionino/` code.
- **Branded Admin UI:** Features customized admin styling, brand color theming, dynamic SVG logo integration, and inherited dashboard typography (`font-family: inherit !important;`).

### 4. 🧱 Dual Placement Strategy
- **Dedicated Tab (`dedicated_tab`):** For expansive modules requiring an independent navigation tab, custom Dashicon/SVG, and specific ordering priority (`get_order()`).
- **Other Tools Tab (`other_tools`):** Automatically consolidates compact, single-purpose utilities into a clean, shared "Other Tools" tab positioned at the end (`PHP_INT_MAX`), eliminating admin menu clutter.

### 5. 🛡️ Default-Inactive Principle
- **Safe Activation:** Every single feature, module switcher, and control defaults to `false` upon installation.
- **Zero Unexpected Side Effects:** Guarantees zero unintentional layout shifts, visual modifications, or database writes until explicitly turned on by administrators.

### 6. 🚀 Static In-Memory Option Caching (`helpers.php`)
- **Static Request Cache:** `singo_modular_get_option()` caches retrieved settings in static memory, bypassing repetitive `get_option()` queries during complex page rendering cycles.
- **Instant Cache Flush:** Automatically flushes the runtime cache whenever options are updated (`update_option_singo_modular_options`).

---

## 📦 Bundled Production Modules & Capabilities

### 📅 1. Solar Hijri (Shamsi / Persian) Date Module (`modules/shamsi-date/`)
*Comprehensive Persian calendar engine converting Gregorian dates across the WordPress ecosystem.*
- **WordPress Core Integration:** Translates dates in posts, comments, archives, admin post list columns, and dashboard widgets.
- **WooCommerce Support:** Persian date conversion for order management tables, order detail views, customer notes, transactional emails, and invoices.
- **Elementor & Elementor Pro Compatibility:** Native date conversion for the Posts Widget, Archive Posts, Loop Builder, and Dynamic Tags.
- **Elementor v4 Atomic Widgets (Twig Support):** Includes native Twig template (`persian-date-picker.html.twig`) engineered for next-generation Elementor v4 atomic controls.
- **Persian Numeral Localization:** Converts standard Latin numerals (0-9) to Persian digits (۰-۹) across dates and numeric strings.
- **Standalone Persian Datepicker:** Lightweight vanilla JS datepicker (`singo-persian-calendar.js` & `singo-datepicker-init.js`) integrated into admin screens and frontend Elementor forms.
- **Granular Controls:** Dedicated admin tab allowing administrators to toggle each integration (Core, WooCommerce, Elementor, Digits, Datepicker) independently.

### ⏱️ 2. Estimated Reading Time Module (`modules/reading-time/`)
*Smart reading time estimator calibrated specifically for Persian content and typography.*
- **Persian Text Tokenizer:** Accurately counts Persian words, numbers, and respects zero-width non-joiners (نیم‌فاصله - ZWNJ).
- **Two-Tier Caching Architecture:**
  - *Tier 1 (Memory):* Static in-memory cache for repeated lookups within the same request.
  - *Tier 2 (Post Meta / Transients):* Stores calculated reading times to avoid repeated string parsing on every visit.
- **Customizable WPM Speed:** Configurable words-per-minute rate to match content density (default: 200 WPM).
- **Flexible Shortcode:** `[reading_time]` shortcode supporting customizable `before` text, `after` text, and post `id` parameters.
- **Clean Placement:** Resides automatically inside the shared "Other Tools" tab.

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

## 👏 Credits & Acknowledgements

- **Optionino Framework:** Powered by the lightweight, developer-friendly WordPress settings framework created by **[Matin Shokri](https://github.com/shokrino)** ([shokrino.com](https://shokrino.com) / [GitHub Repository](https://github.com/shokrino/optionino-framework)).
- **Architecture & Boilerplate:** Designed and maintained by **[Singo Studio](https://singo.studio)**.

---

## 🤝 Contributing & Community Modules

We actively welcome community contributions! If you have developed a useful, well-crafted module for WordPress, feel free to open a Pull Request. Suitable and well-architected modules will be reviewed and merged into the public repository.

### ⚠️ Contribution Standards Checklist
Before submitting a module, please review and ensure compliance with our comprehensive [Module Development Guidelines (MODULE_RULES.md)](MODULE_RULES.md):

1. **Self-Contained & Decoupled:** Place your module entirely within its dedicated directory (`modules/your-module-name/`) and inherit from `AbstractModule`. **Never modify core files or the `optionino/` framework**.
2. **Zero Disk Scanning Policy:** Do not use `glob`, `scandir`, or runtime directory traversal. All module discovery is managed via `ManifestManager` database caching.
3. **Disabled by Default (Default-Inactive):** Every new module switcher must default to **`false`** (`'default' => false`). Features must never be auto-enabled upon installation.
4. **Modern PHP 8+ Strictness:** Every PHP file must begin with `declare(strict_types=1);` and include explicit type hinting for all arguments and return types.
5. **Security & Sanitization:** Follow WordPress security best practices (sanitize inputs, escape all outputs, and enforce capability/nonce checks).
6. **Smart Placement Strategy:** Select the appropriate placement pattern: **Dedicated Tab** (`dedicated_tab`) for complex suites or the shared **Other Tools Tab** (`other_tools`) for compact utilities.

---

## 📄 License

This project is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
