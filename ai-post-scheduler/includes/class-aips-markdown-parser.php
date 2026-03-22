<?php
/**
 * Backward-compatibility shim for Markdown parser.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AIPS_Markdown_Parser', false) && class_exists('AIPS\\Services\\MarkdownParser')) {
    class_alias('AIPS\\Services\\MarkdownParser', 'AIPS_Markdown_Parser');
}
