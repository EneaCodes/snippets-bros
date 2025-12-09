=== Snippets Bros ===
Contributors: enos1
Author: Enea
Donate link: https://ko-fi.com/W7W51P4XY6
Author URI: https://profiles.wordpress.org/enos1
Tags: snippet manager, php, javascript, css, html
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional snippet manager for PHP, HTML, CSS and JS with conditions, safe mode, error log, import/export, revisions and more.

== Description ==

Snippets Bros is a professional code-snippet manager for WordPress that lets you safely run PHP, JavaScript, CSS and HTML anywhere on your site without touching theme files.

It is designed to be both powerful and safe: every snippet is validated and sanitized, there is a global Safe Mode switch, an error log with visual indicators, and import/export protections so a broken snippet cannot take down your site.

The interface is a custom dashboard (no custom post types) with filters, search, bulk tools and clear status badges, so you can manage a large number of snippets comfortably.

Made with ❤️ by Enea.

= Key Features =

* PHP, JS, CSS and HTML snippets with separate editors
* Run snippets everywhere, frontend only, admin only, shortcode only, or header/footer
* Smart conditions: URL contains / multiple URL patterns, user status, device type, AND logic
* Safe Mode switch to instantly stop all snippets
* Run once: execute a snippet a single time then auto-disable
* Execution priority (1–100) for fine-grained control
* Visual error indicators and an error log per snippet
* Revision history (up to 15 revisions per snippet)
* Categories and tags for organization
* Bulk actions: enable, disable, delete, export
* Import/export with safety checks and size limits
* Modern, responsive UI with dark-friendly styling
* Ko-fi support box built into the dashboard

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/snippets-bros` directory, or install it through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Snippets Bros** in the admin menu.
4. Click **Add New Snippet**, choose the type (PHP, JS, CSS, HTML), set the scope/conditions and save.
5. Use the shortcode, header/footer placement or automatic scope to run your code.

== Frequently Asked Questions ==

= Will this conflict with other snippet plugins? =

You should not use two PHP snippet plugins to run the same code. It is safe to keep other plugins installed, but avoid enabling duplicate snippets in more than one plugin at the same time.

= Can I break my site with a PHP snippet? =

Snippets Bros validates code and has a Safe Mode toggle plus an error log, but it is still possible to create fatal errors. Always test new snippets carefully and enable Safe Mode if you need to quickly stop all execution.

= Does it work with block themes and page builders? =

Yes. Snippets run at WordPress level and work with classic themes, block themes and page builders.

= Can I export snippets from one site and import into another? =

Yes, use the built-in export tool to generate a JSON file and import it on another site. The plugin checks the file type, size and content before importing.

== Screenshots ==

1. Main snippet management interface.
2. Snippet editor with code highlighting and scope options.
3. Condition settings with URL patterns, user and device rules.
4. Import/Export tools with safety checks.
5. Error log with visual indicators and Safe Mode.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Multiple URL patterns per snippet (one per line).
* Enhanced validation and sanitization for PHP, JS, CSS and HTML snippets.
* Safe Mode switch and visual error indicators.
* Import/export with size limits and security checks.
* Revision history (15 revisions per snippet), bulk actions and shortcode support.
* Modern admin interface with Ko-fi support panel.

== Upgrade Notice ==

= 1.0.0 =
First release of Snippets Bros.
