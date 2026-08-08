(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.AbilityWorkflows = {
		searchTimer: null,

		init() {
			this.bindEvents();
			this.loadWorkflows();
		},

		bindEvents() {
			$(document).on('click', '#aips-add-workflow-btn, #aips-add-workflow-empty-btn', this.openCreateModal.bind(this));
			$(document).on('click', '#aips-workflow-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-save-workflow-btn', this.saveWorkflow.bind(this));

			$(document).on('click', '.aips-run-workflow-now', this.runWorkflowNow.bind(this));
			$(document).on('click', '.aips-duplicate-workflow', this.duplicateWorkflow.bind(this));
			$(document).on('click', '.aips-archive-workflow', this.archiveWorkflow.bind(this));
			$(document).on('click', '.aips-delete-workflow', this.deleteWorkflow.bind(this));

			$(document).on('input', '#aips-workflow-search', this.onFilterChange.bind(this));
			$(document).on('change', '#aips-workflow-status-filter', this.onFilterChange.bind(this));
		},

		onFilterChange() {
			var self = this;
			clearTimeout(this.searchTimer);
			this.searchTimer = setTimeout(function() {
				self.loadWorkflows();
			}, 300);
		},

		loadWorkflows() {
			var self = this;

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_list_ability_workflows',
				nonce: aipsAjax.nonce,
				search: $('#aips-workflow-search').val() || '',
				status: $('#aips-workflow-status-filter').val() || '',
				per_page: 100
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.saveFailed, 'error');
					return;
				}

				self.renderWorkflows(response.data.workflows || []);
			}).fail(function() {
				AIPS.Utilities.showToast(aipsAbilityWorkflowsL10n.saveFailed, 'error');
			});
		},

		renderWorkflows(workflows) {
			var $body = $('#aips-ability-workflows-table-body');
			$body.empty();

			var statusMeta = {
				draft: { label: 'Draft', cls: 'aips-badge-neutral' },
				active: { label: 'Active', cls: 'aips-badge-success' },
				paused: { label: 'Paused', cls: 'aips-badge-warning' },
				archived: { label: 'Archived', cls: 'aips-badge-neutral' }
			};

			workflows.forEach(function(workflow) {
				var meta = statusMeta[workflow.status] || statusMeta.draft;
				var builderUrl = window.aipsAbilityWorkflowBuilderBaseUrl + '&workflow_id=' + encodeURIComponent(workflow.id);

				var html = AIPS.Templates.render('aips-tmpl-workflow-row', {
					id: workflow.id,
					status: workflow.status,
					name: workflow.name,
					description: workflow.description || '',
					trigger_type: workflow.trigger_type,
					status_badge_class: meta.cls,
					status_label: meta.label,
					updated_display: workflow.updated_at ? new Date(workflow.updated_at * 1000).toLocaleString() : '',
					builder_url: builderUrl
				});

				$body.append(html);
			});

			$('#aips-ability-workflows-count').text(workflows.length + (workflows.length === 1 ? ' workflow' : ' workflows'));
			$('#aips-ability-workflows-empty').toggle(workflows.length === 0);
		},

		openCreateModal() {
			$('#aips-workflow-form')[0].reset();
			$('#aips-workflow-id').val('0');
			$('#aips-workflow-modal-title').text(aipsAbilityWorkflowsL10n.addNewWorkflow);
			this.openModal();
		},

		openModal() {
			$('#aips-workflow-modal').css('display', 'flex');
		},

		closeModal() {
			$('#aips-workflow-modal').hide();
		},

		saveWorkflow() {
			var name = $('#aips-workflow-name').val();

			if (!name || !name.trim()) {
				AIPS.Utilities.showToast(aipsAbilityWorkflowsL10n.nameRequired, 'error');
				return;
			}

			var settings = {
				max_steps: parseInt($('#aips-workflow-max-steps').val(), 10) || 20,
				max_runtime_seconds: parseInt($('#aips-workflow-max-runtime').val(), 10) || 120,
				allow_destructive_abilities: $('#aips-workflow-allow-destructive').is(':checked'),
				log_payloads: $('#aips-workflow-log-payloads').is(':checked')
			};

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_save_ability_workflow',
				nonce: aipsAjax.nonce,
				workflow_id: $('#aips-workflow-id').val(),
				name: name,
				description: $('#aips-workflow-description').val(),
				status: $('#aips-workflow-status').val(),
				trigger_type: $('#aips-workflow-trigger-type').val(),
				trigger_config: JSON.stringify({}),
				settings: JSON.stringify(settings)
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.saveFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message || aipsAbilityWorkflowsL10n.saveWorkflow, 'success');
				AIPS.AbilityWorkflows.closeModal();
				AIPS.AbilityWorkflows.loadWorkflows();
			}).fail(function() {
				AIPS.Utilities.showToast(aipsAbilityWorkflowsL10n.saveFailed, 'error');
			});
		},

		runWorkflowNow(e) {
			var id = $(e.currentTarget).data('id');

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_run_ability_workflow_now',
				nonce: aipsAjax.nonce,
				workflow_id: id
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.runFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message || aipsAbilityWorkflowsL10n.runStarted, 'success');
			}).fail(function() {
				AIPS.Utilities.showToast(aipsAbilityWorkflowsL10n.runFailed, 'error');
			});
		},

		duplicateWorkflow(e) {
			var id = $(e.currentTarget).data('id');

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_duplicate_ability_workflow',
				nonce: aipsAjax.nonce,
				workflow_id: id
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.duplicateFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				AIPS.AbilityWorkflows.loadWorkflows();
			}).fail(function() {
				AIPS.Utilities.showToast(aipsAbilityWorkflowsL10n.duplicateFailed, 'error');
			});
		},

		archiveWorkflow(e) {
			var id = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(
				'Archive this workflow? It will stop running on its trigger until reactivated.',
				'Archive Workflow',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{ label: 'Archive', className: 'aips-btn aips-btn-primary', action: function() {
						$.post(aipsAjax.ajaxUrl, {
							action: 'aips_archive_ability_workflow',
							nonce: aipsAjax.nonce,
							workflow_id: id
						}, function(response) {
							if (!response.success) {
								AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.archiveFailed, 'error');
								return;
							}

							AIPS.Utilities.showToast(response.data.message, 'success');
							AIPS.AbilityWorkflows.loadWorkflows();
						});
					} }
				]
			);
		},

		deleteWorkflow(e) {
			var id = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(
				aipsAbilityWorkflowsL10n.deleteConfirm,
				'Delete Workflow',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{ label: 'Delete', className: 'aips-btn aips-btn-danger', action: function() {
						$.post(aipsAjax.ajaxUrl, {
							action: 'aips_delete_ability_workflow',
							nonce: aipsAjax.nonce,
							workflow_id: id
						}, function(response) {
							if (!response.success) {
								AIPS.Utilities.showToast((response.data && response.data.message) || aipsAbilityWorkflowsL10n.deleteFailed, 'error');
								return;
							}

							AIPS.Utilities.showToast(response.data.message, 'success');
							AIPS.AbilityWorkflows.loadWorkflows();
						});
					} }
				]
			);
		}
	};

	$(document).ready(function() {
		AIPS.AbilityWorkflows.init();
	});
})(jQuery);
