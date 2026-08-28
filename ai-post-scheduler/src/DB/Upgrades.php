<?php
/**
 * Backward-compatibility shim.
 *
 * Upgrades was renamed to DBMigrations in 2.5.1.
 * This class is retained solely so that any third-party code or database
 * tooling that references AIPS_Upgrades or AIPS\DB\Upgrades by name continues to work. All
 * logic now lives in DBMigrations (src/DB/DBMigrations.php).
 *
 * @deprecated 2.5.1 Use DBMigrations directly.
 * @package AI_Post_Scheduler
 */

namespace AIPS\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upgrades extends DBMigrations {}
