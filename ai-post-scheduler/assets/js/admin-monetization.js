/**
 * Admin Monetization Hub JavaScript Module
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * @namespace AIPS.Monetization
	 */
	AIPS.Monetization = {

		config: window.aipsMonetizationAdminConfig || {},
		chartInstance: null,

		/**
		 * Bootstrap module.
		 */
		init: function () {
			this.bindTabs();
			this.bindSlotActions();
			this.bindCampaignActions();
			this.bindAnalyticsActions();

			// Auto load analytics if starting on analytics tab
			if (window.location.hash === '#tab-analytics') {
				this.loadAnalytics();
			}
		},

		/**
		 * Tab switching.
		 */
		bindTabs: function () {
			var self = this;
			$('#aips-monetization-tabs .nav-tab').on('click', function (e) {
				e.preventDefault();
				var targetTab = $(this).data('tab');

				$('#aips-monetization-tabs .nav-tab').removeClass('nav-tab-active');
				$(this).addClass('nav-tab-active');

				$('.aips-tab-panel').removeClass('aips-tab-active');
				$('#tab-' + targetTab).addClass('aips-tab-active');

				window.location.hash = 'tab-' + targetTab;

				if (targetTab === 'analytics') {
					self.loadAnalytics();
				}
			});

			// Activate tab on initial page load if hash exists
			if (window.location.hash) {
				var hashTab = window.location.hash.replace('#tab-', '');
				var $tabLink = $('#aips-monetization-tabs .nav-tab[data-tab="' + hashTab + '"]');
				if ($tabLink.length) {
					$tabLink.trigger('click');
				}
			}
		},

		/**
		 * Ad Slot events: add, edit, save, delete, toggle.
		 */
		bindSlotActions: function () {
			var self = this;

			// Open Add Slot Modal
			$('#aips-btn-add-slot').on('click', function () {
				$('#aips-modal-slot-title').text(self.config.i18n.addSlotTitle || 'Add Ad Slot');
				$('#aips-form-slot')[0].reset();
				$('#aips-slot-id').value = '0';
				$('#aips-wrap-paragraph-offset').show();
				$('#aips-modal-slot').fadeIn(150);
			});

			// Position change toggles offset field
			$('#aips-slot-position').on('change', function () {
				if ($(this).val() === 'after_paragraph') {
					$('#aips-wrap-paragraph-offset').slideDown(120);
				} else {
					$('#aips-wrap-paragraph-offset').slideUp(120);
				}
			});

			// Close Modal
			$('#aips-modal-slot .aips-modal-close, #aips-modal-slot .aips-modal-cancel, #aips-modal-slot .aips-modal-backdrop').on('click', function () {
				$('#aips-modal-slot').fadeOut(120);
			});

			// Edit Slot
			$(document).on('click', '.aips-btn-edit-slot', function () {
				var slot = $(this).data('slot');
				if (typeof slot === 'string') {
					try { slot = JSON.parse(slot); } catch (e) {}
				}

				if (!slot) { return; }

				$('#aips-modal-slot-title').text(self.config.i18n.editSlotTitle || 'Edit Ad Slot');
				$('#aips-slot-id').val(slot.id);
				$('#aips-slot-name').val(slot.name);
				$('#aips-slot-type').val(slot.slot_type);
				$('#aips-slot-code').val(slot.code || '');
				$('#aips-slot-position').val(slot.position).trigger('change');
				$('#aips-slot-paragraph-offset').val(slot.paragraph_offset || 2);
				$('#aips-slot-min-words').val(slot.min_word_count || 300);
				$('#aips-slot-device').val(slot.device_targeting || 'all');
				$('#aips-slot-priority').val(slot.priority || 10);
				$('#aips-slot-classes').val(slot.css_classes || '');

				$('#aips-modal-slot').fadeIn(150);
			});

			// Save Slot Form
			$('#aips-form-slot').on('submit', function (e) {
				e.preventDefault();
				var $btn = $('#aips-btn-save-slot');
				$btn.prop('disabled', true);

				var formData = $(this).serialize() + '&action=aips_save_ad_slot&nonce=' + self.config.nonce;

				$.post(ajaxurl, formData, function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						$('#aips-modal-slot').fadeOut(120);
						self.refreshSlotsList();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error saving slot.');
					}
				}).fail(function () {
					$btn.prop('disabled', false);
					alert('Network error.');
				});
			});

			// Delete Slot
			$(document).on('click', '.aips-btn-delete-slot', function () {
				if (!confirm(self.config.i18n.confirmDeleteSlot || 'Are you sure you want to delete this ad slot?')) {
					return;
				}

				var slotId = $(this).data('id');
				var $row = $('tr[data-slot-id="' + slotId + '"]');

				$.post(ajaxurl, {
					action: 'aips_delete_ad_slot',
					id: slotId,
					nonce: self.config.nonce
				}, function (res) {
					if (res.success) {
						$row.fadeOut(200, function () { $(this).remove(); });
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error deleting slot.');
					}
				});
			});

			// Toggle Slot Status
			$(document).on('click', '.aips-slot-toggle', function () {
				var $btn = $(this);
				var slotId = $btn.data('id');

				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'aips_toggle_ad_slot',
					id: slotId,
					nonce: self.config.nonce
				}, function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						var isNowActive = res.data.status === 'active';
						$btn.text(isNowActive ? 'Active' : 'Paused');
						if (isNowActive) {
							$btn.addClass('button-primary');
						} else {
							$btn.removeClass('button-primary');
						}
					}
				});
			});
		},

		/**
		 * Refresh slots table using AIPS.Templates.
		 */
		refreshSlotsList: function () {
			var self = this;
			$.post(ajaxurl, {
				action: 'aips_get_ad_slots',
				nonce: self.config.nonce
			}, function (res) {
				if (res.success && res.data.slots) {
					var $tbody = $('#aips-tbody-slots');
					$tbody.empty();

					if (!res.data.slots.length) {
						$tbody.append('<tr class="no-items"><td colspan="7">No ad slots configured.</td></tr>');
						return;
					}

					res.data.slots.forEach(function (slot) {
						var placementDesc = '';
						if (slot.position === 'after_paragraph') {
							placementDesc = 'After Paragraph ' + slot.paragraph_offset + ' (Min words: ' + slot.min_word_count + ')';
						} else if (slot.position === 'mid_content') {
							placementDesc = 'Mid-Content (50% depth, Min words: ' + slot.min_word_count + ')';
						} else if (slot.position === 'end_of_post') {
							placementDesc = 'End of Post / Conclusion';
						} else {
							placementDesc = 'Custom Shortcode / Block Only';
						}

						var rowData = {
							id: slot.id,
							name: slot.name,
							slot_type: slot.slot_type,
							css_classes: slot.css_classes || '',
							placementDescription: placementDesc,
							deviceLabel: slot.device_targeting ? slot.device_targeting.charAt(0).toUpperCase() + slot.device_targeting.slice(1) : 'All',
							priority: slot.priority,
							statusLabel: slot.status === 'active' ? 'Active' : 'Paused',
							activeClass: slot.status === 'active' ? 'button-primary' : '',
							jsonString: JSON.stringify(slot)
						};

						var rowHtml = AIPS.Templates.render('aips-tmpl-ad-slot-row', rowData);
						$tbody.append(rowHtml);
					});
				}
			});
		},

		/**
		 * Sponsor Campaign events: add, edit, save, delete, toggle.
		 */
		bindCampaignActions: function () {
			var self = this;

			$('#aips-btn-add-campaign').on('click', function () {
				$('#aips-modal-campaign-title').text(self.config.i18n.addCampaignTitle || 'Add Sponsor Campaign');
				$('#aips-form-campaign')[0].reset();
				$('#aips-campaign-id').val('0');
				$('#aips-modal-campaign').fadeIn(150);
			});

			$('#aips-modal-campaign .aips-modal-close, #aips-modal-campaign .aips-modal-cancel, #aips-modal-campaign .aips-modal-backdrop').on('click', function () {
				$('#aips-modal-campaign').fadeOut(120);
			});

			$(document).on('click', '.aips-btn-edit-campaign', function () {
				var camp = $(this).data('campaign');
				if (typeof camp === 'string') {
					try { camp = JSON.parse(camp); } catch (e) {}
				}
				if (!camp) { return; }

				$('#aips-modal-campaign-title').text(self.config.i18n.editCampaignTitle || 'Edit Sponsor Campaign');
				$('#aips-campaign-id').val(camp.id);
				$('#aips-campaign-brand').val(camp.brand_name);
				$('#aips-campaign-url').val(camp.target_url);
				$('#aips-campaign-logo').val(camp.logo_url || '');
				$('#aips-campaign-cta').val(camp.cta_text || '');
				$('#aips-campaign-keywords').val(camp.keywords || '');
				$('#aips-campaign-disclosure').val(camp.disclosure_text || '');
				$('#aips-campaign-start').val(camp.start_date || '');
				$('#aips-campaign-end').val(camp.end_date || '');

				$('#aips-modal-campaign').fadeIn(150);
			});

			$('#aips-form-campaign').on('submit', function (e) {
				e.preventDefault();
				var $btn = $('#aips-btn-save-campaign');
				$btn.prop('disabled', true);

				var formData = $(this).serialize() + '&action=aips_save_sponsor_campaign&nonce=' + self.config.nonce;

				$.post(ajaxurl, formData, function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						$('#aips-modal-campaign').fadeOut(120);
						self.refreshCampaignsList();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error saving campaign.');
					}
				}).fail(function () {
					$btn.prop('disabled', false);
					alert('Network error.');
				});
			});

			$(document).on('click', '.aips-btn-delete-campaign', function () {
				if (!confirm(self.config.i18n.confirmDeleteCampaign || 'Are you sure you want to delete this sponsor campaign?')) {
					return;
				}

				var campId = $(this).data('id');
				var $row = $('tr[data-campaign-id="' + campId + '"]');

				$.post(ajaxurl, {
					action: 'aips_delete_sponsor_campaign',
					id: campId,
					nonce: self.config.nonce
				}, function (res) {
					if (res.success) {
						$row.fadeOut(200, function () { $(this).remove(); });
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Error deleting campaign.');
					}
				});
			});

			$(document).on('click', '.aips-campaign-toggle', function () {
				var $btn = $(this);
				var campId = $btn.data('id');

				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'aips_toggle_sponsor_campaign',
					id: campId,
					nonce: self.config.nonce
				}, function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						var isNowActive = res.data.status === 'active';
						$btn.text(isNowActive ? 'Active' : 'Paused');
						if (isNowActive) {
							$btn.addClass('button-primary');
						} else {
							$btn.removeClass('button-primary');
						}
					}
				});
			});
		},

		/**
		 * Refresh campaigns list via AIPS.Templates.
		 */
		refreshCampaignsList: function () {
			var self = this;
			$.post(ajaxurl, {
				action: 'aips_get_sponsor_campaigns',
				nonce: self.config.nonce
			}, function (res) {
				if (res.success && res.data.campaigns) {
					var $tbody = $('#aips-tbody-campaigns');
					$tbody.empty();

					if (!res.data.campaigns.length) {
						$tbody.append('<tr class="no-items"><td colspan="6">No sponsor campaigns created yet.</td></tr>');
						return;
					}

					res.data.campaigns.forEach(function (camp) {
						var start = camp.start_date || 'Immediately';
						var end = camp.end_date || 'Ongoing';

						var rowData = {
							id: camp.id,
							brand_name: camp.brand_name,
							target_url: camp.target_url,
							keywords: camp.keywords || '',
							duration: start + ' → ' + end,
							statusLabel: camp.status === 'active' ? 'Active' : 'Paused',
							activeClass: camp.status === 'active' ? 'button-primary' : '',
							jsonString: JSON.stringify(camp)
						};

						var rowHtml = AIPS.Templates.render('aips-tmpl-sponsor-campaign-row', rowData);
						$tbody.append(rowHtml);
					});
				}
			});
		},

		/**
		 * Analytics loading & chart rendering.
		 */
		bindAnalyticsActions: function () {
			var self = this;
			$('#aips-btn-refresh-analytics, #aips-analytics-range').on('change click', function () {
				self.loadAnalytics();
			});
		},

		loadAnalytics: function () {
			var self = this;
			var days = $('#aips-analytics-range').val() || 14;

			$.post(ajaxurl, {
				action: 'aips_get_monetization_analytics',
				days: days,
				nonce: self.config.nonce
			}, function (res) {
				if (res.success && res.data) {
					var data = res.data;

					// Update cards
					$('#aips-stat-impressions').text(Number(data.summary.impressions).toLocaleString());
					$('#aips-stat-clicks').text(Number(data.summary.clicks).toLocaleString());
					$('#aips-stat-ctr').text(data.summary.ctr + '%');

					// Render Chart
					self.renderChart(data.trends);

					// Render Top Posts via AIPS.Templates
					var $topTbody = $('#aips-tbody-top-posts');
					$topTbody.empty();
					if (data.top && data.top.length) {
						data.top.forEach(function (p) {
							$topTbody.append(AIPS.Templates.render('aips-tmpl-top-post-row', p));
						});
					} else {
						$topTbody.append('<tr><td colspan="4">No post traffic recorded yet.</td></tr>');
					}

					// Render Slot Breakdown via AIPS.Templates
					var $slotTbody = $('#aips-tbody-slot-stats');
					$slotTbody.empty();
					if (data.slots && data.slots.length) {
						data.slots.forEach(function (s) {
							$slotTbody.append(AIPS.Templates.render('aips-tmpl-slot-stat-row', s));
						});
					} else {
						$slotTbody.append('<tr><td colspan="4">No slot impressions recorded yet.</td></tr>');
					}
				}
			});
		},

		renderChart: function (trends) {
			if (!trends || !trends.length || typeof Chart === 'undefined') {
				return;
			}

			var labels = trends.map(function (t) { return t.date; });
			var impressions = trends.map(function (t) { return t.impressions; });
			var clicks = trends.map(function (t) { return t.clicks; });

			var ctx = document.getElementById('aips-monetization-chart');
			if (!ctx) { return; }

			if (this.chartInstance) {
				this.chartInstance.destroy();
			}

			this.chartInstance = new Chart(ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: 'Impressions (Viewable)',
							data: impressions,
							borderColor: '#3b82f6',
							backgroundColor: 'rgba(59, 130, 246, 0.08)',
							borderWidth: 2,
							tension: 0.3,
							fill: true
						},
						{
							label: 'Clicks',
							data: clicks,
							borderColor: '#10b981',
							backgroundColor: 'rgba(16, 185, 129, 0.08)',
							borderWidth: 2,
							tension: 0.3,
							fill: true
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						y: {
							beginAtZero: true,
							ticks: { precision: 0 }
						}
					},
					plugins: {
						legend: { position: 'top' }
					}
				}
			});
		}
	};

	$(document).ready(function () {
		if ($('.aips-monetization-wrap').length) {
			AIPS.Monetization.init();
		}
	});

})(jQuery);
