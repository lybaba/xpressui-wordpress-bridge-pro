=== XPressUI WordPress Bridge PRO ===
Contributors: iakpressteam
Tags: form, workflow, document intake, pro, multi-step
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.57
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pro extension for XPressUI Bridge. Adds the full runtime, advanced field types, workflow customization, and Console Sync.

== Description ==

XPressUI Bridge PRO extends the free XPressUI Bridge plugin with advanced features:

* Pro field types: document scan, QR capture, product list, quiz, and more
* Customize Workflow: edit labels, choices, and validation rules from wp-admin
* Console Sync: pull workflow packs directly from your XPressUI Console
* Full XPressUI runtime bundled — no external CDN required
* License-key activation with RSA-SHA256 signature verification
* Automatic updates for the commercial Pro package

Requires the free XPressUI Bridge plugin (v1.0.26+) to be installed and active.

== Installation ==

1. Install and activate the free XPressUI Bridge plugin first.
2. Upload and activate this plugin (xpressui-wordpress-bridge-pro.zip).
3. Go to XPressUI > Pro License and enter your license key.
4. Upload your workflow pack ZIP via XPressUI > Workflows.

== Changelog ==

= 1.0.57 =
* Moved Customize Workflow assets into the Pro package so the feature is fully self-contained.
* Clarified public messaging for the Pro add-on, including Console Sync and workflow customization.
* Documentation cleanup to match the current free/pro architecture.

= 1.0.29 =
* Test release for automatic update detection

= 1.0.28 =
* Automatic updates now work independently of the free plugin activation state
* Disabled choices in Customize Workflow are now correctly hidden on the frontend
* Update detection is now immediate — new versions appear as soon as they are released
* Plugin details popup now shows the full description and changelog from within wp-admin
* Quick-access "Console" link added to the XPressUI wp-admin sidebar

= 1.0.7 =
* Customize Workflow: full field-level customization — labels, choices, validation rules, help text
* License verification with RSA-SHA256 signed API responses and local cache

= 1.0.0 =
* Initial release — PRO runtime bundled, license activation, advanced field type support
