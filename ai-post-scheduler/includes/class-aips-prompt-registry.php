<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Prompt_Registry
 *
 * Declarative catalog of prompt templates and their parameter schemas.
 *
 * Each entry describes a named prompt type (e.g. 'post_title') with a
 * system instruction template and a typed schema of the parameters that
 * template expects. This is the single source of truth future callers can
 * consult instead of hand-rolling system instruction strings per builder.
 *
 * This is additive scaffolding: it does not replace or call into
 * AIPS_Prompt_Builder or any of its dedicated builder classes.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
class AIPS_Prompt_Registry {

    const TYPE_POST_CONTENT    = 'post_content';
    const TYPE_SECTION_CONTENT = 'section_content';
    const TYPE_POST_TITLE      = 'post_title';
    const TYPE_POST_EXCERPT    = 'post_excerpt';
    const TYPE_POST_SLUG       = 'post_slug';
    const TYPE_TAXONOMIES      = 'taxonomies';
    const TYPE_FEATURED_IMAGE  = 'featured_image';
    const TYPE_INLINE_IMAGE    = 'inline_image';
    const TYPE_TOPIC_RESEARCH  = 'topic_research';
    const TYPE_AUTHOR_TOPICS   = 'author_topics';
    const TYPE_AUTHOR_FEEDBACK = 'author_feedback';
    const TYPE_AI_EDIT         = 'ai_edit';

    /** @var array<string,array{system_instructions:string,required_params:array<string,string>,optional_params:array<string,string>}>|null */
    private $catalog = null;

    /**
     * Get the full catalog, building it on first access.
     *
     * @return array<string,array<string,mixed>>
     */
    private function get_catalog() {
        if (null !== $this->catalog) {
            return $this->catalog;
        }

        $this->catalog = array(
            self::TYPE_POST_CONTENT => array(
                'system_instructions' => 'You are an expert content writer producing a complete blog post article.',
                'required_params'     => array('topic' => 'string'),
                'optional_params'     => array('voice' => 'object', 'article_structure' => 'object', 'sources' => 'array'),
            ),
            self::TYPE_SECTION_CONTENT => array(
                'system_instructions' => 'You are an expert content writer producing a single section of a larger blog post article.',
                'required_params'     => array('topic' => 'string', 'section_heading' => 'string'),
                'optional_params'     => array('voice' => 'object'),
            ),
            self::TYPE_POST_TITLE => array(
                'system_instructions' => 'You are an expert copywriter producing a single blog post title based on its content.',
                'required_params'     => array('content' => 'string'),
                'optional_params'     => array('voice' => 'object', 'topic' => 'string'),
            ),
            self::TYPE_POST_EXCERPT => array(
                'system_instructions' => 'You are an expert copywriter producing a short, compelling excerpt for a blog post.',
                'required_params'     => array('title' => 'string', 'content' => 'string'),
                'optional_params'     => array('voice' => 'object', 'topic' => 'string'),
            ),
            self::TYPE_POST_SLUG => array(
                'system_instructions' => 'You are an SEO specialist producing a concise, keyword-rich URL slug for a blog post.',
                'required_params'     => array('title' => 'string', 'content' => 'string'),
                'optional_params'     => array('voice' => 'object', 'topic' => 'string'),
            ),
            self::TYPE_TAXONOMIES => array(
                'system_instructions' => 'You are a content classifier assigning relevant categories and tags to a blog post.',
                'required_params'     => array('title' => 'string', 'content' => 'string'),
                'optional_params'     => array('available_categories' => 'array', 'available_tags' => 'array'),
            ),
            self::TYPE_FEATURED_IMAGE => array(
                'system_instructions' => 'You are a visual art director producing an image generation prompt for a blog post featured image.',
                'required_params'     => array('topic' => 'string'),
                'optional_params'     => array('title' => 'string', 'content' => 'string'),
            ),
            self::TYPE_INLINE_IMAGE => array(
                'system_instructions' => 'You are a visual art director producing an image generation prompt for an in-content illustration.',
                'required_params'     => array('section_heading' => 'string'),
                'optional_params'     => array('content' => 'string'),
            ),
            self::TYPE_TOPIC_RESEARCH => array(
                'system_instructions' => 'You are a research assistant surfacing distinct, non-overlapping blog post topic ideas.',
                'required_params'     => array('niche' => 'string', 'count' => 'integer'),
                'optional_params'     => array('existing_topics' => 'array'),
            ),
            self::TYPE_AUTHOR_TOPICS => array(
                'system_instructions' => 'You are a content strategist producing topic ideas tailored to a specific author persona.',
                'required_params'     => array('author' => 'object', 'count' => 'integer'),
                'optional_params'     => array('existing_topics' => 'array'),
            ),
            self::TYPE_AUTHOR_FEEDBACK => array(
                'system_instructions' => 'You are an editorial reviewer providing structured feedback on an author persona\'s recent output.',
                'required_params'     => array('author' => 'object', 'recent_posts' => 'array'),
                'optional_params'     => array(),
            ),
            self::TYPE_AI_EDIT => array(
                'system_instructions' => 'You are an expert copy editor applying a specific, scoped edit to existing post content.',
                'required_params'     => array('content' => 'string', 'instruction' => 'string'),
                'optional_params'     => array('selection' => 'string'),
            ),
        );

        return $this->catalog;
    }

    /**
     * List all registered prompt type identifiers.
     *
     * @return string[]
     */
    public function get_registered_types() {
        return array_keys($this->get_catalog());
    }

    /**
     * Check whether a prompt type is registered.
     *
     * @param string $type
     * @return bool
     */
    public function has_type($type) {
        return array_key_exists($type, $this->get_catalog());
    }

    /**
     * Get the template definition for a prompt type.
     *
     * @param string $type
     * @return array{system_instructions:string,required_params:array<string,string>,optional_params:array<string,string>}|WP_Error
     */
    public function get_template($type) {
        $catalog = $this->get_catalog();

        if (!array_key_exists($type, $catalog)) {
            return new WP_Error(
                'aips_prompt_registry_unknown_type',
                sprintf('Unknown prompt type "%s".', $type)
            );
        }

        return $catalog[$type];
    }

    /**
     * Validate a set of parameters against a prompt type's schema.
     *
     * Checks that every required parameter is present, and that every
     * supplied parameter (required or optional) has a scalar/array/object
     * shape matching its declared type. Unknown parameter keys (not
     * declared as required or optional) are ignored rather than rejected,
     * so callers can pass through extra context without failing validation.
     *
     * @param string               $type
     * @param array<string,mixed>  $params
     * @return true|WP_Error
     */
    public function validate_params($type, array $params) {
        $template = $this->get_template($type);

        if (is_wp_error($template)) {
            return $template;
        }

        $missing = array();
        foreach ($template['required_params'] as $param_name => $expected_type) {
            if (!array_key_exists($param_name, $params)) {
                $missing[] = $param_name;
            }
        }

        if (!empty($missing)) {
            return new WP_Error(
                'aips_prompt_registry_missing_params',
                sprintf('Missing required parameter(s) for prompt type "%s": %s.', $type, implode(', ', $missing))
            );
        }

        $all_param_types = array_merge($template['required_params'], $template['optional_params']);
        $mismatched       = array();

        foreach ($all_param_types as $param_name => $expected_type) {
            if (!array_key_exists($param_name, $params)) {
                continue;
            }

            if (!$this->matches_type($params[$param_name], $expected_type)) {
                $mismatched[] = sprintf('%s (expected %s)', $param_name, $expected_type);
            }
        }

        if (!empty($mismatched)) {
            return new WP_Error(
                'aips_prompt_registry_invalid_param_type',
                sprintf('Invalid parameter type(s) for prompt type "%s": %s.', $type, implode(', ', $mismatched))
            );
        }

        return true;
    }

    /**
     * @param mixed  $value
     * @param string $expected_type One of 'string', 'integer', 'array', 'object'.
     * @return bool
     */
    private function matches_type($value, $expected_type) {
        switch ($expected_type) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value) || is_array($value);
            default:
                return true;
        }
    }
}
