=== Schema Plugin ===
Contributors: sanjeevbhattarai
Tags: schema, structured data, json-ld, seo, rich results
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Flexible JSON-LD structured data with non-destructive schema blocks, editable default rules, and a raw JSON-LD escape hatch.

== Description ==

Outputs a single JSON-LD `@graph` in `wp_head` built from:

* A sitewide Organization/Person entity and WebSite node.
* Per-post schema blocks edited in the block editor sidebar. Switching a block's type keeps previously entered fields.
* Data-driven Default Rules (URL pattern, post type, taxonomy term, post ID, catch-all) for posts without their own blocks.
* A raw JSON-LD override for any type the UI doesn't cover.

Every editing screen shows a live preview rendered by the same PHP code that produces the frontend output.

== Installation ==

1. Upload the `schema-plugin` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Complete the setup wizard.

== Changelog ==

= 0.1.0 =
* Initial release.
