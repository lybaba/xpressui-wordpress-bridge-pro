# XPressUI WordPress Bridge PRO

**PRO extension for XPressUI WordPress Bridge — full runtime and advanced field types.**

![WordPress: 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP: 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4)

---

## What it adds

The PRO plugin is a companion to the free [XPressUI WordPress Bridge](https://github.com/lybaba/xpressui-wordpress-bridge) plugin. It does not replace it — it extends it with three things:

1. **Full XPressUI runtime** — replaces the light bundle with the standard runtime, unlocking all advanced field types
2. **PRO field templates** — server-side PHP shells for fields not included in the base plugin
3. **Included Pro tools** — bundled QA workflows such as `Validation Playground` to test validation, uploads, and runtime behavior directly inside WordPress

Everything else (submissions inbox, REST endpoint, shortcode, file uploads, notifications) stays in the base plugin.

Today, this plugin sits on the main delivery path for XPressUI Console exports
targeting WordPress. The broader product direction is for the same builder to
support additional standalone export targets later, while WordPress remains the
primary supported host today.

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

The plugin also bundles a QA starter workflow:

- `Validation Playground`

Once the PRO plugin is active, it is installed automatically and appears in:

- `XPressUI -> Workflows -> Included Pro Tools`

---

## What you can edit locally in WordPress

The PRO plugin is designed to reduce routine trips back to the Console after delivery.

Inside wp-admin you can use `Customize Workflow` for local adaptations such as:

- project title and section labels
- field labels, helper text, placeholders, and choice labels
- validation limits such as required, min/max length, min/max choices, and numeric ranges
- upload rules such as accepted file types and max file size
- navigation labels and submit feedback messages

These edits are stored as a lightweight overlay in WordPress, so the installed pack stays usable even if the original Console project is not open.

## What still requires the Console

Structural changes still belong in the XPressUI Console. This includes:

- adding or removing fields
- changing field types
- reworking multi-step structure
- adding new conditional logic or workflow behavior
- producing a new exported pack version for distribution

This keeps the WordPress side focused on safe local customization while the Console remains the place for deeper product changes.

The same Console is also intended to become the export surface for standalone
integration packs on non-WordPress hosts. In other words, WordPress is the
current delivery channel, not the final boundary of the builder itself.

---

## How it works

### Runtime swap

The base plugin loads the light XPressUI runtime by default. The PRO plugin hooks into the `xpressui_runtime_url` filter and replaces the URL with the bundled standard runtime before the shell HTML is rendered.

### Template resolution

Field PHP templates are resolved by scanning a list of directories. The PRO plugin appends its own `templates/generated/fields/` directory via the `xpressui_field_template_dirs` filter, making PRO field types available without modifying the base plugin.

### Local autonomy

An installed workflow pack does not call back to the XPressUI Console at runtime. The site keeps rendering and accepting submissions with the assets already bundled in WordPress. The Console is only needed later if you want to make structural edits and export a new pack version.

---

## Distribution

This plugin is distributed manually to clients. It is not published on WordPress.org.

To request access, contact [hello@iakpress.com](mailto:hello@iakpress.com).

---

## Links

- Base plugin: [XPressUI WordPress Bridge](https://github.com/lybaba/xpressui-wordpress-bridge)
- Demo gallery: [xpressui.iakpress.com/#/demos](https://xpressui.iakpress.com/#/demos)
- Contact: [hello@iakpress.com](mailto:hello@iakpress.com)
