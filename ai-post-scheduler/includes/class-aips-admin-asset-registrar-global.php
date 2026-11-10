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
	 * Whether the History modal footer scaffold has been hooked this request.
	 *
	 * @var bool
	 */
	private static $history_modal_scaffold_registered = false;

	/**
	 * Whether the AI Edit modal footer scaffold has been hooked this request.
	 *
	 * @var bool
	 */
	private static $ai_edit_scaffold_registered = false;

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
			'aips-templates-script',
			AIPS_PLUGIN_URL . 'assets/js/templates.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'aips-datetime-script', 'aips-templates-script'),
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
			'hasUnsplashAccessKey' => !empty(trim((string) AIPS_Config::get_instance()->get_option('aips_unsplash_access_key'))),
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
			'aips-templates-script',
			AIPS_PLUGIN_URL . 'assets/js/templates.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'aips-datetime-script', 'aips-templates-script'),
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

		if (!self::$history_modal_scaffold_registered) {
			add_action('admin_footer', array($this, 'render_history_modal_scaffold'));
			self::$history_modal_scaffold_registered = true;
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

	/**
	 * Enqueue AI Edit modal assets and register footer scaffold.
	 *
	 * @return void
	 */
	public function enqueue_ai_edit() {
		wp_register_style('aips-design-tokens', AIPS_PLUGIN_URL . 'assets/css/design-tokens.css', array(), AIPS_VERSION);

		wp_enqueue_style(
			'aips-admin-ai-edit',
			AIPS_PLUGIN_URL . 'assets/css/admin-ai-edit.css',
			array('dashicons', 'aips-design-tokens'),
			AIPS_VERSION
		);

		if (!wp_script_is('aips-utilities-script', 'registered')) {
			$this->enqueue_history_modal_opener();
		}

		if (!wp_script_is('aips-admin-script', 'registered')) {
			wp_register_script(
				'aips-admin-script',
				AIPS_PLUGIN_URL . 'assets/js/admin.js',
				array('jquery', 'aips-utilities-script'),
				AIPS_VERSION,
				true
			);
			wp_localize_script('aips-admin-script', 'aipsAjax', array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('aips_ajax_nonce'),
			));
		}

		wp_enqueue_script(
			'aips-admin-ai-edit',
			AIPS_PLUGIN_URL . 'assets/js/admin-ai-edit.js',
			array('jquery', 'aips-utilities-script', 'aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-ai-edit', 'aipsAIEditL10n', array(
			'ajaxUrl'              => admin_url('admin-ajax.php'),
			'nonce'                => wp_create_nonce('aips_ajax_nonce'),
			'regenerate'           => __('Re-generate', 'ai-post-scheduler'),
			'regenerating'         => __('Regenerating...', 'ai-post-scheduler'),
			'regenerateAll'        => __('Regenerate All', 'ai-post-scheduler'),
			'regeneratingAll'      => __('Regenerating all components...', 'ai-post-scheduler'),
			'regenerateAllSuccess' => __('Components regenerated successfully.', 'ai-post-scheduler'),
			'regenerateAllError'   => __('Failed to regenerate all components.', 'ai-post-scheduler'),
			'regenerateSuccess'    => __('Component regenerated successfully!', 'ai-post-scheduler'),
			'regenerateError'      => __('Failed to regenerate component.', 'ai-post-scheduler'),
			'save'                 => __('Save Changes', 'ai-post-scheduler'),
			'saving'               => __('Saving...', 'ai-post-scheduler'),
			'saveSuccess'          => __('Post updated successfully!', 'ai-post-scheduler'),
			'saveError'            => __('Failed to update post.', 'ai-post-scheduler'),
			'loadError'            => __('Failed to load post components.', 'ai-post-scheduler'),
			'confirmClose'         => __('You have unsaved changes. Are you sure you want to close?', 'ai-post-scheduler'),
			'noChanges'            => __('No changes to save.', 'ai-post-scheduler'),
			'acceptAndReplace'     => __('Accept & Replace', 'ai-post-scheduler'),
			'discard'              => __('Discard', 'ai-post-scheduler'),
			'acceptAll'            => __('Accept All Regenerations', 'ai-post-scheduler'),
			'currentVersion'       => __('Current Version', 'ai-post-scheduler'),
			'regeneratedVersion'   => __('Regenerated Draft', 'ai-post-scheduler'),
			'stagedForSave'        => __('Staged for Save', 'ai-post-scheduler'),
			'regenerated'          => __('Regenerated', 'ai-post-scheduler'),
			'modified'             => __('Modified', 'ai-post-scheduler'),
			'revisionAiGenerated'  => __('AI Generated', 'ai-post-scheduler'),
			'revisionManualEdit'   => __('Manual Edit', 'ai-post-scheduler'),
			'revisionRestored'     => __('Restored Version', 'ai-post-scheduler'),
			'revisionOriginal'     => __('Original Version', 'ai-post-scheduler'),
			'revisionUnknown'      => __('Revision', 'ai-post-scheduler'),
			'imageStatusCompleted' => __('Completed', 'ai-post-scheduler'),
			'imageStatusFailed'    => __('Failed', 'ai-post-scheduler'),
			'imageStatusPending'   => __('Pending', 'ai-post-scheduler'),
			'noImage'              => __('No image', 'ai-post-scheduler'),
		));

		if (!self::$ai_edit_scaffold_registered) {
			add_action('admin_footer', array($this, 'render_ai_edit_modal_scaffold'));
			self::$ai_edit_scaffold_registered = true;
		}
	}

	/**
	 * Render the AI Edit modal scaffold HTML in the admin footer.
	 *
	 * @return void
	 */
	public function render_ai_edit_modal_scaffold() {
		static $rendered = false;
		if ($rendered) {
			return;
		}
		$rendered = true;
		include AIPS_PLUGIN_DIR . 'templates/partials/ai-edit-modal.php';
	}
}
