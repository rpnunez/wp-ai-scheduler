<?php
/**
 * Generation Context Factory
 *
 * Factory class responsible for creating generation context objects
 * from a history ID. This encapsulates the logic for retrieving the
 * correct context (Template or Topic) for regeneration tasks.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Context_Factory
 *
 * Creates generation context objects.
 */
class AIPS_Generation_Context_Factory {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;

	/**
	 * @var AIPS_Author_Topics_Repository
	 */
	private $author_topics_repository;

	/**
	 * @var AIPS_Authors_Repository
	 */
	private $authors_repository;

	/**
	 * @var AIPS_Voices_Repository
	 */
	private $voices_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		$this->template_repository = new AIPS_Template_Repository();
		$this->author_topics_repository = new AIPS_Author_Topics_Repository();
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->voices_repository = new AIPS_Voices_Repository();
	}

	/**
	 * Create a generation context from a history record ID.
	 *
	 * Retrieves the original generation context (Template or Topic) used to generate
	 * a post so that components can be regenerated with the same context.
	 *
	 * @param int $history_id History record ID.
	 * @return array|WP_Error Context array with 'generation_context' key containing AIPS_Generation_Context object, or error on failure.
	 */
	public function create_from_history_id($history_id) {
		// Fetch history record
		$history = $this->history_repository->get_by_id($history_id);

		if (!$history) {
			return new WP_Error('invalid_history', __('Invalid history record.', 'ai-post-scheduler'));
		}

		$context = array(
			'history_id' => $history_id,
			'post_id'    => $history->post_id,
			'generation_context' => null,
			'context_type' => null,
			'context_name' => null,
		);

		// Determine the generation context type and reconstruct the context object
		if ($history->template_id) {
			// This is a Template-based post
			$template = $this->template_repository->get_by_id($history->template_id);
			if (!$template) {
				return new WP_Error('missing_template', __('Template data not found.', 'ai-post-scheduler'));
			}

			// Fetch voice if available
			$voice = null;
			if (!empty($template->voice_id)) {
				$voice = $this->voices_repository->get_by_id($template->voice_id);
			}

			// Get topic string if available from topic_id
			$topic_string = null;
			if ($history->topic_id) {
				$topic_data = $this->author_topics_repository->get_by_id($history->topic_id);
				if ($topic_data) {
					$topic_string = $topic_data->topic_title;
				}
			}

			// Create Template Context
			$context['generation_context'] = new AIPS_Template_Context($template, $voice, $topic_string);
			$context['context_type'] = 'template';
			$context['context_name'] = $template->name;

		} elseif ($history->author_id && $history->topic_id) {
			// This is a Topic-based post (Author + Topic)
			$author = $this->authors_repository->get_by_id($history->author_id);
			if (!$author) {
				return new WP_Error('missing_author', __('Author data not found.', 'ai-post-scheduler'));
			}

			$topic = $this->author_topics_repository->get_by_id($history->topic_id);
			if (!$topic) {
				return new WP_Error('missing_topic', __('Topic data not found.', 'ai-post-scheduler'));
			}

			// Create Topic Context
			$context['generation_context'] = new AIPS_Topic_Context($author, $topic);
			$context['context_type'] = 'topic';
			$context['context_name'] = $author->name . ': ' . $topic->topic_title;

		} else {
			return new WP_Error('invalid_context', __('Unable to determine generation context type.', 'ai-post-scheduler'));
		}

		return $context;
	}
}
