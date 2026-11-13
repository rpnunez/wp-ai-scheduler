/**
 * Prompt Profile Version History & Diff Admin JavaScript
 *
 * Manages the always-visible "Version History" panel inside the Edit Prompt
 * Profile modal: loading the version timeline, opening the nested diff
 * viewer (comparing any two historical versions, or a version against the
 * current live profile state), and restore-version / restore-stage actions.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

/* global jQuery, aipsPromptProfilesL10n, aipsPromptProfilesDiffL10n, AIPS */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * The 9 known stage field keys, in display order.
	 *
	 * @type {string[]}
	 */
	var STAGE_KEYS = [
		'title_prompt',
		'title_followup_prompt',
		'content_prompt',
		'excerpt_prompt',
		'excerpt_followup_prompt',
		'featured_image_prompt',
		'topic_ideas_prompt',
		'metadata_prompt',
		'taxonomy_prompt'
	];

	/**
	 * Prompt Profile version history / diff / rollback module.
	 *
	 * @namespace AIPS.PromptProfilesDiff
	 * @type {Object}
	 */
	AIPS.PromptProfilesDiff = {

		/**
		 * The profile ID currently loaded in the Edit modal, or null when the
		 * modal is in "Create" mode.
		 *
		 * @type {number|null}
		 */
		currentProfileId: null,

		/**
		 * The most recently fetched version list for the current profile.
		 *
		 * @type {Array}
		 */
		versionsCache: [],

		/**
		 * Initialize the module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind DOM event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', '.aips-edit-profile-btn', this.handleEditProfileClick.bind(this));
			$(document).on('click', '.aips-add-profile-btn, #aips-add-profile-btn', this.handleAddProfileClick.bind(this));
			$(document).on('click', '#aips-refresh-versions-btn', this.handleRefreshVersionsClick.bind(this));
			$(document).on('click', '.aips-view-diff-btn', this.handleViewDiffClick.bind(this));
			$(document).on('click', '.aips-restore-version-btn', this.handleRestoreVersionClick.bind(this));
			$(document).on('click', '.aips-restore-stage-btn', this.handleRestoreStageClick.bind(this));
			$(document).on('click', '#aips-run-diff-btn', this.handleRunDiffClick.bind(this));
			$(document).on('click', '#aips-version-diff-modal .aips-modal-close', this.handleDiffModalClose.bind(this));
		},

		/**
		 * Handle click on the "Add Profile" button: hides the version history
		 * panel since a brand-new profile has no history yet.
		 *
		 * @return {void}
		 */
		handleAddProfileClick: function () {
			this.currentProfileId = null;
			this.versionsCache = [];
			$('#aips-profile-version-history').hide();
		},

		/**
		 * Handle click on an "Edit Profile" button: shows the version history
		 * panel and loads the timeline for that profile.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleEditProfileClick: function (e) {
			var profileId = $(e.currentTarget).data('id');
			this.currentProfileId = profileId;
			$('#aips-profile-version-history').show();
			this.loadVersions(profileId);
		},

		/**
		 * Handle click on the Refresh button in the version history panel.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleRefreshVersionsClick: function (e) {
			e.preventDefault();
			if (this.currentProfileId) {
				this.loadVersions(this.currentProfileId);
			}
		},

		/**
		 * Fetch the version timeline for a profile via AJAX and render it.
		 *
		 * @param {number} profileId - The profile ID to load versions for.
		 * @return {void}
		 */
		loadVersions: function (profileId) {
			var self = this;
			var $loadingBody = $('#aips-versions-timeline-body');
			$loadingBody.html('<tr class="aips-versions-loading-row"><td colspan="5">' + self.escapeText(self.l10n('loadingVersions')) + '</td></tr>');
			$('#aips-versions-empty-state').hide();

			$.ajax({
				url: self.getAjaxUrl(),
				type: 'POST',
				data: {
					action: 'aips_get_prompt_profile_versions',
					profile_id: profileId,
					nonce: self.getNonce()
				},
				success: function (response) {
					if (response.success && response.data && $.isArray(response.data.versions)) {
						self.versionsCache = response.data.versions;
						self.renderVersions(self.versionsCache);
					} else {
						self.notify('error', (response.data && response.data.message) || self.l10n('errorLoadingVersions'));
						$('#aips-versions-timeline-body').empty();
						$('#aips-versions-table').hide();
						$('#aips-versions-empty-state').show();
					}
				},
				error: function () {
					self.notify('error', self.l10n('errorLoadingVersions'));
				}
			});
		},

		/**
		 * Render the version timeline table body from a version list.
		 *
		 * @param {Array} versions - Version entries from aips_get_prompt_profile_versions.
		 * @return {void}
		 */
		renderVersions: function (versions) {
			var self = this;
			var $body = $('#aips-versions-timeline-body');
			$body.empty();

			if (!versions || !versions.length) {
				$('#aips-versions-table').hide();
				$('#aips-versions-empty-state').show();
				return;
			}

			$('#aips-versions-table').show();
			$('#aips-versions-empty-state').hide();

			versions.forEach(function (v) {
				var rowHtml = AIPS.Templates.render('aips-tmpl-version-row', {
					id: v.id,
					version_number: v.version_number,
					created_at_display: self.formatDate(v.created_at),
					created_by_display: v.created_by || self.l10n('unknownUser'),
					change_summary: v.change_summary || ''
				});
				$body.append(rowHtml);
			});
		},

		/**
		 * Handle click on a version row's "View Diff" button: opens the diff
		 * modal comparing that version against the current live profile state.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleViewDiffClick: function (e) {
			e.preventDefault();
			var versionId = $(e.currentTarget).data('version-id');
			this.openDiffModal(versionId, 'current');
		},

		/**
		 * Open the nested diff viewer modal, pre-selecting the two versions to
		 * compare, and immediately run the comparison.
		 *
		 * @param {number}        versionIdA - The baseline/historical version ID.
		 * @param {number|string} versionIdB - A second version ID, or 'current' for the live profile state.
		 * @return {void}
		 */
		openDiffModal: function (versionIdA, versionIdB) {
			this.populateDiffSelectors(versionIdA, versionIdB);
			$('#aips-version-diff-modal').attr('aria-hidden', 'false').fadeIn(200);
			this.runDiff();
		},

		/**
		 * Populate the "Version" and "Compared to" dropdowns in the diff modal
		 * from the cached version list.
		 *
		 * @param {number|string} [selectedA] - Version ID to pre-select on the "Version" side.
		 * @param {number|string} [selectedB] - Version ID (or 'current') to pre-select on the "Compared to" side.
		 * @return {void}
		 */
		populateDiffSelectors: function (selectedA, selectedB) {
			var self = this;
			var $a = $('#aips-diff-version-a').empty();
			var $b = $('#aips-diff-version-b').empty();

			this.versionsCache.forEach(function (v) {
				var label = 'v' + v.version_number + ' — ' + self.formatDate(v.created_at);
				$a.append($('<option></option>').val(v.id).text(label));
				$b.append($('<option></option>').val(v.id).text(label));
			});

			$b.prepend($('<option></option>').val('current').text(self.l10n('currentLiveState')));

			if (selectedA) {
				$a.val(String(selectedA));
			}
			$b.val(selectedB ? String(selectedB) : 'current');
		},

		/**
		 * Handle click on the "Compare" button in the diff modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleRunDiffClick: function (e) {
			e.preventDefault();
			this.runDiff();
		},

		/**
		 * Fetch and render the diff between the two currently-selected versions.
		 *
		 * @return {void}
		 */
		runDiff: function () {
			var self = this;
			var versionIdA = $('#aips-diff-version-a').val();
			var versionIdB = $('#aips-diff-version-b').val();

			if (!versionIdA) {
				return;
			}

			$('#aips-diff-results').html('<p class="aips-diff-placeholder">' + self.escapeText(self.l10n('loadingDiff')) + '</p>');

			$.ajax({
				url: self.getAjaxUrl(),
				type: 'POST',
				data: {
					action: 'aips_get_prompt_profile_version_diff',
					profile_id: self.currentProfileId,
					version_id_a: versionIdA,
					version_id_b: versionIdB,
					nonce: self.getNonce()
				},
				success: function (response) {
					if (response.success && response.data && response.data.diff) {
						self.renderDiffResult(response.data.diff, versionIdA);
					} else {
						var msg = (response.data && response.data.message) || self.l10n('errorDiff');
						self.notify('error', msg);
						$('#aips-diff-results').html('<p class="aips-diff-placeholder">' + self.escapeText(msg) + '</p>');
					}
				},
				error: function () {
					self.notify('error', self.l10n('errorDiff'));
					$('#aips-diff-results').html('<p class="aips-diff-placeholder">' + self.escapeText(self.l10n('errorDiff')) + '</p>');
				}
			});
		},

		/**
		 * Render the structured per-stage diff result returned by
		 * aips_get_prompt_profile_version_diff into the diff modal body.
		 *
		 * Unchanged stages are omitted to keep the view compact.
		 *
		 * @param {Object} diff       - { version_a, version_b, stages: { stageKey: { changed, segments } } }.
		 * @param {number} versionIdA - The version ID used as the restore-from baseline for per-stage restores.
		 * @return {void}
		 */
		renderDiffResult: function (diff, versionIdA) {
			var self = this;
			var $results = $('#aips-diff-results').empty();
			var stages = (diff && diff.stages) || {};
			var stageLabels = (window.aipsPromptProfilesDiffL10n && aipsPromptProfilesDiffL10n.stageLabels) || {};
			var anyChanged = false;

			STAGE_KEYS.forEach(function (stageKey) {
				var stageDiff = stages[stageKey];
				if (!stageDiff || !stageDiff.changed) {
					return;
				}

				anyChanged = true;
				var label = stageLabels[stageKey] || stageKey;
				var segmentsHtml = self.renderSegments(stageDiff.segments || []);

				var blockHtml = AIPS.Templates.renderRaw('aips-tmpl-stage-diff-block', {
					stageKey: AIPS.Templates.escape(stageKey),
					stageLabel: AIPS.Templates.escape(label),
					stageLabelAttr: AIPS.Templates.escape(label),
					versionAId: AIPS.Templates.escape(String(versionIdA)),
					segmentsHtml: segmentsHtml
				});
				$results.append(blockHtml);
			});

			if (!anyChanged) {
				$results.append('<p class="aips-diff-placeholder">' + self.escapeText(self.l10n('noDifferences')) + '</p>');
			}
		},

		/**
		 * Render an ordered list of diff segments as escaped green/red/plain spans.
		 *
		 * Segment text comes from the server's diff engine and is treated as
		 * untrusted plain text: it is explicitly HTML-escaped here before being
		 * wrapped in markup, never inserted as raw HTML.
		 *
		 * @param {Array} segments - [{ type: 'equal'|'add'|'remove', text: string }, ...].
		 * @return {string} Rendered HTML string.
		 */
		renderSegments: function (segments) {
			var html = '';

			(segments || []).forEach(function (seg) {
				var escaped = AIPS.Templates.escape(seg.text || '').replace(/\n/g, '<br>');
				var cssClass = 'diff-equal';
				if (seg.type === 'add') {
					cssClass = 'diff-add';
				} else if (seg.type === 'remove') {
					cssClass = 'diff-remove';
				}
				html += '<span class="' + cssClass + '">' + escaped + '</span>';
			});

			return html;
		},

		/**
		 * Handle click on the diff modal's close button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleDiffModalClose: function (e) {
			e.preventDefault();
			$('#aips-version-diff-modal').attr('aria-hidden', 'true').fadeOut(200);
		},

		/**
		 * Handle click on a version row's "Restore" button: confirms, then
		 * restores all 9 stages of the live profile from that version.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleRestoreVersionClick: function (e) {
			e.preventDefault();
			var self = this;
			var $btn = $(e.currentTarget);
			var versionId = $btn.data('version-id');
			var versionNumber = $btn.data('version-number');
			var message = self.l10n('confirmRestoreVersion').replace('%d', versionNumber);

			AIPS.Utilities.confirm(
				message,
				self.l10n('confirmHeading'),
				[
					{ label: self.l10n('cancel'), className: 'aips-btn aips-btn-secondary' },
					{
						label: self.l10n('restoreAction'),
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							self.restoreVersion(versionId);
						}
					}
				]
			);
		},

		/**
		 * Restore an entire profile from a historical version via AJAX.
		 *
		 * @param {number} versionId - The version row ID to restore from.
		 * @return {void}
		 */
		restoreVersion: function (versionId) {
			var self = this;

			$.ajax({
				url: self.getAjaxUrl(),
				type: 'POST',
				data: {
					action: 'aips_restore_prompt_profile_version',
					profile_id: self.currentProfileId,
					version_id: versionId,
					nonce: self.getNonce()
				},
				success: function (response) {
					if (response.success) {
						self.notify('success', self.l10n('versionRestored'));
						self.applyRestoredProfile(response.data && response.data.profile);
						self.loadVersions(self.currentProfileId);
						$('#aips-version-diff-modal').attr('aria-hidden', 'true').fadeOut(200);
					} else {
						self.notify('error', (response.data && response.data.message) || self.l10n('errorRestoring'));
					}
				},
				error: function () {
					self.notify('error', self.l10n('errorRestoring'));
				}
			});
		},

		/**
		 * Handle click on a stage diff block's "Restore this stage" button:
		 * confirms, then restores only that single stage.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleRestoreStageClick: function (e) {
			e.preventDefault();
			var self = this;
			var $btn = $(e.currentTarget);
			var stage = $btn.data('stage');
			var versionId = $btn.data('version-id');
			var stageLabel = $btn.data('stage-label') || stage;
			var message = self.l10n('confirmRestoreStage').replace('%s', stageLabel);

			AIPS.Utilities.confirm(
				message,
				self.l10n('confirmHeading'),
				[
					{ label: self.l10n('cancel'), className: 'aips-btn aips-btn-secondary' },
					{
						label: self.l10n('restoreAction'),
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							self.restoreStage(versionId, stage);
						}
					}
				]
			);
		},

		/**
		 * Restore a single named stage field from a historical version via AJAX.
		 *
		 * @param {number} versionId - The version row ID to restore the stage from.
		 * @param {string} stage     - The stage key to restore.
		 * @return {void}
		 */
		restoreStage: function (versionId, stage) {
			var self = this;

			$.ajax({
				url: self.getAjaxUrl(),
				type: 'POST',
				data: {
					action: 'aips_restore_prompt_profile_stage',
					profile_id: self.currentProfileId,
					version_id: versionId,
					stage: stage,
					nonce: self.getNonce()
				},
				success: function (response) {
					if (response.success) {
						self.notify('success', self.l10n('stageRestored'));
						self.applyRestoredProfile(response.data && response.data.profile);
						self.loadVersions(self.currentProfileId);
					} else {
						self.notify('error', (response.data && response.data.message) || self.l10n('errorRestoring'));
					}
				},
				error: function () {
					self.notify('error', self.l10n('errorRestoring'));
				}
			});
		},

		/**
		 * Reflect a restored profile's stage values into the open form, and
		 * refresh the customized-stage indicator dots, without a full page
		 * reload.
		 *
		 * @param {Object} profile - The updated profile object returned by the restore endpoints.
		 * @return {void}
		 */
		applyRestoredProfile: function (profile) {
			if (!profile) {
				return;
			}

			STAGE_KEYS.forEach(function (stg) {
				if (Object.prototype.hasOwnProperty.call(profile, stg)) {
					$('#stage_' + stg).val(profile[stg] || '');
				}
			});

			if (AIPS.PromptProfiles && typeof AIPS.PromptProfiles.updateCustomizedIndicators === 'function') {
				AIPS.PromptProfiles.updateCustomizedIndicators();
			}
		},

		/**
		 * Format a Unix timestamp (seconds) for display using the browser locale.
		 *
		 * @param {number} timestamp - Unix timestamp in seconds.
		 * @return {string}
		 */
		formatDate: function (timestamp) {
			if (!timestamp) {
				return '';
			}
			try {
				return new Date(timestamp * 1000).toLocaleString();
			} catch (err) {
				return '';
			}
		},

		/**
		 * HTML-escape plain text for safe insertion via .html().
		 *
		 * @param {string} str - Raw text.
		 * @return {string}
		 */
		escapeText: function (str) {
			return AIPS.Templates ? AIPS.Templates.escape(str) : String(str);
		},

		/**
		 * Retrieve the admin AJAX URL with multi-source fallback.
		 *
		 * @return {string}
		 */
		getAjaxUrl: function () {
			return (window.aipsPromptProfilesDiffL10n && aipsPromptProfilesDiffL10n.ajaxUrl) ||
				(window.aipsPromptProfilesL10n && aipsPromptProfilesL10n.ajaxUrl) ||
				(window.aipsAjax && window.aipsAjax.ajaxUrl) ||
				window.ajaxurl ||
				'';
		},

		/**
		 * Retrieve the AJAX nonce with multi-source fallback.
		 *
		 * @return {string}
		 */
		getNonce: function () {
			return (window.aipsPromptProfilesDiffL10n && aipsPromptProfilesDiffL10n.nonce) ||
				(window.aipsPromptProfilesL10n && aipsPromptProfilesL10n.nonce) ||
				'';
		},

		/**
		 * Look up a localized string by key, falling back to the key itself.
		 *
		 * @param {string} key - The localization string key.
		 * @return {string}
		 */
		l10n: function (key) {
			return (window.aipsPromptProfilesDiffL10n && aipsPromptProfilesDiffL10n[key]) || key;
		},

		/**
		 * Display an administrative notification toast.
		 *
		 * @param {string} type    - 'success', 'error', 'info', or 'warning'.
		 * @param {string} message - The message text to display.
		 * @return {void}
		 */
		notify: function (type, message) {
			if (typeof AIPS !== 'undefined' && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
				AIPS.Utilities.showToast(message, type);
			} else {
				window.alert(message); // eslint-disable-line no-alert
			}
		}
	};

	$(document).ready(function () {
		AIPS.PromptProfilesDiff.init();
	});

})(jQuery);
