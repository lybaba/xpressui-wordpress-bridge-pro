# XPressUI WordPress Bridge PRO

**PRO extension for XPressUI WordPress Bridge — full runtime and advanced field types.**

![WordPress: 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP: 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4)

---

## What it adds

The PRO plugin is a companion to the free [XPressUI WordPress Bridge](https://github.com/lybaba/xpressui-wordpress-bridge) plugin. It does not replace it — it extends it with two things:

1. **Full XPressUI runtime** — replaces the light bundle with the standard runtime, unlocking all advanced field types
2. **PRO field templates** — server-side PHP shells for fields not included in the base plugin

Everything else (submissions inbox, REST endpoint, shortcode, file uploads, notifications) stays in the base plugin.

---

## PRO field types

| Field type | Description |
|---|---|
| `qr-scan` | Camera or image upload to decode a QR code |
| `document-scan` | Multi-slot document capture (front/back, OCR, MRZ) |
| `product-list` | Product catalog with image, pricing, and quantity controls |
| `quiz` | Choice-based or open-answer quiz with scoring support |
| `select-product` | Single product picker |
| `select-image` | Image-based choice field |
| `select-multiple` | Multi-select with tag display |
| `section-select` | Section-level branching selector |

---

## Requirements

- **XPressUI WordPress Bridge** (free, base plugin) — must be installed and active
- WordPress 6.0 or later
- PHP 8.0 or later

---

## Installation

1. Download the latest `xpressui-wordpress-bridge-pro-{version}.zip` from the [Releases page](../../releases).
2. Make sure the base plugin **XPressUI WordPress Bridge** is already installed and active.
3. In wp-admin go to **Plugins › Add New › Upload Plugin**, select the PRO zip, click **Install Now**.
4. Activate the plugin.

The PRO runtime is activated automatically — no configuration required.

---

## How it works

### Runtime swap

The base plugin loads the light XPressUI runtime by default. The PRO plugin hooks into the `xpressui_runtime_url` filter and replaces the URL with the bundled standard runtime before the shell HTML is rendered.

### Template resolution

Field PHP templates are resolved by scanning a list of directories. The PRO plugin appends its own `templates/generated/fields/` directory via the `xpressui_field_template_dirs` filter, making PRO field types available without modifying the base plugin.

---

## Distribution

This plugin is distributed manually to clients. It is not published on WordPress.org.

To request access, contact [hello@iakpress.com](mailto:hello@iakpress.com).

---

## Links

- Base plugin: [XPressUI WordPress Bridge](https://github.com/lybaba/xpressui-wordpress-bridge)
- Demo gallery: [demos.iakpress.com](https://demos.iakpress.com/)
- Contact: [hello@iakpress.com](mailto:hello@iakpress.com)
