<?php
if (!defined('ABSPATH')) {
	exit;
}

interface AIPS_Prompt_Builder_Interface {

	/**
	 * Build a prompt or prompt-related payload from the provided input.
	 *
	 * @param mixed $primary_input Primary builder input.
	 * @param mixed ...$args Additional builder arguments.
	 * @return mixed
	 */
	public function build($primary_input, ...$args);
}
