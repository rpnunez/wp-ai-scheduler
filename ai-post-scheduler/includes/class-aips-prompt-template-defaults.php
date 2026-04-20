<?php
/**
 * Prompt Template Defaults
 *
 * Central registry for the built-in default group definition and per-component
 * default prompt text.  Keeping this data outside the Repository classes lets
 * the two repositories (Group and Item) stay focused on persistence without
 * embedding domain configuration in infrastructure code.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Template_Defaults
 *
 * Static registry. Call the helper methods to access defaults rather than
 * reading the properties directly, so that future subclassing or filtering
 * remains straightforward.
 */
class AIPS_Prompt_Template_Defaults {

	/**
	 * Built-in default group definition.
	 *
	 * Name, description, and is_default flag used when the database contains no
	 * groups yet and the factory default must be seeded.
	 *
	 * @var array{name:string,description:string,is_default:int}
	 */
	public static $default_group = array(
		'name'        => 'Default',
		'description' => 'Built-in default prompt template group shipped with the plugin.',
		'is_default'  => 1,
	);

	/**
	 * Built-in component definitions.
	 *
	 * Every key corresponds to a component_key stored in aips_prompt_template_items.
	 * The default_prompt value is used when no user-defined override exists in the DB.
	 *
	 * Components where default_prompt is an empty string act as optional prefixes —
	 * when empty, the builder behaves identically to the pre-2.5.0 code; when an
	 * admin supplies text the builder prepends it to the generation prompt.
	 *
	 * @var array<string,array{key:string,label:string,description:string,default_prompt:string}>
	 */
	public static $default_components = array(
		'post_title' => array(
			'key'            => 'post_title',
			'label'          => 'Post Title',
			'description'    => 'Base instruction used when generating a post title from article content.',
			'default_prompt' => 'Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.',
		),
		'post_excerpt' => array(
			'key'            => 'post_excerpt',
			'label'          => 'Post Excerpt',
			'description'    => 'Opening instruction used when generating a post excerpt.',
			'default_prompt' => 'Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.',
		),
		'post_content' => array(
			'key'            => 'post_content',
			'label'          => 'Post Content',
			'description'    => 'Optional system-level instruction prepended before the template\'s content generation prompt. Leave empty to use only the template\'s own prompt (default behaviour).',
			'default_prompt' => '',
		),
		'post_featured_image' => array(
			'key'            => 'post_featured_image',
			'label'          => 'Post Featured Image',
			'description'    => 'Optional base instruction prepended before the template\'s image prompt. Leave empty to use only the template\'s own image prompt (default behaviour).',
			'default_prompt' => '',
		),
		'author_topic' => array(
			'key'            => 'author_topic',
			'label'          => 'Author Topic Generation',
			'description'    => 'Requirements and format instructions appended to the author-topic generation prompt. Use {niche} as a placeholder for the author\'s niche.',
			'default_prompt' => "Requirements:\n- Each topic should be specific and actionable\n- Topics should be diverse and cover different aspects of {niche}\n- Avoid duplicating previously approved or rejected topics\n- Format each topic as a clear, engaging blog post title",
		),
		'author_suggestions' => array(
			'key'            => 'author_suggestions',
			'label'          => 'Author Suggestions',
			'description'    => 'System role and task description used when generating AI author persona suggestions. Use {count} as a placeholder for the number of personas requested.',
			'default_prompt' => "You are an expert content strategist.\n\nA blog or website needs {count} distinct AI author persona(s) to produce varied, high-quality content.",
		),
		'taxonomy' => array(
			'key'            => 'taxonomy',
			'label'          => 'Taxonomy Generation',
			'description'    => 'Opening instruction for taxonomy term (categories / tags) generation. Use {type_label} as a placeholder for the taxonomy type.',
			'default_prompt' => 'Based on the following posts, generate appropriate {type_label} for a WordPress site.',
		),
	);

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the default group definition with translated user-facing strings.
	 *
	 * @return array{name:string,description:string,is_default:int}
	 */
	public static function get_default_group() {
		return array(
			'name'        => __( 'Default', 'ai-post-scheduler' ),
			'description' => __( 'Built-in default prompt template group shipped with the plugin.', 'ai-post-scheduler' ),
			'is_default'  => self::$default_group['is_default'],
		);
	}

	/**
	 * Return all built-in component definitions with translated labels and descriptions.
	 *
	 * @return array<string,array>
	 */
	public static function get_components() {
		$components = array();
		foreach ( self::$default_components as $key => $comp ) {
			$components[ $key ] = array(
				'key'            => $comp['key'],
				'label'          => __( $comp['label'], 'ai-post-scheduler' ),
				'description'    => __( $comp['description'], 'ai-post-scheduler' ),
				'default_prompt' => $comp['default_prompt'],
			);
		}
		return $components;
	}

	/**
	 * Return the definition for a single component with translated strings, or null if unknown.
	 *
	 * @param string $component_key Component key.
	 * @return array|null
	 */
	public static function get_component( $component_key ) {
		if ( ! isset( self::$default_components[ $component_key ] ) ) {
			return null;
		}
		$comp = self::$default_components[ $component_key ];
		return array(
			'key'            => $comp['key'],
			'label'          => __( $comp['label'], 'ai-post-scheduler' ),
			'description'    => __( $comp['description'], 'ai-post-scheduler' ),
			'default_prompt' => $comp['default_prompt'],
		);
	}

	/**
	 * Return the built-in default prompt for a component key.
	 *
	 * @param string $component_key Component key.
	 * @return string Built-in default prompt text, or empty string if the key is unknown.
	 */
	public static function get_component_prompt( $component_key ) {
		$component = self::get_component( $component_key );
		return $component !== null ? $component['default_prompt'] : '';
	}
}
