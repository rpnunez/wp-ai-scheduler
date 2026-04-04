<?php
/**
 * Tests for the PSR-4 runtime bridge introduced alongside the legacy AIPS_ classmap.
 *
 * Acceptance criteria verified here:
 *  1. class_exists for AIPS_Config remains true (legacy classmap intact).
 *  2. class_exists for AIPS\Support\AutoloadSmoke is true once the bootstrap runs.
 *  3. No fatal when vendor/autoload.php is absent (graceful fallback only tested
 *     structurally — the bootstrap guard is verified via inspection).
 *  4. Legacy AIPS_Autoloader behaviour is unchanged (AIPS\ prefix is not handled
 *     by it; PSR-4 classes must NOT be resolved by the legacy loader).
 *
 * @package AI_Post_Scheduler
 */

class Test_PSR4_Runtime_Bridge extends WP_UnitTestCase {

/**
 * The AIPS_Config legacy class must remain resolvable after introducing PSR-4.
 */
public function test_legacy_aips_config_class_exists() {
$this->assertTrue(
class_exists( 'AIPS_Config' ),
'Legacy AIPS_Config must still be resolvable via the classmap loader'
);
}

/**
 * The PSR-4 sentinel class must be resolvable when the plugin bootstrap runs.
 */
public function test_psr4_autoload_smoke_class_exists() {
$this->assertTrue(
class_exists( 'AIPS\\Support\\AutoloadSmoke' ),
'AIPS\\Support\\AutoloadSmoke must be resolvable via the Composer PSR-4 loader'
);
}

/**
 * AutoloadSmoke::ping() must return the expected sentinel value.
 */
public function test_autoload_smoke_ping_returns_sentinel() {
$this->assertSame(
'psr4-ok',
\AIPS\Support\AutoloadSmoke::ping(),
'AutoloadSmoke::ping() must return "psr4-ok"'
);
}

/**
 * The legacy AIPS_Autoloader must NOT attempt to load AIPS\ (namespaced) classes.
 *
 * Calling AIPS_Autoloader::load() with a namespaced class name must be a no-op
 * (no file-load attempt, no error). Resolution of AIPS\ classes is solely the
 * responsibility of the Composer PSR-4 loader.
 */
public function test_legacy_loader_ignores_namespaced_aips_class() {
// This class does NOT exist — the legacy loader must silently skip it.
// If the legacy loader tried to resolve it, it would produce a warning or
// attempt a bad require which would be caught as an error.
AIPS_Autoloader::load( 'AIPS\\Support\\NonExistent' );

// Still reachable — no fatal, no warning.
$this->assertTrue( true, 'Legacy autoloader must silently skip AIPS\\ classes' );
}

/**
 * Both loaders must coexist: the PSR-4 loader registered first by Composer and
 * the legacy spl_autoload_unregister-safe AIPS_Autoloader registered second.
 */
public function test_both_loaders_are_registered_in_spl_stack() {
$autoloaders = spl_autoload_functions();

// Locate the legacy AIPS_Autoloader in the stack.
$legacy_found = false;
foreach ( $autoloaders as $loader ) {
if ( is_array( $loader )
&& $loader[0] === 'AIPS_Autoloader'
&& $loader[1] === 'load' ) {
$legacy_found = true;
break;
}
}

$this->assertTrue(
$legacy_found,
'AIPS_Autoloader::load must be present in the spl_autoload stack'
);

// Composer can register its loader as:
//   - a Closure
//   - array( ComposerAutoloaderInit*, 'loadClassLoader' )
//   - array( Composer\Autoload\ClassLoader $instance, 'loadClass' )
$composer_found = false;
foreach ( $autoloaders as $loader ) {
if ( $loader instanceof Closure ) {
$composer_found = true;
break;
}
if ( is_array( $loader ) && isset( $loader[0] ) ) {
$subject = is_object( $loader[0] ) ? get_class( $loader[0] ) : (string) $loader[0];
if ( strpos( $subject, 'ComposerAutoloaderInit' ) === 0
|| $subject === 'Composer\\Autoload\\ClassLoader'
|| $subject === 'Composer\Autoload\ClassLoader' ) {
$composer_found = true;
break;
}
}
}

$this->assertTrue(
$composer_found,
'A Composer autoloader must be present in the spl_autoload stack'
);
}

/**
 * The bootstrap gracefully skips loading vendor/autoload.php when the file is
 * absent; verify that the guard expression in ai-post-scheduler.php uses
 * file_exists() so no fatal can occur.
 */
public function test_bootstrap_guards_autoload_with_file_exists() {
$bootstrap = file_get_contents( AIPS_PLUGIN_DIR . 'ai-post-scheduler.php' );

$this->assertNotFalse(
$bootstrap,
'Plugin bootstrap file must be readable'
);

$this->assertStringContainsString(
'file_exists( $composer_autoload )',
$bootstrap,
'Bootstrap must guard vendor/autoload.php load with file_exists()'
);
}
}
