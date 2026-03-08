<?php
/**
 * Markdown Parser Utility.
 *
 * Provides functionality to convert basic Markdown syntax into HTML
 * suitable for WordPress post content.
 *
 * @package AI_Post_Scheduler\Markdown
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Markdown_Parser
 *
 * Handles detection and conversion of Markdown to HTML.
 */
class AIPS_Markdown_Parser {

    /**
     * Determine if text appears to be Markdown.
     *
     * @param string $content Text content.
     * @return bool
     */
    public function is_markdown($content) {
        $markdown_patterns = array(
            '/^#{1,6}\s+/m',
            '/^\s*[-*+]\s+/m',
            '/^\s*\d+\.\s+/m',
            '/```[\s\S]*?```/m',
            '/\[[^\]]+\]\([^\)]+\)/',
            '/^\|.+\|\s*$/m',
        );

        foreach ($markdown_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if text already contains HTML markup.
     *
     * @param string $content Text content.
     * @return bool
     */
    public function contains_html($content) {
        return preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $content) === 1;
    }

    /**
     * Convert common Markdown structures into HTML.
     *
     * @param string $content Markdown text.
     * @return string HTML output.
     */
    public function parse($content) {
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        $lines = explode("\n", $content);
        $output = array();
        $paragraph_lines = array();
        $list_items = array();
        $list_type = '';
        $in_code_block = false;
        $code_language = '';
        $code_lines = array();

        foreach ($lines as $line) {
            if (preg_match('/^```\s*([a-zA-Z0-9_-]+)?\s*$/', $line, $fence_match)) {
                if ($in_code_block) {
                    $code_class = $code_language !== '' ? ' class="language-' . esc_attr($code_language) . '"' : '';
                    $output[] = '<pre><code' . $code_class . '>' . esc_html(implode("\n", $code_lines)) . '</code></pre>';
                    $in_code_block = false;
                    $code_language = '';
                    $code_lines = array();
                } else {
                    $this->flush_markdown_paragraph($paragraph_lines, $output);
                    $this->flush_markdown_list($list_items, $list_type, $output);
                    $in_code_block = true;
                    $code_language = isset($fence_match[1]) ? trim($fence_match[1]) : '';
                }
                continue;
            }

            if ($in_code_block) {
                $code_lines[] = $line;
                continue;
            }

            if (trim($line) === '') {
                $this->flush_markdown_paragraph($paragraph_lines, $output);
                $this->flush_markdown_list($list_items, $list_type, $output);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $heading_match)) {
                $this->flush_markdown_paragraph($paragraph_lines, $output);
                $this->flush_markdown_list($list_items, $list_type, $output);
                $heading_level = strlen($heading_match[1]);
                $heading_text = $this->format_inline_markdown($heading_match[2]);
                $output[] = '<h' . $heading_level . '>' . $heading_text . '</h' . $heading_level . '>';
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $unordered_match)) {
                $this->flush_markdown_paragraph($paragraph_lines, $output);
                if ($list_type !== '' && $list_type !== 'ul') {
                    $this->flush_markdown_list($list_items, $list_type, $output);
                }
                $list_type = 'ul';
                $list_items[] = $this->format_inline_markdown($unordered_match[1]);
                continue;
            }

            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $ordered_match)) {
                $this->flush_markdown_paragraph($paragraph_lines, $output);
                if ($list_type !== '' && $list_type !== 'ol') {
                    $this->flush_markdown_list($list_items, $list_type, $output);
                }
                $list_type = 'ol';
                $list_items[] = $this->format_inline_markdown($ordered_match[1]);
                continue;
            }

            if (preg_match('/^>\s*(.+)$/', $line, $quote_match)) {
                $this->flush_markdown_paragraph($paragraph_lines, $output);
                $this->flush_markdown_list($list_items, $list_type, $output);
                $output[] = '<blockquote><p>' . $this->format_inline_markdown($quote_match[1]) . '</p></blockquote>';
                continue;
            }

            if ($list_type !== '') {
                $this->flush_markdown_list($list_items, $list_type, $output);
            }

            $paragraph_lines[] = trim($line);
        }

        if ($in_code_block) {
            $code_class = $code_language !== '' ? ' class="language-' . esc_attr($code_language) . '"' : '';
            $output[] = '<pre><code' . $code_class . '>' . esc_html(implode("\n", $code_lines)) . '</code></pre>';
        }

        $this->flush_markdown_paragraph($paragraph_lines, $output);
        $this->flush_markdown_list($list_items, $list_type, $output);

        return implode("\n", $output);
    }

    /**
     * Flush buffered paragraph lines into HTML output.
     *
     * @param array $paragraph_lines Paragraph line buffer.
     * @param array $output Output buffer.
     * @return void
     */
    private function flush_markdown_paragraph(&$paragraph_lines, &$output) {
        if (empty($paragraph_lines)) {
            return;
        }

        $paragraph = implode(' ', $paragraph_lines);
        $output[] = '<p>' . $this->format_inline_markdown($paragraph) . '</p>';
        $paragraph_lines = array();
    }

    /**
     * Flush buffered list items into HTML output.
     *
     * @param array  $list_items List item buffer.
     * @param string $list_type  List type (ul|ol).
     * @param array  $output     Output buffer.
     * @return void
     */
    private function flush_markdown_list(&$list_items, &$list_type, &$output) {
        if (empty($list_items) || empty($list_type)) {
            $list_items = array();
            $list_type = '';
            return;
        }

        $output[] = '<' . $list_type . '><li>' . implode('</li><li>', $list_items) . '</li></' . $list_type . '>';
        $list_items = array();
        $list_type = '';
    }

    /**
     * Convert simple inline Markdown syntax to HTML.
     *
     * @param string $text Text fragment.
     * @return string
     */
    private function format_inline_markdown($text) {
        $formatted = esc_html(trim($text));

        $formatted = preg_replace('/`([^`]+)`/', '<code>$1</code>', $formatted);
        $formatted = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $formatted);
        $formatted = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $formatted);
        $formatted = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $formatted);
        $formatted = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $formatted);

        $formatted = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/', function($matches) {
            return '<a href="' . esc_url($matches[2]) . '">' . $matches[1] . '</a>';
        }, $formatted);

        return $formatted;
    }
}
