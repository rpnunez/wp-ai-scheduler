<?php
/**
 * Admin Asset Registrar: Management & Configuration
 *
 * Handles enqueueing for Taxonomy, Sources, Onboarding, and Settings.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Registrar_Management
 */
class AIPS_Admin_Asset_Registrar_Management {

	/**
	 * Enqueue assets for the taxonomy page.
	 *
	 * @return void
	 */
	public function enqueue_taxonomy() {
		wp_enqueue_style(
			'aips-authors-style',
			AIPS_PLUGIN_URL . 'assets/css/authors.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-taxonomy',
			AIPS_PLUGIN_URL . 'assets/js/taxonomy.js',
			array('jquery', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-taxonomy', 'aipsTaxonomyL10n', array(
			'nonce'              => wp_create_nonce('aips_ajax_nonce'),
			'selectTaxonomyType' => __('Please select a taxonomy type.', 'ai-post-scheduler'),
			'selectPost'         => __('Please select at least one post.', 'ai-post-scheduler'),
			'selectAction'       => __('Please select an action.', 'ai-post-scheduler'),
			'selectItem'         => __('Please select at least one item.', 'ai-post-scheduler'),
			'confirmBulkAction'  => __('Are you sure you want to %s %d items?', 'ai-post-scheduler'),
			'confirmDelete'      => __('Are you sure you want to delete this item?', 'ai-post-scheduler'),
			'confirmCreateTerm'  => __('Create this term in WordPress?', 'ai-post-scheduler'),
			'generating'         => __('Generating...', 'ai-post-scheduler'),
			'generate'           => __('Generate', 'ai-post-scheduler'),
			'actionFailed'       => __('Action failed.', 'ai-post-scheduler'),
			'generationFailed'   => __('Generation failed.', 'ai-post-scheduler'),
			'deleteFailed'       => __('Delete failed.', 'ai-post-scheduler'),
			'termCreationFailed' => __('Term creation failed.', 'ai-post-scheduler'),
			'updateFailed'       => __('Update failed.', 'ai-post-scheduler'),
			'item'               => __('item', 'ai-post-scheduler'),
			'items'              => __('items', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the sources page.
	 *
	 * @return void
	 */
	public function enqueue_sources() {
		if (class_exists('AIPS_Admin_Asset_Registrar_Content_Strategy')) {
			$strategy = new AIPS_Admin_Asset_Registrar_Content_Strategy();
			$strategy->enqueue_ai_assistance(array('aips-admin-sources'), 'sources');
		}

		wp_enqueue_script(
			'aips-admin-sources',
			AIPS_PLUGIN_URL . 'assets/js/admin-sources.js',
			array('jquery', 'aips-utilities-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-sources', 'aipsSourcesL10n', array(
			'addNewSource'       => __('Add New Source', 'ai-post-scheduler'),
			'editSource'         => __('Edit Source', 'ai-post-scheduler'),
			'saveSource'         => __('Save Source', 'ai-post-scheduler'),
			'saving'             => __('Saving…', 'ai-post-scheduler'),
			'deleteConfirm'      => __('Are you sure you want to delete this source?', 'ai-post-scheduler'),
			'saveFailed'         => __('Failed to save source.', 'ai-post-scheduler'),
			'deleteFailed'       => __('Failed to delete source.', 'ai-post-scheduler'),
			'toggleFailed'       => __('Failed to update source status.', 'ai-post-scheduler'),
			'urlRequired'        => __('A URL is required.', 'ai-post-scheduler'),
			'groupNameRequired'  => __('Please enter a group name.', 'ai-post-scheduler'),
			'deleteGroupConfirm' => __('Delete this Source Group? Sources in this group will not be deleted.', 'ai-post-scheduler'),
			'deleteDataConfirm'  => __('Are you sure you want to delete this source data record?', 'ai-post-scheduler'),
			'viewDataFailed'     => __('Failed to load source data.', 'ai-post-scheduler'),
			'saveDataFailed'     => __('Failed to save source data.', 'ai-post-scheduler'),
			'deleteDataFailed'   => __('Failed to delete source data.', 'ai-post-scheduler'),
			'saveData'           => __('Save Source Data', 'ai-post-scheduler'),
			'sourceDataNonces'   => array(
				'get'    => wp_create_nonce('aips_source_data_get'),
				'save'   => wp_create_nonce('aips_source_data_save'),
				'delete' => wp_create_nonce('aips_source_data_delete'),
			),
		));
	}

	/**
	 * Enqueue assets for the settings page.
	 *
	 * @return void
	 */
	public function enqueue_settings() {
		if (class_exists('AIPS_Admin_Asset_Registrar_Content_Strategy')) {
			$strategy = new AIPS_Admin_Asset_Registrar_Content_Strategy();
			$strategy->enqueue_ai_assistance(array('aips-admin-settings'), 'content_strategy');
		}

		wp_enqueue_script(
			'aips-admin-settings',
			AIPS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-settings', 'aipsSettingsL10n', array(
			'saving'       => __('Saving…', 'ai-post-scheduler'),
			'saveSuccess'  => __('Settings saved successfully.', 'ai-post-scheduler'),
			'saveError'    => __('Failed to save settings.', 'ai-post-scheduler'),
			'payloadError' => __('No settings were found to save.', 'ai-post-scheduler'),
		));

		wp_enqueue_script(
			'aips-admin-gsc',
			AIPS_PLUGIN_URL . 'assets/js/admin-gsc.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-gsc', 'aipsGscL10n', array(
			'nonce'                  => wp_create_nonce('aips_ajax_nonce'),
			'working'                => __('Working…', 'ai-post-scheduler'),
			'error'                  => __('The Search Console request failed. Please try again.', 'ai-post-scheduler'),
			'cancel'                 => __('Cancel', 'ai-post-scheduler'),
			'disconnect'             => __('Disconnect', 'ai-post-scheduler'),
			'disconnected'           => __('Not connected', 'ai-post-scheduler'),
			'confirmDisconnectTitle' => __('Disconnect Search Console', 'ai-post-scheduler'),
			'confirmDisconnect'      => __('Delete the saved service account key? Daily keyword syncs stop until you add a key again.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the onboarding page.
	 *
	 * @return void
	 */
	public function enqueue_onboarding() {
		if (class_exists('AIPS_Admin_Asset_Registrar_Content_Strategy')) {
			$strategy = new AIPS_Admin_Asset_Registrar_Content_Strategy();
			$strategy->enqueue_ai_assistance(array('aips-admin-onboarding'), 'content_strategy');
		}

		wp_enqueue_script(
			'aips-admin-onboarding',
			AIPS_PLUGIN_URL . 'assets/js/onboarding.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-onboarding', 'aipsOnboardingL10n', array(
			'confirmSkipOnboarding' => __('Skip the Onboarding Wizard? You can restart it later from System Status.', 'ai-post-scheduler'),
		));
	}
}
