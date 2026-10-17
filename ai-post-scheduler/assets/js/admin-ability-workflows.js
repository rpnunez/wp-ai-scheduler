(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var L10n = window.aipsAbilityWorkflowsL10n || {};

	AIPS.AbilityWorkflows = {
		searchTimer: null,

		init() {
			this.bindEvents();
			this.loadWorkflows();
			this.openFromQueryString();
		},

		bindEvents() {
			$(document).on('click', '#aips-add-workflow-btn, #aips-add-workflow-empty-btn', this.openCreateModal.bind(this));
			$(document).on('click', '.aips-edit-workflow', this.openEditModal.bind(this));
			$(document).on('click', '#aips-workflow-modal .aips-modal-close', this.closeModal.bind(this));
			$(document).on('click', '#aips-save-workflow-btn', this.saveWorkflow.bind(this));

			$(document).on('click', '.aips-run-workflow-now', this.runWorkflowNow.bind(this));
			$(document).on('click', '.aips-duplicate-workflow', this.duplicateWorkflow.bind(this));
			$(document).on('click', '.aips-archive-workflow', this.archiveWorkflow.bind(this));
			$(document).on('click', '.aips-delete-workflow', this.deleteWorkflow.bind(this));

			$(document).on('input', '#aips-workflow-search', this.onFilterChange.bind(this));
			$(document).on('change', '#aips-workflow-status-filter', this.onFilterChange.bind(this));
		},

		/**
		 * The builder links back here with ?edit=<id> to change a workflow's settings.
		 */
		openFromQueryString() {
			var match = /[?&]edit=(\d+)/.exec(window.location.search);

			if (match) {
				this.openEditFor(parseInt(match[1], 10));
			}
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
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.loadFailed, 'error');
					return;
				}

				self.renderWorkflows(response.data.workflows || []);
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.loadFailed, 'error');
			});
		},

		renderWorkflows(workflows) {
			var $body = $('#aips-ability-workflows-table-body');
			$body.empty();

			var statusMeta = {
				draft: { label: L10n.statusDraft, cls: 'aips-badge-neutral' },
				active: { label: L10n.statusActive, cls: 'aips-badge-success' },
				paused: { label: L10n.statusPaused, cls: 'aips-badge-warning' },
				archived: { label: L10n.statusArchived, cls: 'aips-badge-neutral' }
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

			$('#aips-ability-workflows-count').text(
				workflows.length === 1 ? L10n.countOne : (L10n.countMany || '%d').replace('%d', workflows.length)
			);
			$('#aips-ability-workflows-empty').toggle(workflows.length === 0);
		},

		openCreateModal() {
			$('#aips-workflow-form')[0].reset();
			$('#aips-workflow-id').val('0');
			$('#aips-workflow-modal-title').text(L10n.addNewWorkflow);
			this.openModal();
		},

		openEditModal(e) {
			this.openEditFor(parseInt($(e.currentTarget).data('id'), 10));
		},

		/**
		 * Load a workflow and show its settings in the modal.
		 *
		 * @param {number} id Workflow ID.
		 */
		openEditFor(id) {
			var self = this;

			if (!id) {
				return;
			}

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_get_ability_workflow',
				nonce: aipsAjax.nonce,
				workflow_id: id
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.loadFailed, 'error');
					return;
				}

				var workflow = response.data.workflow;
				var settings = workflow.settings || {};

				$('#aips-workflow-form')[0].reset();
				$('#aips-workflow-id').val(workflow.id);
				$('#aips-workflow-name').val(workflow.name);
				$('#aips-workflow-description').val(workflow.description || '');
				$('#aips-workflow-status').val(workflow.status);
				$('#aips-workflow-max-steps').val(settings.max_steps || 20);
				$('#aips-workflow-max-runtime').val(settings.max_runtime_seconds || 120);
				$('#aips-workflow-allow-destructive').prop('checked', !!settings.allow_destructive_abilities);
				$('#aips-workflow-modal-title').text(L10n.editWorkflow);
				self.openModal();
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.loadFailed, 'error');
			});
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
				AIPS.Utilities.showToast(L10n.nameRequired, 'error');
				return;
			}

			var settings = {
				max_steps: parseInt($('#aips-workflow-max-steps').val(), 10) || 20,
				max_runtime_seconds: parseInt($('#aips-workflow-max-runtime').val(), 10) || 120,
				allow_destructive_abilities: $('#aips-workflow-allow-destructive').is(':checked')
			};

			$.post(aipsAjax.ajaxUrl, {
				action: 'aips_save_ability_workflow',
				nonce: aipsAjax.nonce,
				workflow_id: $('#aips-workflow-id').val(),
				name: name,
				description: $('#aips-workflow-description').val(),
				status: $('#aips-workflow-status').val(),
				settings: JSON.stringify(settings)
			}, function(response) {
				if (!response.success) {
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.saveFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message || L10n.saveWorkflow, 'success');
				AIPS.AbilityWorkflows.closeModal();
				AIPS.AbilityWorkflows.loadWorkflows();
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.saveFailed, 'error');
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
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.runFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message || L10n.runStarted, 'success');
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.runFailed, 'error');
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
					AIPS.Utilities.showToast((response.data && response.data.message) || L10n.duplicateFailed, 'error');
					return;
				}

				AIPS.Utilities.showToast(response.data.message, 'success');
				AIPS.AbilityWorkflows.loadWorkflows();
			}).fail(function() {
				AIPS.Utilities.showToast(L10n.duplicateFailed, 'error');
			});
		},

		archiveWorkflow(e) {
			var id = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(
				L10n.archiveConfirm,
				L10n.archiveHeading,
				[
					{ label: L10n.cancel, className: 'aips-btn aips-btn-secondary' },
					{ label: L10n.archive, className: 'aips-btn aips-btn-primary', action: function() {
						$.post(aipsAjax.ajaxUrl, {
							action: 'aips_archive_ability_workflow',
							nonce: aipsAjax.nonce,
							workflow_id: id
						}, function(response) {
							if (!response.success) {
								AIPS.Utilities.showToast((response.data && response.data.message) || L10n.archiveFailed, 'error');
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
				L10n.deleteConfirm,
				L10n.deleteHeading,
				[
					{ label: L10n.cancel, className: 'aips-btn aips-btn-secondary' },
					{ label: L10n.delete, className: 'aips-btn aips-btn-danger', action: function() {
						$.post(aipsAjax.ajaxUrl, {
							action: 'aips_delete_ability_workflow',
							nonce: aipsAjax.nonce,
							workflow_id: id
						}, function(response) {
							if (!response.success) {
								AIPS.Utilities.showToast((response.data && response.data.message) || L10n.deleteFailed, 'error');
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
