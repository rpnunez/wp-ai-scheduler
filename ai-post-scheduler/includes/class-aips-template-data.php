<?php
/**
 * Template Data DTO
 *
 * Immutable value object that wraps a row from the `aips_templates` DB table.
 * Provides typed, IDE-completable access to all template fields instead of
 * ad-hoc `$row->name` / `$row['prompt_template']` lookups scattered
 * throughout the codebase.
 *
 * Usage:
 *   $tpl = AIPS_Template_Data::from_row( $wpdb_row_object );
 *   echo $tpl->name;             // 'Weekly Tech Roundup'
 *   echo $tpl->prompt_template;  // 'Write a post about {{topic}}...'
 *   if ( $tpl->generate_featured_image ) { ... }
 *
 * `AIPS_Bulk_Generation_Result` is the existing public-readonly precedent.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Template_Data
 *
 * Immutable value object representing one row from `aips_templates`.
 * All properties are public readonly.
 */
class AIPS_Template_Data {

	// -----------------------------------------------------------------------
	// Properties
	// -----------------------------------------------------------------------

	/**
	 * Primary key.
	 *
	 * @var int
	 */
	public readonly int $id;

	/**
	 * Human-readable template name.
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Optional description of this template's purpose.
	 *
	 * @var string|null
	 */
	public readonly ?string $description;

	/**
	 * AI prompt used to generate the post body.
	 *
	 * @var string
	 */
	public readonly string $prompt_template;

	/**
	 * Optional AI prompt used to generate the post title.
	 *
	 * @var string|null
	 */
	public readonly ?string $title_prompt;

	/**
	 * Optional FK to `aips_voices` that customises the writing style.
	 *
	 * @var int|null
	 */
	public readonly ?int $voice_id;

	/**
	 * Number of posts to generate per schedule run.
	 *
	 * @var int
	 */
	public readonly int $post_quantity;

	/**
	 * Optional AI prompt used to generate the featured image.
	 *
	 * @var string|null
	 */
	public readonly ?string $image_prompt;

	/**
	 * Whether a featured image should be generated for posts from this template.
	 *
	 * @var bool
	 */
	public readonly bool $generate_featured_image;

	/**
	 * Source strategy for the featured image ('ai_prompt', 'unsplash', 'media_library').
	 *
	 * @var string
	 */
	public readonly string $featured_image_source;

	/**
	 * Comma-separated keywords used when sourcing the image from Unsplash.
	 *
	 * @var string|null
	 */
	public readonly ?string $featured_image_unsplash_keywords;

	/**
	 * Comma-separated media-library attachment IDs for the media_library source.
	 *
	 * @var string|null
	 */
	public readonly ?string $featured_image_media_ids;

	/**
	 * WordPress post status for generated posts (e.g. 'draft', 'publish').
	 *
	 * @var string
	 */
	public readonly string $post_status;

	/**
	 * Category IDs for generated posts (array of WP term IDs).
	 *
	 * An empty array means "no specific category" (WP default applies).
	 *
	 * @var array
	 */
	public readonly array $post_category;

	/**
	 * Comma-separated default tags for generated posts.
	 *
	 * @var string|null
	 */
	public readonly ?string $post_tags;

	/**
	 * Optional default author user ID for generated posts.
	 *
	 * @var int|null
	 */
	public readonly ?int $post_author;

	/**
	 * Whether source citations should be appended to generated posts.
	 *
	 * @var bool
	 */
	public readonly bool $include_sources;

	/**
	 * Serialised source group IDs used when include_sources is enabled.
	 *
	 * @var string|null
	 */
	public readonly ?string $source_group_ids;

	/**
	 * Whether this template is active and available for scheduling.
	 *
	 * @var bool
	 */
	public readonly bool $is_active;

	/**
	 * Row creation datetime (MySQL format).
	 *
	 * @var string
	 */
	public readonly string $created_at;

	/**
	 * Row last-updated datetime (MySQL format).
	 *
	 * @var string
	 */
	public readonly string $updated_at;

	// -----------------------------------------------------------------------
	// Constructor (private — use from_row())
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param int         $id                               Primary key.
	 * @param string      $name                             Template name.
	 * @param string|null $description                      Optional description.
	 * @param string      $prompt_template                  AI body prompt.
	 * @param string|null $title_prompt                     Optional title prompt.
	 * @param int|null    $voice_id                         FK to voices.
	 * @param int         $post_quantity                    Posts per run.
	 * @param string|null $image_prompt                     Optional image prompt.
	 * @param bool        $generate_featured_image          Whether to generate image.
	 * @param string      $featured_image_source            Image source strategy.
	 * @param string|null $featured_image_unsplash_keywords Unsplash keywords.
	 * @param string|null $featured_image_media_ids         Media library IDs.
	 * @param string      $post_status                      WP post status.
	 * @param array         $post_category                    Default category IDs.
	 * @param string|null $post_tags                        Default tags.
	 * @param int|null    $post_author                      Default author ID.
	 * @param bool        $include_sources                  Append citations flag.
	 * @param string|null $source_group_ids                 Source group IDs.
	 * @param bool        $is_active                        Active flag.
	 * @param string      $created_at                       Creation datetime.
	 * @param string      $updated_at                       Last-updated datetime.
	 */
	private function __construct(
		int $id,
		string $name,
		?string $description,
		string $prompt_template,
		?string $title_prompt,
		?int $voice_id,
		int $post_quantity,
		?string $image_prompt,
		bool $generate_featured_image,
		string $featured_image_source,
		?string $featured_image_unsplash_keywords,
		?string $featured_image_media_ids,
		string $post_status,
		array $post_category,
		?string $post_tags,
		?int $post_author,
		bool $include_sources,
		?string $source_group_ids,
		bool $is_active,
		string $created_at,
		string $updated_at
	) {
		$this->id                               = $id;
		$this->name                             = $name;
		$this->description                      = $description;
		$this->prompt_template                  = $prompt_template;
		$this->title_prompt                     = $title_prompt;
		$this->voice_id                         = $voice_id;
		$this->post_quantity                    = $post_quantity;
		$this->image_prompt                     = $image_prompt;
		$this->generate_featured_image          = $generate_featured_image;
		$this->featured_image_source            = $featured_image_source;
		$this->featured_image_unsplash_keywords = $featured_image_unsplash_keywords;
		$this->featured_image_media_ids         = $featured_image_media_ids;
		$this->post_status                      = $post_status;
		$this->post_category                    = $post_category;
		$this->post_tags                        = $post_tags;
		$this->post_author                      = $post_author;
		$this->include_sources                  = $include_sources;
		$this->source_group_ids                 = $source_group_ids;
		$this->is_active                        = $is_active;
		$this->created_at                       = $created_at;
		$this->updated_at                       = $updated_at;
	}

	// -----------------------------------------------------------------------
	// Factory
	// -----------------------------------------------------------------------

	/**
	 * Build an instance from a DB row object returned by wpdb.
	 *
	 * Handles the loose typing produced by wpdb (all values arrive as strings
	 * or null) and coerces them to the correct PHP types.
	 *
	 * @param object $row A stdClass row from aips_templates.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->name,
			isset( $row->description ) && $row->description !== '' ? (string) $row->description : null,
			(string) $row->prompt_template,
			isset( $row->title_prompt ) && $row->title_prompt !== '' ? (string) $row->title_prompt : null,
			isset( $row->voice_id ) && $row->voice_id !== null ? (int) $row->voice_id : null,
			(int) ( $row->post_quantity ?? 1 ),
			isset( $row->image_prompt ) && $row->image_prompt !== '' ? (string) $row->image_prompt : null,
			1 === (int) ( $row->generate_featured_image ?? 0 ),
			(string) ( $row->featured_image_source ?? 'ai_prompt' ),
			isset( $row->featured_image_unsplash_keywords ) && $row->featured_image_unsplash_keywords !== '' ? (string) $row->featured_image_unsplash_keywords : null,
			isset( $row->featured_image_media_ids ) && $row->featured_image_media_ids !== '' ? (string) $row->featured_image_media_ids : null,
			(string) ( $row->post_status ?? 'draft' ),
			self::parse_post_categories( $row->post_category ?? null ),
			isset( $row->post_tags ) && $row->post_tags !== '' ? (string) $row->post_tags : null,
			isset( $row->post_author ) && $row->post_author !== null ? (int) $row->post_author : null,
			1 === (int) ( $row->include_sources ?? 0 ),
			isset( $row->source_group_ids ) && $row->source_group_ids !== '' ? (string) $row->source_group_ids : null,
			1 === (int) ( $row->is_active ?? 1 ),
			(string) ( $row->created_at ?? '' ),
			(string) ( $row->updated_at ?? '' )
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Normalise a raw post_category DB value to an array of integer term IDs.
	 *
	 * Handles:
	 *   - null / '' / 0 / '0'  → []
	 *   - JSON array string     → decoded and filtered (e.g. "[1,2]" → [1,2])
	 *   - plain integer string  → single-element array (e.g. "5" → [5])
	 *   - int                   → single-element array (e.g. 5 → [5])
	 *   - PHP array             → filtered array of ints
	 *
	 * @param mixed $value Raw DB value.
	 * @return array<int>
	 */
	public static function parse_post_categories( $value ): array {
		if ( is_null( $value ) || $value === '' || $value === 0 || $value === '0' ) {
			return array();
		}

		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'intval', $value ), static function ( $id ) {
				return $id > 0;
			} ) );
		}

		if ( is_int( $value ) ) {
			return $value > 0 ? array( $value ) : array();
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'intval', $decoded ), static function ( $id ) {
					return $id > 0;
				} ) );
			}
			// Not valid JSON — treat as a legacy plain integer string.
			$int_val = (int) $value;
			return $int_val > 0 ? array( $int_val ) : array();
		}

		return array();
	}

	/**
	 * Whether this template has a title prompt configured.
	 *
	 * @return bool
	 */
	public function has_title_prompt(): bool {
		return $this->title_prompt !== null && $this->title_prompt !== '';
	}

	/**
	 * Whether this template has an image prompt configured.
	 *
	 * @return bool
	 */
	public function has_image_prompt(): bool {
		return $this->image_prompt !== null && $this->image_prompt !== '';
	}

	/**
	 * Whether a voice override is attached to this template.
	 *
	 * @return bool
	 */
	public function has_voice(): bool {
		return $this->voice_id !== null;
	}
}
