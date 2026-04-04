<?php
/**
 * AutoloadSmoke — lightweight PSR-4 smoke-test sentinel.
 *
 * The sole purpose of this class is to act as a canary that confirms the
 * Composer PSR-4 autoloader is wired up and resolving the AIPS\ namespace
 * before the legacy AIPS_ classmap loader runs.  It carries no production
 * behaviour and should never be instantiated at runtime outside of tests.
 *
 * @package AIPS\Support
 */

namespace AIPS\Support;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class AutoloadSmoke {

/**
 * Return a fixed sentinel string so tests can assert the class is reachable.
 *
 * @return string
 */
public static function ping(): string {
return 'psr4-ok';
}
}
