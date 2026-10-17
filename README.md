# Schema Plugin

Flexible JSON-LD structured data for WordPress. It prints one `@graph` in `wp_head`, built from a sitewide entity, per-page overrides and editable default rules, and every screen that edits schema shows a live preview of exactly what will be output.

> **Status:** version 0.1.0, built for personal use. It is not on wordpress.org.

## What it does

- **Sitewide entity.** A `Person`, `LocalBusiness`, `OnlineStore`, `Organization` or any other schema.org type you pick, plus a `WebSite` node. Five common types are one click in the setup wizard, and "Show more types" searches the rest.
- **Default Rules.** Which schema a page gets is stored data, not code. Rules match by post type, URL pattern, taxonomy term, a specific page or "everything else", and use tokens such as `{{post_title}}`, `{{permalink}}` and `{{organization_id}}`. They are edited in plain language under Settings.
- **Per-page schema.** A Schema panel in the block editor lets you customize one page. Changing a page's schema type moves fields that no longer apply to a "legacy" store instead of deleting them, so switching back restores them.
- **Raw JSON-LD where you need it.** For any type the fields don't cover, paste JSON-LD at three levels: one page, one default rule (tokens still resolve), or the sitewide entity. Input must be valid JSON and a single object, and it is checked before it is saved.
- **Breadcrumbs.** A `BreadcrumbList` for posts, pages and archives, linked from the page's `WebPage` node. One switch under Settings → Site entity → Search appearance, and a filter for changing the trail.
- **Linked content (optional).** Services, Locations, Events and Team members, each its own node that points back at the sitewide entity by `@id`. Team members can be bound to WordPress users so they appear as post authors, and author archives become a `ProfilePage`.
- **Network identity (optional).** For subdomain, subdirectory or multisite installs: share one organization `@id` across sites, or declare a site a division of a parent brand.
- **Completeness Dashboard.** Scores the site entity and every linked entry against Google's required and recommended fields, lists what to fix (with links), and lists the pages that use raw JSON-LD.
- **Works beside other SEO plugins.** By default it switches off Yoast's and Rank Math's own JSON-LD on pages where it prints its own, so a page never carries two competing graphs. A setting keeps both if you prefer.
- **WooCommerce.** The default Product rule fills in price, currency and availability from the product.
- **Light by default.** Classes load on first use, so a front-end request loads only what building the graph needs. The REST controllers, sanitizer and admin code never load for visitors. The installable zip is about 0.14 MB. Optional features that are off add no menu items, no REST routes and no front-end work.

## Requirements

- WordPress 6.5 or newer
- PHP 8.0 or newer

## Install

**From a release zip:** upload `schema-plugin.zip` at Plugins → Add New → Upload Plugin, activate, and follow the setup wizard.

**From this repository:** the compiled admin scripts (`build/`) are not committed, so build them first:

```bash
npm ci
npm run build
```

Then copy the folder into `wp-content/plugins/schema-plugin/` and activate it. `.distignore` lists what to leave out of a distributable build (`src/`, `node_modules/`, `package*.json`, `webpack.config.js` and the dotfiles). Don't zip `node_modules/`.

## Settings at a glance

| Where | What |
|---|---|
| **Schema → Dashboard** | Health score, what needs fixing, the next suggested step, health details |
| **Schema → Settings → Site entity** | Your details for the chosen type, search appearance (sitelinks search box, breadcrumbs) and the raw JSON-LD box |
| **Settings → Default rules** | The ordered rules that decide each page's schema |
| **Settings → Exclusions** | URL patterns that get no schema (`/my-account/*`, `/cart/*` and `/checkout/*` by default) and switches for search, 404 and noindex pages |
| **Settings → Features** | Switch Services, Locations, Events, Team & authors and Network identity on or off. Turning one off never deletes its data |
| **Schema → Content** | Add and edit Services, Locations, Events and Team members |
| **Post editor → Schema panel** | Customize or write raw JSON-LD for one page, with live preview |

## Extending it

| Filter | Purpose |
|---|---|
| `schema_plugin_graph` | Change the final `@graph` array (`$graph`, `$post_id`). The supported hook for anything the UI doesn't cover. |
| `schema_plugin_breadcrumb_items` | Change the breadcrumb trail: a list of `array( 'name' => ..., 'url' => ... )` items, `url` optional. Return `array()` for none. |
| `schema_plugin_page_is_noindex` | Tell the plugin a page is noindex, so the "exclude noindex pages" setting can apply (for Yoast, Rank Math or your own logic). |

The REST API lives under `schema-plugin/v1`. Entity and rule routes need `manage_options`; per-page routes need permission to edit the post.

## How a page's graph is built

1. Skip admin, AJAX, cron, REST, feeds and robots requests, then the exclusion list.
2. Add the sitewide entity node and the `WebSite` node.
3. Add the page's own node: its Schema panel block if it has one (raw JSON-LD wins over fields), otherwise the first matching default rule.
4. Add linked Services, Locations, Events and Team entries that belong on this page, an author `Person` if the graph references one, and an author archive's `ProfilePage`.
5. Add the `BreadcrumbList`, run the `schema_plugin_graph` filter, and print one `<script type="application/ld+json">` block. A literal `</script>` inside any value is escaped so it can't break out of the tag.

The preview in every editing screen calls the same PHP builders, so what you see is what is output.

## Development

```text
schema-plugin.php     bootstrap, autoloader map, hooks
includes/             PHP: graph builder, entity registry, default rules,
                      breadcrumbs, sanitizer, REST controllers, admin, dashboard
src/                  React admin (wizard, settings, dashboard, post editor)
build/                compiled output (generated, not committed)
uninstall.php         removes every schema_plugin_* option and post/user meta
```

- `npm run build` compiles once; `npm start` rebuilds on change. Admin UI uses `@wordpress/components` and `@wordpress/scripts`.
- Code follows the WordPress coding standards. Strings use the `schema-plugin` text domain.
- Deactivating keeps all data. Deleting the plugin from the Plugins screen removes it.

## Known limits

- Raw JSON-LD is checked for shape (valid JSON, one object), not against schema.org or Google's rules.
- A post's breadcrumb uses one category: the primary category set in Yoast or Rank Math if there is one, otherwise the first.
- No Product ↔ Review linking yet, and no dedicated field set for software or digital goods beyond the generic schema.org type search.
- Not yet checked on a classic theme with WooCommerce.

## License

GPL-2.0-or-later. See https://www.gnu.org/licenses/gpl-2.0.html.
