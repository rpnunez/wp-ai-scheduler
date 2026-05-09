<?php
/**
 * Prompt Builder Diversity Injector
 *
 * Builds shared prompt blocks that steer the AI away from reusing recently
 * generated titles and topic ideas.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Diversity_Injector
 */
class AIPS_Prompt_Builder_Diversity_Injector {

	/**
	 * Default number of recent titles to include in diversity blocks.
	 */
	const DEFAULT_TITLE_LIMIT = 12;

	/**
	 * Number of random bytes used for the uniqueness seed.
	 */
	const UNIQUENESS_SEED_BYTES = 4;

	/**
	 * @var AIPS_History_Repository|null
	 */
	private $history_repository;

	/**
	 * @var AIPS_Author_Topics_Repository|null
	 */
	private $author_topics_repository;

	/**
	 * @var AIPS_Post_Slices_Repository|null
	 */
	private $post_slices_repository;

	/**
	 * @param AIPS_History_Repository|null       $history_repository History repository.
	 * @param AIPS_Author_Topics_Repository|null $author_topics_repository Author topics repository.
	 * @param AIPS_Post_Slices_Repository|null   $post_slices_repository Post slices repository.
	 */
	public function __construct($history_repository = null, $author_topics_repository = null, $post_slices_repository = null) {
		$this->history_repository = $history_repository ?: new AIPS_History_Repository();
		$this->author_topics_repository = $author_topics_repository ?: new AIPS_Author_Topics_Repository();
		$this->post_slices_repository = $post_slices_repository;
	}

	/**
	 * Build an avoid-titles block for post-generation prompts.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return string
	 */
	public function build_avoid_titles_block($subject) {
		$limit = $this->get_limit($subject);
		$titles = array();

		if ($subject instanceof AIPS_Template_Context) {
			$titles = $this->get_recent_post_titles(
				array(
					'template_id' => (int) $subject->get_id(),
					'status'      => 'completed',
					'per_page'    => $limit,
					'fields'      => 'list',
					'orderby'     => 'completed_at',
					'order'       => 'DESC',
				)
			);
		} elseif ($subject instanceof AIPS_Topic_Context) {
			$author = $subject->get_author();
			if ($author && isset($author->id)) {
				$titles = $this->get_recent_post_titles(
					array(
						'author_id' => (int) $author->id,
						'status'    => 'completed',
						'per_page'  => $limit,
						'fields'    => 'list',
						'orderby'   => 'completed_at',
						'order'     => 'DESC',
					)
				);
			}
		} elseif (is_object($subject) && !empty($subject->id)) {
			$titles = $this->get_recent_post_titles(
				array(
					'template_id' => (int) $subject->id,
					'status'      => 'completed',
					'per_page'    => $limit,
					'fields'      => 'list',
					'orderby'     => 'completed_at',
					'order'       => 'DESC',
				)
			);
		}

		return $this->format_titles_block(
			$titles,
			'Avoid these existing titles or very close variations:'
		);
	}

	/**
	 * Build an already-created topic titles block for author-topic prompts.
	 *
	 * @param object $author Author record.
	 * @return string
	 */
	public function build_created_topic_titles_block($author) {
		if (!is_object($author) || empty($author->id)) {
			return '';
		}

		$rows = $this->author_topics_repository->get_by_author((int) $author->id);
		if (empty($rows) || !is_array($rows)) {
			return '';
		}

		$limit  = $this->get_limit($author);
		$titles = array();

		foreach ($rows as $row) {
			if (!is_object($row) || empty($row->topic_title)) {
				continue;
			}

			$titles[] = $row->topic_title;

			if (count($titles) >= $limit) {
				break;
			}
		}

		return $this->format_titles_block(
			$titles,
			'Avoid these already-created topic titles or very close variations:'
		);
	}

	/**
	 * Build a content-format block for post-generation prompts.
	 *
	 * Rotates through a filterable set of structural formats based on the number
	 * of completed generations for the current template or author, so repeated
	 * runs naturally spread across different article shapes.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return string
	 */
	public function build_content_format_block($subject) {
		$formats = $this->get_content_formats($subject);

		if (empty($formats)) {
			return '';
		}

		$index = $this->get_rotation_index($subject, count($formats));
		$format = isset($formats[ $index ]) ? $formats[ $index ] : reset($formats);
		$format = trim((string) $format);

		if ($format === '') {
			return '';
		}

		/**
		 * Filters the heading used for the content-format diversity block.
		 *
		 * @since 2.6.0
		 *
		 * @param string $heading Default heading.
		 * @param mixed  $subject Template object or generation context.
		 * @param string $format  Selected content format.
		 * @param array  $formats Normalized format list.
		 */
		$heading = apply_filters(
			'aips_diversity_content_format_heading',
			'Use this content format for this generation:',
			$subject,
			$format,
			$formats
		);

		$block  = $heading . "\n";
		$block .= '- ' . $format . "\n\n";
		$block .= 'Treat this as the primary structure and framing for this piece. Do not default to a generic overview unless this format naturally calls for it.';

		/**
		 * Filters the final content-format diversity block.
		 *
		 * @since 2.6.0
		 *
		 * @param string $block   Formatted prompt block.
		 * @param mixed  $subject Template object or generation context.
		 * @param string $format  Selected content format.
		 * @param array  $formats Normalized format list.
		 */
		return apply_filters('aips_diversity_content_format_block', $block, $subject, $format, $formats);
	}

	/**
	 * Build the post-slice guidance block for title/content prompts.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return string
	 */
	public function build_post_slice_block($subject) {
		$slices = $this->get_post_slices($subject);

		if (empty($slices)) {
			return '';
		}

		$index = $this->get_rotation_index($subject, count($slices));
		$slice = isset($slices[ $index ]) ? $slices[ $index ] : reset($slices);
		$slice = trim((string) $slice);

		if ($slice === '') {
			return '';
		}

		/**
		 * Filters the heading used for the post-slice diversity block.
		 *
		 * @since 2.6.0
		 *
		 * @param string $heading Default heading.
		 * @param mixed  $subject Template object or generation context.
		 * @param string $slice   Selected post slice.
		 * @param array  $slices  Normalized active post slice list.
		 */
		$heading = apply_filters(
			'aips_diversity_post_slice_heading',
			'Use this post style for this generation:',
			$subject,
			$slice,
			$slices
		);

		$block  = $heading . "\n";
		$block .= '- ' . $slice . "\n\n";
		$block .= 'Treat this as the editorial lens for the piece. Shape the title angle, examples, section emphasis, and framing around this style without simply naming the style in the post.';

		/**
		 * Filters the final post-slice diversity block.
		 *
		 * @since 2.6.0
		 *
		 * @param string $block   Formatted prompt block.
		 * @param mixed  $subject Template object or generation context.
		 * @param string $slice   Selected post slice.
		 * @param array  $slices  Normalized active post slice list.
		 */
		return apply_filters('aips_diversity_post_slice_block', $block, $subject, $slice, $slices);
	}

	/**
	 * Build the uniqueness-seed guidance block for content prompts.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return string
	 */
	public function build_uniqueness_seed_line_block($subject = null) {
		$seed = $this->generate_uniqueness_seed();
		$block = 'Unique generation seed: ' . $seed . '. Use this run-specific seed to vary angle, framing, structure, and examples while keeping the post meaningfully distinct from past generations.';

		/**
		 * Filters the uniqueness-seed prompt block.
		 *
		 * @since 2.6.0
		 *
		 * @param string $block   Formatted prompt block.
		 * @param mixed  $subject Template object or generation context.
		 * @param string $seed    Generated seed value.
		 */
		return apply_filters('aips_diversity_uniqueness_seed_line_block', $block, $subject, $seed);
	}

	/**
	 * Fetch recent generated post titles from history.
	 *
	 * @param array $args History repository arguments.
	 * @return array
	 */
	private function get_recent_post_titles(array $args) {
		$history = $this->history_repository->get_history($args);
		if (empty($history['items']) || !is_array($history['items'])) {
			return array();
		}

		$titles = array();

		foreach ($history['items'] as $item) {
			if (!is_object($item) || empty($item->generated_title)) {
				continue;
			}

			$titles[] = $item->generated_title;
		}

		return $titles;
	}

	/**
	 * Fetch the completed generation count for the current subject.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return int
	 */
	private function get_completed_generation_count($subject) {
		$args = array(
			'status'   => 'completed',
			'per_page' => 1,
			'page'     => 1,
			'fields'   => 'list',
		);

		if ($subject instanceof AIPS_Template_Context) {
			$args['template_id'] = (int) $subject->get_id();
		} elseif ($subject instanceof AIPS_Topic_Context) {
			$author = $subject->get_author();
			if (!$author || empty($author->id)) {
				return 0;
			}

			$args['author_id'] = (int) $author->id;
		} elseif (is_object($subject) && !empty($subject->id)) {
			$args['template_id'] = (int) $subject->id;
		} else {
			return 0;
		}

		$history = $this->history_repository->get_history($args);
		if (!is_array($history) || !isset($history['total'])) {
			return 0;
		}

		return max(0, (int) $history['total']);
	}

	/**
	 * Format a list of titles into a reusable diversity block.
	 *
	 * @param array  $titles Title strings.
	 * @param string $heading Block heading.
	 * @return string
	 */
	private function format_titles_block(array $titles, $heading) {
		$titles = array_values(
			array_unique(
				array_filter(
					array_map(
						function($title) {
							return trim((string) $title);
						},
						$titles
					)
				)
			)
		);

		if (empty($titles)) {
			return '';
		}

		$block = $heading . "\n";

		foreach ($titles as $title) {
			$block .= '- ' . $title . "\n";
		}

		$block .= "\nDo not reuse, lightly rephrase, or mirror these titles. Choose a clearly different angle, wording, and framing.";

		return $block;
	}

	/**
	 * Get the normalized list of supported content formats.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return array
	 */
	private function get_content_formats($subject) {
		$defaults = array(
			'implementation checklist',
			'case-study teardown',
			'myth-vs-reality analysis',
			'comparison guide',
			'failure analysis',
			'migration guide',
			'step-by-step tutorial',
			'decision framework',
		);

		/**
		 * Filters the available content formats used by the diversity injector.
		 *
		 * @since 2.6.0
		 *
		 * @param array $defaults Default content format labels.
		 * @param mixed $subject  Template object or generation context.
		 */
		$formats = apply_filters('aips_diversity_content_formats', $defaults, $subject);
		if (!is_array($formats)) {
			return array();
		}

		$formats = array_values(
			array_unique(
				array_filter(
					array_map(
						function($format) {
							return trim((string) $format);
						},
						$formats
					)
				)
			)
		);

		return $formats;
	}

	/**
	 * Get the normalized list of active post slices.
	 *
	 * @param mixed $subject Template object or generation context.
	 * @return array
	 */
	private function get_post_slices($subject) {
		if ($this->post_slices_repository === null && class_exists('AIPS_Post_Slices_Repository')) {
			$this->post_slices_repository = AIPS_Post_Slices_Repository::instance();
		}

		$slices = array();

		if ($this->post_slices_repository && method_exists($this->post_slices_repository, 'get_active_names')) {
			$slices = $this->post_slices_repository->get_active_names();
		}

		/**
		 * Filters the active post slices used by the diversity injector.
		 *
		 * @since 2.6.0
		 *
		 * @param array $slices  Active post slice labels.
		 * @param mixed $subject Template object or generation context.
		 */
		$slices = apply_filters('aips_diversity_post_slices', $slices, $subject);

		if (!is_array($slices)) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						function($slice) {
							return trim((string) $slice);
						},
						$slices
					)
				)
			)
		);
	}

	/**
	 * Resolve which diversity item should be assigned for this subject.
	 *
	 * @param mixed $subject Prompt subject.
	 * @param int   $count   Number of available formats.
	 * @return int
	 */
	private function get_rotation_index($subject, $count) {
		if ($count < 2) {
			return 0;
		}

		$total = $this->get_completed_generation_count($subject);

		return $total % $count;
	}

	/**
	 * Generate a uniqueness seed with fallback entropy.
	 *
	 * @return string
	 */
	private function generate_uniqueness_seed() {
		try {
			return bin2hex(random_bytes(self::UNIQUENESS_SEED_BYTES));
		} catch (Random\RandomException) {
			$random_one = function_exists('wp_rand') ? wp_rand(0, 0xffffffff) : mt_rand(0, 0xffffffff);
			$random_two = function_exists('wp_rand') ? wp_rand(0, 0xffffffff) : mt_rand(0, 0xffffffff);
			$fallback = $random_one . '|' . $random_two . '|' . uniqid('', true) . '|' . microtime(true);

			return substr(hash('sha256', $fallback), 0, self::UNIQUENESS_SEED_BYTES * 2);
		}
	}

	/**
	 * Resolve the maximum number of titles to include.
	 *
	 * @param mixed $subject Prompt subject.
	 * @return int
	 */
	private function get_limit($subject) {
		/**
		 * Filters how many recent titles are included in diversity avoid-title blocks.
		 *
		 * The subject may be a template object, an AIPS_Generation_Context instance,
		 * or an author object when building author-topic prompts. Default 12 titles.
		 *
		 * @since 2.5.0
		 * @param int   $limit   Number of titles to include.
		 * @param mixed $subject Template object, generation context, or author object.
		 */
		$limit = (int) apply_filters('aips_diversity_avoid_titles_limit', self::DEFAULT_TITLE_LIMIT, $subject);

		return max(1, $limit);
	}
}
