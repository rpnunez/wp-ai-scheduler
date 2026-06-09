import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';

/**
 * Research View
 */
export const ResearchView = BaseListView.extend({
	el: 'body',

	listSelector: '.aips-research-table',
	rowSelector: '.aips-research-table tbody tr',
	searchSelector: '#filter-search',
	selectAllSelector: '#select-all-topics',
	checkboxSelector: '.topic-checkbox',
	bulkApplySelector: '', // Managed individually in updateSelectedTopics

	events: _.extend({}, BaseListView.prototype.events, {
		'submit #aips-research-form': 'submitResearchForm',
		'submit #aips-research-from-sources-form': 'submitResearchFromSourcesForm',
		'click #load-topics': 'loadTopics',
		'click #filter-search-clear, #clear-topics-search': 'clearTopicsSearch',
		'change #select-all-topics': 'toggleAllTopics',
		'change .topic-checkbox': 'toggleTopicSelection',
		'click .delete-topic': 'deleteTopic',
		'submit #bulk-schedule-form': 'submitBulkSchedule',
		'click #aips-clear-filters': 'clearTopicFilters',
		'click #aips-start-research': 'focusResearchForm',
		'click #analyze-gaps-btn': 'analyzeGaps',
		'click .generate-gap-ideas': 'generateGapIdeas',
		'click #aips-delete-selected-topics': 'bulkDeleteSelectedTopics',
		'click #aips-schedule-selected-topics': 'scheduleSelectedTopics',
		'click #aips-generate-selected-topics': 'bulkGenerateSelectedTopics',
		'click #aips-generate-now-confirm': 'confirmGenerateNow',
		'click .aips-post-count-badge[data-context="trending-topic"]': 'viewTrendingTopicPosts',
		'click #aips-reload-topics-btn': 'reloadTopics'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		this.researchSelectedTopics = [];

		// Initialize modals if elements exist in DOM
		if ($('#aips-generate-now-modal').length) {
			this.generateNowModal = new BaseModalView({ el: '#aips-generate-now-modal' });
		}
		if ($('#aips-trending-topic-posts-modal').length) {
			this.postsModal = new BaseModalView({ el: '#aips-trending-topic-posts-modal' });
		}

		if (this.isResearchPage()) {
			if (this.$('#load-topics').length) {
				this.loadTopics();
			}
		}
	},

	isResearchPage() {
		return this.$('#aips-research-form').length > 0 || this.$('#topics-container').length > 0;
	},

	submitResearchForm(e) {
		e.preventDefault();

		const $form = $(e.currentTarget);
		const $submit = this.$('#research-submit');
		const $spinner = $form.find('.spinner');

		const niche = this.$('#research-niche').val();
		const count = this.$('#research-count').val();
		const keywordsStr = this.$('#research-keywords').val();
		const keywords = keywordsStr ? keywordsStr.split(',').map(k => k.trim()) : [];

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($submit, 'Researching...');
		}
		$spinner.addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_research_topics',
				nonce: this.$('#aips_nonce').val(),
				niche: niche,
				count: count,
				keywords: keywords
			},
			success: (response) => {
				if (response.success) {
					this.renderResearchResults(response.data);
					this.loadTopics();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
					}
				}
			},
			error: () => {
				const errorMsg = (window.aipsResearchL10n && window.aipsResearchL10n.researchError) || 'An error occurred during research.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errorMsg, 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($submit);
				}
				$spinner.removeClass('is-active');
			}
		});
	},

	submitResearchFromSourcesForm(e) {
		e.preventDefault();

		const $form = $(e.currentTarget);
		const $submit = this.$('#source-research-submit');
		const $spinner = this.$('#source-research-spinner');

		const niche = this.$('#source-research-niche').val();
		const count = this.$('#source-research-count').val();
		const termIds = $form.find('input[name="term_ids[]"]:checked').map(function() {
			return $(this).val();
		}).get();

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($submit, 'Scanning Sources...');
		}
		$spinner.addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_research_from_sources',
				nonce: this.$('#aips-source-research-nonce').val(),
				niche: niche,
				count: count,
				term_ids: termIds
			},
			success: (response) => {
				if (response.success) {
					this.renderResearchResults(response.data);
					this.loadTopics();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
					}
				}
			},
			error: () => {
				const errorMsg = (window.aipsResearchL10n && window.aipsResearchL10n.researchError) || 'An error occurred during research.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errorMsg, 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($submit);
				}
				$spinner.removeClass('is-active');
			}
		});
	},

	renderResearchResults(data) {
		const $container = this.$('#research-results-content');
		let topTopicsBlockHtml = '';
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : str => String(str || '');
		const l10n = window.aipsResearchL10n || {};

		if (T && data.top_topics && data.top_topics.length > 0) {
			const itemsHtml = data.top_topics.map(topic => {
				const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
				const reasonHtml = topic.reason
					? T.render('aips-tmpl-research-top-topic-reason', { reason: topic.reason })
					: '';

				return T.renderRaw('aips-tmpl-research-top-topic-item', {
					topic: esc(topic.topic),
					score_class: esc(scoreClass),
					score: esc(topic.score),
					reason_html: reasonHtml
				});
			}).join('');

			topTopicsBlockHtml = T.renderRaw('aips-tmpl-research-top-topics-block', {
				top_topics_label: esc(l10n.topTopics || 'Top Recommended Topics'),
				items_html: itemsHtml
			});
		}

		const html = T
			? T.renderRaw('aips-tmpl-research-results-summary', {
				saved_count: esc(data.saved_count),
				topics_saved: esc(l10n.topicsSaved || 'Topics Saved'),
				niche: esc(data.niche),
				top_topics_block_html: topTopicsBlockHtml
			})
			: '';

		$container.html(html || '');
		this.$('#research-results').slideDown();
	},

	loadTopics(e) {
		if (e) e.preventDefault();

		const niche = this.$('#filter-niche').val();
		const minScore = this.$('#filter-score').val();
		const freshOnly = this.$('#filter-fresh').is(':checked');
		const includeUsed = this.$('#filter-include-used').is(':checked');
		const status = includeUsed ? 'all' : 'new';

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_trending_topics',
				nonce: this.$('#aips_nonce').val(),
				niche: niche,
				min_score: minScore,
				fresh_only: freshOnly ? 'true' : 'false',
				status: status,
				limit: 50
			},
			success: (response) => {
				if (response.success && response.data && response.data.topics) {
					this.renderTopicsTable(response.data.topics);
				} else {
					const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + errorMsg, 'error');
					}
				}
			},
			error: (xhr, status, error) => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Error loading topics: ' + error, 'error');
				}
			}
		});
	},

	renderTopicsTable(topics) {
		let html = '';
		const niche = this.$('#filter-niche').val();
		const minScore = this.$('#filter-score').val();
		const freshOnly = this.$('#filter-fresh').is(':checked');
		const includeUsed = this.$('#filter-include-used').is(':checked');
		const isFiltered = niche || minScore !== '0' || freshOnly || includeUsed;
		
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : str => String(str || '');
		const l10n = window.aipsResearchL10n || {};

		this.researchSelectedTopics = [];
		this.updateSelectedTopics();

		if (!topics || topics.length === 0) {
			if (T) {
				html = T.render('aips-tmpl-research-empty-state', {
					title: isFiltered ? (l10n.noTopicsFound || 'No topics found') : (l10n.libraryEmpty || 'Library empty'),
					description: isFiltered ? (l10n.noTopicsFound || 'No topics found') : (l10n.libraryEmpty || 'Library empty'),
					button_id: isFiltered ? 'aips-clear-filters' : 'aips-start-research',
					button_class: isFiltered ? 'aips-btn-secondary' : 'aips-btn-primary',
					button_label: isFiltered
						? (l10n.clearFilters || 'Clear Filters')
						: (l10n.startResearch || 'Start Research')
				});
			}

			this.$('#topics-container').html(html);
			this.$('#bulk-schedule-section').hide();
			this.$('#topics-tablenav').hide();
			return;
		}

		if (T) {
			const rowsHtml = topics.map(topic => {
				const scoreClass = topic.score >= 90 ? 'high' : (topic.score >= 70 ? 'medium' : 'low');
				const keywords = Array.isArray(topic.keywords) ? topic.keywords : [];
				const keywordsHtml = keywords.map(kw => {
					return T.render('aips-tmpl-research-keyword-tag', { keyword: kw });
				}).join('');

				const reasonHtml = topic.reason
					? T.render('aips-tmpl-research-topic-reason', { reason: topic.reason })
					: '';
				
				const generatedPostCount = parseInt(topic.generated_post_count || 0, 10);
				const postCountBadgeHtml = generatedPostCount > 0
					? T.render('aips-tmpl-research-topic-post-count-badge', {
						topic_id: esc(topic.id),
						count: esc(generatedPostCount)
					})
					: '';

				const statusLabel = (topic.status || 'new').toLowerCase();
				const statusLabelText = {
					'new': l10n.statusNew || 'New',
					'scheduled': l10n.statusScheduled || 'Scheduled',
					'generated': l10n.statusGenerated || 'Generated'
				}[statusLabel] || statusLabel;
				const statusChipHtml = T.render('aips-tmpl-research-topic-status-chip', {
					status: esc(statusLabel),
					status_label: esc(statusLabelText)
				});

				const relativeTime = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatRelative === 'function')
					? window.AIPS.DateTime.formatRelative(topic.researched_at)
					: topic.researched_at;

				return T.renderRaw('aips-tmpl-research-topics-row', {
					id: esc(topic.id),
					topic: esc(topic.topic),
					status_chip_html: statusChipHtml,
					post_count_badge_html: postCountBadgeHtml,
					reason_html: reasonHtml,
					score_class: esc(scoreClass),
					score: esc(topic.score),
					niche: esc(topic.niche),
					keywords_html: keywordsHtml,
					researched_at: esc(relativeTime),
					delete_label: esc(l10n.delete || 'Delete')
				});
			}).join('');

			const searchEmptyHtml = T.render('aips-tmpl-research-topics-search-empty', {
				title: l10n.noTopicsFoundTitle || 'No matching topics',
				description: l10n.noTopicsFound || 'No topics match search criteria.',
				clear_label: l10n.clearSearch || 'Clear Search'
			});

			html = T.renderRaw('aips-tmpl-research-topics-table', {
				rows_html: rowsHtml,
				search_empty_html: searchEmptyHtml
			});
		}

		this.$('#topics-container').html(html);
		this.$('#topics-count').text(topics.length + ' ' + (topics.length === 1 ? 'topic' : 'topics'));
		this.$('#topics-tablenav').show();
		this.$('#bulk-schedule-section').hide();

		if (this.$('#filter-search').val()) {
			this.filterTopics();
		}
	},

	filterTopics(e) {
		if (e) e.preventDefault();

		const query = this.$('#filter-search').val().toLowerCase();
		const $rows = this.$('.aips-research-table tbody tr');
		let visibleCount = 0;

		if (query.length > 0) {
			this.$('#filter-search-clear').show();
		} else {
			this.$('#filter-search-clear').hide();
		}

		$rows.each(function() {
			const topicText = $(this).find('td:nth-child(2)').text().toLowerCase();
			if (topicText.indexOf(query) > -1) {
				$(this).show();
				visibleCount++;
			} else {
				$(this).hide();
			}
		});

		if (visibleCount === 0 && $rows.length > 0) {
			this.$('.aips-research-table').hide();
			this.$('#topics-search-empty').show();
		} else {
			this.$('.aips-research-table').show();
			this.$('#topics-search-empty').hide();
		}
	},

	clearTopicsSearch(e) {
		e.preventDefault();
		this.$('#filter-search').val('').trigger('search').focus();
	},

	toggleAllTopics(e) {
		const isChecked = $(e.currentTarget).is(':checked');
		this.$('.topic-checkbox:visible').prop('checked', isChecked);
		this.updateSelectedTopics();
	},

	toggleTopicSelection(e) {
		e.preventDefault();
		this.updateSelectedTopics();
	},

	updateSelectedTopics() {
		this.researchSelectedTopics = this.$('.topic-checkbox:checked').map(function() {
			return $(this).val();
		}).get();

		const hasSelected = this.researchSelectedTopics.length > 0;
		this.$('#aips-delete-selected-topics').prop('disabled', !hasSelected);
		this.$('#aips-schedule-selected-topics').prop('disabled', !hasSelected);
		this.$('#aips-generate-selected-topics').prop('disabled', !hasSelected);
	},

	deleteTopic(e) {
		e.preventDefault();

		const $el = $(e.currentTarget);
		const topicId = $el.data('id');
		const l10n = window.aipsResearchL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(l10n.deleteTopicConfirm || 'Are you sure you want to delete this topic?', 'Notice', [
				{ label: l10n.cancel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: l10n.confirmDelete || 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_delete_trending_topic',
								nonce: this.$('#aips_nonce').val(),
								topic_id: topicId
							},
							success: (response) => {
								if (response.success) {
									this.loadTopics();
								} else {
									window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
								}
							}
						});
					}
				}
			]);
		}
	},

	bulkDeleteSelectedTopics(e) {
		e.preventDefault();

		if (this.researchSelectedTopics.length === 0) return;

		const l10n = window.aipsResearchL10n || {};
		const confirmMsg = l10n.deleteTopicsConfirm || ('Delete ' + this.researchSelectedTopics.length + ' selected topic(s)?');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: l10n.cancel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: l10n.confirmDelete || 'Yes, delete',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_delete_trending_topic_bulk',
								nonce: this.$('#aips_nonce').val(),
								topic_ids: this.researchSelectedTopics
							},
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message, 'success');
									this.researchSelectedTopics = [];
									this.loadTopics();
								} else {
									window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
								}
							}
						});
					}
				}
			]);
		}
	},

	scheduleSelectedTopics(e) {
		e.preventDefault();

		if (this.researchSelectedTopics.length === 0) return;

		const now = new Date();
		const pad = n => String(n).padStart(2, '0');
		const localDT = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());

		this.$('#schedule-start-date').val(localDT);
		this.$('#bulk-schedule-section').show();

		if (this.$('#bulk-schedule-section').length) {
			$('html, body').animate({
				scrollTop: this.$('#bulk-schedule-section').offset().top - 50
			}, 400);
		}
	},

	submitBulkSchedule(e) {
		e.preventDefault();

		if (this.researchSelectedTopics.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(window.aipsResearchL10n.selectTopicSchedule || 'Please select at least one topic.', 'warning');
			}
			return;
		}

		const $form = $(e.currentTarget);
		const $submit = $form.find('button[type="submit"]');
		const $spinner = $form.find('.spinner');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($submit, 'Scheduling...');
		}
		$spinner.addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_schedule_trending_topics',
				nonce: this.$('#aips_nonce').val(),
				topic_ids: this.researchSelectedTopics,
				template_id: this.$('#schedule-template').val(),
				start_date: this.$('#schedule-start-date').val(),
				frequency: this.$('#schedule-frequency').val()
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}
					this.researchSelectedTopics = [];
					this.$('.topic-checkbox').prop('checked', false);
					this.$('#select-all-topics').prop('checked', false);
					this.updateSelectedTopics();
					this.$('#bulk-schedule-section').hide();
					this.loadTopics();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
					}
				}
			},
			error: () => {
				const errorMsg = (window.aipsResearchL10n && window.aipsResearchL10n.schedulingError) || 'Scheduling error.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errorMsg, 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($submit);
				}
				$spinner.removeClass('is-active');
			}
		});
	},

	clearTopicFilters(e) {
		e.preventDefault();
		this.$('#filter-niche').val('');
		this.$('#filter-score').val('0');
		this.$('#filter-fresh').prop('checked', false);
		this.$('#filter-include-used').prop('checked', false);
		this.loadTopics();
	},

	focusResearchForm(e) {
		e.preventDefault();

		const $form = this.$('#aips-research-form');
		if (!$form.length) return;

		$('html, body').animate({
			scrollTop: $form.offset().top - 50
		}, 500);

		const $nicheField = this.$('#research-niche');
		if ($nicheField.length) {
			$nicheField.focus();
		}
	},

	analyzeGaps(e) {
		e.preventDefault();

		const niche = this.$('#gap-niche').val();
		const $btn = $(e.currentTarget);
		const $spinner = $btn.next('.spinner');

		if (!niche) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please enter a target niche.', 'warning');
			}
			return;
		}

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_perform_gap_analysis',
				nonce: this.$('#aips_nonce').val(),
				niche: niche
			},
			success: (response) => {
				if (response.success) {
					this.renderGapResults(response.data.gaps);
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred during gap analysis.', 'error');
				}
			},
			complete: () => {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	},

	renderGapResults(gaps) {
		const $container = this.$('#gap-results-container');
		const $grid = $container.find('.aips-gap-grid');
		$grid.empty();

		const T = window.AIPS.Templates;
		const l10n = window.aipsResearchL10n || {};

		if (!gaps || gaps.length === 0) {
			if (T) {
				$grid.html(T.renderRaw('aips-tmpl-research-gap-empty', {}));
			} else {
				$grid.html('<p>No gaps found.</p>');
			}
			$container.show();
			return;
		}

		gaps.forEach(gap => {
			const priorityClass = (gap.priority || 'Medium').toLowerCase();

			if (T) {
				$grid.append(T.render('aips-tmpl-research-gap-card', {
					priority_class: priorityClass,
					priority: gap.priority || 'Medium',
					missing_topic: gap.missing_topic,
					reason: gap.reason,
					search_intent: gap.search_intent,
					generate_ideas_label: (l10n.generateIdeas || 'Generate Ideas')
				}));
			} else {
				let cardHtml = '';
				cardHtml += '<div class="aips-gap-card priority-' + _.escape(priorityClass) + '">';
				cardHtml += '<span class="aips-gap-badge ' + _.escape(priorityClass) + '">' + _.escape(gap.priority) + ' Priority</span>';
				cardHtml += '<h4>' + _.escape(gap.missing_topic) + '</h4>';
				cardHtml += '<p class="aips-gap-reason">' + _.escape(gap.reason) + '</p>';
				cardHtml += '<p class="aips-gap-intent">Intent: ' + _.escape(gap.search_intent) + '</p>';
				cardHtml += '<div class="aips-gap-actions">';
				cardHtml += '<button class="aips-btn aips-btn-sm aips-btn-secondary generate-gap-ideas" data-topic="' + _.escape(gap.missing_topic) + '">' + _.escape(l10n.generateIdeas || 'Generate Ideas') + '</button>';
				cardHtml += '</div></div>';
				$grid.append(cardHtml);
			}
		});

		$container.slideDown();
	},

	generateGapIdeas(e) {
		e.preventDefault();

		const $btn = $(e.currentTarget);
		const topic = $btn.data('topic');
		const niche = this.$('#gap-niche').val();
		const l10n = window.aipsResearchL10n || {};

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, l10n.generatingIdeas || 'Generating...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_generate_topics_from_gap',
				nonce: this.$('#aips_nonce').val(),
				gap_topic: topic,
				niche: niche
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message, 'success');
					}
					this.$('.aips-tab-link[data-tab="trending"]').trigger('click');
					setTimeout(() => {
						this.loadTopics();
					}, 500);
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + response.data.message, 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('An error occurred while generating topics.', 'error');
				}
			},
			complete: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	bulkGenerateSelectedTopics(e) {
		e.preventDefault();

		if (this.researchSelectedTopics.length === 0) return;

		const count = this.researchSelectedTopics.length;
		const l10n = window.aipsResearchL10n || {};
		const message = (l10n.confirmGenerationMessage || 'Generate %d posts now?').replace('%d', count);
		this.$('#aips-generate-now-count-message').text(message);
		this.$('#aips-generate-now-template').val('');
		
		if (this.generateNowModal) {
			this.generateNowModal.open();
		}
	},

	confirmGenerateNow(e) {
		e.preventDefault();

		const templateId = this.$('#aips-generate-now-template').val();
		const l10n = window.aipsResearchL10n || {};

		if (!templateId) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.selectTemplateRequired || 'Template selection required.', 'error');
			}
			return;
		}

		if (this.generateNowModal) {
			this.generateNowModal.close();
		}

		const $btn = this.$('#aips-generate-selected-topics');
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update aips-spin"></span> ' + (l10n.generatingButton || 'Generating...'));

		this.runResearchBulkGenerateWithProgress($btn, {
			action: 'aips_generate_trending_topics_bulk',
			nonce: this.$('#aips_nonce').val(),
			topic_ids: this.researchSelectedTopics,
			template_id: templateId
		});
	},

	runResearchBulkGenerateWithProgress($button, ajaxData) {
		const DEFAULT_PER_POST_SECONDS = 30;

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_bulk_generate_estimate',
				nonce: this.$('#aips_nonce').val()
			},
			success: (estimateResponse) => {
				let perPost = DEFAULT_PER_POST_SECONDS;

				if (
					estimateResponse &&
					estimateResponse.success &&
					estimateResponse.data &&
					estimateResponse.data.per_post_seconds > 0
				) {
					perPost = estimateResponse.data.per_post_seconds;
				}

				this.launchResearchBulkGenerateProgress($button, ajaxData, perPost);
			},
			error: () => {
				this.launchResearchBulkGenerateProgress($button, ajaxData, DEFAULT_PER_POST_SECONDS);
			}
		});
	},

	launchResearchBulkGenerateProgress($button, ajaxData, perPostSeconds) {
		const topicCount = this.researchSelectedTopics.length;
		const MIN_PROGRESS_SECONDS = 10;
		const totalSeconds = Math.max(perPostSeconds * topicCount, MIN_PROGRESS_SECONDS);
		const l10n = window.aipsResearchL10n || {};
		const buttonDefaultText = l10n.generateSelected || 'Generate Selected';
		const progressTitle = l10n.generatingPostsTitle || 'Generating Posts';
		const progressMessage = l10n.generatingPostsMessage || 'Please wait while posts generate.';

		let progressBar = null;
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.showProgressBar === 'function') {
			progressBar = window.AIPS.Utilities.showProgressBar({
				title: progressTitle,
				message: progressMessage,
				totalSeconds: totalSeconds
			});
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: ajaxData,
			success: (response) => {
				if (response.success) {
					if (progressBar) progressBar.complete(response.data.message, 'success');
					this.researchSelectedTopics = [];
					setTimeout(() => {
						this.loadTopics();
					}, 1400);
				} else {
					const errorMessage = response.data && response.data.message
						? response.data.message
						: (l10n.generateError || 'Generation error.');

					if (progressBar) progressBar.complete(errorMessage, 'error');
					setTimeout(() => {
						if (window.AIPS && window.AIPS.Utilities) {
							window.AIPS.Utilities.showToast('Error: ' + errorMessage, 'error');
						}
					}, 1400);
				}
			},
			error: (xhr, status, error) => {
				const fallbackError = error ? error : (l10n.generateError || 'Generation error.');
				if (progressBar) progressBar.complete(fallbackError, 'error');
				setTimeout(() => {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Error: ' + fallbackError, 'error');
					}
				}, 1400);
			},
			complete: () => {
				$button.prop('disabled', false).html(buttonDefaultText);
			}
		});
	},

	viewTrendingTopicPosts(e) {
		e.preventDefault();
		e.stopPropagation();

		const topicId = $(e.currentTarget).data('topic-id');
		if (!topicId) return;

		const l10n = window.aipsResearchL10n || {};
		this.$('#aips-trending-topic-posts-content').html('<p>' + (l10n.loadingPosts || 'Loading posts...') + '</p>');
		
		if (this.postsModal) {
			this.postsModal.open();
		}

		this.loadTrendingTopicPosts(topicId);
	},

	loadTrendingTopicPosts(topicId) {
		const l10n = window.aipsResearchL10n || {};

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_trending_topic_posts',
				nonce: this.$('#aips_nonce').val(),
				topic_id: topicId
			},
			success: (response) => {
				if (response.success) {
					const topicTitle = response.data.topic && response.data.topic.topic
						? response.data.topic.topic
						: '';

					this.$('#aips-trending-topic-posts-modal-title').text(
						(l10n.postsGeneratedFrom || 'Posts Generated from Topic') + ': ' + topicTitle
					);

					this.renderTrendingTopicPosts(response.data.posts || []);
				} else {
					const msg = response.data && response.data.message ? response.data.message : (l10n.errorLoadingPosts || 'Error loading posts.');
					this.$('#aips-trending-topic-posts-content').html('<p>' + _.escape(msg) + '</p>');
				}
			},
			error: () => {
				this.$('#aips-trending-topic-posts-content').html('<p>' + (l10n.errorLoadingPosts || 'Error loading posts.') + '</p>');
			}
		});
	},

	renderTrendingTopicPosts(posts) {
		const l10n = window.aipsResearchL10n || {};
		if (!posts || posts.length === 0) {
			this.$('#aips-trending-topic-posts-content').html('<p>' + (l10n.noPostsFound || 'No posts found.') + '</p>');
			return;
		}

		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		const rowsHtml = posts.map(post => {
			let actionsHtml = '';
			if (post.edit_url) {
				actionsHtml += '<a href="' + esc(post.edit_url) + '" class="button" target="_blank" rel="noopener noreferrer">' + esc(l10n.editPost || 'Edit Post') + '</a> ';
			}
			if (post.post_url && post.post_status === 'publish') {
				actionsHtml += '<a href="' + esc(post.post_url) + '" class="button" target="_blank" rel="noopener noreferrer">' + esc(l10n.viewPost || 'View Post') + '</a>';
			}

			return T.renderRaw('aips-tmpl-research-topic-post-row', {
				post_id: esc(post.post_id || ''),
				post_title: esc(post.post_title || ''),
				date_generated: esc(post.date_generated || ''),
				date_published: esc(post.date_published || (l10n.notPublished || 'Not published')),
				actions: actionsHtml
			});
		}).join('');

		const tableHtml = T.renderRaw('aips-tmpl-research-topic-posts-table', {
			id_label: esc(l10n.postId || 'Post ID'),
			title_label: esc(l10n.postTitle || 'Post Title'),
			generated_label: esc(l10n.dateGenerated || 'Date Generated'),
			published_label: esc(l10n.datePublished || 'Date Published'),
			actions_label: esc(l10n.actions || 'Actions'),
			rows: rowsHtml
		});

		this.$('#aips-trending-topic-posts-content').html(tableHtml);
	},

	reloadTopics(e) {
		if (e) e.preventDefault();
		this.loadTopics();
	}
});
