# Wordpress Block External Services - افزونه مسدودسازی سرویس‌های خارجی وردپرس

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![Version](https://img.shields.io/badge/Version-2.1.0-brightgreen.svg)](https://github.com/Deldar/block-external-services)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Block External Services** is a lightweight, high-performance WordPress plugin designed for emergency situations (network outages, slow international routing, sanctions, or extreme admin panel lag). It blocks outgoing HTTP requests, external scripts, styles, fonts, and Gravatar connections to make your WordPress admin lightning fast.

### 🚀 Key Features (v2.1.0)
- ⚡ **Emergency Master Switch (Kill Switch):** Quickly activate or pause blocking with a single click without deactivating the plugin.
- 🎯 **Accurate Domain & Subdomain Matching:** Uses host-level URL parsing to prevent accidental over-blocking of local site assets.
- 🚫 **External Scripts & Styles Blocking:** Automatically dequeues and filters external JS/CSS assets (`wp_styles`, `wp_scripts`, `style_loader_src`, `script_loader_src`).
- 👤 **100% Offline Gravatar Replacement:** Replaces Gravatar requests with a local inline SVG data URI, resulting in zero outbound network requests.
- 📊 **Blocked Requests Log:** Real-time visibility into which external URLs were blocked and when, helping diagnose problematic plugins.
- ⚡ **Quick Presets:** One-click shortcuts to block Google Fonts, Gravatar, Elementor, and WordPress.org updates.
- 🎯 **Removes DNS prefetch & preconnect hints** for blocked domains.

### 📥 Installation
1. Upload the plugin folder to `/wp-content/plugins/block-external-services`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **Settings → Block External Services (تنظیمات ← مسدودسازی سرویس‌ها)** to configure

---

**افزونه مسدودسازی سرویس‌های خارجی (Block External Services)** یک افزونه سبک و قدرتمند وردپرس برای مواقع اضطراری (اختلالات شدید اینترنت بین‌الملل، فیلترینگ، تحریم یا کندی پیشخوان وردپرس) است. هسته وردپرس و افزونه‌هایی مانند المنتور دائماً در حال ارسال درخواست‌های پس‌زمینه هستند که در صورت اختلال شبکه، باعث قفل شدن و کندی شدید پیشخوان می‌شوند. این افزونه با قطع فوری این درخواست‌ها، سرعت پیشخوان را نجات می‌دهد.

### 🚀 قابلیت‌های کلیدی (نسخه ۲.۱.۰)
- ⚡ **کلید قطع و وصل اضطراری (Master Switch):** توقف یا فعال‌سازی آنی مسدودسازی با یک کلیک بدون نیاز به غیرفعال کردن افزونه.
- 🎯 **تطابق هوشمند و دقیق دامنه:** فیلتر دقیق بر اساس Host بدون ریسک مسدود شدن فایل‌های داخلی و محلی هاست.
- 🚫 **مسدودسازی اسکریپت‌ها و استایل‌ها:** پشتیبانی کامل از غیرفعال‌سازی کدهای JS و CSS خارجی در صف وردپرس (`wp_scripts`, `wp_styles`).
- 👤 **مسدودسازی ۱۰۰٪ محلی گراواتار:** جایگزینی تصاویر گراواتار با آیکون بهینه‌ی SVG به صورت آفلاین (بدون حتی ۱ درخواست خارجی به Gravatar).
- 📊 **لاگ درخواست‌های مسدود شده:** ثبت و نمایش جدول درخواست‌های خروجی قطع‌شده برای ردیابی افزونه‌هایی که باعث کندی بوده‌اند.
- ⚡ **میانبرهای پرکاربرد (Presets):** دکمه‌های آماده برای افزودن سریع فونت‌های گوگل، گراواتار، المنتور و بررسی آپدیت‌های وردپرس.
- 🎯 **حذف هوشمند Resource Hints:** پاکسازی تگ‌های DNS prefetch و preconnect مربوط به دامنه‌های مسدود شده.

### 📥 نحوه نصب و استفاده
۱. پوشه افزونه را در مسیر `/wp-content/plugins/block-external-services` قرار دهید.  
۲. افزونه را از طریق بخش «افزونه‌ها» در وردپرس فعال کنید.  
۳. از منوی **تنظیمات ← مسدودسازی سرویس‌ها** وضعیت حالت اضطراری و دامنه‌ها را مدیریت کنید.

### 📄 مجوز (License)
GPL v2 or later
