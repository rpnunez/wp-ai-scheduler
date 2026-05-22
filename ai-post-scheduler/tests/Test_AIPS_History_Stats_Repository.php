<?php
/**
 * Tests for AIPS_History_Stats_Repository
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    die;
}

require_once dirname(__DIR__) . '/includes/class-aips-history-stats-repository.php';

class Test_AIPS_History_Stats_Repository extends WP_UnitTestCase {

    /**
     * @var AIPS_History_Stats_Repository
     */
    private $repository;

    public function setUp(): void {
        parent::setUp();
        $this->repository = AIPS_History_Stats_Repository::instance();

        // Ensure wp_date is mocked for limited mode
        if (!function_exists('wp_date')) {
            function wp_date($format, $timestamp = null, $timezone = null) {
                return date($format, $timestamp ?: time());
            }
        }

        if (!function_exists('wp_timezone')) {
            function wp_timezone() {
                return new DateTimeZone('UTC');
            }
        }
    }

    public function test_instance_is_singleton() {
        $repo_a = AIPS_History_Stats_Repository::instance();
        $repo_b = AIPS_History_Stats_Repository::instance();

        $this->assertSame($repo_a, $repo_b);
        $this->assertInstanceOf(AIPS_History_Stats_Repository::class, $repo_a);
    }

    public function test_stats_return_arrays() {
        $stats = $this->repository->get_stats();
        $this->assertIsArray($stats);

        $daily_counts = $this->repository->get_daily_generation_counts();
        $this->assertIsArray($daily_counts);
    }

    public function test_template_stats_returns_integer() {
        $count = $this->repository->get_template_stats(1);
        $this->assertIsInt($count);
    }
}
