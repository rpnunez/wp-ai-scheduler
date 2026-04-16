<?php
if (!defined('ABSPATH')) {
return;
}

/**
 * AIPS_System_Diagnostics_Service
 *
 * Aggregates diagnostic data from multiple system diagnostic providers.
 */
class AIPS_System_Diagnostics_Service {

/**
 * @var AIPS_System_Diagnostic_Provider_Interface[]
 */
private $providers = array();

/**
 * Constructor. Accepts an optional array of providers; when none are
 * supplied the default built-in providers are registered automatically.
 *
 * @param AIPS_System_Diagnostic_Provider_Interface[]|null $providers Optional provider list for testing or custom setups.
 */
public function __construct( array $providers = null ) {
if ( $providers !== null ) {
$this->providers = $providers;
} else {
$this->providers = array(
new AIPS_System_Diagnostics_Environment_Provider(),
new AIPS_System_Diagnostics_Scheduler_Provider(),
new AIPS_System_Diagnostics_Queue_Provider(),
new AIPS_System_Diagnostics_Logs_Provider(),
);
}
}

/**
 * Add a custom provider.
 *
 * @param AIPS_System_Diagnostic_Provider_Interface $provider
 */
public function add_provider( AIPS_System_Diagnostic_Provider_Interface $provider ) {
$this->providers[] = $provider;
}

/**
 * Aggregates system info from all registered providers.
 *
 * Known keys are returned in a consistent display order; any additional
 * keys contributed by custom providers are appended afterwards.
 *
 * @return array<string, mixed>
 */
public function get_system_info(): array {
$system_info = array();
foreach ( $this->providers as $provider ) {
$system_info = array_merge( $system_info, $provider->get_diagnostics() );
}

$expected_keys = array(
'environment', 'plugin', 'database', 'filesystem',
'cron', 'scheduler health', 'queue health',
'generation metrics', 'resilience', 'notifications', 'logs',
);

$ordered_info = array();
foreach ( $expected_keys as $key ) {
if ( isset( $system_info[ $key ] ) ) {
$ordered_info[ $key ] = $system_info[ $key ];
}
}

// Append any keys from custom providers that are not in the expected list.
foreach ( $system_info as $key => $value ) {
if ( ! isset( $ordered_info[ $key ] ) ) {
$ordered_info[ $key ] = $value;
}
}

return $ordered_info;
}
}
