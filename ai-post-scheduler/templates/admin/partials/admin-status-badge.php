<?php
/**
 * Admin Status Badge Partial
 *
 * Renders consistent status badges with tokens (success, warning, error, info, neutral).
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

echo AIPS_Admin_UI_Primitives::render_status_badge($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
