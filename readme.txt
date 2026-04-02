=== XPressUI WordPress Bridge PRO ===
Contributors: iakpressteam
Tags: form, workflow, document intake, pro, multi-step
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.27
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pro extension for XPressUI Bridge. Unlocks advanced field types and workflow customization inside WordPress.

== Description ==

XPressUI Bridge PRO extends the free XPressUI Bridge plugin with advanced features:

* Pro field types: document scan, QR capture, product list, quiz, and more
* Customize Workflow: edit labels, choices, and validation rules from wp-admin
* Full XPressUI runtime bundled — no external CDN required
* License-key activation with RSA-SHA256 signature verification

Requires the free XPressUI Bridge plugin (v1.0.26+) to be installed and active.

== Installation ==

1. Install and activate the free XPressUI Bridge plugin first.
2. Upload and activate this plugin (xpressui-wordpress-bridge-pro.zip).
3. Go to XPressUI > Settings > License and enter your license key.
4. Upload your workflow pack ZIP via XPressUI > Workflows > Upload.

== Changelog ==

= 1.0.27 =
* Fix plugin details popup: read Description and Changelog from readme.txt
* Fix "not tested" warning: tested version now matches the running WordPress version

= 1.0.26 =
* Fix update checker: load unconditionally (no longer requires free plugin active to detect updates)
* Mark beta field types: document-scan, qr-scan, product-list, quiz, section-select (@status beta, v1-unsupported)

= 1.0.25 =
* Test release for update detection

= 1.0.24 =
* Fix update detection: don't cache up-to-date/error states — only cache when an update is available
* Clear update cache when admin visits Dashboard > Updates with force-check

= 1.0.23 =
* Fix disabled choices: remove from output instead of marking disabled (ensures runtime hides them)

= 1.0.22 =
* Test release for automatic update detection

= 1.0.21 =
* Fix update detection: clear cache when WordPress forces a check (Dashboard > Updates > Check again)

= 1.0.20 =
* Test release for automatic update detection

= 1.0.19 =
* Remove manual "Check for updates" button — WordPress checks automatically every 12h

= 1.0.18 =
* Show update-available notice directly on XPressUI Workflows page

= 1.0.17 =
* Fix license form: hide input field when license is active, show only masked key

= 1.0.16 =
* Fix license activate/deactivate redirect (wrong page slug)

= 1.0.15 =
* Add "↗ Console" link in XPressUI wp-admin sidebar — opens XPressUI Console directly

= 1.0.14 =
* Add "Check for updates" button on Workflows admin page
* Auto-clear update transient after plugin upgrade
* Reduce update check cache from 12h to 2h

= 1.0.13 =
* Fix license gate link on Customize Workflow page (wrong page slug)

= 1.0.12 =
* Add automatic update checker — wp-admin shows update badge when new version available
* Gate Customize Workflow page behind active license check

= 1.0.11 =
* Wire license enforcement: xpressui_pro_is_license_active() now hooked to xpressui_bridge_has_valid_pro_license filter

= 1.0.10 =
* Fix CI: exclude scripts/ and .distignore from release ZIP

= 1.0.8 =
* Fix Plugin Check: text domain alignment, ABSPATH guards, input sanitization

= 1.0.7 =
* License verification with RSA-SHA256 signed API responses
* Customize Workflow: full field-level customization UI

= 1.0.0 =
* Initial release
