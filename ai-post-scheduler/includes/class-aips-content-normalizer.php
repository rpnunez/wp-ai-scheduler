<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * Normalizes generated content for WordPress and handles truncation.
 *
 * Extracted from AIPS_Generator to adhere to Single Responsibility Principle.
 */
class AIPS_Content_Normalizer {

    /**
     * @var mixed Markdown parser instance.
     */
    private $markdown_parser;

    /**
     * Constructor for AIPS_Content_Normalizer.
     *
     * @param mixed $markdown_parser Parser to handle markdown content.
     */
    public function __construct($markdown_parser) {
        $this->markdown_parser = $markdown_parser;
    }

    /**
     * Prepares generated content for WordPress insertion.
     *
     * Trims content, optionally parses Markdown, and applies wp_kses_post.
     *
     * @param string $content The raw content from the AI generator.
     * @return string Sanitized HTML content.
     */
    public function normalize_generated_content_for_wordpress($content) {
        if (!is_string($content)) {
            return '';
        }

        $normalized_content = trim($content);

        if ($normalized_content === '') {
            return '';
        }

        if ($this->markdown_parser && method_exists($this->markdown_parser, 'is_markdown') && $this->markdown_parser->is_markdown($normalized_content) && !$this->markdown_parser->contains_html($normalized_content)) {
            $normalized_content = $this->markdown_parser->parse($normalized_content);
        }

        return wp_kses_post($normalized_content);
    }

    /**
     * Smart truncate content to preserve key information from both beginning and end.
     *
     * Instead of simply truncating from the beginning, this method takes content
     * from both the start and end of the text to provide better context for AI
     * variable resolution. Articles often have introductions at the start and
     * conclusions/summaries at the end, both of which are valuable for context.
     *
     * @param string $content    The content to truncate.
     * @param int    $max_length Maximum total length of the result. Minimum of 100 chars.
     * @return string Truncated content with beginning and end preserved.
     */
    public function smart_truncate_content($content, $max_length = 2000) {
        $content_length = mb_strlen($content);

        // If content fits within limit, return as-is
        if ($content_length <= $max_length) {
            return $content;
        }

        // Define separator and calculate its length
        $separator = "\n\n[...]\n\n";
        $separator_length = mb_strlen($separator);

        // Ensure minimum length to avoid negative values
        $min_length = $separator_length + 40; // At least 20 chars on each end
        if ($max_length < $min_length) {
            $max_length = $min_length;
        }

        // Calculate how much to take from each end
        // Take 60% from the beginning (introductions, key points) and 40% from the end (conclusions)
        $available_length = $max_length - $separator_length;
        $start_length = (int) ($available_length * 0.6);
        $end_length = $available_length - $start_length;

        $start_content = mb_substr($content, 0, $start_length);
        $end_content = mb_substr($content, -$end_length);

        return $start_content . $separator . $end_content;
    }
}
