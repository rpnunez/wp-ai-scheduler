<?php
/**
 * AI JSON Extractor
 *
 * Extracts and sanitizes JSON from potentially malformed or conversational AI responses.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_AI_JSON_Extractor
 *
 * Handles the extraction of JSON objects/arrays from raw AI text responses.
 * Includes sanitization for control characters and malformed structures.
 */
class AIPS_AI_JSON_Extractor {

    /**
     * Extract the first balanced JSON object/array from text.
     *
     * @param string $text Raw AI text response.
     * @return string|WP_Error Balanced JSON fragment or WP_Error.
     */
    public function extract($text) {
        $text = trim((string) $text);

        // Remove common markdown wrappers.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim((string) $text);

        $start_pos_obj = strpos($text, '{');
        $start_pos_arr = strpos($text, '[');

        if ($start_pos_obj === false && $start_pos_arr === false) {
            return new WP_Error('json_extract_failed', __('No JSON start token found in AI response.', 'ai-post-scheduler'));
        }

        if ($start_pos_obj === false) {
            $start_pos = $start_pos_arr;
        } elseif ($start_pos_arr === false) {
            $start_pos = $start_pos_obj;
        } else {
            $start_pos = min($start_pos_obj, $start_pos_arr);
        }

        $slice = substr($text, $start_pos);

        $in_string = false;
        $escape    = false;
        $stack     = array();
        $length    = strlen($slice);

        for ($i = 0; $i < $length; $i++) {
            $ch = $slice[$i];

            if ($in_string) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $in_string = false;
                }

                continue;
            }

            if ($ch === '"') {
                $in_string = true;
                continue;
            }

            if ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
                continue;
            }

            if ($ch === '}' || $ch === ']') {
                if (empty($stack)) {
                    return new WP_Error('json_extract_failed', __('JSON appears malformed (unexpected closing token).', 'ai-post-scheduler'));
                }

                $open = array_pop($stack);
                if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
                    return new WP_Error('json_extract_failed', __('JSON appears malformed (mismatched tokens).', 'ai-post-scheduler'));
                }

                if (empty($stack)) {
                    $candidate = substr($slice, 0, $i + 1);
                    return $this->sanitize($candidate);
                }
            }
        }

        return new WP_Error('json_extract_failed', __('JSON appears truncated before closing token.', 'ai-post-scheduler'));
    }

    /**
     * Normalize control characters in a candidate JSON fragment.
     *
     * @param string $candidate Candidate JSON fragment.
     * @return string
     */
    private function sanitize($candidate) {
        return preg_replace_callback(
            '/"((?:[^"\\\\]|\\\\.)*)"/',
            function ($m) {
                $inner = $m[1];
                $inner = str_replace("\r", '\\r', $inner);
                $inner = str_replace("\n", '\\n', $inner);
                $inner = str_replace("\t", '\\t', $inner);
                $inner = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $inner);

                return '"' . $inner . '"';
            },
            (string) $candidate
        );
    }
}