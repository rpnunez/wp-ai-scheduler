<?php
/**
 * Template Entry DTO
 *
 * Immutable value object representing the generator-facing view of a template.
 * This is a projection of template DB fields combined with execution-specific
 * overrides (post_quantity, article_structure_id) that are determined at
 * dispatch time rather than stored directly in the template row.
 *
 * Usage:
 *   $entry = AIPS_Template_Entry::from_template_and_overrides(
 *       $schedule->template_id,
 *       $template_row,
 *       $post_quantity,
 *       $article_structure_id
 *   );
 *   $context = new AIPS_Template_Context( $entry, null, $topic, 'scheduled' );
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Template_Entry
 *
 * Immutable value object representing the generator-facing shape of a template.
 * All properties are public readonly.
 */
class AIPS_Template_Entry {

	// -----------------------------------------------------------------------
	// Properties
	// -----------------------------------------------------------------------

	/**
	 * Template primary key.
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
	 * Optional AI prompt used to generate the featured image.
	 *
	 * @var string|null
	 */
	public readonly ?string $image_prompt;

	/**
	 * Whether a featured image should be generated for this post.
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
	 * Comma-separated keywords for Unsplash image sourcing.
	 *
	 * @var string|null
	 */
	public readonly ?string $featured_image_unsplash_keywords;

	/**
	 * Comma-separated media-library attachment IDs.
	 *
	 * @var string|null
	 */
	public readonly ?string $featured_image_media_ids;

	/**
	 * WordPress post status for the generated post (e.g. 'draft', 'publish').
	 *
	 * @var string
	 */
	public readonly string $post_status;

	/**
	 * Optional default category ID for generated posts.
	 *
	 * @var int|null
	 */
	public readonly ?int $post_category;

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
	 * Number of posts to generate in this execution (runtime override).
	 *
	 * @var int
	 */
	public readonly int $post_quantity;

	/**
	 * Article structure FK to use for this execution, or null for no structure.
	 * This is a runtime override computed at dispatch time (e.g. via rotation).
	 *
	 * @var int|null
	 */
	public readonly ?int $article_structure_id;

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

	// -----------------------------------------------------------------------
	// Constructor (private — use factory methods)
	// -----------------------------------------------------------------------

	/**
	 * Constructor.
	 *
	 * @param int         $id                               Template primary key.
	 * @param string      $name                             Template name.
	 * @param string      $prompt_template                  AI body prompt.
	 * @param string|null $title_prompt                     Optional title prompt.
	 * @param string|null $image_prompt                     Optional image prompt.
	 * @param bool        $generate_featured_image          Whether to generate image.
	 * @param string      $featured_image_source            Image source strategy.
	 * @param string|null $featured_image_unsplash_keywords Unsplash keywords.
	 * @param string|null $featured_image_media_ids         Media library IDs.
	 * @param string      $post_status                      WP post status.
	 * @param int|null    $post_category                    Default category ID.
	 * @param string|null $post_tags                        Default tags.
	 * @param int|null    $post_author                      Default author ID.
	 * @param int         $post_quantity                    Number of posts to generate.
	 * @param int|null    $article_structure_id             Runtime article structure FK.
	 * @param bool        $include_sources                  Append citations flag.
	 * @param string|null $source_group_ids                 Source group IDs.
	 */
	private function __construct(
		int $id,
		string $name,
		string $prompt_template,
		?string $title_prompt,
		?string $image_prompt,
		bool $generate_featured_image,
		string $featured_image_source,
		?string $featured_image_unsplash_keywords,
		?string $featured_image_media_ids,
		string $post_status,
		?int $post_category,
		?string $post_tags,
		?int $post_author,
		int $post_quantity,
		?int $article_structure_id,
		bool $include_sources,
		?string $source_group_ids
	) {
		$this->id                               = $id;
		$this->name                             = $name;
		$this->prompt_template                  = $prompt_template;
		$this->title_prompt                     = $title_prompt;
		$this->image_prompt                     = $image_prompt;
		$this->generate_featured_image          = $generate_featured_image;
		$this->featured_image_source            = $featured_image_source;
		$this->featured_image_unsplash_keywords = $featured_image_unsplash_keywords;
		$this->featured_image_media_ids         = $featured_image_media_ids;
		$this->post_status                      = $post_status;
		$this->post_category                    = $post_category;
		$this->post_tags                        = $post_tags;
		$this->post_author                      = $post_author;
		$this->post_quantity                    = $post_quantity;
		$this->article_structure_id             = $article_structure_id;
		$this->include_sources                  = $include_sources;
		$this->source_group_ids                 = $source_group_ids;
	}

	// -----------------------------------------------------------------------
	// Factories
	// -----------------------------------------------------------------------

	/**
	 * Build an instance from a raw template source object and execution overrides.
	 *
	 * Accepts any object that carries template fields (e.g. a raw wpdb row
	 * from the templates table, or a merged schedule+template object).
	 * Handles the loose typing produced by wpdb (all values arrive as strings
	 * or null) and coerces them to the correct PHP types.
	 *
	 * @param int      $template_id          Template primary key. Provided
	 *                                       explicitly because the source object
	 *                                       may carry a different `id` field
	 *                                       (e.g. schedule->id in merged rows).
	 * @param object   $source               Object carrying template fields.
	 * @param int      $post_quantity        Number of posts for this execution.
	 * @param int|null $article_structure_id Article structure FK for this run.
	 * @return self
	 */
	public static function from_template_and_overrides(
		int $template_id,
		object $source,
		int $post_quantity,
		?int $article_structure_id = null
	): self {
		return new self(
			$template_id,
			(string) ($source->name ?? ''),
			(string) ($source->prompt_template ?? ''),
			isset($source->title_prompt) && $source->title_prompt !== '' ? (string) $source->title_prompt : null,
			isset($source->image_prompt) && $source->image_prompt !== '' ? (string) $source->image_prompt : null,
			1 === (int) ($source->generate_featured_image ?? 0),
			isset($source->featured_image_source) && $source->featured_image_source !== '' ? (string) $source->featured_image_source : 'ai_prompt',
			isset($source->featured_image_unsplash_keywords) && $source->featured_image_unsplash_keywords !== '' ? (string) $source->featured_image_unsplash_keywords : null,
			isset($source->featured_image_media_ids) && $source->featured_image_media_ids !== '' ? (string) $source->featured_image_media_ids : null,
			(string) ($source->post_status ?? 'draft'),
			isset($source->post_category) && null !== $source->post_category && '' !== $source->post_category ? (int) $source->post_category : null,
			isset($source->post_tags) && $source->post_tags !== '' ? (string) $source->post_tags : null,
			isset($source->post_author) && null !== $source->post_author && '' !== $source->post_author ? (int) $source->post_author : null,
			$post_quantity,
			$article_structure_id,
			1 === (int) ($source->include_sources ?? 0),
			isset($source->source_group_ids) && $source->source_group_ids !== '' ? (string) $source->source_group_ids : null
		);
	}
}
