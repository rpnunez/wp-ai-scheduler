<?php
/**
 * Prompt Templates Admin Page
 *
 * Provides the UI for managing prompt template groups and their per-component
 * base-prompt overrides.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e( 'Prompt Templates', 'ai-post-scheduler' ); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e( 'Manage groups of base-prompt overrides for every AI generation component. The active default group\'s prompts are used whenever the plugin generates content.', 'ai-post-scheduler' ); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button id="aips-add-pt-group-btn" class="aips-btn aips-btn-primary">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Group', 'ai-post-scheduler' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Content Panel -->
		<div class="aips-content-panel">
			<div id="aips-pt-groups-wrap">

				<?php if ( empty( $groups ) ) : ?>
					<div class="aips-empty-state">
						<span class="dashicons dashicons-edit-page aips-empty-state-icon"></span>
						<p><?php esc_html_e( 'No prompt template groups found. Click "Add Group" to create one.', 'ai-post-scheduler' ); ?></p>
					</div>
				<?php else : ?>

				<!-- Filter bar -->
				<div class="aips-filter-bar">
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-pt-search"><?php esc_html_e( 'Search Groups:', 'ai-post-scheduler' ); ?></label>
						<input type="search" id="aips-pt-search" class="aips-form-input" placeholder="<?php esc_attr_e( 'Search groups\xe2\x80\xa6', 'ai-post-scheduler' ); ?>">
					</div>
				</div>

				<!-- Groups table -->
				<div class="aips-panel-body no-padding">
					<table class="aips-table aips-pt-groups-table">
						<thead>
							<tr>
								<th class="column-name"><?php esc_html_e( 'Group Name', 'ai-post-scheduler' ); ?></th>
								<th class="column-description"><?php esc_html_e( 'Description', 'ai-post-scheduler' ); ?></th>
								<th class="column-status"><?php esc_html_e( 'Status', 'ai-post-scheduler' ); ?></th>
								<th class="column-actions"><?php esc_html_e( 'Actions', 'ai-post-scheduler' ); ?></th>
							</tr>
						</thead>
						<tbody id="aips-pt-groups-tbody">
							<?php foreach ( $groups as $group ) : ?>
							<tr data-group-id="<?php echo esc_attr( $group->id ); ?>">
								<td class="column-name cell-primary">
									<strong><?php echo esc_html( $group->name ); ?></strong>
								</td>
								<td class="column-description cell-meta">
									<?php echo esc_html( $group->description ?: '—' ); ?>
								</td>
								<td class="column-status">
									<?php if ( (int) $group->is_default === 1 ) : ?>
										<span class="aips-badge aips-badge-success">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Default', 'ai-post-scheduler' ); ?>
										</span>
									<?php else : ?>
										<span class="aips-badge aips-badge-neutral">
											<?php esc_html_e( 'Inactive', 'ai-post-scheduler' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td class="column-actions">
									<div class="aips-action-buttons">
										<button class="aips-btn aips-btn-sm aips-pt-edit-group"
											data-id="<?php echo esc_attr( $group->id ); ?>"
											title="<?php esc_attr_e( 'Edit Group', 'ai-post-scheduler' ); ?>"
											aria-label="<?php esc_attr_e( 'Edit Group', 'ai-post-scheduler' ); ?>">
											<span class="dashicons dashicons-edit"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Edit', 'ai-post-scheduler' ); ?></span>
										</button>
										<?php if ( (int) $group->is_default !== 1 ) : ?>
										<button class="aips-btn aips-btn-sm aips-btn-secondary aips-pt-set-default"
											data-id="<?php echo esc_attr( $group->id ); ?>"
											title="<?php esc_attr_e( 'Set as Default', 'ai-post-scheduler' ); ?>"
											aria-label="<?php esc_attr_e( 'Set as Default', 'ai-post-scheduler' ); ?>">
											<span class="dashicons dashicons-yes"></span>
											<?php esc_html_e( 'Set Default', 'ai-post-scheduler' ); ?>
										</button>
										<?php endif; ?>
										<button class="aips-btn aips-btn-sm aips-btn-danger aips-pt-delete-group"
											data-id="<?php echo esc_attr( $group->id ); ?>"
											data-name="<?php echo esc_attr( $group->name ); ?>"
											title="<?php esc_attr_e( 'Delete Group', 'ai-post-scheduler' ); ?>"
											aria-label="<?php esc_attr_e( 'Delete Group', 'ai-post-scheduler' ); ?>">
											<span class="dashicons dashicons-trash"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Delete', 'ai-post-scheduler' ); ?></span>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php endif; ?>

			</div><!-- #aips-pt-groups-wrap -->
		</div><!-- .aips-content-panel -->

		<!-- Component definitions for JS (hidden) -->
		<script type="application/json" id="aips-pt-components-data">
		<?php
		$components_for_js = array();
		foreach ( $components as $key => $def ) {
			$components_for_js[] = array(
				'key'            => esc_attr( $def['key'] ),
				'label'          => esc_html( $def['label'] ),
				'description'    => esc_html( $def['description'] ),
				'default_prompt' => $def['default_prompt'],
			);
		}
		echo wp_json_encode( $components_for_js );
		?>
		</script>

	</div><!-- .aips-page-container -->
</div><!-- .wrap.aips-wrap -->


<!-- ============================================================
	 Edit Group Modal
	 ============================================================ -->
<div id="aips-pt-group-modal" class="aips-modal" style="display:none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="aips-pt-modal-title">
	<div class="aips-modal-content aips-modal-large">

		<!-- Modal Header -->
		<div class="aips-modal-header">
			<h2 id="aips-pt-modal-title" class="aips-modal-title"><?php esc_html_e( 'Edit Prompt Template Group', 'ai-post-scheduler' ); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ai-post-scheduler' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<!-- Modal Body -->
		<div class="aips-modal-body">

			<input type="hidden" id="aips-pt-modal-group-id" value="">

			<!-- Group meta -->
			<div class="aips-form-row">
				<label for="aips-pt-modal-name" class="aips-form-label">
					<?php esc_html_e( 'Group Name', 'ai-post-scheduler' ); ?>
					<span class="aips-required">*</span>
				</label>
				<input type="text" id="aips-pt-modal-name" class="aips-form-input" maxlength="255"
					placeholder="<?php esc_attr_e( 'e.g. Marketing Voice', 'ai-post-scheduler' ); ?>">
			</div>

			<div class="aips-form-row">
				<label for="aips-pt-modal-description" class="aips-form-label">
					<?php esc_html_e( 'Description', 'ai-post-scheduler' ); ?>
				</label>
				<textarea id="aips-pt-modal-description" class="aips-form-textarea" rows="2"
					placeholder="<?php esc_attr_e( 'Optional description of this group\xe2\x80\xa6', 'ai-post-scheduler' ); ?>"></textarea>
			</div>

			<div class="aips-form-row">
				<label class="aips-form-label aips-checkbox-label">
					<input type="checkbox" id="aips-pt-modal-is-default">
					<?php esc_html_e( 'Set as default group', 'ai-post-scheduler' ); ?>
				</label>
				<p class="aips-form-help"><?php esc_html_e( 'The default group\'s prompts are used by all generation pipelines.', 'ai-post-scheduler' ); ?></p>
			</div>

			<hr class="aips-divider">

			<!-- Component prompts -->
			<h3 class="aips-section-title"><?php esc_html_e( 'Component Prompts', 'ai-post-scheduler' ); ?></h3>
			<p class="aips-section-description">
				<?php esc_html_e( 'Customise the base prompt text for each generation component. Leave a field blank to use the built-in default.', 'ai-post-scheduler' ); ?>
			</p>

			<div id="aips-pt-components-container">
				<!-- Populated by JS -->
				<div class="aips-loading-spinner">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading\xe2\x80\xa6', 'ai-post-scheduler' ); ?>
				</div>
			</div>

		</div><!-- .aips-modal-body -->

		<!-- Modal Footer -->
		<div class="aips-modal-footer">
			<button id="aips-pt-modal-save" class="aips-btn aips-btn-primary">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save Group', 'ai-post-scheduler' ); ?>
			</button>
			<button class="aips-btn aips-btn-ghost aips-modal-close">
				<?php esc_html_e( 'Cancel', 'ai-post-scheduler' ); ?>
			</button>
		</div>

	</div><!-- .aips-modal-content -->
</div><!-- #aips-pt-group-modal -->
