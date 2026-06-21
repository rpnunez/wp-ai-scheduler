<?php
/**
 * Affiliate Link Inserter Service
 *
 * Injects affiliate CTA blocks into post content based on mapping configuration.
 * Optionally uses AI to find a natural in-content anchor location.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Affiliate_Link_Inserter_Service {

	/**
	 * Post meta key that records which mapping IDs have already been injected,
	 * preventing duplicate injection across generation-time and post-save paths.
	 */
	const INJECTED_META_KEY = '_aips_affiliate_injected';

	/**
	 * Placeholder substituted with the affiliate URL in cta_html templates.
	 */
	const URL_PLACEHOLDER = '{{affiliate_url}}';

	/**
	 * @var AIPS_AI_Service
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	public function __construct( $ai_service = null, $logger = null ) {
		$this->ai_service = $ai_service ?: new AIPS_AI_Service();
		$this->logger     = $logger     ?: new AIPS_Logger();
	}

	// -------------------------------------------------------------------------
	// Post-save path
	// -------------------------------------------------------------------------

	/**
	 * Inject affiliate links into an existing saved post.
	 *
	 * Reads the post content, applies all applicable mappings that have not
	 * been injected yet, then saves via wp_update_post().
	 *
	 * @param int      $post_id  WordPress post ID.
	 * @param object[] $mappings Mapping rows from AIPS_Affiliate_Links_Repository.
	 * @return void
	 */
	public function inject( $post_id, array $mappings ) {
		$post = get_post( absint( $post_id ) );

		if ( ! $post ) {
			$this->logger->log( "Affiliate link injection skipped: post {$post_id} not found.", 'warning' );
			return;
		}

		$already_injected = $this->get_injected_ids( $post_id );
		$content          = $post->post_content;
		$injected_ids     = array();

		foreach ( $mappings as $mapping ) {
			$mapping_id = (int) $mapping->id;

			if ( in_array( $mapping_id, $already_injected, true ) ) {
				continue;
			}

			$content      = $this->apply_mapping( $content, $mapping );
			$injected_ids[] = $mapping_id;
		}

		if ( empty( $injected_ids ) ) {
			return;
		}

		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $content,
		) );

		$this->record_injected_ids( $post_id, array_merge( $already_injected, $injected_ids ) );

		$this->logger->log(
			sprintf( 'Affiliate links injected into post %d: mapping IDs %s', $post_id, implode( ', ', $injected_ids ) ),
			'info'
		);
	}

	// -------------------------------------------------------------------------
	// Generation-time path
	// -------------------------------------------------------------------------

	/**
	 * Inject affiliate links into a content string before the post is saved.
	 *
	 * Returns the modified content. Duplicate tracking is not possible here
	 * (no post ID yet), so every matching mapping is applied.
	 *
	 * @param string   $content  Raw post content.
	 * @param object[] $mappings Mapping rows from AIPS_Affiliate_Links_Repository.
	 * @return string Modified content.
	 */
	public function inject_into_content( $content, array $mappings ) {
		foreach ( $mappings as $mapping ) {
			$content = $this->apply_mapping( $content, $mapping );
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Core injection logic
	// -------------------------------------------------------------------------

	/**
	 * Apply a single mapping to content: CTA block placement + optional AI injection.
	 *
	 * @param string $content Post content.
	 * @param object $mapping Mapping row.
	 * @return string Modified content.
	 */
	private function apply_mapping( $content, $mapping ) {
		$affiliate_url = (string) $mapping->affiliate_url;
		$cta_html      = (string) $mapping->cta_html;
		$max           = max( 1, (int) $mapping->cta_max_insertions );

		if ( ! empty( $cta_html ) ) {
			$rendered_cta = str_replace( self::URL_PLACEHOLDER, esc_url( $affiliate_url ), $cta_html );
			$content      = $this->insert_cta_block( $content, $rendered_cta, $mapping, $max );
		}

		if ( ! empty( $mapping->use_ai_injection ) ) {
			$content = $this->apply_ai_injection( $content, $affiliate_url, (string) $mapping->tag );
		}

		return $content;
	}

	/**
	 * Place the CTA block at the configured position within the content.
	 *
	 * @param string $content      Post content.
	 * @param string $rendered_cta Rendered CTA HTML (URL already substituted).
	 * @param object $mapping      Mapping row.
	 * @param int    $max          Maximum insertions allowed.
	 * @return string
	 */
	private function insert_cta_block( $content, $rendered_cta, $mapping, $max ) {
		$position = (string) $mapping->cta_position;

		switch ( $position ) {
			case 'prepend':
				return $rendered_cta . "\n" . $content;

			case 'after_heading':
				return $this->insert_after_heading( $content, $rendered_cta, (string) $mapping->cta_heading, $max );

			case 'after_text':
				return $this->insert_after_text( $content, $rendered_cta, (string) $mapping->cta_match_text, $max );

			case 'append':
			default:
				return $content . "\n" . $rendered_cta;
		}
	}

	/**
	 * Insert the CTA block after each occurrence of a heading (up to $max times).
	 *
	 * Heading matching is case-insensitive and works with h1–h6 tags.
	 *
	 * @param string $content       Post content.
	 * @param string $rendered_cta  CTA HTML.
	 * @param string $heading_text  Heading text to match.
	 * @param int    $max           Maximum insertions.
	 * @return string
	 */
	private function insert_after_heading( $content, $rendered_cta, $heading_text, $max ) {
		if ( empty( $heading_text ) ) {
			return $content . "\n" . $rendered_cta;
		}

		$escaped   = preg_quote( $heading_text, '/' );
		$inserted  = 0;

		$content = preg_replace_callback(
			'/<h[1-6][^>]*>' . $escaped . '<\/h[1-6]>/i',
			function ( $match ) use ( $rendered_cta, $max, &$inserted ) {
				if ( $inserted >= $max ) {
					return $match[0];
				}
				$inserted++;
				return $match[0] . "\n" . $rendered_cta;
			},
			$content
		);

		if ( 0 === $inserted ) {
			$content .= "\n" . $rendered_cta;
		}

		return $content;
	}

	/**
	 * Insert the CTA block after each occurrence of an arbitrary text snippet (up to $max times).
	 *
	 * @param string $content      Post content.
	 * @param string $rendered_cta CTA HTML.
	 * @param string $match_text   Text to search for.
	 * @param int    $max          Maximum insertions.
	 * @return string
	 */
	private function insert_after_text( $content, $rendered_cta, $match_text, $max ) {
		if ( empty( $match_text ) ) {
			return $content . "\n" . $rendered_cta;
		}

		$inserted = 0;
		$escaped  = preg_quote( $match_text, '/' );

		$content = preg_replace_callback(
			'/' . $escaped . '/i',
			function ( $match ) use ( $rendered_cta, $max, &$inserted ) {
				if ( $inserted >= $max ) {
					return $match[0];
				}
				$inserted++;
				return $match[0] . "\n" . $rendered_cta;
			},
			$content
		);

		if ( 0 === $inserted ) {
			$content .= "\n" . $rendered_cta;
		}

		return $content;
	}

	/**
	 * Use AI to identify a natural sentence in the content to anchor the affiliate URL.
	 *
	 * The AI is asked to return the exact sentence that should become an anchor and
	 * the anchor text wrapped in [[double brackets]]. On failure the content is
	 * returned unchanged.
	 *
	 * @param string $content       Post content.
	 * @param string $affiliate_url Affiliate URL.
	 * @param string $tag           Tag name (for context).
	 * @return string
	 */
	private function apply_ai_injection( $content, $affiliate_url, $tag ) {
		$prompt = sprintf(
			"You are editing a blog post. Find ONE sentence in the post content below that naturally relates to the topic \"%s\" and would benefit from an affiliate link. " .
			"Return ONLY a JSON object with two keys:\n" .
			"  \"match\": the exact sentence from the content (verbatim, no changes)\n" .
			"  \"replacement\": the same sentence with the most relevant 2-5 words wrapped in [[double square brackets]] to become the hyperlink anchor\n\n" .
			"Rules:\n" .
			"- The [[bracketed]] text must appear verbatim inside \"match\"\n" .
			"- If no suitable sentence exists, return {\"match\":\"\",\"replacement\":\"\"}\n\n" .
			"Post content:\n%s",
			esc_html( $tag ),
			wp_strip_all_tags( $content )
		);

		$response = $this->ai_service->generate_json( $prompt, array( 'maxTokens' => 500 ) );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			$this->logger->log( 'Affiliate AI injection failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'invalid response' ), 'warning' );
			return $content;
		}

		$match       = isset( $response['match'] ) ? (string) $response['match'] : '';
		$replacement = isset( $response['replacement'] ) ? (string) $response['replacement'] : '';

		if ( empty( $match ) || empty( $replacement ) ) {
			return $content;
		}

		if ( strpos( $content, $match ) === false ) {
			$this->logger->log( 'Affiliate AI injection: match sentence not found in content.', 'debug' );
			return $content;
		}

		// Build the replacement with an actual <a> tag.
		$linked = preg_replace_callback(
			'/\[\[(.*?)\]\]/s',
			function ( $m ) use ( $affiliate_url ) {
				return '<a href="' . esc_url( $affiliate_url ) . '" rel="nofollow sponsored" target="_blank">' . esc_html( $m[1] ) . '</a>';
			},
			$replacement
		);

		return str_replace( $match, $linked, $content );
	}

	// -------------------------------------------------------------------------
	// Duplicate tracking
	// -------------------------------------------------------------------------

	/**
	 * Return the list of mapping IDs already injected into a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function get_injected_ids( $post_id ) {
		$meta = get_post_meta( $post_id, self::INJECTED_META_KEY, true );
		return is_array( $meta ) ? array_map( 'intval', $meta ) : array();
	}

	/**
	 * Persist the updated list of injected mapping IDs.
	 *
	 * @param int   $post_id Post ID.
	 * @param int[] $ids     Combined list of already-injected IDs.
	 * @return void
	 */
	private function record_injected_ids( $post_id, array $ids ) {
		update_post_meta( $post_id, self::INJECTED_META_KEY, array_unique( $ids ) );
	}
}
