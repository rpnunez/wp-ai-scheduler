<?php
/**
 * Backward-compatibility shim.
 *
 * AIPS_Upgrades was renamed to AIPS_DB_Migrations in 2.5.1.
 * This file is retained solely so that any third-party code or database
 * tooling that references AIPS_Upgrades by name continues to work. All
 * logic now lives in AIPS_DB_Migrations (class-aips-db-migrations.php).
 *
 * @deprecated 2.5.1 Use AIPS_DB_Migrations directly.
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure the real class is loaded before creating the alias.
if ( ! class_exists( 'AIPS_DB_Migrations' ) ) {
	require_once __DIR__ . '/class-aips-db-migrations.php';
}

class_alias( 'AIPS_DB_Migrations', 'AIPS_Upgrades' );
