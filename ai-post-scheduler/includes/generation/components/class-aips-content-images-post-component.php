<?php
/**
 * Content Images Post Component
 *
 * Owns in-article content image generation, figure markup injection,
 * preview mock card rendering, config resolution, and single/all image
 * regeneration for AI Edit.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Images_Post_Component
 */
class AIPS_Content_Images_Post_Component extends AIPS_Abstract_Post_Component {

	/**
	 * @var AIPS_History_Repository_Interface|null
	 */
	private $history_repository;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Generator|null                    $generator          Optional generator instance.
	 * @param AIPS_History_Repository_Interface|null $history_repository Optional history repository.
	 */
	public function __construct(
		?AIPS_Generator $generator = null,
		?AIPS_History_Repository_Interface $history_repository = null
	) {
		parent::__construct($generator);
		$this->history_repository = $history_repository;
	}

	/**
	 * Resolve history repository lazily.
	 *
	 * @return AIPS_History_Repository_Interface
	 */
	private function get_history_repository(): AIPS_History_Repository_Interface {
		if ($this->history_repository === null) {
			$this->history_repository = new AIPS_History_Repository();
		}

		return $this->history_repository;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_key(): string {
		return 'post_images';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __('Post Images', 'ai-post-scheduler');
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
		return (bool) $context->has_content_images();
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
			'content_image_prompts' => array(
				'type'        => 'object',
				'description' => 'Map of content image placeholder index to visual image generation prompt.',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function build_prompt(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null): string {
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(AIPS_Generation_Context $context, ?AIPS_AI_Conversation $session = null, array $options = array()) {
		if (!$this->is_enabled($context)) {
			return array(
				'content' => isset($options['content']) ? (string) $options['content'] : '',
				'success' => true,
			);
		}

		$post_id             = isset($options['post_id']) ? absint($options['post_id']) : 0;
		$title               = isset($options['title']) ? (string) $options['title'] : '';
		$content             = isset($options['content']) ? (string) $options['content'] : '';
		$precomputed_prompts = isset($options['precomputed_prompts']) && is_array($options['precomputed_prompts'])
			? $options['precomputed_prompts']
			: array();

		return $this->get_generator()->generate_and_inject_content_images(
			$context,
			$post_id,
			$title,
			$content,
			$precomputed_prompts
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

		$generator         = $this->get_generator();
		$history_id        = isset($options['history_id']) ? absint($options['history_id']) : 0;
		$image_id          = isset($options['image_id']) ? $options['image_id'] : 'all';
		$title             = isset($options['current_title']) ? (string) $options['current_title'] : (string) get_the_title($post_id);
		$content           = isset($options['current_content']) ? (string) $options['current_content'] : (string) get_post_field('post_content', $post_id);
		$history_container = isset($options['history_container']) && $options['history_container'] instanceof AIPS_History_Container ? $options['history_container'] : null;

		$generated_repo     = new AIPS_Post_Images_Generated_Repository();
		$existing_generated = $generated_repo->get_for_post_history($post_id, $history_id);

		$images_to_regenerate = array();
		if ($image_id === 'all') {
			$images_to_regenerate = $existing_generated;
		} else {
			$target_row_id = absint($image_id);
			foreach ($existing_generated as $eg) {
				if ((int) $eg->id === $target_row_id && (int) $eg->post_id === $post_id) {
					$images_to_regenerate[] = $eg;
					break;
				}
			}
		}

		// Fallback: a post generated before images were configured has no rows yet.
		if ($image_id === 'all' && empty($images_to_regenerate) && $context->has_content_images()) {
			$config_images = $context->get_content_images();
			foreach ($config_images as $idx => $ci) {
				$new_gen_id = $generated_repo->create(array(
					'post_id'         => $post_id,
					'history_id'      => $history_id,
					'config_image_id' => !empty($ci->id) ? (int) $ci->id : 0,
					'image_index'     => !empty($ci->placeholder_index) ? (int) $ci->placeholder_index : ($idx + 1),
					'attachment_id'   => 0,
					'image_source'    => !empty($ci->source) ? $ci->source : 'ai_prompt',
					'image_prompt'    => !empty($ci->image_prompt) ? $ci->image_prompt : '',
					'image_url'       => '',
					'placement'       => !empty($ci->placement) ? $ci->placement : 'auto',
					'status'          => 'pending',
				));
				$row = $generated_repo->get_by_id($new_gen_id);
				if ($row) {
					$images_to_regenerate[] = $row;
				}
			}
		}

		if (empty($images_to_regenerate)) {
			return new WP_Error('no_images', __('No post content images found to regenerate.', 'ai-post-scheduler'));
		}

		$total_images        = max(count($existing_generated), count($images_to_regenerate));
		$regenerated_results = array();
		$source_content      = $content;
		$directive           = trim($directive);

		foreach ($images_to_regenerate as $target_row) {
			$gen_id        = (int) $target_row->id;
			$order         = (int) $target_row->image_index;
			$component_key = 'post_image_' . $gen_id;
			$config        = $this->resolve_post_image_config($target_row, $context);

			if ($directive !== '' && (!empty($config->image_prompt) || empty($config->source) || $config->source === 'ai_prompt')) {
				$config = clone $config;
				$base_prompt = !empty($config->image_prompt) ? (string) $config->image_prompt : '';
				$config->image_prompt = $base_prompt !== '' ? ($base_prompt . ', ' . $directive) : $directive;
			}

			$current_url = !empty($target_row->image_url) ? (string) $target_row->image_url : '';
			if ('' === $current_url && (int) $target_row->attachment_id > 0) {
				$current_url = (string) wp_get_attachment_url((int) $target_row->attachment_id);
			}

			if ((int) $target_row->attachment_id > 0 || '' !== $current_url) {
				$snapshot = array(
					'id'            => $gen_id,
					'image_index'   => $order,
					'attachment_id' => (int) $target_row->attachment_id,
					'url'           => $current_url,
				);
				if (!$this->post_image_revision_exists($post_id, $component_key, (int) $target_row->attachment_id)) {
					$this->capture_image_revision($post_id, $history_id, $component_key, $snapshot, 'persisted', 'pre_regenerate_current');
				}
			}

			$resolved = $generator->resolve_post_image($config, $title, $context->get_topic());

			if ($resolved['attachment_id'] <= 0) {
				$has_previous_image = (int) $target_row->attachment_id > 0;
				$status             = $has_previous_image ? 'completed' : 'failed';

				if ($history_container) {
					$history_container->record_error($resolved['error'], array(
						'component' => 'post_images',
						'image_id'  => $gen_id,
						'post_id'   => $post_id,
					));
				}

				$regenerated_results[] = array(
					'id'            => $gen_id,
					'image_index'   => $order,
					'status'        => $status,
					'error_message' => $resolved['error'],
				);
				continue;
			}

			$new_markup = $generator->build_post_image_markup(
				$gen_id,
				$resolved['attachment_id'],
				$resolved['image_url'],
				$title,
				!empty($config->caption) ? $config->caption : ''
			);
			$isolated = $this->replace_post_image_markup($source_content, $target_row, $new_markup, $total_images);
			$content  = $this->replace_post_image_markup($content, $target_row, $new_markup, $total_images);

			$image_value = array(
				'id'            => $gen_id,
				'image_index'   => $order,
				'attachment_id' => $resolved['attachment_id'],
				'url'           => $resolved['image_url'],
			);

			if ($history_container) {
				$history_container->record(
					'ai_response',
					sprintf('AI image generation successful for %s', $component_key),
					null,
					$image_value,
					array('component' => $component_key, 'post_id' => $post_id, 'source' => 'ai_generated')
				);
			}

			$regenerated_results[] = array(
				'id'            => $gen_id,
				'image_index'   => $order,
				'attachment_id' => $resolved['attachment_id'],
				'url'           => $resolved['image_url'],
				'status'        => 'completed',
				'error_message' => '',
				'markup'        => $new_markup,
				'content'       => $isolated,
			);
		}

		return array(
			'post_images'    => $regenerated_results,
			'content'        => $content,
			'source_content' => $source_content,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function apply_to_post(int $post_id, $value): bool {
		if ($post_id <= 0) {
			return false;
		}

		$content = is_array($value) && isset($value['content']) ? (string) $value['content'] : (is_string($value) ? $value : '');
		if ($content === '') {
			return false;
		}

		$updated = wp_update_post(array(
			'ID'           => $post_id,
			'post_content' => wp_slash($content),
		), true);

		return !is_wp_error($updated) && (int) $updated === $post_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function reconcile_status($target_id): bool {
		$post_id = absint($target_id);
		if ($post_id <= 0) {
			return false;
		}

		$generated_images = (new AIPS_Post_Images_Generated_Repository())->get_by_post_id($post_id);
		if (empty($generated_images)) {
			return true;
		}

		return !in_array('failed', wp_list_pluck($generated_images, 'status'), true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function sanitize_value($value) {
		if (is_array($value)) {
			return $value;
		}

		return is_string($value) ? wp_kses_post($value) : array();
	}

	/**
	 * Replace one image figure or placeholder comment, or inject it when absent.
	 *
	 * @param string $content      Post content.
	 * @param object $row          Generated image row.
	 * @param string $markup       Replacement figure markup.
	 * @param int    $total_images Total images being distributed.
	 * @return string
	 */
	public function replace_post_image_markup(string $content, $row, string $markup, int $total_images): string {
		$gen_id          = (int) $row->id;
		$order           = (int) $row->image_index;
		$placement       = !empty($row->placement) ? $row->placement : 'auto';
		$figure_pattern  = AIPS_Post_Images_Service::get_figure_pattern($gen_id);
		$comment_pattern = '/<!--\s*aips-image-placeholder\s+id=["\']?' . preg_quote((string) $gen_id, '/') . '["\']?\s*-->/i';
		$replace_fn      = static function () use ($markup) {
			return $markup;
		};

		if (preg_match($figure_pattern, $content)) {
			return (string) preg_replace_callback($figure_pattern, $replace_fn, $content, 1);
		}

		if (preg_match($comment_pattern, $content)) {
			return (string) preg_replace_callback($comment_pattern, $replace_fn, $content, 1);
		}

		return $this->get_generator()->inject_single_post_image($content, $order, $placement, $markup, $total_images);
	}

	/**
	 * Resolve the template/author image configuration behind a generated image row.
	 *
	 * @param object                  $row                Generated image row.
	 * @param AIPS_Generation_Context $generation_context Generation context.
	 * @return object
	 */
	public function resolve_post_image_config($row, AIPS_Generation_Context $generation_context) {
		if (!empty($row->config_image_id)) {
			$config = (new AIPS_Post_Images_Repository())->get_by_id((int) $row->config_image_id);
			if ($config) {
				return $config;
			}
		}

		$indexed = AIPS_Post_Images_Service::with_placeholder_indexes($generation_context->get_content_images());
		foreach ($indexed as $image) {
			if ((int) $image->placeholder_index === (int) $row->image_index) {
				return $image;
			}
		}
		foreach ($indexed as $image) {
			$image_order = isset($image->image_order) ? (int) $image->image_order : 0;
			if ($image_order > 0 && $image_order === (int) $row->image_index) {
				return $image;
			}
		}

		return (object) array(
			'source'            => $row->image_source,
			'image_prompt'      => $row->image_prompt,
			'unsplash_keywords' => $row->image_prompt,
			'media_ids'         => '',
			'placement'         => $row->placement,
			'caption'           => '',
		);
	}

	/**
	 * Check whether a post image attachment is already stored as a revision.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $component_key post_image_{id} key.
	 * @param int    $attachment_id Attachment ID.
	 * @return bool
	 */
	private function post_image_revision_exists(int $post_id, string $component_key, int $attachment_id): bool {
		$revisions = $this->get_history_repository()->get_component_revisions($post_id, $component_key, 10);
		foreach ($revisions as $revision) {
			$value       = isset($revision['value']) ? $revision['value'] : null;
			$existing_id = is_array($value) && isset($value['attachment_id']) ? (int) $value['attachment_id'] : 0;
			if ($existing_id === $attachment_id) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Capture a pre-regeneration revision snapshot for a content image.
	 *
	 * @param int    $post_id    Post ID.
	 * @param int    $history_id History ID.
	 * @param string $component  Component key.
	 * @param mixed  $value      Snapshot payload.
	 * @param string $source     Source tag.
	 * @param string $reason     Reason tag.
	 * @return void
	 */
	private function capture_image_revision(int $post_id, int $history_id, string $component, $value, string $source, string $reason): void {
		$history_container = AIPS_History_Container::resolve_existing($this->get_history_repository(), $post_id, $history_id);
		if (is_wp_error($history_container)) {
			return;
		}

		$history_container->record(
			'ai_response',
			sprintf('Captured revision snapshot for %s before regeneration', $component),
			null,
			$value,
			array(
				'component' => $component,
				'post_id'   => $post_id,
				'source'    => $source,
				'reason'    => $reason,
			)
		);
	}
}
