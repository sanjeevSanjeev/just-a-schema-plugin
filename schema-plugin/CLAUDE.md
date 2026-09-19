# CLAUDE.md — Build Instructions for Schema Plugin

You are building a WordPress plugin from scratch. This file is your complete spec.
Work through it top to bottom. Do not stop to ask clarifying questions unless you
hit a genuine ambiguity not covered below — the architecture, data model, and
scope have already been fully decided. Build autonomously.

## Project identity

- Plugin name: `schema-plugin` (working name — do not bikeshed on branding)
- Text domain: `schema-plugin`
- Purpose: personal-use WordPress plugin for flexible JSON-LD structured data,
  built to fix specific documented gaps in Rank Math/Yoast (see Section 1).
- Single WordPress install target for MVP. Not building for public distribution,
  not building for multisite compatibility beyond the deferred Network Identity
  feature (out of scope for MVP — see Section 8).

## Architecture: PHP backend + React admin (decided, not up for reconsideration)

- **PHP** owns: all data storage, the JSON-LD Graph Builder, the frontend
  `wp_head` output, REST API endpoints, all validation/sanitization.
- **React** (via `@wordpress/scripts` and `@wordpress/element`) owns: the setup
  wizard, the Gutenberg post-editor sidebar panel, the Default Rules manager UI.
- React NEVER renders on the public-facing site. Only inside wp-admin. The live
  site's `<script type="application/ld+json">` output is pure PHP, zero JS
  dependency, rendered in `wp_head`.
- All React ↔ PHP communication goes through the REST API namespace
  `schema-plugin/v1` (see Section 6). React must never independently replicate
  the Graph Builder's resolution logic in JS — it always calls the `/preview`
  endpoint and renders whatever PHP returns, so preview and live output can
  never drift out of sync.

## Tooling

```
npm install @wordpress/scripts --save-dev
```

Use `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`,
`@wordpress/plugins`, `@wordpress/edit-post`. Do not add React from npm
directly — use WordPress's bundled version via these packages to avoid version
conflicts with WP core's own React instance.

Build scripts in `package.json`:
```json
"scripts": {
  "build": "wp-scripts build",
  "start": "wp-scripts start"
}
```

---

## Section 1: Problem statement (why fields/behaviors exist the way they do)

Seven original complaints this plugin fixes. Solving #2, #3, #5, #7 is REQUIRED
for MVP. #1, #4, #6 are deferred (Section 8) but the architecture must not
block them being added later.

1. No flexibility for subdirectory/subdomain sites (no shared identity across
   installs) — DEFERRED to Network Identity, v1.1+.
2. No way to manually add schema types the plugin's UI doesn't expose
   (e.g. CollectionPage) — SOLVED via raw JSON-LD escape hatch, must exist at
   every tier of every editor.
3. Changing a global/type selection wipes previously entered field data —
   SOLVED via non-destructive schema blocks (see Section 3, `legacy_fields`).
4. No flexibility for software/digital goods sites — PARTIAL, covered by
   generic schema.org type search fallback, no dedicated field verification
   in MVP.
5. Fields are filled in with no visibility into what they produce — SOLVED via
   mandatory live JSON-LD preview on every field-entry surface.
6. No way to connect related schemas (Product ↔ Review) — DEFERRED to
   `LinkedContentEntity` engine, v1.1+, but the entity `@id` system in MVP
   must support future cross-references without a data migration.
7. No way to edit default schema templates without code — SOLVED via the
   Default Rules engine being fully data-driven (stored config, editable in
   admin UI), never hardcoded PHP.

---

## Section 2: Locked type list (DO NOT add, remove, or reorder without explicit instruction)

Five types, exactly these five, in this order, for the MVP wizard:

```php
$locked_types = [
  'individual' => [
    'label' => 'Individual',
    'description' => 'Personal site, portfolio, freelancer, blogger',
    'schema_type' => 'Person',
    'google_status' => 'blue', // Structured Data Only — see Section 7
  ],
  'local_business' => [
    'label' => 'Local or Small Business',
    'description' => 'Shop, agency, clinic, restaurant, service provider',
    'schema_type' => 'LocalBusiness',
    'google_status' => 'green', // Google Rich Result eligible
  ],
  'online_store' => [
    'label' => 'Online Store',
    'description' => 'Sells products online',
    'schema_type' => 'Organization',
    'flavor' => 'commerce',
    'google_status' => 'green',
  ],
  'general_business' => [
    'label' => 'General Business / Company',
    'description' => 'Not local — SaaS, agency, corporate site',
    'schema_type' => 'Organization',
    'google_status' => 'green',
  ],
  'blog_publication' => [
    'label' => 'Blog or Publication',
    'description' => 'Multi-author blog, magazine, news site',
    'schema_type' => 'Organization',
    'flavor' => 'publisher',
    'google_status' => 'green',
  ],
];
```

A "Show more" option must exist below these five, opening a searchable list of
the full schema.org Organization/Person type hierarchy. Selecting from "Show
more" stores the literal schema.org type string chosen; it does not need a
`google_status` badge in MVP (default to 'white' / Schema.org Valid, no
verification needed).

### Composition-based suggestion (wizard Step 1 only)

Read-only checks against `active_plugins()` — no external calls, no content
scanning. Show a dismissible suggestion chip above the type list if a strong
signal exists:

```php
$composition_signals = [
  'woocommerce/woocommerce.php' => 'online_store',
  'easy-digital-downloads/easy-digital-downloads.php' => 'online_store',
];
```

Chip text: `"We noticed {plugin} is active — this is often a {suggested type}
site. [Use this suggestion] [Dismiss]"`. Never auto-select. Never block
manual choice.

### Post-selection disambiguation nudge

After a type is selected, if a conflicting signal exists, show a non-blocking
inline warning (does not prevent clicking Continue):

```php
$disambiguation_rules = [
  'individual' => [
    // if these plugins are active AND 'individual' was chosen, show nudge
    'triggers' => ['learndash/learndash.php', 'sfwd-lms/sfwd_lms.php', 'lifterlms/lifterlms.php'],
    'message' => 'This site sells courses. Some educators prefer "Local or Small
      Business" or "Online Store" — it unlocks pricing and course-listing
      fields. You can stay on "Individual" if you\'re the product yourself
      (e.g. a personal coach).',
    'suggest' => 'local_business',
  ],
];
```

---

## Section 3: Data model

### 3.1 Entity Registry (sitewide singleton — Organization/Person + WebSite)

Stored in `wp_options`:

```php
'schema_plugin_entity_type'   // one of the 5 locked keys, or a custom schema.org type string
'schema_plugin_entity_flavor' // 'commerce' | 'publisher' | null
'schema_plugin_entity_fields' // flat assoc array, keyed by schema.org property name
                               // e.g. ['name' => '...', 'address' => [...], 'sameAs' => [...]]
'schema_plugin_entity_legacy_fields' // fields entered under a previous type
                                       // selection, preserved but not rendered
                                       // in the active field set — NEVER auto-deleted
'schema_plugin_website_enabled_search' // bool — SearchAction eligibility
```

`@id` generation is deterministic, never user-input for the base entity:
```
Organization/Person @id = home_url('/#organization') or home_url('/#person')
WebSite @id            = home_url('/#website')
```

### 3.2 Per-post schema blocks (non-destructive, this is the fix for complaint #3)

Stored in post meta, one key per post:

```php
'_schema_plugin_blocks' => [
  [
    'block_id' => 'blk_' . wp_generate_password(8, false), // stable, never reused
    'schema_type' => 'Article', // current active type
    'fields' => [ /* current field values, keyed by schema.org property */ ],
    'legacy_fields' => [ /* fields from a prior type selection on this same
                            block, preserved, not shown by default */ ],
    'raw_override' => null, // if set (string, raw JSON-LD), this wins over
                             // `fields` entirely for this block
  ],
];
```

RULE: switching `schema_type` on an existing block MUST move any fields not
valid for the new type into `legacy_fields` rather than deleting them. Fields
valid under both old and new type carry over directly. Never destructive.

If `_schema_plugin_blocks` is empty/absent for a post, the post has NO
explicit override — render falls through to Default Rules (Section 4).

### 3.3 LinkedContentEntity (scaffolded already, do not redesign — extend only if MVP work touches it)

The following PHP classes already exist and should be treated as correct,
finished interfaces for MVP purposes (Services/Team/Locations/Events managers
themselves are DEFERRED — see Section 8 — but do not delete or contradict
these files if present in the repo):

- `includes/class-linked-content-entity.php`
- `includes/class-entity-resolver.php`
- `includes/class-graph-builder.php`

If these files are not present, recreate them exactly per the interface below
before building anything that depends on them:

```php
class Schema_Linked_Content_Entity {
  public string $id;
  public string $schema_type;
  public array $fields = [];
  public string $link_mode = 'manual'; // 'manual' | 'auto'
  public ?int $linked_post_id = null;
  public ?string $auto_link_source = null; // e.g. 'post_author'
  public ?int $wp_user_id = null;
  public string $parent_relationship;
  public array $legacy_fields = [];

  public function get_id_uri(): string { /* see below */ }
  public function has_linked_page(): bool { /* see below */ }
}
```

`get_id_uri()`: if `link_mode === 'manual'` and `linked_post_id` is set, use
`trailingslashit(get_permalink($linked_post_id)) . '#' . strtolower($this->schema_type)`.
If `link_mode === 'auto'` and `auto_link_source === 'post_author'`, use
`trailingslashit(get_author_posts_url($this->wp_user_id)) . '#person'`.
Otherwise fall back to `home_url('/#' . strtolower($this->schema_type) . '-' . $this->id)`.

`Schema_Entity_Resolver::emits_on($entity, $post_id)`: for `manual` mode,
return `$entity->linked_post_id === $post_id`. For `auto` mode with
`post_author` source, return whether `get_post_field('post_author', $post_id)`
matches `$entity->wp_user_id`.

MVP does NOT need a UI for creating these entities. Just keep the classes
present and correct so v1.1 can build managers against them without a data
migration.

### 3.4 Default Rules (editable templates — fix for complaint #7)

Stored as an ordered array in `wp_options`:

```php
'schema_plugin_default_rules' => [
  [
    'id' => 'rule_...',
    'priority' => 0, // lower = evaluated first, first match wins
    'match' => [
      'type' => 'url_pattern' | 'post_type' | 'post_type_taxonomy' | 'post_id' | 'catch_all',
      'value' => '/blog/*', // shape depends on type — see below
    ],
    'schema_type' => 'BlogPosting',
    'template' => [
      // property => token string, resolved at render time from live WP data
      'headline' => '{{post_title}}',
      'image' => '{{featured_image}}',
      'author' => ['@id' => '{{post_author_id}}'],
      'datePublished' => '{{post_date}}',
      'publisher' => ['@id' => '{{organization_id}}'],
    ],
  ],
  // ... a final catch_all rule MUST always exist, defaulting to WebPage
];
```

`match.value` shapes:
- `url_pattern`: string with `*` wildcard, matched against the request path.
- `post_type`: WP post type slug string.
- `post_type_taxonomy`: `['post_type' => '...', 'taxonomy' => '...', 'term' => '...']`.
- `post_id`: integer.
- `catch_all`: value ignored, always matches.

Token resolution supported in MVP: `{{post_title}}`, `{{post_excerpt}}`,
`{{featured_image}}`, `{{post_author_id}}`, `{{post_date}}`,
`{{post_modified}}`, `{{permalink}}`, `{{organization_id}}` (resolves to the
sitewide Organization/Person `@id`).

Seed each of the 5 locked types with a sensible starting rule set on plugin
activation (see Section 9, activation hook) — reuse the rule sets already
worked out per type below.

**Individual default rules:**
```php
[
  ['match' => ['type' => 'post_type', 'value' => 'post'], 'schema_type' => 'BlogPosting'],
  ['match' => ['type' => 'catch_all'], 'schema_type' => 'WebPage'],
]
```

**Local or Small Business default rules:**
```php
[
  ['match' => ['type' => 'post_id', 'value' => '{{homepage_id}}'], 'schema_type' => 'LocalBusiness'],
  ['match' => ['type' => 'url_pattern', 'value' => '/services/*'], 'schema_type' => 'Service'],
  ['match' => ['type' => 'post_type', 'value' => 'post'], 'schema_type' => 'Article'],
  ['match' => ['type' => 'catch_all'], 'schema_type' => 'WebPage'],
]
```

**Online Store default rules:**
```php
[
  ['match' => ['type' => 'post_type', 'value' => 'product'], 'schema_type' => 'Product'],
  ['match' => ['type' => 'url_pattern', 'value' => '/product-category/*'], 'schema_type' => 'CollectionPage'],
  ['match' => ['type' => 'post_type', 'value' => 'post'], 'schema_type' => 'BlogPosting'],
  ['match' => ['type' => 'catch_all'], 'schema_type' => 'WebPage'],
]
```

**General Business default rules:**
```php
[
  ['match' => ['type' => 'url_pattern', 'value' => '/services/*'], 'schema_type' => 'Service'],
  ['match' => ['type' => 'post_type', 'value' => 'post'], 'schema_type' => 'Article'],
  ['match' => ['type' => 'post_id', 'value' => '{{homepage_id}}'], 'schema_type' => 'WebPage'],
  ['match' => ['type' => 'catch_all'], 'schema_type' => 'WebPage'],
]
```

**Blog/Publication default rules:**
```php
[
  ['match' => ['type' => 'post_type', 'value' => 'post'], 'schema_type' => 'BlogPosting'],
  ['match' => ['type' => 'url_pattern', 'value' => '/category/*'], 'schema_type' => 'CollectionPage'],
  ['match' => ['type' => 'catch_all'], 'schema_type' => 'WebPage'],
]
```

### 3.5 Exclusions (fixed system list + editable backend list)

```php
// Fixed, hardcoded, never shown as editable UI:
is_admin() || wp_doing_ajax() || wp_doing_cron()
  || (defined('REST_REQUEST') && REST_REQUEST)
  || is_feed() || is_robots() || is_trackback();

// User-editable, stored in wp_options:
'schema_plugin_backend_exclusions' => [
  ['pattern' => '/my-account/*', 'enabled' => true, 'source' => 'default'],
  ['pattern' => '/cart/*', 'enabled' => true, 'source' => 'default'],
  ['pattern' => '/checkout/*', 'enabled' => true, 'source' => 'default'],
],
'schema_plugin_exclude_search' => true,
'schema_plugin_exclude_404' => true,
'schema_plugin_exclude_noindex' => true,
```

Auto-suggest (not auto-add) `/my-account/*` and `/cart/*` if WooCommerce is
active, `/checkout/*` similarly. These become part of the default list seeded
on activation if WooCommerce is detected at that time; otherwise the list
starts with just the three generic defaults shown above regardless.

`is_marked_noindex()` should be implemented as an `apply_filters()` call
(`schema_plugin_page_is_noindex`, default `false`) so it can integrate with
Yoast/RankMath's noindex meta later without a hard dependency on either
plugin now.

---

## Section 4: Graph Builder — render order (do not change this sequence)

On `wp_head`, build the `@graph` array in exactly this order:

1. Check system exclusions (Section 3.5, fixed list) → if excluded, output nothing, stop.
2. Check backend exclusions (Section 3.5, editable list) + search/404/noindex toggles → if excluded, output nothing, stop.
3. Add the sitewide Organization/Person node (Section 3.1).
4. Add the sitewide WebSite node, including `SearchAction` if enabled.
5. Resolve the page's primary node:
   a. If `_schema_plugin_blocks` has an entry with no `raw_override`, render its `fields`.
   b. If a block has `raw_override` set, parse and use that JSON directly, skip `fields` entirely.
   c. If no blocks exist for this post, resolve via Default Rules (Section 3.4), first matching rule wins, apply its `template` with token resolution.
6. Apply the `schema_plugin_graph` filter (`apply_filters('schema_plugin_graph', $graph, $post_id)`) as the final step, always — this is the extension point for anything not covered by the UI.
7. Output as a single `<script type="application/ld+json">` block, `wp_json_encode()` with `JSON_UNESCAPED_SLASHES`.

---

## Section 5: Google recognition badges (informational only, never gates functionality)

Three states. Apply at minimum to the 5 locked types plus the two explicitly
known-deprecated types:

```php
$type_status = [
  'Person' => ['google_rich_result' => false, 'geo_relevant' => true], // blue
  'LocalBusiness' => ['google_rich_result' => true], // green
  'Organization' => ['google_rich_result' => true], // green
  'FAQPage' => [
    'google_rich_result' => false,
    'google_rich_result_note' => 'Retired May 2026',
    'geo_relevant' => true,
  ], // blue
  'HowTo' => [
    'google_rich_result' => false,
    'google_rich_result_note' => 'Deprecated 2023',
    'geo_relevant' => true,
  ], // blue
];
```

Badge display: green if `google_rich_result === true`. blue if false but
`geo_relevant === true`. white/gray otherwise (default for anything not in
this table — do not block or warn, just show the neutral badge).

Badges are shown in: the wizard type picker (Section 2), and the per-post
schema type picker (Section 6, post editor panel). Do NOT build badge display
into Default Rules UI or any deferred (Section 8) feature — out of scope for MVP.

Never let a badge prevent a selection. It is always informational, always
paired with the reason (e.g. the retirement note above) when not green.

---

## Section 6: REST API (namespace `schema-plugin/v1`)

```
GET    /entity                    -> current Organization/Person data + type
POST   /entity                    -> save Organization/Person data

GET    /default-rules             -> ordered rule list
POST   /default-rules             -> save full ordered rule list (whole-list replace is fine for MVP)

GET    /posts/{id}/schema         -> { source: 'default'|'custom', blocks: [...], resolved_preview: {...} }
POST   /posts/{id}/schema         -> save/update _schema_plugin_blocks for a post
DELETE /posts/{id}/schema         -> clear _schema_plugin_blocks (revert to default)

POST   /preview                   -> body: { entity_type, fields, schema_type }
                                      returns resolved JSON-LD for arbitrary
                                      draft data WITHOUT saving anything --
                                      used by both the wizard and the post
                                      editor panel for live preview
```

All routes require `edit_posts` capability at minimum; entity/default-rules
routes require `manage_options`. Use WP's REST nonce
(`wp_localize_script` or `@wordpress/api-fetch`'s built-in nonce middleware --
do not hand-roll auth).

---

## Section 7: React admin surfaces (MVP only — build in this order)

1. **Post editor sidebar panel** (`@wordpress/plugins` + `@wordpress/edit-post`)
   registered under a "Schema" panel in the Gutenberg sidebar. Shows:
   - If no blocks exist for this post: "Using site default" state, calls
     `/preview` with the resolved Default Rule data, renders read-only
     JSON-LD, with buttons "Customize schema for this page" and "Edit the
     default rule" (the latter can just be a link to the Default Rules admin
     page for MVP, doesn't need to deep-link to the specific rule).
   - If blocks exist: "Customized for this page" state, structured field
     form + raw JSON-LD textarea (raw wins if non-empty), live preview via
     `/preview`, and a "Revert to site default" button that calls
     `DELETE /posts/{id}/schema`.

2. **Setup wizard** (standalone admin page, not Gutenberg-embedded):
   - Step 1: type picker (5 types + Show More) with inline field reveal,
     composition suggestion chip, disambiguation nudge, Google badge —
     all as specified in Section 2.
   - Step 2: Default Rules review/edit (reuse the Default Rules manager
     component built in item 3 below, just embedded in the wizard).
   - Step 3: Review & Finish. Include a collapsed, visually de-emphasized
     link/section for "Advanced: Network Identity" that for MVP just says
     "Coming soon" or links to a stub settings page — do not build the full
     Network Identity feature (Section 8), just leave the entry point
     present and clearly non-blocking.
   - On finish: redirect to a simple static post-wizard landing page
     (Section 7, item 4).

3. **Default Rules manager** (standalone admin page under Settings):
   drag-and-drop reorderable list (a simple library like `@dnd-kit/core` or
   even basic up/down buttons is acceptable for MVP — full drag-and-drop is
   a nice-to-have, not blocking), add/edit/delete rule, each rule shows its
   `match` condition and resulting `schema_type` + template fields.

4. **Entity settings page** (standalone admin page under Settings, e.g.
   Settings → Schema → Entity): the SAME field form used in wizard Step 1
   (type picker + Tier 1/1.5/2/3 fields + live preview), but reachable at any
   time after setup completes, not just during onboarding. This is required
   for MVP — without it, updating the Organization/Person data after initial
   setup has no UI path. Reuse the same React components and the same
   `/entity` REST endpoint the wizard uses; do not build a second, separate
   implementation of this form.

5. **Post-wizard landing screen**: can be a plain PHP-rendered admin page for
   MVP (does not need to be a React component) — static content is
   explicitly acceptable per the scope doc. Show: what's live (Organization
   entity, default rules active, WebSite entity), and links to Settings →
   Default Rules and Settings → Entity (item 4 above). No dynamic per-type
   content required for MVP. Only shown the FIRST time the wizard completes
   (see item 6 below for re-run behavior).

6. **Re-run setup wizard**: accessible from both the Entity Settings page
   (item 4) and the post-wizard landing screen (item 5) via a clearly labeled
   link/button — "Re-run setup wizard". This is NOT a reset. Re-running the
   wizard must:
   - Pre-fill Step 1 (type + fields) with the CURRENT saved entity data, not
     blank fields. If the user changes the type, apply the same non-destructive
     `legacy_fields` handling used for per-post blocks (Section 3.2) — fields
     not valid under the new type move to `schema_plugin_entity_legacy_fields`,
     never deleted.
   - Pre-fill Step 2 (Default Rules) with the CURRENT saved rule list, not the
     type's stock seed rules. Only offer to reset to stock defaults via an
     explicit, separate, clearly-labeled action ("Reset to default rules for
     this type") — never as an automatic side effect of re-running the wizard.
   - NEVER touch per-post schema blocks (`_schema_plugin_blocks`) on any post.
     Re-running the wizard only affects the sitewide entity and the default
     rule list — it must not iterate over or modify existing posts' explicit
     overrides.
   - On finish, return to the Entity Settings page (item 4) rather than the
     first-run landing screen (item 5) — the landing screen's "first run"
     framing ("Schema activated for...") is not appropriate language for a
     re-run and should not be shown again once the wizard has been completed
     once (track via `schema_plugin_wizard_completed_at`, already in Section
     9's bootstrap notes — only show the first-run landing screen the first
     time this timestamp is set).

Build a shared `LivePreview` React component (`src/shared/LivePreview.js`)
used by both the post editor panel and the wizard — it takes draft field
data as props, calls `/preview`, and renders the returned JSON-LD in a
syntax-highlighted (or just monospace `<pre>`, syntax highlighting is not
required for MVP) read-only block. Build this before either the wizard or
the post editor panel, since both depend on it.

---

## Section 8: Explicitly OUT of scope for MVP — do not build

Do not spend time on any of the following. Leave clear `// TODO(v1.1):` comments
where the architecture anticipates them, but do not implement:

- Services/Team/Locations/Events managers (the UI layer for `LinkedContentEntity`)
- Author auto-link system
- Network Identity (multisite `@id` linking) — UI entry point only, no logic
- Completeness Dashboard / scoring
- Field-group verification against Google docs for any type other than
  LocalBusiness (already verified — see Section 3.4's rule sets and the
  field list below)
- Badge system applied anywhere outside the wizard type picker and per-post
  type picker

### LocalBusiness Tier 1.5 fields (the one type with full Google-doc verification — implement these fields specifically for this type in the entity/per-post field forms)

```php
'local_business_fields' => [
  'address' => ['required' => true, 'type' => 'PostalAddress'],
  'telephone' => ['required' => false, 'type' => 'text'],
  'priceRange' => ['required' => false, 'type' => 'select', 'options' => ['$','$$','$$$','$$$$']],
  'openingHoursSpecification' => ['required' => false, 'type' => 'repeatable_hours'],
  'geo' => ['required' => false, 'type' => 'lat_long'],
  'department' => ['required' => false, 'type' => 'text'],
  // conditional -- show with an inline note that this is only for sites
  // that aggregate reviews of OTHER businesses, not self-testimonials:
  'aggregateRating' => ['required' => false, 'type' => 'aggregate_rating', 'condition_note' => true],
  'review' => ['required' => false, 'type' => 'review', 'condition_note' => true],
],
```

For the other 4 types, use reasonable minimal field sets (name, description,
sameAs from Tier 1, plus 2-3 obviously relevant fields per type — e.g.
`foundingDate`/`founder` for General Business) without treating them as final
or fully researched. Comment them as `// TODO(v1.1): verify against Google docs`.

---

## Section 9: Plugin bootstrap requirements

- Standard plugin header, activation hook that seeds default rules per type
  (Section 3.4) ONLY if no rules exist yet (never overwrite existing user
  config on reactivation).
- Deactivation hook: do not delete any data (options/post meta persist).
- Uninstall (`uninstall.php`): prompt-free full cleanup of all
  `schema_plugin_*` options and `_schema_plugin_blocks` post meta — only runs
  on actual uninstall, never on deactivation.
- Enqueue React build output only on relevant wp-admin screens (wizard page,
  Default Rules page, post editor) — never load the admin JS bundle on the
  public-facing site.

---

## Section 10.5: Input validation & output integrity (GIGO prevention — REQUIRED, not optional)

This section is required for MVP, not a nice-to-have. Implement all four layers:

### A. Sanitize on save, every field, no exceptions
- URL-typed fields (`url`, `logo`, `image`, `sameAs` entries, any `@id` a user
  can manually edit) → `esc_url_raw()`.
- Plain text fields → `sanitize_text_field()`; textareas/descriptions →
  `sanitize_textarea_field()`.
- The raw JSON-LD override field → attempt `json_decode()` on save; if it
  fails, reject the save with a clear error, do not store invalid JSON.
- Numeric fields (lat/long, price) → `floatval()`/`absint()` as appropriate,
  never trust client-side validation alone.

### B. Output-time escaping — SECURITY REQUIREMENT, not a data-quality nicety
`wp_json_encode()` does NOT prevent a field value containing the literal
string `</script>` from breaking out of the `<script type="application/ld+json">`
tag. Before echoing the final payload in `Schema_Graph_Builder::output()`,
apply a `</` → `<\/` replacement (or equivalent) to the encoded JSON string.
This must be present for MVP — treat as a blocking requirement, not deferred.

```php
$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
$json = str_replace( '</', '<\/', $json ); // prevent script-tag breakout
echo '<script type="application/ld+json">' . $json . '</script>';
```

### C. Pre-save/preview linting (non-blocking, informational — consistent with the
badge system's "inform, never silently omit" principle used elsewhere)

Before saving a block or entity, and every time `/preview` is called, run a
lightweight check against the field definitions already in the spec
(Section 8's `local_business_fields` `required` flags, and equivalent flags
on other types' minimal field sets) and surface warnings in the UI:

- Required field (per the type's field table) is empty → warning, not a
  save-blocker: `"Google requires 'address' for LocalBusiness — this page
  won't be rich-result eligible until it's filled in."`
- A token (`{{featured_image}}`, etc.) resolved to an empty string at
  preview/render time → omit that property from the output entirely rather
  than emitting an empty string value. An absent property is valid schema;
  an empty-string property often is not.
- A URL-typed field's saved value fails `filter_var($value, FILTER_VALIDATE_URL)`
  → warning shown next to the field, do not block save (a user may be
  mid-edit or using a relative URL intentionally).

### D. Known-misuse guardrail (non-blocking warning)
If `aggregateRating` or `review` is being set directly on the site's own
Organization/LocalBusiness entity (not on a linked/child entity), show the
existing UI note about this being intended for sites that aggregate reviews
of other businesses, not self-testimonials. Do not block saving — this is
guidance, not enforcement, consistent with the plugin's non-blocking
philosophy throughout.

### Add to Definition of Done (Section 10):
- [ ] A field value containing `</script>` cannot break out of the JSON-LD
      script tag (test this explicitly)
- [ ] Saving invalid JSON in the raw override field is rejected with a clear error
- [ ] An empty token resolution omits the property rather than emitting an
      empty string
- [ ] Required-field warnings appear in the live preview for LocalBusiness
      when `address` is empty
- [ ] Shared field components (`<UrlField>`, `<PhoneField>`, `<DateField>`,
      `<SelectField>`) are used consistently in both the wizard and the post
      editor panel — no duplicated, inconsistent field implementations
- [ ] All validation states are conveyed with text, not color alone
- [ ] Organization/Person entity data can be viewed and edited after initial
      setup via a dedicated Settings page — not only during the wizard
- [ ] Re-running the setup wizard pre-fills existing data (does not start
      blank), and does not delete or reset per-post schema blocks or
      customized Default Rules
- [ ] All icon-only controls (e.g. `sameAs` platform icons) have `aria-label`s
- [ ] All interactive elements are keyboard-navigable with visible focus states
- [ ] All user-facing strings are wrapped for translation (`__()`/`_e()` in
      PHP, `@wordpress/i18n`'s `__()` in React) under the `schema-plugin`
      text domain
- [ ] Entity Settings page collapses from two-column to single-column at the
      782px breakpoint, with the preview panel moving below the form
- [ ] Default Rules Manager rows collapse from horizontal to stacked-card
      layout at the 782px breakpoint
- [ ] The 8px spacing scale (Section 10.8) is used consistently — no ad-hoc
      margin/padding values scattered across components

---

## Section 10.6: UI/UX standards (REQUIRED — applies to every field, every surface)

These are not cosmetic preferences — inconsistent or poorly-typed UI is a
primary source of the GIGO problems Section 10.5 defends against. A
well-typed, well-defaulted field prevents bad data before validation ever
has to catch it.

### A. Shared field components — build once, reuse everywhere
Build a small set of shared React field components and use them in the
wizard, the post editor panel, and every future settings screen. Do not let
each surface implement its own version of a URL input, date input, etc. —
inconsistent validation across surfaces erodes user trust in validation
generally.

Minimum required shared components:
- `<UrlField>` — `type="url"`, inline `filter_var`-equivalent client-side
  check, shows a red-outline + text error (never color alone) on invalid format
- `<PhoneField>` — `type="tel"`, format hint placeholder (e.g. `+1 555-123-4567`)
- `<DateField>` — native date picker, ISO 8601 output
- `<SelectField>` — used for any property with a bounded set of valid values
  (`priceRange`, country, currency) instead of free text
- `<SameAsBuilder>` — the icon-grid + fallback URL list component (already
  speced conceptually in the project's design notes) — reused identically
  in the wizard and any future entity-editing surface

### B. Prefer constrained input over free text wherever schema.org defines a
bounded value set
Examples: `priceRange` is a 4-value select, not a text field. `addressCountry`
is a country dropdown, not typed text. `eventAttendanceMode` (if/when Events
is built in v1.1) is a select, not free text. Every free-text field is a GIGO
surface; every constrained field eliminates a category of bad input entirely.

### C. Smart defaults, never blank-by-default when a correct default exists
- Organization/entity `url` field pre-fills with `home_url()`.
- `inLanguage` pre-fills from the WP site's configured locale.
- Currency (Online Store flavor) pre-fills from WP's locale-implied currency
  if determinable, otherwise leave blank rather than guessing wrong.

### D. Inline, real-time validation — never only on-submit
Validation feedback appears as the user types/blurs the field, not only
after clicking Save. Save must remain enabled even with validation warnings
present (never trap the user or block their work) — the live preview
(Section 10.5.C) is the authoritative signal for "this won't produce valid
rich results," not a blocked save button.

### E. Accessibility requirement for all validation states
Every validation state (error, warning, required-but-empty) must be
conveyed via text, not color alone. A red border with no accompanying text
message is not acceptable — screen readers and colorblind users must get
the same information sighted users do.

### F. Progressive disclosure (already required by Section 2/7 wizard flow)
Confirming explicitly here: Tier 1.5 fields must not render until a type is
selected. This is both a UX clarity requirement and a GIGO reducer — a field
the user doesn't understand the purpose of is a field likely to be filled
with garbage or left in a misleading half-complete state.

---

## Section 10.7: Design framework, coding standards & compatibility targets

These are baseline engineering standards, not style preferences — apply them
throughout, not just at the end.

### A. Visual design system
Use `@wordpress/components` exclusively for all admin UI chrome (buttons,
panels, notices, toggles, dropdowns, cards). Do not hand-roll custom CSS for
things `@wordpress/components` already provides — this guarantees the plugin
looks and behaves like native wp-admin rather than a bolted-on third-party
UI, and it's the same component set Gutenberg itself uses, so it stays
visually consistent with the post editor sidebar panel specifically.
Custom CSS is acceptable only for layout/spacing not covered by the
component library (e.g. the wizard's step layout, the `sameAs` icon grid).

### B. Accessibility target: WCAG 2.1 AA, minimum, for all admin screens
- All interactive elements keyboard-navigable (tab order, enter/space
  activation) — do not rely on click-only handlers.
- Icon-only buttons/controls (e.g. each platform icon in the `sameAs`
  builder) require an `aria-label` describing the action, not just a visual icon.
- Visible focus states on all interactive elements — do not suppress default
  browser focus outlines without providing a replacement.
- Validation/error states: text-based, not color-alone (already required by
  Section 10.6.E — restated here as part of the same accessibility baseline).
- Form fields have properly associated `<label>` elements, not placeholder
  text used as the only label.

### C. PHP coding standard
Follow WordPress PHP Coding Standards (WPCS). If time allows, install and run
`phpcs` with the `WordPress` ruleset (`wp-coding-standards/wpcs` via
Composer) against all PHP files before considering the build complete. At
minimum, without tooling: WP naming conventions (`snake_case` functions,
`Prefixed_Class_Names` in PascalCase with underscores as already used in the
scaffolded classes), Yoda conditions not required but tabs-for-indentation
and WP docblock conventions (`/** @param ... @return ... */`) should be
followed consistently with the existing scaffolded files.

### D. JS/React coding standard
Use the ESLint and Prettier configuration bundled with `@wordpress/scripts`
(`@wordpress/eslint-plugin`) rather than a custom config. Run
`wp-scripts lint-js` as part of the build process. Do not introduce a
different formatting style than what `@wordpress/scripts` defaults enforce.

### E. Internationalization (i18n) — required even for single-site personal use
Every user-facing string in both PHP and React must be wrapped for
translation: PHP via `__()`/`_e()`/`esc_html__()` with text domain
`schema-plugin`; React via `@wordpress/i18n`'s `__()`. This is required
regardless of whether translation is ever actually used — it's a WordPress
plugin convention baseline, and skipping it now makes retrofitting painful
later for no real time savings today.

### F. Compatibility targets
- Minimum WordPress version: 6.5 (required for the Gutenberg sidebar APIs
  and current `@wordpress/scripts` compatibility).
- Minimum PHP version: 8.0 (enables constructor property promotion, typed
  properties as already used in the scaffolded `Schema_Linked_Content_Entity`
  class — do not write code requiring PHP 7 compatibility).
- Browser support: current evergreen browsers only (Chrome, Firefox, Safari,
  Edge, last 2 versions) — matches WordPress core's own admin support
  matrix. Do not add polyfills or fallbacks for legacy browsers (e.g. IE11).

---

## Section 10.8: Layout & grid specifications (per-screen — required)

Do not use one generic layout template across all admin screens. Each screen
has a different task shape (linear onboarding vs. ongoing comparison vs.
row-based data vs. constrained sidebar), and the layout must match the task.

### Spacing scale (use consistently everywhere — do not invent ad-hoc values)
Base unit: 8px. All margins/padding/gaps use multiples of this:
`4px` (tight, e.g. icon-to-label), `8px` (default field gap), `16px`
(between field groups), `24px` (card/section internal padding), `32px`
(between major sections), `48px` (top-level page padding).

### A. Setup Wizard
- Single column, horizontally centered, **max-width: 720px**.
- Step indicator ("Step 1 of 3") at the top, full width of the 720px column.
- Fields stack vertically, full width of the column, 16px gap between fields,
  32px gap between field groups (e.g. Tier 1 block vs. Tier 1.5 block).
- Live JSON-LD preview renders **below** the field group, inside a
  collapsible `<details>`-style panel, collapsed by default with a "Show
  live preview" toggle — do NOT place it side-by-side in a second column at
  this width; 720px is too narrow to show both without cramping either.
- Navigation buttons at the bottom: "Back" left-aligned, "Continue"/"Activate
  Schema" right-aligned, same row, 48px top margin separating them from the
  last field group.

### B. Entity Settings page (post-wizard, ongoing edit screen)
- Two-column layout at viewport widths ≥ 1200px: **left column 60% width**
  (the field form), **right column 40% width** (live preview, `position:
  sticky` so it stays visible while scrolling a long form).
- Below 1200px (including the standard WP admin collapse point at 782px):
  collapse to single column, preview moves below the form (same
  collapsible-panel treatment as the wizard, Section A).
- Field groups use the same vertical stacking/spacing rules as the wizard
  (16px/32px), just within the narrower left column rather than full width.

### C. Default Rules Manager
- Full-width single column, max-width 960px, centered.
- Each rule renders as a **horizontal row**: drag handle (left) → condition
  type dropdown → condition value input → arrow/label ("→") → resulting
  schema type dropdown → row actions (Edit/Delete, right-aligned).
- Row height consistent, 8px vertical padding, 16px gap between rows.
- "Add rule" button top-right, above the list, not inline with existing rows.
- Below 782px: each row collapses from one horizontal line into a stacked
  mini-card (drag handle top, then condition, then result, then actions
  stacked vertically) — do not attempt to horizontally scroll a row-based
  layout on narrow viewports.

### D. Post Editor sidebar panel (Gutenberg)
- Fixed single column — Gutenberg's own sidebar sets the available width
  (~280px); do not attempt a two-column layout here under any viewport.
- Fields stack vertically, 8px gap (tighter than other screens, since space
  is constrained) between related fields, 16px between field groups.
- Live preview always renders below the fields in a collapsible panel
  (same pattern as the wizard) — collapsed by default to keep the sidebar
  scannable, since the sidebar already competes for space with Gutenberg's
  own native panels (Categories, Tags, Featured Image, etc.).

### E. Post-wizard Landing Screen
- Single column, centered, max-width 720px (matches the wizard for visual
  continuity between the two consecutive screens).
- Recommendation items (Section 7 item 5's cards) stack vertically as
  full-width cards, 16px gap between cards, each card using 24px internal
  padding.

### F. Responsive breakpoint (single source of truth)
Use **782px** as the one collapse breakpoint across all screens — this
matches WordPress admin's own built-in responsive breakpoint (where the WP
admin sidebar itself collapses), so the plugin's layout shifts in sync with
wp-admin's native behavior rather than introducing a second, conflicting
breakpoint.

---

## Section 10: Definition of done for MVP

Build until all of these are true, then stop and report status rather than
continuing into Section 8's deferred items:

- [ ] Activating the plugin seeds default rules for whichever type is later
      selected in the wizard (or a sensible neutral default before wizard completion)
- [ ] Wizard completes end to end, all 5 types selectable, fields save correctly
- [ ] A post with no explicit schema block renders correct JSON-LD via Default
      Rules on the frontend, verifiable by viewing page source
- [ ] Adding a per-post override in the Gutenberg sidebar changes frontend
      output, and reverting removes the override cleanly
- [ ] Switching a per-post block's schema type does not delete previously
      entered field values (verify by switching back and forth)
- [ ] Raw JSON-LD entered in a per-post block overrides structured fields entirely
- [ ] Excluded pages (cart/checkout/account, if WooCommerce present; always
      wp-admin/feeds/etc.) emit zero schema
- [ ] Output validates in Google's Rich Results Test for at least the
      LocalBusiness and Individual (Person) types
- [ ] `/preview` endpoint is used by both the wizard and post editor — no
      duplicated resolution logic in JS

Report back with a summary of what was built, any deviations from this spec
and why, and a clear list of what remains for v1.1 per Section 8.
