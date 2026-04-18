<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AIPS_System_Diagnostic_Provider_Interface
 *
 * Defines the contract for diagnostic providers that supply system information.
 */
interface AIPS_System_Diagnostic_Provider_Interface {

	/**
	 * Retrieves the diagnostic data for the provider's domain.
	 *
	 * @return array<string, mixed> Array containing diagnostic keys and data.
	 */
	public function get_diagnostics(): array;
}
