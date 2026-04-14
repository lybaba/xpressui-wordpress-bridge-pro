=== XPressUI WordPress Bridge PRO ===
Contributors: iakpressteam
Tags: form, workflow, document intake, pro, multi-step
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.35
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
