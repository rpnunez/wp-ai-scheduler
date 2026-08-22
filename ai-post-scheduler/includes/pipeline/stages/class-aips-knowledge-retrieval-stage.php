<?php
/**
 * Knowledge Retrieval Pipeline Stage
 *
 * Injects relevant factual context from Sources and Knowledge Base into generation payload.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Knowledge_Retrieval_Stage implements AIPS_Generation_Stage_Interface {

	/**
	 * @var AIPS_Sources_Data_Repository|null
	 */
	private $data_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Sources_Data_Repository|null $data_repo Sources data repository.
	 */
	public function __construct(?AIPS_Sources_Data_Repository $data_repo = null) {
		$container = AIPS_Container::get_instance();
		$this->data_repo = $data_repo ?: ($container->has(AIPS_Sources_Data_Repository::class) ? $container->make(AIPS_Sources_Data_Repository::class) : null);
	}

	public function get_id(): string {
		return 'knowledge_retrieval';
	}

	public function get_label(): string {
		return __('Knowledge Base & Dynamic Sources Retrieval', 'ai-post-scheduler');
	}

	public function get_priority(): int {
		return 20;
	}

	public function should_run(AIPS_Generation_Context $context, AIPS_Generation_Pipeline_Payload $payload): bool {
		return (bool) $context->get_include_sources();
	}

	public function process(
		AIPS_Generation_Context $context,
		AIPS_Generation_Pipeline_Payload $payload,
		callable $next
	): AIPS_Generation_Pipeline_Payload {
		$group_ids = (array) $context->get_source_group_ids();

		if (!empty($group_ids) && class_exists('AIPS_Sources_Repository')) {
			$container = AIPS_Container::get_instance();
			$sources_repo = $container->has(AIPS_Sources_Repository::class) ? $container->make(AIPS_Sources_Repository::class) : new AIPS_Sources_Repository();
			$sources = $sources_repo->get_sources_by_group_ids($group_ids);

			$retrieved = array();
			$max_chars = (int) AIPS_Config::get_instance()->get_option('aips_source_snippet_max_chars', AIPS_Sources_Fetcher::DEFAULT_PROMPT_SNIPPET_CHARS);

			if ($this->data_repo === null && class_exists('AIPS_Sources_Data_Repository')) {
				$this->data_repo = new AIPS_Sources_Data_Repository();
			}

			if ($this->data_repo !== null && !empty($sources)) {
				foreach ($sources as $source) {
					$source_id = is_object($source) ? (int) $source->id : (int) ($source['id'] ?? 0);
					$source_title = is_object($source) ? (string) $source->title : (string) ($source['title'] ?? '');
					$latest_data = $this->data_repo->get_latest_by_source_id($source_id);

					if ($latest_data && !empty($latest_data->extracted_text)) {
						$snippet = substr($latest_data->extracted_text, 0, $max_chars);
						$retrieved[] = array(
							'source_id' => $source_id,
							'title'     => $source_title,
							'snippet'   => $snippet,
						);
					}
				}
			}

			$payload->retrieved_sources = $retrieved;
		}

		return $next($payload);
	}
}
