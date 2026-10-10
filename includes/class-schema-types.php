<?php
/**
 * Schema_Types
 *
 * Static config: the locked type list (CLAUDE.md Section 2), composition
 * suggestions, disambiguation nudges, Google recognition badges (Section 5),
 * field definitions (Section 8), and the per-type Default Rules seed sets
 * (Section 3.4). Everything here is deliberately data, not behavior, so it
 * stays inspectable and editable in one place.
 *
 * @package Schema_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema_Types {

	/**
	 * Locked type list -- CLAUDE.md Section 2. DO NOT add, remove, or
	 * reorder without explicit instruction.
	 */
	const LOCKED_TYPES = array(
		'individual'       => array(
			'label'         => 'Individual',
			'description'   => 'Personal site, portfolio, freelancer, blogger',
			'schema_type'   => 'Person',
			'google_status' => 'blue',
		),
		'local_business'   => array(
			'label'         => 'Local or Small Business',
			'description'   => 'Shop, agency, clinic, restaurant, service provider',
			'schema_type'   => 'LocalBusiness',
			'google_status' => 'green',
		),
		'online_store'     => array(
			'label'         => 'Online Store',
			'description'   => 'Sells products online',
			// OnlineStore (an Organization subtype) is what Google recommends
			// for ecommerce sites. Changed from Organization with the user's
			// approval, 2026-09-25; @id stays /#organization.
			'schema_type'   => 'OnlineStore',
			'flavor'        => 'commerce',
			'google_status' => 'green',
		),
		'general_business' => array(
			'label'         => 'General Business / Company',
			'description'   => 'Not local -- SaaS, agency, corporate site',
			'schema_type'   => 'Organization',
			'google_status' => 'green',
		),
		'blog_publication' => array(
			'label'         => 'Blog or Publication',
			'description'   => 'Multi-author blog, magazine, news site',
			'schema_type'   => 'Organization',
			'flavor'        => 'publisher',
			'google_status' => 'green',
		),
	);

	/** Wizard Step 1 composition-based suggestion chip -- read-only plugin checks only. */
	const COMPOSITION_SIGNALS = array(
		'woocommerce/woocommerce.php'                   => 'online_store',
		'easy-digital-downloads/easy-digital-downloads.php' => 'online_store',
	);

	/** Wizard Step 1 post-selection disambiguation nudge. */
	const DISAMBIGUATION_RULES = array(
		'individual' => array(
			'triggers' => array( 'learndash/learndash.php', 'sfwd-lms/sfwd_lms.php', 'lifterlms/lifterlms.php' ),
			'message'  => 'This site sells courses. Some educators prefer "Local or Small Business" or "Online Store" -- it unlocks pricing and course-listing fields. You can stay on "Individual" if you\'re the product yourself (e.g. a personal coach).',
			'suggest'  => 'local_business',
		),
	);

	/** Google recognition badges -- CLAUDE.md Section 5. Informational only, never gates functionality. */
	const TYPE_STATUS = array(
		'Person'        => array(
			'google_rich_result' => false,
			'geo_relevant'       => true,
		),
		'LocalBusiness' => array(
			'google_rich_result' => true,
		),
		'Organization'  => array(
			'google_rich_result' => true,
		),
		'OnlineStore'   => array(
			'google_rich_result' => true,
		),
		'FAQPage'       => array(
			'google_rich_result'      => false,
			'google_rich_result_note' => 'Retired May 2026',
			'geo_relevant'            => true,
		),
		'HowTo'         => array(
			'google_rich_result'      => false,
			'google_rich_result_note' => 'Deprecated 2023',
			'geo_relevant'            => true,
		),
	);

	/** The one type with full Google-doc field verification in MVP -- CLAUDE.md Section 8. */
	const LOCAL_BUSINESS_FIELDS = array(
		'address'                   => array(
			'required' => true,
			'type'     => 'address',
		),
		'telephone'                 => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'tel',
		),
		'priceRange'                => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'select',
			'options'     => array( '$', '$$', '$$$', '$$$$' ),
		),
		'openingHoursSpecification' => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'repeatable_hours',
		),
		'geo'                       => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'lat_long',
		),
		'department'                => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'text',
		),
		// Conditional: only for sites that aggregate reviews of OTHER
		// businesses, not self-testimonials (CLAUDE.md Section 10.5.D).
		'aggregateRating'           => array(
			'required'      => false,
			'type'          => 'aggregate_rating',
			'condition_note' => true,
		),
		'review'                    => array(
			'required'      => false,
			'type'          => 'review',
			'condition_note' => true,
		),
	);

	/** Every entity/post carries these Tier 1 fields regardless of type. */
	const TIER1_COMMON_FIELDS = array(
		'name'        => array(
			'required' => true,
			'type'     => 'text',
		),
		'url'         => array(
			'required' => false,
			'type'     => 'url',
		),
		'description' => array(
			'required' => false,
			'type'     => 'textarea',
		),
		'sameAs'      => array(
			'required' => false,
			'type'     => 'sameAs_list',
		),
	);

	/**
	 * Organization properties Google documents as recommended (it lists
	 * no required ones), shared by the three Organization-based locked
	 * types. Source: developers.google.com/search/docs/appearance/
	 * structured-data/organization (page updated 2026-09-08, checked
	 * 2026-09-25, v1.1 Phase 5).
	 *
	 * - `recommended`: documented by Google as recommended (informational).
	 * - `nudge`: also raises a soft, non-blocking lint warning when empty.
	 * - Not included: contactPoint, numberOfEmployees, hasMerchantReturnPolicy,
	 *   hasShippingService, hasMemberProgram (nested objects this form
	 *   can't edit yet -- use the raw JSON-LD / schema_plugin_graph filter),
	 *   and the rarer identifiers (duns, leiCode, iso6523Code, naics,
	 *   globalLocationNumber).
	 *
	 * Fields are only ever added to a type, never removed: the migrator
	 * treats a field missing from a type's defs as legacy and stops
	 * emitting it.
	 */
	const ORGANIZATION_RECOMMENDED_FIELDS = array(
		// Overrides Tier 1's required name: Google requires nothing on
		// Organization, so the lint must not claim it does.
		'name'          => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'text',
		),
		// Google: at least 112x112px, crawlable and indexable.
		'logo'          => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'media',
		),
		'alternateName' => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'text',
		),
		'legalName'     => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'text',
		),
		'email'         => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'email',
		),
		'telephone'     => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'tel',
		),
		'address'       => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'address',
		),
	);

	/**
	 * Per-type fields on top of Tier 1, for the four locked types other
	 * than LocalBusiness. Verified 2026-09-25 (v1.1 Phase 5):
	 *
	 * - individual (Person): Google has no Person rich result. Fields
	 *   follow ProfilePage's Person guidance (name required; image,
	 *   alternateName recommended; description and sameAs are Tier 1).
	 *   developers.google.com/search/docs/appearance/structured-data/profile-page
	 * - online_store / general_business / blog_publication: the Google
	 *   Organization recommended set above, plus per-type extras.
	 *   Blog/Publication's logo and name also serve Article `publisher`.
	 *
	 * Fields marked "schema.org only" are valid schema.org but not used by
	 * any Google feature; they stay because removing them would move saved
	 * values to legacy.
	 */
	const ENTITY_EXTRA_FIELDS = array(
		'individual'       => array(
			'image'         => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'media',
			),
			'alternateName' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'text',
			),
			// schema.org only.
			'jobTitle'      => array(
				'required' => false,
				'type'     => 'text',
			),
		),
		// Store-wide return and shipping policies: Google's documented
		// OnlineStore extras (return-policy and shipping-policy docs,
		// updated 2026-09-08). They're defaults; Merchant Center and
		// product-level markup take precedence.
		'online_store'     => self::ORGANIZATION_RECOMMENDED_FIELDS + array(
			'hasMerchantReturnPolicy' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'return_policy',
			),
			'hasShippingService'      => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'shipping_service',
			),
			'vatID' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'text',
			),
			'taxID' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'text',
			),
		),
		'general_business' => self::ORGANIZATION_RECOMMENDED_FIELDS + array(
			'foundingDate' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'date',
			),
			'vatID'        => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'text',
			),
			'taxID'        => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'text',
			),
			// schema.org only.
			'founder'      => array(
				'required' => false,
				'type'     => 'text',
			),
		),
		'blog_publication' => self::ORGANIZATION_RECOMMENDED_FIELDS + array(
			'foundingDate' => array(
				'required'    => false,
				'recommended' => true,
				'type'        => 'date',
			),
		),
	);

	/**
	 * ENTITY_EXTRA_FIELDS with translated labels, for the admin UI
	 * (constants can't call __()). TypedField shows `label` and falls back
	 * to the property name.
	 */
	public static function get_entity_extra_fields(): array {
		$labels = array(
			'name'          => __( 'Name', 'schema-plugin' ),
			'logo'          => __( 'Logo', 'schema-plugin' ),
			'image'         => __( 'Photo', 'schema-plugin' ),
			'alternateName' => __( 'Alternate name', 'schema-plugin' ),
			'legalName'     => __( 'Legal name', 'schema-plugin' ),
			'email'         => __( 'Email', 'schema-plugin' ),
			'telephone'     => __( 'Telephone', 'schema-plugin' ),
			'jobTitle'      => __( 'Job title', 'schema-plugin' ),
			'foundingDate'  => __( 'Founding date', 'schema-plugin' ),
			'founder'       => __( 'Founder', 'schema-plugin' ),
			'vatID'         => __( 'VAT ID', 'schema-plugin' ),
			'taxID'         => __( 'Tax ID', 'schema-plugin' ),
		);
		$labels['hasMerchantReturnPolicy'] = __( 'Return policy', 'schema-plugin' );
		$labels['hasShippingService']      = __( 'Shipping', 'schema-plugin' );

		$fields = self::ENTITY_EXTRA_FIELDS;
		foreach ( $fields as $type => $defs ) {
			foreach ( $defs as $key => $def ) {
				if ( isset( $labels[ $key ] ) ) {
					$fields[ $type ][ $key ]['label'] = $labels[ $key ];
				}
			}
		}
		return $fields;
	}

	/**
	 * Generic minimal field set for per-post schema blocks whose
	 * schema_type isn't LocalBusiness -- CLAUDE.md Section 8 excludes
	 * per-type field verification beyond LocalBusiness for MVP, so every
	 * other block type gets this same reasonable set plus the raw JSON-LD
	 * escape hatch (Section 1, complaint #2) for anything more specific.
	 * TODO(v1.1): verify per-type field sets against Google docs.
	 */
	const GENERIC_BLOCK_FIELDS = array(
		'headline'      => array(
			'required' => false,
			'type'     => 'text',
		),
		'image'         => array(
			'required' => false,
			'type'     => 'media',
		),
		'datePublished' => array(
			'required' => false,
			'type'     => 'date',
		),
		'dateModified'  => array(
			'required' => false,
			'type'     => 'date',
		),
	);

	/**
	 * Article and BlogPosting per-post fields. Source: Google's Article
	 * doc (developers.google.com/search/docs/appearance/structured-data/
	 * article, updated 2026-09-08, checked 2026-10-10). Google lists no
	 * required properties; headline, image, datePublished, dateModified and
	 * author are recommended. `author` and `publisher` are added as @id
	 * references by Schema_Post_Meta::build_block_node(), not edited here.
	 */
	const ARTICLE_BLOCK_FIELDS = array(
		'headline'      => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'text',
		),
		'image'         => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'media',
		),
		'datePublished' => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'date',
		),
		'dateModified'  => array(
			'required'    => false,
			'recommended' => true,
			'type'        => 'date',
		),
	);

	/**
	 * Product per-post fields. Source: Google's Product snippet doc
	 * (developers.google.com/search/docs/appearance/structured-data/
	 * product-snippet, updated 2026-09-08, checked 2026-10-10): `name` is
	 * required, and so is one of offers, review or aggregateRating. Only
	 * `offers` is editable here (`one_of` lists the alternatives the raw
	 * JSON-LD can supply instead). An Offer requires `price`; priceCurrency,
	 * availability and priceValidUntil are recommended. `image` is only
	 * recommended here: Product snippets don't need it, but Google's
	 * merchant-listing doc (checked 2026-10-10) requires name, image and
	 * offers, and a Rich Results Test of a product without one came back
	 * invalid for merchant listings.
	 */
	const PRODUCT_BLOCK_FIELDS = array(
		'image'  => array(
			'required'    => false,
			'recommended' => true,
			'nudge'       => true,
			'type'        => 'media',
		),
		'offers' => array(
			'required' => false,
			'type'     => 'offer',
			'one_of'   => array( 'offers', 'review', 'aggregateRating' ),
		),
	);

	/** Token templates shared across all seeded Default Rules -- CLAUDE.md Section 3.4. */
	const TEMPLATE_BY_SCHEMA_TYPE = array(
		'WebPage'        => array(
			'name'        => '{{post_title}}',
			'description' => '{{post_excerpt}}',
			'url'         => '{{permalink}}',
		),
		'Article'        => array(
			'headline'      => '{{post_title}}',
			'image'         => '{{featured_image}}',
			'author'        => array( '@id' => '{{post_author_id}}' ),
			'datePublished' => '{{post_date}}',
			'dateModified'  => '{{post_modified}}',
			'publisher'     => array( '@id' => '{{organization_id}}' ),
		),
		'BlogPosting'    => array(
			'headline'      => '{{post_title}}',
			'image'         => '{{featured_image}}',
			'author'        => array( '@id' => '{{post_author_id}}' ),
			'datePublished' => '{{post_date}}',
			'dateModified'  => '{{post_modified}}',
			'publisher'     => array( '@id' => '{{organization_id}}' ),
		),
		'Service'        => array(
			'name'        => '{{post_title}}',
			'description' => '{{post_excerpt}}',
			'url'         => '{{permalink}}',
			'provider'    => array( '@id' => '{{organization_id}}' ),
		),
		// offers comes from WooCommerce (see Schema_Token_Resolver); with it
		// off, or on a non-product, the Offer resolves to nothing and is
		// dropped. Google requires one of offers/review/aggregateRating.
		'Product'        => array(
			'name'        => '{{post_title}}',
			'description' => '{{post_excerpt}}',
			'image'       => '{{featured_image}}',
			'url'         => '{{permalink}}',
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => '{{product_price}}',
				'priceCurrency' => '{{product_currency}}',
				'availability'  => '{{product_availability}}',
			),
		),
		'CollectionPage' => array(
			'name'        => '{{post_title}}',
			'description' => '{{post_excerpt}}',
			'url'         => '{{permalink}}',
		),
		// Homepage rules pointing back at the sitewide entity resolve to a
		// bare @id reference into the node already emitted earlier in the
		// same @graph, rather than a duplicate node.
		'LocalBusiness'  => array(
			'@id' => '{{organization_id}}',
		),
	);

	/** CLAUDE.md Section 3.4 -- five per-type seed rule sets (match + schema_type only; templates injected from the lookup above). */
	const DEFAULT_RULE_SETS = array(
		'individual'       => array(
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'post',
				),
				'schema_type' => 'BlogPosting',
			),
			array(
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
			),
		),
		'local_business'   => array(
			array(
				'match'       => array(
					'type'  => 'post_id',
					'value' => '{{homepage_id}}',
				),
				'schema_type' => 'LocalBusiness',
			),
			array(
				'match'       => array(
					'type'  => 'url_pattern',
					'value' => '/services/*',
				),
				'schema_type' => 'Service',
			),
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'post',
				),
				'schema_type' => 'Article',
			),
			array(
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
			),
		),
		'online_store'     => array(
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'product',
				),
				'schema_type' => 'Product',
			),
			array(
				'match'       => array(
					'type'  => 'url_pattern',
					'value' => '/product-category/*',
				),
				'schema_type' => 'CollectionPage',
			),
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'post',
				),
				'schema_type' => 'BlogPosting',
			),
			array(
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
			),
		),
		'general_business' => array(
			array(
				'match'       => array(
					'type'  => 'url_pattern',
					'value' => '/services/*',
				),
				'schema_type' => 'Service',
			),
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'post',
				),
				'schema_type' => 'Article',
			),
			array(
				'match'       => array(
					'type'  => 'post_id',
					'value' => '{{homepage_id}}',
				),
				'schema_type' => 'WebPage',
			),
			array(
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
			),
		),
		'blog_publication' => array(
			array(
				'match'       => array(
					'type'  => 'post_type',
					'value' => 'post',
				),
				'schema_type' => 'BlogPosting',
			),
			array(
				'match'       => array(
					'type'  => 'url_pattern',
					'value' => '/category/*',
				),
				'schema_type' => 'CollectionPage',
			),
			array(
				'match'       => array( 'type' => 'catch_all' ),
				'schema_type' => 'WebPage',
			),
		),
	);

	/**
	 * LOCKED_TYPES stores plain literals (PHP class constants can't contain
	 * function calls), so translation wrapping happens here instead --
	 * every caller (bootstrap data, PHP-side lookups) goes through this
	 * method rather than the constant directly.
	 */
	public static function get_locked_types(): array {
		$labels = array(
			'individual'       => __( 'Individual', 'schema-plugin' ),
			'local_business'   => __( 'Local or Small Business', 'schema-plugin' ),
			'online_store'     => __( 'Online Store', 'schema-plugin' ),
			'general_business' => __( 'General Business / Company', 'schema-plugin' ),
			'blog_publication' => __( 'Blog or Publication', 'schema-plugin' ),
		);
		$descriptions = array(
			'individual'       => __( 'Personal site, portfolio, freelancer, blogger', 'schema-plugin' ),
			'local_business'   => __( 'Shop, agency, clinic, restaurant, service provider', 'schema-plugin' ),
			'online_store'     => __( 'Sells products online', 'schema-plugin' ),
			'general_business' => __( 'Not local -- SaaS, agency, corporate site', 'schema-plugin' ),
			'blog_publication' => __( 'Multi-author blog, magazine, news site', 'schema-plugin' ),
		);

		$types = self::LOCKED_TYPES;
		foreach ( $types as $key => $type ) {
			$types[ $key ]['label']       = $labels[ $key ] ?? $type['label'];
			$types[ $key ]['description'] = $descriptions[ $key ] ?? $type['description'];
		}
		return $types;
	}

	public static function get_composition_signals(): array {
		return self::COMPOSITION_SIGNALS;
	}

	/** DISAMBIGUATION_RULES stores a plain literal message; translated here for the same reason as get_locked_types(). */
	public static function get_disambiguation_rules(): array {
		$messages = array(
			'individual' => __( 'This site sells courses. Some educators prefer "Local or Small Business" or "Online Store" -- it unlocks pricing and course-listing fields. You can stay on "Individual" if you\'re the product yourself (e.g. a personal coach).', 'schema-plugin' ),
		);

		$rules = self::DISAMBIGUATION_RULES;
		foreach ( $rules as $key => $rule ) {
			$rules[ $key ]['message'] = $messages[ $key ] ?? $rule['message'];
		}
		return $rules;
	}

	/**
	 * Per-type wizard copy (CLAUDE.md doesn't specify exact wording; this
	 * personalizes headings and field labels by the chosen type so the
	 * wizard speaks in the site owner's own terms -- "Business name"
	 * rather than a generic "Name" -- everywhere EntityFields/EntityForm
	 * is used, wizard and Entity Settings alike).
	 */
	public static function get_wizard_copy(): array {
		return array(
			'individual'       => array(
				'details_heading'    => __( 'Tell us about yourself', 'schema-plugin' ),
				'name_label'         => __( 'Your name', 'schema-plugin' ),
				'description_label'  => __( 'A short bio', 'schema-plugin' ),
				'description_help'   => __( 'A sentence or two about who you are and what you do.', 'schema-plugin' ),
				'rules_intro'        => __( "Since this is a personal site, here's how your pages will be tagged by default.", 'schema-plugin' ),
				'finish_heading'     => __( "You're all set!", 'schema-plugin' ),
			),
			'local_business'   => array(
				'details_heading'    => __( 'Tell us about your business', 'schema-plugin' ),
				'name_label'         => __( 'Business name', 'schema-plugin' ),
				'description_label'  => __( 'What does your business do?', 'schema-plugin' ),
				'description_help'   => __( 'Shown to customers in search results and rich results.', 'schema-plugin' ),
				'rules_intro'        => __( "Here's how pages across your site will be tagged by default -- your homepage, service pages, and blog posts.", 'schema-plugin' ),
				'finish_heading'     => __( 'Your business is ready to be found!', 'schema-plugin' ),
			),
			'online_store'     => array(
				'details_heading'    => __( 'Tell us about your store', 'schema-plugin' ),
				'name_label'         => __( 'Store name', 'schema-plugin' ),
				'description_label'  => __( 'What do you sell?', 'schema-plugin' ),
				'description_help'   => __( 'A short summary of your store or product range.', 'schema-plugin' ),
				'rules_intro'        => __( "Here's how your products, categories, and blog posts will be tagged by default.", 'schema-plugin' ),
				'finish_heading'     => __( 'Your store is ready!', 'schema-plugin' ),
			),
			'general_business' => array(
				'details_heading'    => __( 'Tell us about your company', 'schema-plugin' ),
				'name_label'         => __( 'Company name', 'schema-plugin' ),
				'description_label'  => __( 'What does your company do?', 'schema-plugin' ),
				'description_help'   => __( 'A short summary of what your company offers.', 'schema-plugin' ),
				'rules_intro'        => __( "Here's how pages across your site will be tagged by default -- your homepage, service pages, and articles.", 'schema-plugin' ),
				'finish_heading'     => __( 'Your company is ready to be found!', 'schema-plugin' ),
			),
			'blog_publication' => array(
				'details_heading'    => __( 'Tell us about your publication', 'schema-plugin' ),
				'name_label'         => __( 'Publication name', 'schema-plugin' ),
				'description_label'  => __( 'What is your publication about?', 'schema-plugin' ),
				'description_help'   => __( 'A short summary readers and search engines will see.', 'schema-plugin' ),
				'rules_intro'        => __( "Here's how your posts and category pages will be tagged by default.", 'schema-plugin' ),
				'finish_heading'     => __( 'Your publication is ready!', 'schema-plugin' ),
			),
			'_default'         => array(
				'details_heading'   => __( 'Tell us more about your site', 'schema-plugin' ),
				'name_label'        => __( 'Name', 'schema-plugin' ),
				'description_label' => __( 'Description', 'schema-plugin' ),
				'description_help'  => __( 'A short summary shown in search results.', 'schema-plugin' ),
				'rules_intro'       => __( "Here's how pages across your site will be tagged by default.", 'schema-plugin' ),
				'finish_heading'    => __( "You're all set!", 'schema-plugin' ),
			),
		);
	}

	public static function get_type_status(): array {
		return self::TYPE_STATUS;
	}

	/**
	 * Composition-based suggestion for wizard Step 1 -- read-only checks
	 * against active_plugins(), no external calls, no content scanning.
	 */
	public static function get_composition_suggestion(): ?array {
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( self::COMPOSITION_SIGNALS as $plugin_file => $suggested_type ) {
			if ( in_array( $plugin_file, $active, true ) ) {
				return array(
					'plugin'         => $plugin_file,
					'suggested_type' => $suggested_type,
				);
			}
		}
		return null;
	}

	/** Post-selection disambiguation nudge for wizard Step 1. */
	public static function get_disambiguation_nudge( string $selected_type_key ): ?array {
		if ( ! isset( self::DISAMBIGUATION_RULES[ $selected_type_key ] ) ) {
			return null;
		}
		$rule   = self::DISAMBIGUATION_RULES[ $selected_type_key ];
		$active = (array) get_option( 'active_plugins', array() );
		foreach ( $rule['triggers'] as $plugin_file ) {
			if ( in_array( $plugin_file, $active, true ) ) {
				return array(
					'message' => $rule['message'],
					'suggest' => $rule['suggest'],
				);
			}
		}
		return null;
	}

	/**
	 * Badge for a resolved schema.org @type string -- green if
	 * google_rich_result, blue if not but geo_relevant, white otherwise.
	 * Never blocks a selection; always informational.
	 */
	public static function get_badge_for_schema_type( string $schema_type ): array {
		$entry = self::TYPE_STATUS[ $schema_type ] ?? array();

		if ( ! empty( $entry['google_rich_result'] ) ) {
			$color = 'green';
		} elseif ( ! empty( $entry['geo_relevant'] ) ) {
			$color = 'blue';
		} else {
			$color = 'white';
		}

		return array(
			'color' => $color,
			'note'  => $entry['google_rich_result_note'] ?? null,
		);
	}

	/**
	 * Field definitions for a given type identifier -- either a locked
	 * type key ('individual', 'local_business', ...) used for the
	 * sitewide entity, or a schema.org @type string used for per-post
	 * schema blocks and "Show more" custom entity types.
	 */
	public static function get_field_defs( string $type_identifier ): array {
		if ( isset( self::LOCKED_TYPES[ $type_identifier ] ) ) {
			$fields = self::TIER1_COMMON_FIELDS;
			if ( 'local_business' === $type_identifier ) {
				return array_merge( $fields, self::LOCAL_BUSINESS_FIELDS );
			}
			if ( isset( self::ENTITY_EXTRA_FIELDS[ $type_identifier ] ) ) {
				return array_merge( $fields, self::ENTITY_EXTRA_FIELDS[ $type_identifier ] );
			}
			return $fields;
		}

		if ( 'LocalBusiness' === $type_identifier ) {
			return array_merge( self::TIER1_COMMON_FIELDS, self::LOCAL_BUSINESS_FIELDS );
		}

		if ( in_array( $type_identifier, array( 'Article', 'BlogPosting' ), true ) ) {
			return array_merge( self::block_common_fields(), self::ARTICLE_BLOCK_FIELDS );
		}

		if ( 'Product' === $type_identifier ) {
			// Google requires name for Product snippets, so Tier 1's stays.
			return array_merge( self::TIER1_COMMON_FIELDS, self::PRODUCT_BLOCK_FIELDS );
		}

		return array_merge( self::block_common_fields(), self::GENERIC_BLOCK_FIELDS );
	}

	/**
	 * Tier 1 for per-post types Google has no required fields for (Article,
	 * WebPage, Service, CollectionPage...): `name` stays editable but isn't
	 * claimed as required, since the lint would otherwise tell users Google
	 * requires something it doesn't.
	 */
	private static function block_common_fields(): array {
		$fields                     = self::TIER1_COMMON_FIELDS;
		$fields['name']['required'] = false;
		return $fields;
	}

	/**
	 * Field definitions for every per-post type that has its own set, for
	 * the editor (the rest use the default). Keyed by schema.org @type.
	 */
	public static function get_block_field_defs(): array {
		return array(
			'_default'      => self::get_field_defs( 'WebPage' ),
			'Article'       => self::get_field_defs( 'Article' ),
			'BlogPosting'   => self::get_field_defs( 'BlogPosting' ),
			'Product'       => self::get_field_defs( 'Product' ),
			'LocalBusiness' => self::get_field_defs( 'LocalBusiness' ),
		);
	}

	/**
	 * Builds a ready-to-store rule set for a locked type: injects a stable
	 * id, sequential priority, and the shared template for each rule's
	 * schema_type.
	 */
	public static function get_default_rule_set( string $locked_type_key ): array {
		if ( ! isset( self::DEFAULT_RULE_SETS[ $locked_type_key ] ) ) {
			$locked_type_key = 'individual';
		}

		$rules = array();
		foreach ( self::DEFAULT_RULE_SETS[ $locked_type_key ] as $index => $rule ) {
			$rules[] = array(
				'id'          => 'rule_' . $locked_type_key . '_' . $index . '_' . strtolower( wp_generate_password( 6, false ) ),
				'priority'    => $index,
				'match'       => $rule['match'],
				'schema_type' => $rule['schema_type'],
				'template'    => self::TEMPLATE_BY_SCHEMA_TYPE[ $rule['schema_type'] ] ?? array(
					'name' => '{{post_title}}',
					'url'  => '{{permalink}}',
				),
			);
		}
		return $rules;
	}

	/**
	 * Friendly, human-labeled schema type choices for the Default Rules
	 * manager's "tag it as" dropdown -- most site owners don't know
	 * schema.org type strings by name. An "Other" escape hatch (handled
	 * in the UI, not here) still allows any literal type string, per
	 * CLAUDE.md complaint #2's "manually add schema types the UI doesn't
	 * expose" principle.
	 */
	public static function get_friendly_schema_types(): array {
		return array(
			array(
				'value' => 'Article',
				'label' => __( 'Article', 'schema-plugin' ),
			),
			array(
				'value' => 'BlogPosting',
				'label' => __( 'Blog Post', 'schema-plugin' ),
			),
			array(
				'value' => 'Product',
				'label' => __( 'Product', 'schema-plugin' ),
			),
			array(
				'value' => 'Service',
				'label' => __( 'Service', 'schema-plugin' ),
			),
			array(
				'value' => 'WebPage',
				'label' => __( 'Web Page', 'schema-plugin' ),
			),
			array(
				'value' => 'CollectionPage',
				'label' => __( 'Collection / Category Page', 'schema-plugin' ),
			),
			array(
				'value' => 'LocalBusiness',
				'label' => __( 'Local Business', 'schema-plugin' ),
			),
			array(
				'value' => 'FAQPage',
				'label' => __( 'FAQ Page', 'schema-plugin' ),
			),
			array(
				'value' => 'HowTo',
				'label' => __( 'How-To Guide', 'schema-plugin' ),
			),
		);
	}

	/**
	 * Real, registered public post types on this site (label + slug) --
	 * backs the Default Rules manager's "a specific type of content"
	 * condition so a site owner picks "Products" from a list rather than
	 * typing the slug "product" from memory.
	 */
	public static function get_post_type_options(): array {
		$options = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$options[] = array(
				'value' => $post_type->name,
				'label' => $post_type->labels->name,
			);
		}
		return $options;
	}
}
