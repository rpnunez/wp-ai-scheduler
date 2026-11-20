<?php
/**
 * Editor Internal Link Weave Service
 *
 * Scans editor content, identifies relevant published site posts, and prompts
 * the AI Engine to naturally weave contextual internal links into the prose.
 * Delegates execution to AIPS_Internal_Links_Post_Component while preserving
 * full backward compatibility.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Editor_Link_Weave_Service
 */
class AIPS_Editor_Link_Weave_Service {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Internal_Links_Post_Component
	 */
	private $component;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null          $ai_service AI Service.
	 * @param AIPS_Internal_Links_Post_Component|null $component  Optional internal links post component.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Internal_Links_Post_Component $component = null
	) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->component  = $component ?: new AIPS_Internal_Links_Post_Component(null, $this->ai_service);
	}

	/**
	 * Fetch candidate published posts for link weaving.
	 *
	 * @param int $current_post_id Post ID being edited.
	 * @param int $limit           Maximum candidates to retrieve.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidate_posts(int $current_post_id, int $limit = 10): array {
		return $this->component->get_candidate_posts($current_post_id, $limit);
	}

	/**
	 * Weave internal links naturally into content using candidate posts.
	 *
	 * @param int    $post_id Post ID being edited.
	 * @param string $content Content snippet or paragraph.
	 * @return array|WP_Error Array with 'modified_html' and 'inserted_links', or WP_Error.
	 */
	public function weave_internal_links(int $post_id, string $content) {
		return $this->component->weave_links($post_id, $content, '', array(
			'ai_service' => $this->ai_service,
			'limit'      => 8,
		));
	}
}
