<?php
/**
 * Logger Interface
 *
 * Defines the contract for logging operations.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.1
 */

namespace AIPS\Core;

if (!defined('ABSPATH')) {
	exit;
}

interface LoggerInterface {

	/**
	 * Write a log entry.
	 *
	 * @param string $message Message.
	 * @param string $level Log level.
	 * @param array  $context Context payload.
	 * @return void
	 */
	public function log($message, $level = 'info', $context = array());

	/**
	 * Write a visible separator entry.
	 *
	 * @param string $text Separator text.
	 * @return void
	 */
	public function addSeparator($text);
}
