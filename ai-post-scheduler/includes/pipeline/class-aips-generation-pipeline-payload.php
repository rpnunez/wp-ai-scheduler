<?php
/**
 * Generation Pipeline Payload
 *
 * Encapsulates the mutable data passing through each stage of the post generation pipeline.
 *
 * @package AI_Post_Scheduler
 * @since 3.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Generation_Pipeline_Payload {

	/**
	 * @var string
	 */
	public $title = '';

	/**
	 * @var string|null
	 */
	public $outline = null;

	/**
	 * @var string
	 */
	public $raw_content = '';

	/**
	 * @var string
	 */
	public $formatted_content = '';

	/**
	 * @var string
	 */
	public $excerpt = '';

	/**
	 * @var int|null
	 */
	public $post_id = null;

	/**
	 * @var string
	 */
	public $post_status = 'draft';

	/**
	 * @var array<int|string>
	 */
	public $categories = array();

	/**
	 * @var array<string>
	 */
	public $tags = array();

	/**
	 * @var int|null
	 */
	public $featured_image_id = null;

	/**
	 * @var string|null
	 */
	public $featured_image_url = null;

	/**
	 * @var array<string, mixed>
	 */
	public $seo_metadata = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $internal_links = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $affiliate_links = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $retrieved_sources = array();

	/**
	 * @var array<string, float>
	 */
	public $stage_timings = array();

	/**
	 * @var array<int, string|WP_Error>
	 */
	public $errors = array();

	/**
	 * @var array<string, mixed>
	 */
	public $meta_fields = array();

	/**
	 * Constructor.
	 *
	 * @param array $initial_data Initial state values.
	 */
	public function __construct(array $initial_data = array()) {
		foreach ($initial_data as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Record time taken by a specific stage.
	 *
	 * @param string $stage_id Stage identifier.
	 * @param float  $seconds  Execution time in seconds.
	 * @return void
	 */
	public function record_stage_timing(string $stage_id, float $seconds): void {
		$this->stage_timings[$stage_id] = round($seconds, 4);
	}

	/**
	 * Add an error to the payload.
	 *
	 * @param string|WP_Error $error Error message or object.
	 * @return void
	 */
	public function add_error($error): void {
		$this->errors[] = $error;
	}

	/**
	 * Check if any errors have occurred.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return !empty($this->errors);
	}

	/**
	 * Convert payload into structured array for history logging.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'post_id'           => $this->post_id,
			'title'             => $this->title,
			'outline'           => $this->outline,
			'content_length'    => strlen($this->formatted_content ?: $this->raw_content),
			'excerpt'           => $this->excerpt,
			'post_status'       => $this->post_status,
			'categories'        => $this->categories,
			'tags'              => $this->tags,
			'featured_image_id' => $this->featured_image_id,
			'seo_metadata'      => $this->seo_metadata,
			'internal_links'    => count($this->internal_links),
			'affiliate_links'   => count($this->affiliate_links),
			'retrieved_sources' => count($this->retrieved_sources),
			'stage_timings'     => $this->stage_timings,
			'errors'            => $this->errors,
		);
	}
}
