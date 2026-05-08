<?php
/**
 * Prompt Builder Diversity Injector
 *
 * Builds shared prompt blocks that steer the AI away from reusing recently
 * generated titles and topic ideas.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Diversity_Injector
 */
class AIPS_Prompt_Builder_Diversity_Injector {

	/**
	 * @var AIPS_History_Repository|null
	 */
	private $history_repository;

	/**
	 * @var AIPS_Author_Topics_Repository|null
	 */
	private $author_topics_repository;

	/**
	 * @param AIPS_History_Repository|null       $history_repository History repository.
	 * @param AIPS_Author_Topics_Repository|null $author_topics_repository Author topics repository.
	 */
	public function __construct($history_repository = null, $author_topics_repository = null) {
		$this->history_repository = $history_repository ?: new AIPS_History_Repository();
		$this->author_topics_repository = $author_topics_repository ?: new AIPS_Author_Topics_Repository();
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

		if (!method_exists($this->author_topics_repository, 'get_by_author')) {
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
	 * Fetch recent generated post titles from history.
	 *
	 * @param array $args History repository arguments.
	 * @return array
	 */
	private function get_recent_post_titles(array $args) {
		if (!method_exists($this->history_repository, 'get_history')) {
			return array();
		}

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
	 * Resolve the maximum number of titles to include.
	 *
	 * @param mixed $subject Prompt subject.
	 * @return int
	 */
	private function get_limit($subject) {
		$limit = (int) apply_filters('aips_diversity_avoid_titles_limit', 12, $subject);

		return max(1, $limit);
	}
}
