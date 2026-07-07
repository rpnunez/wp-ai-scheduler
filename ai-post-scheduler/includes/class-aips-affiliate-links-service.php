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
}
