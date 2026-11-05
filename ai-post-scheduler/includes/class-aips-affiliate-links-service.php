<?php
/**
 * Affiliate Links Service
 *
 * Resolves which affiliate link mappings apply to a given post based on its
 * tags, and delegates content injection to AIPS_Affiliate_Link_Inserter_Service.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Affiliate_Links_Service {

	/**
	 * @var AIPS_Affiliate_Links_Repository
	 */
	private $repo;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	public function __construct( $repo = null, $logger = null ) {
		$this->repo   = $repo   ?: new AIPS_Affiliate_Links_Repository();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	/**
	 * Return enabled affiliate link mappings whose tag matches any tag on the post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return object[] Array of mapping rows from AIPS_Affiliate_Links_Repository.
	 */
	public function get_applicable_mappings( $post_id ) {
		$terms = get_the_tags( absint( $post_id ) );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$tag_names = wp_list_pluck( $terms, 'name' );

		return $this->repo->get_enabled_by_tags( $tag_names );
	}

	/**
	 * Inject affiliate links into a post based on its tags.
	 *
	 * Delegates to AIPS_Affiliate_Link_Inserter_Service. This is the post-save
	 * path triggered by the aips_post_generated hook.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return void
	 */
	public function inject_for_post( $post_id ) {
		$mappings = $this->get_applicable_mappings( $post_id );

		if ( empty( $mappings ) ) {
			return;
		}

		$inserter = new AIPS_Affiliate_Link_Inserter_Service();
		$inserter->inject( $post_id, $mappings );
	}

	/**
	 * Inject affiliate links into a content string (generation-time path).
	 *
	 * Called from AIPS_Generator when affiliate_links_enabled is true on the
	 * context. Returns the modified content string.
	 *
	 * @param string   $content  Post content.
	 * @param string[] $tag_names Array of tag name strings derived from the generation context.
	 * @return string Modified content.
	 */
	public function inject_into_content( $content, array $tag_names ) {
		if ( empty( $tag_names ) ) {
			return $content;
		}

		$mappings = $this->repo->get_enabled_by_tags( $tag_names );

		if ( empty( $mappings ) ) {
			return $content;
		}

		$inserter = new AIPS_Affiliate_Link_Inserter_Service();
		return $inserter->inject_into_content( $content, $mappings );
	}

	/**
	 * Resolve applicable affiliate offers for a given generation context.
	 *
	 * Checks pinned author/template overrides first, then multi-factor matching
	 * (topic keywords, categories, tags), and finally global fallback offers.
	 *
	 * @param object|AIPS_Generation_Context $context Generation context.
	 * @param string|null                    $topic   Optional topic string.
	 * @return object[] Applicable mapping rows.
	 */
	public function resolve_offers_for_context( $context, $topic = null ) {
		if ( ! ( $context instanceof AIPS_Generation_Context ) ) {
			return array();
		}

		if ( ! $context->get_affiliate_links_enabled() ) {
			return array();
		}

		$max_links = max( 1, (int) AIPS_Config::get_instance()->get_option( 'aips_affiliate_max_links_per_post', 1 ) );

		// 1. Check for pinned override on Author or Template.
		$pinned_id = absint( $context->get_affiliate_link_override_id() );

		if ( $pinned_id > 0 ) {
			$pinned = $this->repo->get_by_id( $pinned_id );
			if ( $pinned && ! empty( $pinned->enabled ) ) {
				return array( $pinned );
			}
		}

		// 2. Multi-factor matching: Topic / Keywords / Category / Tags.
		$eval_topic = ! empty( $topic ) ? $topic : (string) $context->get_topic();
		$raw_tags   = (string) $context->get_post_tags();
		$tags       = ! empty( $raw_tags ) ? array_filter( array_map( 'trim', explode( ',', $raw_tags ) ) ) : array();
		$category   = $context->get_post_category();

		$matched = $this->repo->find_matching_offers( $eval_topic, $category, $tags );

		if ( ! empty( $matched ) ) {
			return array_slice( $matched, 0, $max_links );
		}

		// 3. Fallback to global fallback offers if configured.
		$fallbacks = $this->repo->get_enabled_fallbacks();
		if ( ! empty( $fallbacks ) ) {
			return array_slice( $fallbacks, 0, $max_links );
		}

		return array();
	}

	/**
	 * Format prompt instructions for generation-time affiliate linking.
	 *
	 * @param object[] $offers Applicable affiliate offer rows.
	 * @return string Prompt block text or empty string.
	 */
	public function format_prompt_instructions( array $offers ) {
		if ( empty( $offers ) ) {
			return '';
		}

		$lines = array(
			'Affiliate Linking Requirement:',
			'You MUST naturally reference and hyperlink the following recommendation within the post content at least once where it is organically relevant and helpful to the reader:',
		);

		foreach ( $offers as $offer ) {
			$label    = ! empty( $offer->label ) ? $offer->label : $offer->tag;
			$url      = $offer->affiliate_url;
			$guidance = ! empty( $offer->prompt_guidance ) ? $offer->prompt_guidance : "Provide a natural endorsement for {$label}.";

			$lines[] = sprintf( '- Recommended Resource: %s', esc_html( $label ) );
			$lines[] = sprintf( '- Target URL: %s', esc_url( $url ) );
			$lines[] = sprintf( '- Guidance: %s', esc_html( $guidance ) );
			$lines[] = sprintf(
				'- Format Requirement: Embed using natural contextual anchor text (e.g. brand name or descriptive action phrase, NEVER generic "click here"). Format as: <a href="%s" rel="nofollow sponsored" target="_blank">{Contextual Anchor}</a> or Markdown [{Contextual Anchor}](%s).',
				esc_url( $url ),
				esc_url( $url )
			);
		}

		return implode( "\n", $lines );
	}
}
