<?php
/**
 * Featured Image Post Component
 *
 * Owns featured image prompt resolution, generation/selection (AI prompt,
 * Unsplash, Media Library), attachment application, and AI Edit regeneration.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Featured_Image_Post_Component
 */
class AIPS_Featured_Image_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_Image_Service|null
	 */
	private $image_service;

	/**
	 * @var AIPS_Post_Manager|null
	 */
	private $post_manager;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null     $generator     Optional generator instance.
	 * @param AIPS_Image_Service|object|null $image_service Optional image service (or compatible double).
	 * @param AIPS_Post_Manager|object|null  $post_manager  Optional post manager (or compatible double).
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		$image_service = null,
		$post_manager = null
	) {
		parent::__construct($generator);
		$this->image_service = $image_service;
		$this->post_manager  = $post_manager;
	}

	/**
	 * Resolve image service lazily.
	 *
	 * @return AIPS_Image_Service
	 */
	private function get_image_service() {
		if ($this->image_service === null) {
			$this->image_service = new AIPS_Image_Service(new AIPS_AI_Service());
		}

		return $this->image_service;
	}

	/**
	 * Resolve post manager lazily.
	 *
	 * @return AIPS_Post_Manager
	 */
	private function get_post_manager() {
		if ($this->post_manager === null) {
			$this->post_manager = new AIPS_Post_Manager();
		}

		return $this->post_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'featured_image';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Featured Image', 'ai-post-scheduler');
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_stage(): string {
		return self::STAGE_MEDIA;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled(AIPS_Generation_Context $context): bool {
		return (bool) $context->should_generate_featured_image();
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_required(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function participates_in_metadata_turn(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_metadata_turn_schema(): array {
		return array(
			'image_prompt' => array(
				'type'        => 'string',
				'description' => 'Resolved visual prompt for generating the featured image.',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return (string) $this->get_generator()->process_featured_image_prompt($context, '', '');
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!$this->is_enabled($context)) {
			return null;
		}

		$post_id = isset($options['post_id']) ? absint($options['post_id']) : 0;
		$title   = isset($options['title']) ? (string) $options['title'] : '';
		$content = isset($options['content']) ? (string) $options['content'] : '';
		$precomputed_prompt = array_key_exists('precomputed_image_prompt', $options) ? $options['precomputed_image_prompt'] : null;

		$component_success = false;
		$attachment_id = $this->get_generator()->resolve_and_attach_featured_image(
			$context,
			$post_id,
			$title,
			$component_success,
			$content,
			$precomputed_prompt
		);

		return array(
			'attachment_id' => $attachment_id ? absint($attachment_id) : 0,
			'success'       => (bool) $component_success,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function regenerate($target_id, AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, string $directive = '', array $options = array()) {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return new WP_Error('missing_post_id', __('Post ID is required.', 'ai-post-scheduler'));
		}

		$title = isset($options['current_title']) ? (string) $options['current_title'] : '';
		if ($title === '') {
			$title = (string) get_the_title($post_id);
		}

		$current_content = isset($options['current_content']) && is_string($options['current_content'])
			? $options['current_content']
			: (string) get_post_field('post_content', $post_id);

		$history_container      = isset($options['history_container']) && $options['history_container'] instanceof AIPS_History_Container ? $options['history_container'] : null;
		$image_generation_start = microtime(true);

		$processed_image_prompt = $this->get_generator()->process_featured_image_prompt($context, $current_content, $title);
		$directive = trim($directive);
		if ($directive !== '') {
			$processed_image_prompt .= ', ' . $directive;
		}

		if (empty($processed_image_prompt)) {
			if ($history_container) {
				$history_container->record(
					'metric_generation_result',
					'Featured image regeneration metric snapshot',
					array(
						'outcome'          => 'failed',
						'duration_seconds' => (int) round(microtime(true) - $image_generation_start),
						'image_attempted'  => true,
						'image_success'    => false,
					)
				);
			}

			return new WP_Error('no_image_prompt', __('No image prompt available for this context.', 'ai-post-scheduler'));
		}

		if ($history_container) {
			$history_container->record(
				'ai_request',
				'Requesting AI image generation for featured_image',
				array(
					'prompt' => $processed_image_prompt,
					'title'  => $title,
				),
				null,
				array('component' => 'featured_image', 'post_id' => $post_id)
			);
		}

		$attachment_id = $this->get_image_service()->generate_and_upload_featured_image($processed_image_prompt, $title);

		if (is_wp_error($attachment_id)) {
			if ($history_container) {
				$history_container->record_error($attachment_id->get_error_message(), array(
					'component' => 'featured_image',
					'post_id'   => $post_id,
				));

				$history_container->record(
					'metric_generation_result',
					'Featured image regeneration metric snapshot',
					array(
						'outcome'          => 'failed',
						'duration_seconds' => (int) round(microtime(true) - $image_generation_start),
						'image_attempted'  => true,
						'image_success'    => false,
					)
				);
			}

			return $attachment_id;
		}

		$url = (string) wp_get_attachment_url($attachment_id);

		if ($history_container) {
			$history_container->record(
				'ai_response',
				'AI image generation successful for featured_image',
				null,
				array(
					'attachment_id' => $attachment_id,
					'url'           => $url,
				),
				array('component' => 'featured_image', 'post_id' => $post_id)
			);

			$history_container->record(
				'metric_generation_result',
				'Featured image regeneration metric snapshot',
				array(
					'outcome'          => 'completed',
					'duration_seconds' => (int) round(microtime(true) - $image_generation_start),
					'image_attempted'  => true,
					'image_success'    => true,
				)
			);
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $url,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$attachment_id = $this->sanitize_value($value);
		if ($attachment_id <= 0) {
			return false;
		}

		// Parity with the classic AIPS_AI_Edit_Controller::validate_featured_image_attachment()
		// guard: only a real image attachment may become the thumbnail.
		if ('attachment' !== get_post_type($attachment_id)
			|| 0 !== strpos((string) get_post_mime_type($attachment_id), 'image/')) {
			return false;
		}

		return (bool) $this->get_post_manager()->set_featured_image($post_id, $attachment_id);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return false;
		}

		return absint(get_post_thumbnail_id($post_id)) > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (is_array($value) && isset($value['attachment_id'])) {
			return absint($value['attachment_id']);
		}

		return absint($value);
	}
}
