<?php
/**
 * Admin Asset Registrar: Global
 *
 * Handles enqueueing of global styles, scripts, and the History modal opener scaffold.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Registrar_Global
 */
class AIPS_Admin_Asset_Registrar_Global {

	/**
	 * Enqueue global plugin assets.
	 *
	 * @return void
	 */
	public function enqueue_global() {
		wp_enqueue_style(
			'aips-design-tokens',
			AIPS_PLUGIN_URL . 'assets/css/design-tokens.css',
			array(),
			AIPS_VERSION
		);

		wp_enqueue_style(
			'aips-admin-style',
			AIPS_PLUGIN_URL . 'assets/css/admin.css',
			array('aips-design-tokens'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-datetime-script',
			AIPS_PLUGIN_URL . 'assets/js/datetime.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'aips-datetime-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', array(
			'closeLabel'             => __('Close notification', 'ai-post-scheduler'),
			'fieldRequired'          => __('%s is required.', 'ai-post-scheduler'),
			'estimatedTimeRemaining' => __('Estimated time remaining: %s', 'ai-post-scheduler'),
			'generationComplete'     => __('Generation complete!', 'ai-post-scheduler'),
			'takingLonger'           => __('Taking a little bit longer than expected…', 'ai-post-scheduler'),
			'seconds'                => __('seconds', 'ai-post-scheduler'),
			'minute'                 => __('1 minute', 'ai-post-scheduler'),
			'minutes'                => __('%d minutes', 'ai-post-scheduler'),
			'minutesSeconds'         => __('%dm %ds', 'ai-post-scheduler'),
		));

		wp_enqueue_script(
			'aips-templates-script',
			AIPS_PLUGIN_URL . 'assets/js/templates.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-admin-script',
			AIPS_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery', 'aips-utilities-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-script', 'aipsAjax', array(
			'ajaxUrl'         => admin_url('admin-ajax.php'),
			'nonce'           => wp_create_nonce('aips_ajax_nonce'),
			'schedulePageUrl' => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
		));

		$this->enqueue_history_modal_opener_script();

		// Shared strings needed on every plugin admin page.
		wp_localize_script('aips-admin-script', 'aipsAdminL10n', array(
			'errorOccurred'       => __('An error occurred.', 'ai-post-scheduler'),
			'errorTryAgain'       => __('An error occurred. Please try again.', 'ai-post-scheduler'),
			'confirmCancelButton' => __('No, cancel', 'ai-post-scheduler'),
			'confirmDeleteButton' => __('Yes, delete', 'ai-post-scheduler'),
			'saving'              => __('Saving...', 'ai-post-scheduler'),
			'generating'          => __('Generating...', 'ai-post-scheduler'),
			'generationFailed'    => __('Generation failed.', 'ai-post-scheduler'),
			'activeLabel'         => __('Active', 'ai-post-scheduler'),
			'inactiveLabel'       => __('Inactive', 'ai-post-scheduler'),
			'defaultLabel'        => __('Default', 'ai-post-scheduler'),
			'noVoiceDefault'      => __('No Voice (Use Default)', 'ai-post-scheduler'),
			'noneOption'          => __('None', 'ai-post-scheduler'),
			'persistTableFilters' => (bool) AIPS_Config::get_instance()->get_option('aips_persist_table_filters'),
		));
	}

	/**
	 * Enqueue only the assets required for the History modal opener on native
	 * WordPress post/admin screens.
	 *
	 * @return void
	 */
	public function enqueue_history_modal_opener() {
		wp_enqueue_style(
			'aips-design-tokens',
			AIPS_PLUGIN_URL . 'assets/css/design-tokens.css',
			array(),
			AIPS_VERSION
		);

		wp_enqueue_style(
			'aips-admin-style',
			AIPS_PLUGIN_URL . 'assets/css/admin.css',
			array('aips-design-tokens'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-datetime-script',
			AIPS_PLUGIN_URL . 'assets/js/datetime.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'aips-datetime-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', array(
			'closeLabel'             => __('Close notification', 'ai-post-scheduler'),
			'fieldRequired'          => __('%s is required.', 'ai-post-scheduler'),
			'estimatedTimeRemaining' => __('Estimated time remaining: %s', 'ai-post-scheduler'),
			'generationComplete'     => __('Generation complete!', 'ai-post-scheduler'),
			'takingLonger'           => __('Taking a little bit longer than expected…', 'ai-post-scheduler'),
			'seconds'                => __('seconds', 'ai-post-scheduler'),
			'minute'                 => __('1 minute', 'ai-post-scheduler'),
			'minutes'                => __('%d minutes', 'ai-post-scheduler'),
			'minutesSeconds'         => __('%dm %ds', 'ai-post-scheduler'),
		));

		$this->enqueue_history_modal_opener_script();
	}

	/**
	 * Enqueue/localize the History modal opener script.
	 *
	 * @return void
	 */
	public function enqueue_history_modal_opener_script() {
		wp_enqueue_script(
			'aips-admin-history',
			AIPS_PLUGIN_URL . 'assets/js/admin-history.js',
			array('jquery', 'aips-utilities-script', 'heartbeat'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-history', 'aipsHistoryModalAjax', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('aips_ajax_nonce'),
		));

		wp_localize_script('aips-admin-history', 'aipsHistoryModalL10n', array(
			'historyDetailsTitle' => __('History Details', 'ai-post-scheduler'),
			'closeModal'          => __('Close modal', 'ai-post-scheduler'),
			'loading'             => __('Loading…', 'ai-post-scheduler'),
			'loadingLogs'         => __('Loading logs…', 'ai-post-scheduler'),
			'showDetails'         => __('Show details', 'ai-post-scheduler'),
			'hideDetails'         => __('Hide details', 'ai-post-scheduler'),
			'copyDetails'         => __('Copy', 'ai-post-scheduler'),
			'copiedDetails'       => __('Copied!', 'ai-post-scheduler'),
			'copyDiagnostic'      => __('Copy diagnostic', 'ai-post-scheduler'),
			'invalidHistoryId'    => __('Invalid history ID.', 'ai-post-scheduler'),
			'loadingFailed'       => __('Failed to load history modal.', 'ai-post-scheduler'),
			'loadingError'        => __('Error loading history modal.', 'ai-post-scheduler'),
		));

		static $scaffold_registered = false;
		if (!$scaffold_registered) {
			add_action('admin_footer', array($this, 'render_history_modal_scaffold'));
			$scaffold_registered = true;
		}
	}

	/**
	 * Output the History modal scaffold HTML in the admin footer.
	 *
	 * @return void
	 */
	public function render_history_modal_scaffold() {
		?>
		<div id="aips-history-modal" class="aips-modal" style="display: none;" aria-hidden="true">
			<div class="aips-modal-content aips-modal-large">
				<div class="aips-modal-header">
					<div class="aips-history-modal-header-main">
						<h3 id="aips-history-modal-title"><?php esc_html_e('History Details', 'ai-post-scheduler'); ?></h3>
						<div id="aips-history-modal-actions" class="aips-history-modal-header-links"></div>
					</div>
					<div class="aips-history-modal-header-side">
						<div class="aips-history-modal-header-tools">
							<div id="aips-history-modal-status"></div>
							<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
						</div>
						<div class="aips-history-modal-header-diagnostic"></div>
					</div>
				</div>
				<div class="aips-modal-body" id="aips-history-modal-content" style="white-space: normal;"></div>
			</div>
		</div>
		<?php
	}
}
