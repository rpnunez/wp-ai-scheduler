import Backbone from 'backbone';
import $ from 'jquery';
import { SystemStatusModel } from '../models/system-status';

/**
 * System Status View
 * Handles collapse/expand sections, log detail toggles, and system operations
 */
export const SystemStatusView = Backbone.View.extend({
	el: 'body',

	events: {
		'click .aips-toggle-log-details': 'toggleLogDetails',
		'click .aips-status-data-panel': 'toggleStatusSectionFromPanel',
		'click .aips-panel-collapse-toggle': 'toggleStatusSection',
		'click .aips-status-sections-toggle': 'toggleAllStatusSections',
		'click .aips-reset-circuit-breaker': 'resetCircuitBreaker',
		'click .aips-status-op': 'runStatusOperation',
		'click .aips-rebuild-cache-btn': 'rebuildCaches'
	},

	l10n: {},

	initialize() {
		this.model = new SystemStatusModel();
		this.l10n = window.aipsSystemStatusL10n || {};
		this.syncStatusSectionsToggleState();
	},

	toggleStatusSectionFromPanel(e) {
		const $target = $(e.target);
		const isInteractive = $target.closest('a, button, input, select, textarea, label').length > 0;

		if (isInteractive) {
			return;
		}

		const $panel = $(e.currentTarget);
		const $button = $panel.find('.aips-panel-collapse-toggle').first();

		if ($button.length) {
			$button.trigger('click');
		}
	},

	syncStatusSectionsToggleState() {
		const $toggle = this.$('.aips-status-sections-toggle').first();
		if (!$toggle.length) {
			return;
		}

		const $bodies = this.$('.aips-status-data-panel-body');
		if (!$bodies.length) {
			$toggle.hide();
			return;
		}

		const hiddenCount = $bodies.filter(function() {
			return !$(this).is(':visible');
		}).length;

		if (hiddenCount === 0) {
			$toggle.attr('data-mode', 'collapse').text('Collapse all');
		} else {
			$toggle.attr('data-mode', 'expand').text('Expand all');
		}
	},

	toggleStatusSection(e) {
		e.preventDefault();
		e.stopPropagation();

		const $button = $(e.currentTarget);
		const targetId = $button.data('target');
		const $panelBody = this.$(`#${targetId}`);

		if (!$panelBody.length) {
			return;
		}

		const isExpanded = $button.attr('aria-expanded') === 'true';
		const nextExpanded = !isExpanded;
		const $icon = $button.find('.dashicons');
		const $label = $button.find('.aips-panel-collapse-label');

		$button.attr('aria-expanded', nextExpanded ? 'true' : 'false');
		$icon
			.toggleClass('dashicons-arrow-up-alt2', nextExpanded)
			.toggleClass('dashicons-arrow-down-alt2', !nextExpanded);
		$icon.addClass('aips-chevron-pop');

		window.setTimeout(() => {
			$icon.removeClass('aips-chevron-pop');
		}, 180);

		$label.text(nextExpanded ? 'Collapse' : 'Expand');

		$panelBody
			.attr('aria-hidden', nextExpanded ? 'false' : 'true')
			.slideToggle(140);

		this.syncStatusSectionsToggleState();
	},

	toggleAllStatusSections(e) {
		e.preventDefault();

		const $button = $(e.currentTarget);
		const mode = $button.attr('data-mode') || 'expand';
		const shouldExpand = mode === 'expand';
		const $panelBodies = this.$('.aips-status-data-panel-body');
		const $sectionButtons = this.$('.aips-panel-collapse-toggle');

		if (shouldExpand) {
			$panelBodies.stop(true, true).slideDown(140).attr('aria-hidden', 'false');
			$sectionButtons.attr('aria-expanded', 'true');
			$sectionButtons.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
			$sectionButtons.find('.aips-panel-collapse-label').text('Collapse');
		} else {
			$panelBodies.stop(true, true).slideUp(140).attr('aria-hidden', 'true');
			$sectionButtons.attr('aria-expanded', 'false');
			$sectionButtons.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
			$sectionButtons.find('.aips-panel-collapse-label').text('Expand');
		}

		$sectionButtons.find('.dashicons').addClass('aips-chevron-pop');
		window.setTimeout(() => {
			$sectionButtons.find('.dashicons').removeClass('aips-chevron-pop');
		}, 180);

		this.syncStatusSectionsToggleState();
	},

	toggleLogDetails(e) {
		e.preventDefault();

		const $link = $(e.currentTarget);
		const target = $link.data('target');
		const $detail = this.$(`#${target}`);

		$detail.slideToggle(() => {
			$link.text(
				$detail.is(':visible')
					? (this.l10n.hideDetails || 'Hide Details')
					: (this.l10n.showDetails || 'Show Details')
			);
		});
	},

	resetCircuitBreaker(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const $result = $btn.siblings('.aips-reset-circuit-result');

		$btn.prop('disabled', true);

		$.post(
			(window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			{
				action: 'aips_reset_circuit_breaker',
				nonce: this.l10n.nonce || ''
			},
			(response) => {
				if (response && response.success) {
					$result.text(this.l10n.resetSuccess || 'Circuit reset. Reload the page to confirm.').show();
					$btn.hide();
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: (this.l10n.resetFailed || 'Reset failed.');
					$result.text(msg).show();
					$btn.prop('disabled', false);
				}
			}
		).fail(() => {
			$result.text(this.l10n.requestFailed || 'Request failed. Please try again.').show();
			$btn.prop('disabled', false);
		});
	},

	runStatusOperation(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const action = $btn.data('op');
		const $result = this.$('.aips-status-op-result');

		const nonceMap = {
			'aips_status_reschedule_missed_cron': this.l10n.nonceCronReschedule || '',
			'aips_status_retry_failed_slices': this.l10n.nonceRetrySlices || '',
			'aips_status_repair_campaign_data': this.l10n.nonceRepairCampaignData || '',
			'aips_status_clear_partial_generations': this.l10n.nonceClearPartialGenerations || '',
			'aips_status_cleanup_stale_jobs_cache': this.l10n.nonceCleanupStaleJobsCache || ''
		};

		const nonce = nonceMap[action] || '';

		$btn.prop('disabled', true);
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, { action, nonce }, (response) => {
			if (response && response.success) {
				$result.text((response.data && response.data.message) ? response.data.message : 'Done.').show();
			} else {
				$result.text((response && response.data && response.data.message) ? response.data.message : (this.l10n.requestFailed || 'Request failed.')).show();
			}
			$btn.prop('disabled', false);
		}).fail(() => {
			$result.text(this.l10n.requestFailed || 'Request failed.').show();
			$btn.prop('disabled', false);
		});
	},

	rebuildCaches(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const subsystem = this.$('#aips-cache-subsystem').val() || 'all';
		const $result = this.$('.aips-status-op-result');

		$btn.prop('disabled', true);
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_rebuild_caches',
			nonce: this.l10n.nonceRebuildCaches || '',
			subsystem
		}, (response) => {
			if (response && response.success) {
				$result.text((response.data && response.data.message) ? response.data.message : 'Done.').show();
			} else {
				$result.text((response && response.data && response.data.message) ? response.data.message : (this.l10n.requestFailed || 'Request failed.')).show();
			}
			$btn.prop('disabled', false);
		}).fail(() => {
			$result.text(this.l10n.requestFailed || 'Request failed.').show();
			$btn.prop('disabled', false);
		});
	}
});
