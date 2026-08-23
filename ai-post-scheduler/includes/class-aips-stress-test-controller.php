<?php
/**
 * Stress Test Controller
 *
 * Renders the Stress Test admin page and serves its AJAX endpoints.
 *
 * Registered AJAX actions (all in AIPS_Ajax_Registry):
 *   aips_stress_test_run
 *   aips_stress_test_cleanup
 *   aips_stress_test_status
 *
 * Each case runs in its own request so a slow provider cannot blow the PHP time
 * limit for the whole suite; the browser sequences them.
 *
 * @package AI_Post_Scheduler
 * @since   3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Stress_Test_Controller extends AIPS_Ajax_Controller_Base {

    /**
     * Admin page slug.
     */
    const PAGE_SLUG = 'aips-stress-test';

    /**
     * Nonce action shared by every endpoint on this page.
     */
    const NONCE_ACTION = 'aips_stress_test';

    /**
     * @var AIPS_Stress_Test_Service
     */
    private $service;

    /**
     * @var array<string, string>
     */
    protected array $actions = array(
        'aips_stress_test_run'     => 'ajax_run',
        'aips_stress_test_cleanup' => 'ajax_cleanup',
        'aips_stress_test_status'  => 'ajax_status',
    );

    /**
     * Register AJAX hooks.
     *
     * @param AIPS_Stress_Test_Service|null $service Optional service override.
     */
    public function __construct($service = null) {
        $container = AIPS_Container::get_instance();
        $this->service = $service ?: $container->makeIfExists(AIPS_Stress_Test_Service::class, function() {
            return new AIPS_Stress_Test_Service();
        });

        parent::__construct();
    }

    // -----------------------------------------------------------------------
    // Page render
    // -----------------------------------------------------------------------

    /**
     * Render the Stress Test admin page.
     *
     * @param bool $embedded Whether the page is rendered inside a Diagnostics tab,
     *                       in which case the outer page chrome is suppressed.
     * @return void
     */
    public function render_page($embedded = false) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
        }

        $cases       = $this->service->get_cases();
        $environment = $this->service->get_environment();
        $test_data   = $this->service->count_test_data();

        include AIPS_PLUGIN_DIR . 'templates/admin/stress-test.php';
    }

    // -----------------------------------------------------------------------
    // AJAX endpoints
    // -----------------------------------------------------------------------

    /**
     * Run a single test case.
     *
     * @return void
     */
    public function ajax_run() {
        $this->verify_request(self::NONCE_ACTION);

        $case_id = isset($_POST['case']) ? sanitize_key(wp_unslash($_POST['case'])) : '';

        if ($case_id === '' || !$this->service->has_case($case_id)) {
            AIPS_Ajax_Response::error(__('Unknown test case.', 'ai-post-scheduler'), 'unknown_case');
        }

        // A bulk run can outlast the default limit; raise it where the host allows
        // rather than letting the request die mid-post. Kept in step with the
        // browser-side timeout in admin-stress-test.js so neither side gives up
        // while the other is still working.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled by some hosts.
        }

        AIPS_Ajax_Response::success(array('result' => $this->service->run($case_id)));
    }

    /**
     * Delete every post and attachment the page created.
     *
     * @return void
     */
    public function ajax_cleanup() {
        $this->verify_request(self::NONCE_ACTION);

        $deleted = $this->service->cleanup_test_data();

        AIPS_Ajax_Response::success(
            array(
                'deleted'   => $deleted,
                'test_data' => $this->service->count_test_data(),
            ),
            sprintf(
                /* translators: 1: post count, 2: attachment count */
                __('Removed %1$d posts and %2$d attachments.', 'ai-post-scheduler'),
                $deleted['posts'],
                $deleted['attachments']
            )
        );
    }

    /**
     * Current environment snapshot and leftover test-data counts.
     *
     * @return void
     */
    public function ajax_status() {
        $this->verify_request(self::NONCE_ACTION);

        AIPS_Ajax_Response::success(array(
            'environment' => $this->service->get_environment(),
            'test_data'   => $this->service->count_test_data(),
        ));
    }
}
