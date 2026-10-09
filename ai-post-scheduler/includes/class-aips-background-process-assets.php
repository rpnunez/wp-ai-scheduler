<?php
/**
 * Background Process Assets
 *
 * Enqueues the shared background-process script and styles (live status via
 * Heartbeat, pulsing indicators, control buttons) wherever they are needed:
 * the admin toolbar, the Internal Links page and the Diagnostics tab.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Background_Process_Assets {

	/**
	 * Enqueue the script and styles once per request.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		wp_enqueue_style(
			'aips-background-processes',
			AIPS_PLUGIN_URL . 'assets/css/background-processes.css',
			array(),
			AIPS_VERSION
		);

		if (wp_script_is('aips-background-processes', 'enqueued')) {
			return;
		}

		wp_enqueue_script(
			'aips-background-processes',
			AIPS_PLUGIN_URL . 'assets/js/background-processes.js',
			array('jquery', 'heartbeat'),
			AIPS_VERSION,
			true
		);

		$manager = AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class);

		wp_localize_script('aips-background-processes', 'aipsBgL10n', array(
			'ajaxUrl'        => admin_url('admin-ajax.php'),
			'nonce'          => wp_create_nonce(AIPS_Background_Processes_Controller::NONCE_ACTION),
			'initial'        => $manager->get_summary(),
			'serverTime'     => time(),
			'statusLabels'   => AIPS_Background_Process_Base::get_status_labels(),
			'lastRun'        => __('last run: %s', 'ai-post-scheduler'),
			'resumesIn'      => __('Resumes in %s', 'ai-post-scheduler'),
			'lessThanMinute' => __('under a minute', 'ai-post-scheduler'),
			'minutes'        => __('min', 'ai-post-scheduler'),
			'hours'          => __('h', 'ai-post-scheduler'),
			'days'           => __('d', 'ai-post-scheduler'),
			'noneRunning'    => __('No background processes running', 'ai-post-scheduler'),
			'playing'        => __('Playing', 'ai-post-scheduler'),
			'pause'          => __('Pause', 'ai-post-scheduler'),
			'resume'         => __('Resume', 'ai-post-scheduler'),
			'stop'           => __('Stop', 'ai-post-scheduler'),
			'cancel'         => __('Cancel', 'ai-post-scheduler'),
			'start'          => __('Start', 'ai-post-scheduler'),
			'startHeading'   => __('Start background process', 'ai-post-scheduler'),
			'budgetLabel'    => __('AI call budget (optional)', 'ai-post-scheduler'),
			'budgetHelp'     => __('Pause this run after it has made this many AI calls. 0 means no limit.', 'ai-post-scheduler'),
			'startFallback'  => __('Start this process? It runs in the background and can be paused or stopped at any time.', 'ai-post-scheduler'),
			'stopHeading'    => __('Stop process', 'ai-post-scheduler'),
			'confirmStop'    => __('Stop this process? Work already done is kept, but it will not continue unless you start it again.', 'ai-post-scheduler'),
			'confirmStopAgain' => __('Click Stop again within 5 seconds to stop this process.', 'ai-post-scheduler'),
			'startElsewhere'   => __('Open Diagnostics > Background Processes to start this process.', 'ai-post-scheduler'),
			'requestFailed'  => __('Request failed. Please try again.', 'ai-post-scheduler'),
		));
	}
}
