<?php
/**
 * Generation Result DTO
 *
 * Immutable value object representing the outcome of a single post-generation
 * operation.  Replaces the ad-hoc associative arrays that were previously
 * returned by generator methods and assembled inline in controllers.
 *
 * Named constructors mirror the three observable outcomes:
 *   AIPS_Generation_Result::success( $post_id, $component_statuses, $generation_time )
 *   AIPS_Generation_Result::partial( $post_id, $errors, $component_statuses, $generation_time )
 *   AIPS_Generation_Result::failure( $errors, $generation_time )
 *
 * `AIPS_Bulk_Generation_Result` (in class-aips-bulk-generator-service.php) is the
 * existing precedent for the public-readonly pattern used here.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Generation_Result
 *
 * Immutable value object produced by a single post-generation run.
 * All properties are public readonly — readable like normal public properties
 * but cannot be mutated after construction.
 */
class AIPS_Generation_Result {

	// -----------------------------------------------------------------------
	// Status constants
	// -----------------------------------------------------------------------

	/** All components generated successfully. */
	const STATUS_COMPLETED = 'completed';

	/** Post was created but one or more optional components failed. */
	const STATUS_PARTIAL = 'partial';

	/** Generation failed; no post was created. */
	const STATUS_FAILED = 'failed';

	// -----------------------------------------------------------------------
	// Properties
	// -----------------------------------------------------------------------

	/**
	 * ID of the WordPress post that was created, or null on failure.
	 *
	 * @var int|null
	 */
	public readonly ?int $post_id;

	/**
	 * Overall outcome status.
	 *
	 * One of the STATUS_* class constants: 'completed', 'partial', or 'failed'.
	 *
	 * @var string
	 */
	public readonly string $status;

	/**
	 * Human-readable error strings accumulated during this generation run.
	 *
	 * Empty for a fully successful result.
	 *
	 * @var string[]
	 */
	public readonly array $errors;

	/**
	 * Per-component boolean success flags.
	 *
	 * Standard keys: 'post_title', 'post_content', 'post_excerpt', 'featured_image'.
	 * A missing key should be treated as false by callers.
	 *
	 * @var array<string, bool>
	 */
	public readonly array $component_statuses;

	/**
	 * Wall-clock generation time in seconds (float precision).
	 *
	 * @var float
	 */
	public readonly float $generation_time;

	// -----------------------------------------------------------------------
	// Constructor (private — use named constructors)
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param int|null $post_id            Post ID on success; null on failure.
	 * @param string   $status             One of the STATUS_* constants.
	 * @param string[] $errors             Per-component error messages.
	 * @param array    $component_statuses Boolean flag per generated component.
	 * @param float    $generation_time    Elapsed time in seconds.
	 */
	private function __construct(
		?int $post_id,
		string $status,
		array $errors,
		array $component_statuses,
		float $generation_time
	) {
		$this->post_id            = $post_id;
		$this->status             = $status;
		$this->errors             = $errors;
		$this->component_statuses = $component_statuses;
		$this->generation_time    = $generation_time;
	}

	// -----------------------------------------------------------------------
	// Named constructors
	// -----------------------------------------------------------------------

	/**
	 * Create a fully-successful generation result.
	 *
	 * All components were generated and the post was published/drafted without
	 * any missing pieces.
	 *
	 * @param int   $post_id            ID of the created post.
	 * @param array $component_statuses Optional boolean flags per component.
	 * @param float $generation_time    Optional elapsed time in seconds.
	 * @return self
	 */
	public static function success(
		int $post_id,
		array $component_statuses = array(),
		float $generation_time = 0.0
	): self {
		return new self(
			$post_id,
			self::STATUS_COMPLETED,
			array(),
			$component_statuses,
			$generation_time
		);
	}

	/**
	 * Create a partial generation result.
	 *
	 * The post was created but one or more optional components (e.g. featured
	 * image, excerpt) could not be generated successfully.
	 *
	 * @param int      $post_id            ID of the created post.
	 * @param string[] $errors             Per-component error messages.
	 * @param array    $component_statuses Boolean flags per component.
	 * @param float    $generation_time    Optional elapsed time in seconds.
	 * @return self
	 */
	public static function partial(
		int $post_id,
		array $errors = array(),
		array $component_statuses = array(),
		float $generation_time = 0.0
	): self {
		return new self(
			$post_id,
			self::STATUS_PARTIAL,
			$errors,
			$component_statuses,
			$generation_time
		);
	}

	/**
	 * Create a failed generation result.
	 *
	 * No post was created.  The provided errors describe what went wrong.
	 *
	 * @param string[] $errors          Human-readable error messages.
	 * @param float    $generation_time Optional elapsed time in seconds.
	 * @return self
	 */
	public static function failure(
		array $errors = array(),
		float $generation_time = 0.0
	): self {
		return new self(
			null,
			self::STATUS_FAILED,
			$errors,
			array(),
			$generation_time
		);
	}

	/**
	 * Convenience factory: build from a WP_Error.
	 *
	 * @param WP_Error $error           The WP_Error instance.
	 * @param float    $generation_time Optional elapsed time in seconds.
	 * @return self
	 */
	public static function from_wp_error(
		WP_Error $error,
		float $generation_time = 0.0
	): self {
		return self::failure(
			array( $error->get_error_message() ),
			$generation_time
		);
	}

	// -----------------------------------------------------------------------
	// Array conversion
	// -----------------------------------------------------------------------

	/**
	 * Convert this result to an associative array.
	 *
	 * Provided for backward compatibility with code that previously consumed
	 * the ad-hoc arrays returned by the generator.  New code should read the
	 * typed properties directly.
	 *
	 * @return array{post_id: int|null, status: string, errors: string[], component_statuses: array<string, bool>, generation_time: float}
	 */
	public function toArray(): array {
		return array(
			'post_id'            => $this->post_id,
			'status'             => $this->status,
			'errors'             => $this->errors,
			'component_statuses' => $this->component_statuses,
			'generation_time'    => $this->generation_time,
		);
	}

	// -----------------------------------------------------------------------
	// Status helpers
	// -----------------------------------------------------------------------

	/**
	 * Whether the generation completed fully without errors.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->status === self::STATUS_COMPLETED;
	}

	/**
	 * Whether the post was created but with one or more missing components.
	 *
	 * @return bool
	 */
	public function is_partial(): bool {
		return $this->status === self::STATUS_PARTIAL;
	}

	/**
	 * Whether the generation failed entirely (no post was created).
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return $this->status === self::STATUS_FAILED;
	}

	/**
	 * Whether a post was created (true for both completed and partial outcomes).
	 *
	 * @return bool
	 */
	public function has_post(): bool {
		return $this->post_id !== null;
	}
}
